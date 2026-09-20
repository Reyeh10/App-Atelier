<?php

namespace App\Services;

/**
 * Arrondit un ensemble de lignes (devis ou facture) pour que leur total TTC
 * final soit un multiple de 5 FDJ. Le Franc Djiboutien n'a pas de coupure de
 * 1 ni 2 — la plus petite est 5 FDJ — donc un total qui n'en est pas un
 * multiple n'est tout simplement pas payable en espèces.
 *
 * Utilisé aussi bien pour les devis (avant que le client ne valide) que pour
 * les factures, afin que le montant annoncé au client soit déjà le même que
 * celui qui lui sera demandé au moment de payer.
 */
class ArrondiFdjService
{
    /**
     * Ajuste le prix unitaire de la ligne la plus chère pour que le total TTC
     * final soit un multiple de 5 FDJ.
     *
     * Modifie $lignes par référence (prix_unitaire/total_ht de la ligne
     * choisie) et retourne les montants recalculés : [montant_ht, montant_tva,
     * montant_ttc].
     *
     * @param array<int, array{quantite: float, prix_unitaire: float, remise: float, total_ht: float}> $lignes
     */
    public static function arrondir(array &$lignes, float $tauxTva): array
    {
        $montantHt = array_sum(array_column($lignes, 'total_ht'));
        $tva       = round($montantHt * $tauxTva / 100, 2);
        $ttcBrut   = $montantHt + $tva;
        $ttcCible  = round($ttcBrut / 5) * 5;
        $delta     = round($ttcCible - $ttcBrut, 2);

        if ($delta != 0.0 && ! empty($lignes)) {
            // Ligne la plus chère : minimise la distorsion relative et évite un prix négatif.
            $totaux     = array_column($lignes, 'total_ht');
            $indexCible = array_search(max($totaux), $totaux);
            $deltaHt    = round($delta / (1 + $tauxTva / 100), 2);

            $ligne    = &$lignes[$indexCible];
            $diviseur = $ligne['quantite'] * (1 - $ligne['remise'] / 100);
            if ($diviseur > 0) {
                $ligne['total_ht']      = round($ligne['total_ht'] + $deltaHt, 2);
                $ligne['prix_unitaire'] = round($ligne['total_ht'] / $diviseur, 2);
            }
            unset($ligne);

            $montantHt = array_sum(array_column($lignes, 'total_ht'));
            $tva       = round($montantHt * $tauxTva / 100, 2);
        }

        return [$montantHt, $tva, round($montantHt + $tva, 2)];
    }
}
