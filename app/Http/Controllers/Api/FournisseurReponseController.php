<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\BonCommande;
use App\Models\BonTransfert;
use Illuminate\Http\Request;

/**
 * Reçoit les mises à jour de disponibilité envoyées par stcd-magasin
 * après identification manuelle d'une pièce par le vendeur (cas où le
 * garage n'avait pas transmis de référence à la création du BC).
 */
class FournisseurReponseController extends Controller
{
    public function updateLigne(Request $request, string $numero, int $index)
    {
        $data = $request->validate([
            'disponible'          => 'required|boolean',
            'quantite_disponible' => 'nullable|numeric',
            'prix_unitaire'       => 'nullable|numeric|min:0',
            'note'                => 'nullable|string|max:255',
        ]);

        $bc = BonCommande::where('numero', $numero)->firstOrFail();
        $bc->load('lignes');

        $ligne = $bc->lignes->values()->get($index);
        if (! $ligne) {
            abort(404, 'Ligne introuvable pour ce bon de commande.');
        }

        $ligne->update([
            'disponible'          => $data['disponible'],
            'quantite_disponible' => $data['quantite_disponible'] ?? null,
            'prix_unitaire'       => $data['prix_unitaire'] ?? null,
            'note'                => $data['note'] ?? null,
        ]);
        $ligne->propagerVersDevis();

        $bc->update(['fournisseur_repondu_at' => now()]);

        Activite::journaliser(
            'maj_disponibilite_fournisseur',
            "Fournisseur : {$ligne->designation} ({$bc->numero}) marquée " . ($data['disponible'] ? 'disponible' : 'indisponible'),
            $bc
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Bon de transfert (BT) créé par stcd-magasin pour un bon de commande de
     * l'atelier : numéro, date, dépôt, lignes (index, référence, désignation,
     * quantité) et PDF facultatif (champ « fichier », envoi multipart).
     * Un seul BT par bon de commande : un nouvel envoi remplace le précédent.
     * Les références des pièces sont reportées sur le BC et le devis.
     */
    public function enregistrerBonTransfert(Request $request, string $numero)
    {
        $data = $request->validate([
            'numero'               => 'required|string|max:50',
            'date'                 => 'nullable|date',
            'depot'                => 'nullable|string|max:100',
            'notes'                => 'nullable|string|max:1000',
            'lignes'               => 'nullable|array',
            'lignes.*.index'       => 'nullable|integer|min:0',
            'lignes.*.reference'   => 'nullable|string|max:100',
            'lignes.*.designation' => 'nullable|string|max:255',
            'lignes.*.quantite'    => 'nullable|numeric|min:0',
            'fichier'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $bc = BonCommande::where('numero', $numero)->firstOrFail();
        $bc->bonTransfert?->oublierFichierSiAutreNumero($data['numero']);

        $bt = BonTransfert::updateOrCreate(
            ['bon_commande_id' => $bc->id],
            [
                'numero'         => $data['numero'],
                'date_transfert' => $data['date'] ?? now()->toDateString(),
                'depot'          => $data['depot'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'lignes'         => array_values($data['lignes'] ?? []),
                'source'         => 'magasin',
                'saisi_par'      => null,
            ]
        );
        $bt->remplacerFichier($request->file('fichier'));
        $completees = $bt->reporterReferences();

        Activite::journaliser(
            'bon_transfert_recu',
            "Bon de transfert {$bt->numero} reçu du magasin pour {$bc->numero}" . ($completees ? " — {$completees} référence(s) complétée(s)" : ''),
            $bc
        );

        return response()->json(['ok' => true, 'bon_transfert' => $bt->numero, 'references_completees' => $completees]);
    }
}
