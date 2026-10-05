<?php

namespace App\Services;

use App\Models\Activite;
use App\Models\BonCommande;
use App\Models\Devis;
use App\Models\LigneBonCommande;

/**
 * Logique d'acceptation d'un devis, partagée entre :
 *   - DevisController::accepter() / uploadSignature() (acceptation manuelle par le chef)
 *   - DossierReceptionController (acceptation automatique Service Rapide, prix fixes connus d'avance)
 *
 * Dans les deux cas : crée l'OR si le devis était encore rattaché à un dossier
 * de réception (pas encore d'OR).
 */
class DevisWorkflowService
{
    public static function accepter(Devis $devis, array $attributsSupplementaires = []): void
    {
        if (! $devis->or_id && $devis->dossier_id) {
            $or = $devis->dossier->creerOrDepuisDossier();
            $devis->update(['or_id' => $or->id]);
        }

        $devis->update(array_merge([
            'statut'          => 'accepte',
            'date_validation' => now(),
        ], $attributsSupplementaires));

        // Statut du véhicule : « Devis accepté » tant que les travaux n'ont pas
        // commencé. Un devis complémentaire accepté pendant les travaux ne le
        // fait pas reculer ; s'il arrive après la fin des travaux (contrôle
        // qualité, lavage, prêt), le véhicule repasse « En cours » pour sa
        // nouvelle feuille de travail. Facturé / livré / annulé : inchangé.
        $or = $devis->ordreReparation;
        if (! $or) {
            // Filet de sécurité : un devis en avance (sans OR ni dossier) n'est
            // jamais accepté — DevisController::accepter() / uploadSignature()
            // le refusent. Il est accepté après la réception, une fois repris
            // par le dossier (cf. DossierReceptionController::store()).
            $devis->load('lignes');
            self::genererBonCommande($devis);
            return;
        }
        if ($or->estAvantAcceptationDevis() || $or->statut === 'devis_accepte') {
            $or->update(['statut' => 'devis_accepte']);
        } elseif (in_array($or->statut, ['controle_qualite', 'lavage', 'pret'], true)) {
            $or->update(['statut' => 'en_cours']);
        }
        $devis->load('lignes');
        // Filet de sécurité : dans le flux normal, le BC est déjà parti au
        // fournisseur dès la création du devis (cf. genererBonCommande()
        // appelé depuis DevisController::store()/storePourDossier() et
        // DossierReceptionController::genererDevisServiceRapide()) — cet
        // appel est un no-op idempotent sauf cas limite où ça n'aurait pas
        // eu lieu (ex: devis créé avant ce changement).
        self::genererBonCommande($devis);
    }

    /**
     * Génère et transmet immédiatement le bon de commande pièces au fournisseur
     * (stcd-magasin) à partir des lignes du devis — appelé dès la création du
     * devis (auto ou manuelle), pas seulement à son acceptation, pour que le
     * prix de vente et la disponibilité reviennent avant même la validation.
     * N'est créé que si aucun BC n'existe encore pour ce devis et que le devis
     * contient au moins une ligne de type "pièce". Fonctionne aussi bien pour
     * un devis encore rattaché à un dossier (pas d'OR) qu'à un OR existant.
     */
    public static function genererBonCommande(Devis $devis): void
    {
        if ($devis->bonCommande) return;

        $pieces = $devis->lignes->where('type', 'piece');
        if ($pieces->isEmpty()) return;

        $bc = BonCommande::create([
            'numero'     => BonCommande::genererNumero(),
            'devis_id'   => $devis->id,
            'or_id'      => $devis->or_id,
            'dossier_id' => $devis->or_id ? null : $devis->dossier_id,
            'statut'     => 'en_attente',
        ]);

        foreach ($pieces as $ligne) {
            LigneBonCommande::create([
                'bon_commande_id' => $bc->id,
                'ligne_devis_id'  => $ligne->id,
                'designation'     => $ligne->designation,
                'reference'       => $ligne->reference,
                'quantite'        => $ligne->quantite,
            ]);
        }

        app(\App\Services\FournisseurApiService::class)->envoyerBonCommande($bc);
    }

