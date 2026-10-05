<?php

namespace App\Services;

use App\Models\BonCommande;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoie les bons de commande pièces au système du fournisseur (stcd-magasin)
 * dès leur création, et enregistre la disponibilité renvoyée en retour.
 *
 * L'appel est synchrone : si le fournisseur ne répond pas (indisponible, hors
 * ligne...), on journalise l'erreur mais on ne bloque jamais la validation
 * du devis côté garage — la disponibilité pourra être renseignée plus tard.
 */
class FournisseurApiService
{
    public function envoyerBonCommande(BonCommande $bc): void
    {
        $url = config('services.stcd_magasin.url');
        $token = config('services.stcd_magasin.token');

        if (! $url || ! $token) {
            Log::warning("Envoi fournisseur ignoré pour {$bc->numero} : STCD_MAGASIN_URL/TOKEN non configurés.");
            return;
        }

        // Le BC part désormais dès la création du devis, avant qu'un OR n'existe
        // forcément (devis encore rattaché à un dossier de réception) — on lit
        // le véhicule/client depuis l'OR s'il existe déjà, sinon depuis le dossier.
        // Devis en avance (réservation / devis libre) : ni OR ni dossier encore,
        // le véhicule et le client sont portés par le devis lui-même.
        $bc->loadMissing('lignes', 'ordreReparation.vehicule', 'ordreReparation.client', 'dossier.vehicule', 'dossier.client', 'devis.vehicule', 'devis.client', 'vehiculeDirect', 'clientDirect');
        // BC flotte (import Excel) : ni OR, ni dossier, ni devis — le BC porte le bus et le client
        $vehicule = $bc->vehicule;
        $client   = $bc->client;

        $payload = [
            'numero' => $bc->numero,
            'vehicule' => [
                'marque'          => $vehicule?->marque,
                'modele'          => $vehicule?->modele,
                'immatriculation' => $vehicule?->immatriculation,
                'vin'             => $vehicule?->vin,
            ],
            'client' => [
                'nom'       => $client?->nom_complet,
                'telephone' => $client?->telephone,
            ],
            'pieces' => $bc->lignes->map(fn ($ligne) => [
                'reference'   => $ligne->reference,
                'designation' => $ligne->designation,
                'quantite'    => (float) $ligne->quantite,
            ])->all(),
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withoutRedirecting()
                ->timeout(5)
                ->retry(2, 1000)
                ->post(rtrim($url, '/').'/bons-commande', $payload);

            if (! $response->successful()) {
                Log::warning("Réponse fournisseur en échec pour {$bc->numero} : HTTP {$response->status()} — {$response->body()}");
                return;
            }

            $this->enregistrerReponse($bc, $response->json());
        } catch (\Throwable $e) {
            Log::warning("Impossible de joindre le système fournisseur pour {$bc->numero} : {$e->getMessage()}");
        }
    }

    /**
     * Prévient le magasin que le bon de commande est annulé (devis refusé, ou
     * plus aucune pièce) : il ne doit plus préparer ni transférer ces pièces.
     * Appel non bloquant, comme l'envoi du BC.
     *
     * Côté magasin : POST {STCD_MAGASIN_URL}/bons-commande/{numero}/annuler
     * avec le champ « motif » (route à ajouter dans stcd-magasin).
     */
    public function annulerBonCommande(BonCommande $bc, string $motif): void
    {
        $url = config('services.stcd_magasin.url');
        $token = config('services.stcd_magasin.token');

        if (! $url || ! $token) {
            Log::warning("Annulation fournisseur ignorée pour {$bc->numero} : STCD_MAGASIN_URL/TOKEN non configurés.");
            return;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withoutRedirecting()
                ->timeout(5)
                ->post(rtrim($url, '/').'/bons-commande/'.rawurlencode($bc->numero).'/annuler', ['motif' => $motif]);

            if (! $response->successful()) {
                Log::warning("Annulation du BC {$bc->numero} refusée par le fournisseur : HTTP {$response->status()} — {$response->body()}");
            }
        } catch (\Throwable $e) {
            Log::warning("Impossible de prévenir le fournisseur de l'annulation du BC {$bc->numero} : {$e->getMessage()}");
        }
    }

    private function enregistrerReponse(BonCommande $bc, array $reponse): void
    {
        // On fait correspondre les lignes par position (et non par référence) :
        // certaines lignes (main d'œuvre, peinture...) n'ont pas de référence.
        $pieces = collect($reponse['pieces'] ?? [])->values();

        foreach ($bc->lignes->values() as $index => $ligne) {
            $info = $pieces->get($index);
            if (! $info) continue;

            $ligne->update([
                'disponible'          => $info['disponible'] ?? null,
                'quantite_disponible' => $info['quantite_disponible'] ?? null,
                'prix_unitaire'       => $info['prix_unitaire'] ?? null,
                'note'                => $info['note'] ?? null,
            ]);
            // Pièce reconnue par référence dès l'envoi : le prix revient tout
            // de suite sur le devis, sans attendre une identification manuelle.
            $ligne->propagerVersDevis();
        }

        $bc->update(['fournisseur_repondu_at' => now()]);
    }
}
