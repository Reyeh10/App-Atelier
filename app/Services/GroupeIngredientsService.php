<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Ingrédients de peinture / antirouille : des pièces vendues séparément par le
 * magasin (chacune son stock, son BC, son BT) mais présentées au client sur une
 * seule ligne « Ingrédients de peinture » avec la somme de leurs prix — sur le
 * devis, la facture et l'avoir imprimés. Le détail reste visible dans le système.
 */
class GroupeIngredientsService
{
    /** Groupes possibles (clé enregistrée sur la ligne => libellé imprimé) */
    public const GROUPES = [
        'peinture'    => 'Ingrédients de peinture',
        'antirouille' => 'Ingrédients antirouille',
    ];

    /** Nettoie la valeur reçue d'un formulaire : un groupe connu, et seulement pour une pièce */
    public static function normaliser(?string $groupe, ?string $type): ?string
    {
        return $type === 'piece' && array_key_exists((string) $groupe, self::GROUPES) ? $groupe : null;
    }

    /**
     * Lignes à imprimer : les pièces d'un même groupe fusionnées en une ligne
     * (quantité 1, prix = somme des montants), placée où était la première.
     * Les montants totaux ne changent pas.
     */
    public static function regrouper(Collection $lignes): Collection
    {
        $resultat = collect();
        $fusion   = [];

        foreach ($lignes->values() as $ligne) {
            $groupe = $ligne->groupe ?? null;
            if (! $groupe || ! isset(self::GROUPES[$groupe])) {
                $resultat->push($ligne);
                continue;
            }

            if (! isset($fusion[$groupe])) {
                $ligneGroupe = $ligne->replicate();
                $ligneGroupe->forceFill([
                    'designation'   => self::GROUPES[$groupe],
                    'reference'     => null,
                    'quantite'      => 1,
                    'remise'        => 0,
                    'prix_unitaire' => 0,
                    'total_ht'      => 0,
                ]);
                if (array_key_exists('unite', $ligne->getAttributes())) {
                    $ligneGroupe->unite = 'Lot';
                }
                $fusion[$groupe] = $ligneGroupe;
                $resultat->push($ligneGroupe);
            }

            $total = (float) $fusion[$groupe]->total_ht + (float) $ligne->total_ht;
            $fusion[$groupe]->forceFill(['prix_unitaire' => $total, 'total_ht' => $total]);
        }

        return $resultat;
    }
}
