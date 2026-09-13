<?php

namespace App\Services;

use App\Models\ExternalBonCommandeLigne;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Renvoie vers app-atelier la disponibilité, le prix de vente et une
 * éventuelle note d'une ligne dès qu'elle est identifiée manuellement par
 * le vendeur (cas des lignes sans référence). Appel synchrone, non
 * bloquant : si app-atelier est injoignable, on journalise l'échec mais
 * la mise à jour reste bien enregistrée ici.
 */
class AppAtelierApiService
{
    public function envoyerDisponibilite(ExternalBonCommandeLigne $ligne): void
    {
        $url = config('services.app_atelier.url');
        $token = config('services.app_atelier.token');

        if (! $url || ! $token) {
            Log::warning("Envoi app-atelier ignoré pour la ligne #{$ligne->id} : APP_ATELIER_URL/TOKEN non configurés.");
            return;
        }

        $numero = $ligne->externalBonCommande->numero;

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withoutRedirecting()
                ->timeout(5)
                ->patch(rtrim($url, '/')."/bons-commande/{$numero}/lignes/{$ligne->position}", [
                    'disponible'          => (bool) $ligne->disponible,
                    'quantite_disponible' => $ligne->quantite_disponible,
                    'prix_unitaire'       => $ligne->prix_unitaire,
                    'note'                => $ligne->note,
                ]);

            if (! $response->successful()) {
                Log::warning("Réponse app-atelier en échec pour {$numero} ligne {$ligne->position} : HTTP {$response->status()} — {$response->body()}");
            }
        } catch (\Throwable $e) {
            Log::warning("Impossible de joindre app-atelier pour {$numero} ligne {$ligne->position} : {$e->getMessage()}");
        }
    }
}