    /**
     * Répercute au fournisseur les changements faits sur un devis déjà envoyé
     * (ex: le chef modifie la quantité d'une pièce, en ajoute ou en retire une)
     * — appelé après DevisController::update(). Sans BC existant, se comporte
     * comme genererBonCommande() (premier envoi).
     *
     * Les lignes de BC existantes sont réutilisées (retrouvées par désignation,
     * les identifiants de LigneDevis ayant changé puisque update() recrée les
     * lignes) plutôt que supprimées/recréées : la disponibilité/prix déjà
     * connus du fournisseur pour une pièce inchangée ne sont jamais perdus
     * localement, et stcd-magasin (FournisseurBonCommandeController::store())
     * préserve de même une pièce déjà identifiée par un vendeur — seule la
     * quantité demandée est mise à jour pour elle.
     */
    /**
     * Annule le bon de commande du devis (devis refusé, ou plus aucune pièce) et
     * prévient le magasin. Un BC déjà reçu, ou dont le magasin a déjà fait le
     * BT (pièces sorties du stock), n'est pas annulé : les pièces sont à rendre
     * au magasin. Renvoie un message à afficher, ou null si rien à signaler.
     */
    public static function annulerBonCommande(Devis $devis, string $motif): ?string
    {
        $bc = $devis->bonCommande()->first();
        if (! $bc || $bc->statut === 'annule') {
            return null;
        }
        if ($bc->statut === 'recu' || $bc->bonTransfert()->exists()) {
            return "Le bon de commande {$bc->numero} n'est pas annulé : le magasin a déjà sorti les pièces (BT). Retournez-les au magasin.";
        }

        $bc->update(['statut' => 'annule']);
        Activite::journaliser('annuler_bon_commande', "Bon de commande {$bc->numero} annulé — {$motif}", $bc);
        app(\App\Services\FournisseurApiService::class)->annulerBonCommande($bc, $motif);

        return null;
    }

    /**
     * Un devis accepté vient d'être refusé (correction administrateur) : le
     * véhicule revient au diagnostic s'il n'a plus aucun devis accepté ; si seul
     * un devis complémentaire est retiré et que tout le reste est terminé, il
     * repasse au contrôle qualité.
     */
    public static function apresRefusDevisAccepte(Devis $devis): void
    {
        $or = $devis->ordreReparation()->first();
        if (! $or) {
            return;
        }
        $or->load('allDevis');

        if ($or->devisAcceptes()->isEmpty()) {
            if (in_array($or->statut, ['devis_accepte', 'en_cours'], true)) {
                $or->update(['statut' => 'diagnostic']);
            }
            return;
        }
        if ($or->statut === 'en_cours' && $or->heure_fin_travaux && $or->feuillesComplementairesTerminees()) {
            $or->update(['statut' => $or->service_gratuit ? 'pret' : 'controle_qualite']);
        }
    }

    public static function resynchroniserBonCommande(Devis $devis): void
    {
        // Devis refusé (corrigé par l'administrateur) : ses pièces ne sont plus à
        // commander — son BC reste annulé et le magasin n'est pas relancé.
        if ($devis->statut === 'refuse') {
            return;
        }

        $bc = $devis->bonCommande;
        if (! $bc) {
            self::genererBonCommande($devis);
            return;
        }

        $pieces = $devis->lignes->where('type', 'piece');
        if ($pieces->isEmpty()) {
            $bc->lignes()->delete();
            self::annulerBonCommande($devis, 'plus aucune pièce dans le devis');
            return;
        }

        // Des pièces reviennent dans le devis : le BC annulé redevient actif
        if ($bc->statut === 'annule') {
            $bc->update(['statut' => 'en_attente']);
        }

        $bc->load('lignes');
        $lignesExistantes = $bc->lignes->keyBy('designation');

        $gardees = [];
        $aRecevoir = [];   // pièces nouvelles ou dont la quantité a changé
        foreach ($pieces as $ligneDevis) {
            $ligneBc = $lignesExistantes->get($ligneDevis->designation);
            if ($ligneBc) {
                if ((float) $ligneBc->quantite !== (float) $ligneDevis->quantite) {
                    $aRecevoir[] = $ligneBc->id;
                }
                $ligneBc->update([
                    'ligne_devis_id' => $ligneDevis->id,
                    'reference'      => $ligneDevis->reference ?: $ligneBc->reference,
                    'quantite'       => $ligneDevis->quantite,
                ]);
                $gardees[] = $ligneBc->id;
            } else {
                $nouvelle = LigneBonCommande::create([
                    'bon_commande_id' => $bc->id,
                    'ligne_devis_id'  => $ligneDevis->id,
                    'designation'     => $ligneDevis->designation,
                    'reference'       => $ligneDevis->reference,
                    'quantite'        => $ligneDevis->quantite,
                ]);
                $gardees[] = $nouvelle->id;
                $aRecevoir[] = $nouvelle->id;
            }
        }

        // Pièces retirées du devis : plus lieu d'être commandées.
        $bc->lignes()->whereNotIn('id', $gardees)->delete();

        // BC déjà reçu dont les pièces changent (devis modifié après le BT) : les
        // pièces concernées sont à recevoir de nouveau, avec le BT mis à jour.
        if ($aRecevoir && $bc->statut === 'recu') {
            $bc->lignes()->whereIn('id', $aRecevoir)->update(['recu' => false]);
            $bc->update(['statut' => 'commande']);
        }

        app(\App\Services\FournisseurApiService::class)->envoyerBonCommande($bc->fresh('lignes'));
    }
}
