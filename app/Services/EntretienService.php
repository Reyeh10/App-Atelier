<?php

namespace App\Services;

use App\Models\EntretienTache;
use App\Models\OrdreReparation;
use App\Models\Vehicule;

/**
 * Résout le palier d'entretien constructeur applicable à un véhicule.
 *
 * Chaque palier du barème constructeur est déclenché par un seuil kilométrique
 * OU un délai en mois — jamais les deux à la fois (ex : "5 000 km ou 6 mois").
 * Un véhicule qui a peu roulé mais dont la mise en circulation date de plusieurs
 * mois doit être signalé au même titre qu'un véhicule qui a beaucoup roulé en
 * peu de temps.
 */
class EntretienService
{
    // Marge (en km) en dessous d'un palier d'entretien pour le considérer atteint
    private const MARGE_PROCHE_KM = 100;

    /**
     * Résout le palier kilométrique du barème constructeur à appliquer pour un
     * entretien périodique.
     *
     * Priorité au dernier entretien réellement effectué sur ce véhicule (on
     * avance simplement au palier suivant du barème, dans l'ordre) plutôt qu'un
     * recalcul brut — beaucoup de clients reviennent bien après l'échéance
     * théorique, et le barème se suit en séquence une fois entamé.
     *
     * Sans historique (premier entretien), un palier est considéré atteint dès
     * que SON kilométrage OU SON délai en mois est franchi. Le délai se compte
     * depuis la mise en circulation du véhicule. Si celle-ci n'est pas
     * renseignée, seul le kilométrage est pris en compte (comportement
     * inchangé).
     */
    public static function resoudrePalier(Vehicule $vehicule, int $kmActuel, int $typeMoteurId): ?int
    {
        $paliers = EntretienTache::where('type_moteur_id', $typeMoteurId)
            ->where('designation', 'Huile moteur')
            ->orderBy('km_seuil')
            ->get(['km_seuil', 'mois_seuil']);

        if ($paliers->isEmpty()) return null;

        $dernierEntretien = OrdreReparation::where('vehicule_id', $vehicule->id)
            ->where('type', 'entretien')
            ->whereNotNull('entretien_km_seuil')
            ->latest('date_entree')
            ->first();

        if ($dernierEntretien) {
            // On avance d'un cran dans le barème par rapport au dernier entretien fait
            $suivants = $paliers->filter(fn ($p) => $p->km_seuil > $dernierEntretien->entretien_km_seuil)->values();
            return $suivants->first()->km_seuil ?? $paliers->last()->km_seuil;
        }

        // Pas d'historique : le plus grand palier dont le km OU le mois est atteint,
        // sinon le premier palier du barème.
        $moisEcoules = $vehicule->date_mise_circulation?->diffInMonths(now());

        $atteints = $paliers->filter(function ($p) use ($kmActuel, $moisEcoules) {
            $parKm   = $p->km_seuil <= $kmActuel + self::MARGE_PROCHE_KM;
            $parMois = $moisEcoules !== null && $p->mois_seuil !== null && $moisEcoules >= $p->mois_seuil;
            return $parKm || $parMois;
        })->values();

        return $atteints->isEmpty() ? $paliers->first()->km_seuil : $atteints->last()->km_seuil;
    }

    /**
     * Délai (en mois) du barème constructeur associé à un palier kilométrique
     * donné, ou null si non renseigné — utilisé pour afficher un retard en
     * mois (et pas seulement en km) sur la fiche OR.
     */
    public static function moisSeuilPour(int $typeMoteurId, int $kmSeuil): ?int
    {
        return EntretienTache::where('type_moteur_id', $typeMoteurId)
            ->where('designation', 'Huile moteur')
            ->where('km_seuil', $kmSeuil)
            ->value('mois_seuil');
    }

    /**
     * Calcule le retard d'entretien (kilométrage et/ou délai en mois dépassé)
     * pour un OR de type "entretien". Logique partagée entre la bannière de la
     * fiche OR (permanente) et l'alerte du tableau de bord (limitée à 1h après
     * la création de l'OR) — voir DashboardController::index() et
     * ordres-reparations/show.blade.php.
     *
     * Nécessite que la relation `vehicule` soit déjà chargée sur $or.
     */
    public static function calculerRetard(OrdreReparation $or): array
    {
        $depassementKm = ($or->type === 'entretien' && $or->entretien_km_seuil)
            ? $or->kilometrage_entree - $or->entretien_km_seuil
            : null;

        $moisSeuil = ($or->type === 'entretien' && $or->entretien_km_seuil && $or->vehicule->type_moteur_id)
            ? self::moisSeuilPour($or->vehicule->type_moteur_id, $or->entretien_km_seuil)
            : null;

        $depassementMois = ($moisSeuil && $or->vehicule->date_mise_circulation)
            ? $or->vehicule->date_mise_circulation->diffInMonths($or->date_entree) - $moisSeuil
            : null;

        $enRetardKm   = $depassementKm !== null && $depassementKm > 500;
        $enRetardMois = $depassementMois !== null && $depassementMois > 0;

        return [
            'enRetard'        => $enRetardKm || $enRetardMois,
            'enRetardKm'      => $enRetardKm,
            'enRetardMois'    => $enRetardMois,
            'depassementKm'   => $depassementKm,
            'depassementMois' => $depassementMois,
            'moisSeuil'       => $moisSeuil,
        ];
    }
}
