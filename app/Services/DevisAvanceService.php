<?php

namespace App\Services;

use App\Models\Devis;
use App\Models\LigneDevis;
use Illuminate\Support\Facades\DB;

/**
 * Devis établi AVANT toute réception : rattaché à une réservation (le client
 * connaît le prix avant son RDV) ou « libre » (simple demande de prix).
 *
 * Mêmes règles que le devis généré automatiquement à la réception pour le
 * Service Rapide (cf. DossierReceptionController::genererDevisServiceRapide()) :
 *   - entretien périodique : main d'œuvre au tarif fixe + pièces à remplacer du
 *     palier constructeur (prix des pièces renvoyés par le fournisseur via le BC) ;
 *   - autre service rapide : main d'œuvre au tarif du service choisi.
 * Le bon de commande part au fournisseur dès la création, pour que le prix des
 * pièces soit connu avant la venue du client.
 */
class DevisAvanceService
{
    /**
     * Lignes du devis selon le type de prestation.
     *
     * @return array<int, array{type: string, designation: string, quantite: float, prix_unitaire: float}>
     */
    public static function lignes(string $type, ?string $serviceCle, ?string $designation, ?int $typeMoteurId, ?int $palier): array
    {
        if ($type === 'entretien_periodique') {
            $lignes = [[
                'type'          => 'main_oeuvre',
                'designation'   => 'Entretien périodique — Main d\'œuvre' . ($palier ? ' (palier ' . number_format($palier, 0, ',', ' ') . ' km)' : ''),
                'quantite'      => 1,
                'prix_unitaire' => ReservationService::tarif('entretien_periodique'),
            ]];

            $pieces = ($palier !== null && $typeMoteurId)
                ? EntretienService::piecesDuPalier($typeMoteurId, $palier)
                : collect();

            if ($palier !== null && $typeMoteurId) {
                foreach (EntretienService::mainOeuvreDuPalier($typeMoteurId, $palier) as $operation) {
                    $lignes[] = [
                        'type'          => 'main_oeuvre',
                        'designation'   => $operation['designation'],
                        'quantite'      => 1,
                        'prix_unitaire' => $operation['prix_unitaire'],
                    ];
                }
            }

            foreach ($pieces as $tache) {
                $lignes[] = [
                    'type'          => 'piece',
                    'designation'   => EntretienService::libellePiece($tache->designation),
                    'quantite'      => 1,
                    'prix_unitaire' => 0,
                ];
            }

            return $lignes;
        }

        if ($type === 'service_rapide' && $serviceCle) {
            return [[
                'type'          => 'main_oeuvre',
                'designation'   => $designation ?: (ReservationService::servicesAutre()[$serviceCle]['label'] ?? 'Service Rapide'),
                'quantite'      => 1,
                'prix_unitaire' => ReservationService::tarif($serviceCle),
            ]];
        }

        // Travaux libres : lignes saisies ensuite dans le formulaire du devis
        return [];
    }

    /** Crée le devis (brouillon) et ses lignes, puis envoie le BC pièces au fournisseur. */
    public static function creer(array $attributs, array $lignes): Devis
    {
        $devis = DB::transaction(function () use ($attributs, $lignes) {
            $devis = Devis::create(array_merge([
                'numero'   => Devis::genererNumero(),
                'taux_tva' => 10,
                'statut'   => 'brouillon',
            ], $attributs));

            foreach ($lignes as $ligne) {
                LigneDevis::create([
                    'devis_id'      => $devis->id,
                    'type'          => $ligne['type'],
                    'designation'   => $ligne['designation'],
                    'quantite'      => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'total_ht'      => round($ligne['quantite'] * $ligne['prix_unitaire'], 2),
                ]);
            }

            $devis->recalculer();

            return $devis;
        });

        // Envoi immédiat au fournisseur (hors transaction — appel HTTP externe)
        $devis->load('lignes');
        DevisWorkflowService::genererBonCommande($devis);

        return $devis;
    }
}
