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
     * Arrondit les prix au franc puis ajuste le prix unitaire d'une ligne, par
     * francs entiers, pour que le total TTC final soit un multiple de 5 FDJ.
     * Aucun prix ni total ne garde de décimales ; seules la quantité (heures,
     * litres...) et la remise peuvent en avoir.
     *
     * On cherche le plus petit ajustement possible (+1, -1, +2, -2... francs),
     * en essayant d'abord les lignes à quantité 1, puis la plus chère.
     *
     * Modifie $lignes par référence (prix_unitaire/total_ht) et retourne les
     * montants recalculés : [montant_ht, montant_tva, montant_ttc].
     *
     * @param array<int, array{quantite: float, prix_unitaire: float, remise: float, total_ht: float}> $lignes
     */
    public static function arrondir(array &$lignes, float $tauxTva): array
    {
        foreach ($lignes as &$ligne) {
            $ligne['prix_unitaire'] = round((float) $ligne['prix_unitaire']);
            $ligne['total_ht']      = self::totalLigne($ligne);
        }
        unset($ligne);

        $montants = self::montants($lignes, $tauxTva);
        if ($montants[2] % 5 === 0 || empty($lignes)) {
            return $montants;
        }

        $ordre = array_keys($lignes);
        // Lignes à quantité 1 sans remise d'abord (le prix reste un prix « rond » par
        // unité, ex : une pièce), puis de la plus chère à la moins chère
        $simple = fn ($l) => (float) $l['quantite'] == 1.0 && (float) ($l['remise'] ?? 0) == 0.0;
        usort($ordre, fn ($a, $b) => [$simple($lignes[$b]), $lignes[$b]['total_ht']] <=> [$simple($lignes[$a]), $lignes[$a]['total_ht']]);

        // Le plus petit ajustement d'abord (1 franc, puis 2...), sur la ligne la
        // plus chère qui le permet
        for ($pas = 1; $pas <= 100; $pas++) {
            foreach ($ordre as $index) {
                $diviseur = (float) $lignes[$index]['quantite'] * (1 - (float) ($lignes[$index]['remise'] ?? 0) / 100);
                if ($diviseur <= 0) {
                    continue;
                }
                foreach ([$pas, -$pas] as $delta) {
                    $prix = $lignes[$index]['prix_unitaire'] + $delta;
                    if ($prix < 0) {
                        continue;
                    }
                    $essai = $lignes;
                    $essai[$index]['prix_unitaire'] = $prix;
                    $essai[$index]['total_ht']      = self::totalLigne($essai[$index]);
                    $resultat = self::montants($essai, $tauxTva);
                    if ($resultat[2] % 5 === 0) {
                        $lignes = $essai;
                        return $resultat;
                    }
                }
            }
        }

        return $montants;
    }

    /** Total HT d'une ligne, au franc */
    private static function totalLigne(array $ligne): float
    {
        return round((float) $ligne['quantite'] * (float) $ligne['prix_unitaire'] * (1 - (float) ($ligne['remise'] ?? 0) / 100));
    }

    /**
     * [HT, TVA, TTC] au franc.
     *
     * @return array{0:int, 1:int, 2:int}
     */
    private static function montants(array $lignes, float $tauxTva): array
    {
        $ht  = (int) round(array_sum(array_column($lignes, 'total_ht')));
        $tva = (int) round($ht * $tauxTva / 100);

        return [$ht, $tva, $ht + $tva];
    }
}
