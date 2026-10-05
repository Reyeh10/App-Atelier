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
     * Opérations du barème facturées en main-d'œuvre (et non comme pièce) :
     * libellé mis dans le devis => service du catalogue Service Rapide dont
     * elles prennent le tarif, et motifs (regex sur la désignation sans
     * accents, en minuscules) qui les reconnaissent quel que soit le modèle —
     * les carnets constructeur les écrivent en français ou en anglais
     * (« Permutation des pneus », « Wheel transposition », « Tire rotation »…).
     */
    public const OPERATIONS_MAIN_OEUVRE = [
        'Permutation des pneus' => [
            'service' => 'permutation_pneus',
            'motifs'  => [
                '/\b(permutation|rotation|transposition|inversion)\b.*\b(pneus?|pneumatiques?|roues?|tires?|tyres?|wheels?)\b/',
                '/\b(pneus?|pneumatiques?|roues?|tires?|tyres?|wheels?)\b.*\b(permutation|rotation|transposition|inversion)\b/',
            ],
        ],
    ];

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
    public static function resoudrePalier(Vehicule $vehicule, int $kmActuel, int $typeMoteurId, ?\Carbon\CarbonInterface $date = null, ?int $exclureOrId = null): ?int
    {
        $paliers = self::paliers($typeMoteurId);

        if ($paliers->isEmpty()) return null;

        // Correction d'un OR existant : on ne se prend pas soi-même pour « dernier entretien »
        $dernierEntretien = OrdreReparation::where('vehicule_id', $vehicule->id)
            ->where('type', 'entretien')
            ->whereNotNull('entretien_km_seuil')
            ->when($exclureOrId, fn ($q) => $q->whereKeyNot($exclureOrId))
            ->latest('date_entree')
            ->first();

        if ($dernierEntretien) {
            // On avance d'un cran dans le barème par rapport au dernier entretien fait
            $suivants = $paliers->filter(fn ($p) => $p->km_seuil > $dernierEntretien->entretien_km_seuil)->values();
            return $suivants->first()->km_seuil ?? $paliers->last()->km_seuil;
        }

        // Pas d'historique : le plus grand palier dont le km OU le mois est atteint,
        // sinon le premier palier du barème.
        // Délai compté jusqu'à la date du RDV pour un devis en avance, sinon aujourd'hui
        $moisEcoules = $vehicule->date_mise_circulation ? (int) $vehicule->date_mise_circulation->diffInMonths($date ?? now()) : null;

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
        return self::paliers($typeMoteurId)->firstWhere('km_seuil', $kmSeuil)?->mois_seuil;
    }

    /**
     * Paliers d'entretien du barème d'un moteur (km + délai en mois), dans
     * l'ordre : ceux de la vidange (« Huile moteur »). Si un barème n'a pas de
     * ligne « Huile moteur », tous les kilométrages du barème servent de
     * paliers — plutôt qu'aucun, ce qui laissait le devis vide.
     *
     * @return \Illuminate\Support\Collection<int, object{km_seuil:int, mois_seuil:?int}>
     */
    public static function paliers(int $typeMoteurId): \Illuminate\Support\Collection
    {
        $paliers = EntretienTache::where('type_moteur_id', $typeMoteurId)
            ->where('designation', 'Huile moteur')
            ->orderBy('km_seuil')
            ->get(['km_seuil', 'mois_seuil']);

        if ($paliers->isEmpty()) {
            $paliers = EntretienTache::where('type_moteur_id', $typeMoteurId)
                ->selectRaw('km_seuil, MIN(mois_seuil) as mois_seuil')
                ->groupBy('km_seuil')
                ->orderBy('km_seuil')
                ->get();
        }

        return $paliers->unique('km_seuil')->values();
    }

    /**
     * Tâches du barème à faire au palier $palier, pour les actions données.
     *
     * Certaines tâches sont notées au barème à un kilométrage qui n'est pas un
     * palier de vidange (ex : liquide de frein « ne pas dépasser 40 000 km »
     * alors que les vidanges tombent à 35 000 et 42 500 km) : elles n'étaient
     * jamais proposées. Ce sont des échéances « à ne pas dépasser » : elles
     * sont rattachées au dernier palier qui ne les dépasse pas (au premier
     * palier si elles tombent avant) ; celles notées au-delà du dernier palier
     * du barème restent hors calendrier.
     *
     * @param  array<int, string>  $actions  remplacer / inspecter / nettoyer / lubrifier
     */
    public static function tachesDuPalier(int $typeMoteurId, int $palier, array $actions): \Illuminate\Support\Collection
    {
        $kmPaliers = self::paliers($typeMoteurId)->pluck('km_seuil')->map(fn ($km) => (int) $km);

        return EntretienTache::where('type_moteur_id', $typeMoteurId)
            ->whereIn('action', $actions)
            ->orderBy('designation')
            ->get()
            ->filter(function (EntretienTache $tache) use ($kmPaliers, $palier) {
                $km = (int) $tache->km_seuil;
                if ($kmPaliers->contains($km)) {
                    return $km === $palier;
                }
                // Au-delà du dernier palier du barème : hors calendrier, pas avancé
                if ($km > $kmPaliers->max()) {
                    return false;
                }
                $rattache = $kmPaliers->filter(fn ($p) => $p <= $km)->max() ?? $kmPaliers->min();
                return $rattache === $palier;
            })
            ->values();
    }

    /**
     * Nom de la pièce à mettre dans un devis : la désignation du barème sans sa
     * remarque (« Huile de différentiel — remplacement à 50 000 km… » donne
     * « Huile de différentiel »).
     */
    /**
     * Lignes de main-d'œuvre à ajouter au devis pour les opérations du barème
     * à effectuer à ce palier (ex : permutation des pneus), au tarif du service
     * correspondant (Réglages atelier → Service Rapide).
     *
     * Seules les opérations marquées R (« remplacer ») au barème sont faites et
     * facturées ; un I (ex : permutation « I R I R… » du Tank 700) n'est qu'un
     * contrôle et reste sur la feuille de travail.
     *
     * @return array<int, array{designation:string, prix_unitaire:float}>
     */
    public static function mainOeuvreDuPalier(int $typeMoteurId, int $palier): array
    {
        return self::tachesDuPalier($typeMoteurId, $palier, ['remplacer'])
            ->map(fn (EntretienTache $t) => self::operationMainOeuvre($t->designation))
            ->filter()
            ->unique()
            ->map(fn ($libelle) => [
                'designation'   => $libelle,
                'prix_unitaire' => ReservationService::tarif(self::OPERATIONS_MAIN_OEUVRE[$libelle]['service']),
            ])
            ->values()
            ->all();
    }

    /**
     * Contrôles, nettoyages et lubrifications de la feuille de travail, groupés
     * par action. Les opérations de main-d'œuvre à effectuer (ex : permutation
     * des pneus marquée R) y figurent aussi, avec les contrôles, pour que le
     * technicien les voie.
     */
    public static function controlesDuPalier(int $typeMoteurId, int $palier): \Illuminate\Support\Collection
    {
        return self::tachesDuPalier($typeMoteurId, $palier, ['inspecter', 'nettoyer', 'lubrifier', 'remplacer'])
            ->filter(fn (EntretienTache $t) => $t->action !== 'remplacer' || self::operationMainOeuvre($t->designation) !== null)
            ->groupBy(fn (EntretienTache $t) => $t->action === 'remplacer' ? 'inspecter' : $t->action);
    }

    /**
     * Pièces à remplacer au palier, hors opérations facturées en main-d'œuvre
     * (une permutation des pneus saisie « à remplacer » dans un barème ne doit
     * pas arriver dans le devis comme une pièce à 0 FDJ).
     */
    public static function piecesDuPalier(int $typeMoteurId, int $palier): \Illuminate\Support\Collection
    {
        return self::tachesDuPalier($typeMoteurId, $palier, ['remplacer'])
            ->reject(fn (EntretienTache $t) => self::operationMainOeuvre($t->designation) !== null)
            ->values();
    }

    /**
     * Libellé de l'opération de main-d'œuvre reconnue dans une désignation du
     * barème (français ou anglais, avec ou sans accents), ou null si ce n'est
     * pas une opération facturée en main-d'œuvre.
     */
    public static function operationMainOeuvre(string $designation): ?string
    {
        $texte = mb_strtolower(\Illuminate\Support\Str::ascii($designation));
        foreach (self::OPERATIONS_MAIN_OEUVRE as $libelle => $operation) {
            foreach ($operation['motifs'] as $motif) {
                if (preg_match($motif, $texte)) {
                    return $libelle;
                }
            }
        }
        return null;
    }

    public static function libellePiece(string $designation): string
    {
        return trim(explode(' — ', $designation, 2)[0]);
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
            ? (int) $or->vehicule->date_mise_circulation->diffInMonths($or->date_entree) - $moisSeuil
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
