<?php

namespace App\Services;

use App\Models\HoraireAtelier;
use App\Models\ParametreAtelier;
use App\Models\Reservation;
use App\Models\ServiceRapide;
use Carbon\Carbon;

/**
 * Gère la capacité du Service Rapide comme un planning horaire : chaque
 * réservation occupe un créneau [heure_rdv, heure_rdv + duree_estimee[.
 * La capacité (`capacite_service_rapide_simultanee`, réglée par l'admin ou
 * le chef d'atelier) est le nombre de véhicules pouvant être pris en charge
 * EN MÊME TEMPS (ex: 2 postes Service Rapide) — pas un total par jour.
 * Deux réservations qui ne se chevauchent pas peuvent donc utiliser le même
 * poste l'une après l'autre.
 */
class ReservationService
{
    /** Capacité simultanée configurée, ou null si aucune limite n'est réglée. */
    public static function capacite(): ?int
    {
        return ParametreAtelier::get()->capacite_service_rapide_simultanee;
    }

    /**
     * Catalogue des services "Autre" (hors entretien périodique), réglable par
     * l'admin/chef d'atelier depuis les Réglages atelier (cf. ServiceRapide).
     * Même forme de tableau que l'ancien config/services_rapides.php pour ne
     * pas avoir à toucher aux appelants (label/duree_min/duree_max).
     */
    public static function servicesAutre(): array
    {
        return ServiceRapide::where('actif', true)->orderBy('nom')->get()
            ->mapWithKeys(fn (ServiceRapide $s) => [
                $s->slug => ['label' => $s->nom, 'duree_min' => $s->duree_min, 'duree_max' => $s->duree_max],
            ])
            ->all();
    }

    /** Durée par défaut (en heures) à proposer pour un service du catalogue "Autre", arrondie au 1/4 d'heure supérieur. */
    public static function dureeDefautHeures(string $cleService): ?float
    {
        $service = self::servicesAutre()[$cleService] ?? null;
        if (! $service) return null;

        return ceil(($service['duree_max'] / 60) * 4) / 4;
    }

    /**
     * Prix fixe de main d'œuvre réglé par le chef d'atelier pour une clé de
     * service donnée (un slug ServiceRapide du catalogue, ou
     * "entretien_periodique" pour la main d'œuvre d'entretien périodique).
     */
    public static function tarif(string $cle): float
    {
        if ($cle === self::COLONNE_ENTRETIEN) {
            $tarifs = ParametreAtelier::get()->tarifs_service_rapide ?? [];
            return (float) ($tarifs[$cle] ?? 0);
        }
        return (float) (ServiceRapide::where('slug', $cle)->value('tarif') ?? 0);
    }

    /**
     * Indique si un créneau [heureDebut, heureDebut+dureeHeures[ est libre à
     * la date donnée (assez de postes disponibles compte tenu des réservations
     * déjà planifiées/honorées ce jour-là qui chevauchent ce créneau).
     */
    public static function creneauLibre(Carbon|string $date, string $heureDebut, float $dureeHeures, ?int $excludeReservationId = null): bool
    {
        $capacite = self::capacite();
        if ($capacite === null) return true;

        return self::chevauchements($date, $heureDebut, $dureeHeures, $excludeReservationId) < $capacite;
    }

    /**
     * Clé de colonne/service d'une réservation existante ou d'un choix en cours
     * de saisie (mêmes règles dans les deux cas) — utilisée pour le planning
     * (colonnesPlanning()) et pour empêcher deux réservations sur le même
     * service au même moment (cf. creneauLibrePourService()).
     */
    public static function colonneClePour(string $canalService, ?string $serviceCle, ?string $tache = null): string
    {
        if ($canalService === 'entretien_periodique') {
            return self::COLONNE_ENTRETIEN;
        }
        if ($serviceCle && array_key_exists($serviceCle, self::servicesAutre())) {
            return $serviceCle;
        }
        $labelsCatalogue = array_flip(array_map(fn ($s) => $s['label'], self::servicesAutre()));
        return $labelsCatalogue[$tache] ?? self::COLONNE_AUTRE_NON_CATEGORISE;
    }

    public static function colonneDe(Reservation $r): string
    {
        return self::colonneClePour($r->canal_service, $r->service_cle, $r->tache);
    }

    /**
     * Comme creneauLibre(), mais restreint aux réservations du même service
     * (colonne) — chaque service du catalogue "Autre" a sa propre capacité
     * (postes simultanés, réglable par service) au lieu de partager la
     * capacité globale de l'atelier. "Entretien périodique" et les tâches
     * "Autre" non catégorisées gardent la règle historique (un seul créneau
     * à la fois), n'ayant pas de capacité dédiée.
     */
    public static function creneauLibrePourService(string $colonne, Carbon|string $date, string $heureDebut, float $dureeHeures, ?int $excludeReservationId = null): bool
    {
        return self::chevauchementsPourService($colonne, $date, $heureDebut, $dureeHeures, $excludeReservationId)
            < self::capacitePourColonne($colonne);
    }

    /**
     * Capacité simultanée d'une colonne/service donnée : pour "Entretien
     * périodique", la capacité globale de l'atelier (`capacite_service_rapide_
     * simultanee`, réglée dans Paramètres → Service Rapide — illimité si non
     * renseignée) ; pour un service du catalogue "Autre", celle réglée
     * spécifiquement sur ce service (illimité si non renseignée) ; sinon
     * (tâche "Autre" non catégorisée) 1 par défaut, comme avant l'introduction
     * du catalogue.
     */
    public static function capacitePourColonne(string $colonne): int|float
    {
        if ($colonne === self::COLONNE_ENTRETIEN) {
            return self::capacite() ?? INF;
        }

        $service = ServiceRapide::where('slug', $colonne)->first();
        if ($service) {
            return $service->capacite_simultanee ?? INF;
        }
        return 1;
    }

    /** Nombre de réservations qui chevauchent le créneau donné, dans une colonne/service donnée. */
    public static function chevauchementsPourService(string $colonne, Carbon|string $date, string $heureDebut, float $dureeHeures, ?int $excludeReservationId = null): int
    {
        $debut = Carbon::parse($heureDebut);
        $fin   = $debut->copy()->addMinutes($dureeHeures * 60);

        return Reservation::whereDate('date_rdv', $date)
            ->whereIn('statut', ['planifie', 'honore'])
            ->whereNotNull('heure_rdv')
            ->whereNotNull('duree_estimee')
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->get()
            ->filter(fn (Reservation $r) => self::colonneDe($r) === $colonne)
            ->filter(function (Reservation $r) use ($debut, $fin) {
                $rDebut = Carbon::parse($r->heure_rdv);
                $rFin   = $rDebut->copy()->addMinutes((float) $r->duree_estimee * 60);
                return $rDebut->lt($fin) && $rFin->gt($debut);
            })
            ->count();
    }

    /** Nombre de réservations qui chevauchent le créneau donné (pour affichage "X/Y postes pris"). */
    public static function chevauchements(Carbon|string $date, string $heureDebut, float $dureeHeures, ?int $excludeReservationId = null): int
    {
        $debut = Carbon::parse($heureDebut);
        $fin   = $debut->copy()->addMinutes($dureeHeures * 60);

        return Reservation::whereDate('date_rdv', $date)
            ->whereIn('statut', ['planifie', 'honore'])
            ->whereNotNull('heure_rdv')
            ->whereNotNull('duree_estimee')
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->get()
            ->filter(function (Reservation $r) use ($debut, $fin) {
                $rDebut = Carbon::parse($r->heure_rdv);
                $rFin   = $rDebut->copy()->addMinutes((float) $r->duree_estimee * 60);
                // Chevauchement classique : les deux créneaux se croisent
                return $rDebut->lt($fin) && $rFin->gt($debut);
            })
            ->count();
    }

    /** Places libres à l'instant donné (par défaut maintenant) — utilisé pour le "Sans RDV" à la réception. */
    public static function placesLibresMaintenant(?Carbon $moment = null): ?int
    {
        $moment   = $moment ?? now();
        $capacite = self::capacite();
        if ($capacite === null) return null;

        // Créneau ponctuel d'une minute à l'instant présent, pour réutiliser le même calcul de chevauchement
        $occupees = self::chevauchements($moment->toDateString(), $moment->format('H:i'), 1 / 60);
        return max(0, $capacite - $occupees);
    }

    // Pas de la grille horaire affichée (minutes)
    private const PAS_GRILLE = 30;

    /** Clé/libellé de la colonne "Entretien périodique", affichée en premier dans le planning. */
    private const COLONNE_ENTRETIEN = 'entretien_periodique';
    private const COLONNE_AUTRE_NON_CATEGORISE = '__autre__';

    /**
     * Liste ordonnée des colonnes du planning : Entretien périodique, puis
     * chaque service actif du catalogue "Autre" (cf. ServiceRapide),
     * chacun avec sa propre colonne.
     */
    public static function colonnesPlanning(): array
    {
        $colonnes = [self::COLONNE_ENTRETIEN => 'Entretien périodique'];
        foreach (self::servicesAutre() as $cle => $s) {
            $colonnes[$cle] = $s['label'];
        }
        return $colonnes;
    }

    /**
     * Construit le planning visuel du jour : une grille prête à afficher
     * (lignes = créneaux de 30 min, une colonne par service — Entretien
     * périodique + chaque service du catalogue "Autre"). Si plusieurs
     * réservations du même service se chevauchent (rare), elles sont
     * regroupées dans la même case plutôt que dupliquées en sous-colonnes.
     *
     * @return array{heureDebut:string, heureFin:string, colonnes:array, rows:array, grid:array, sansHeure:\Illuminate\Support\Collection}
     */
    public static function planningJour(Carbon|string $date): array
    {
        $date = Carbon::parse($date);

        $horaire = HoraireAtelier::where('jour', $date->dayOfWeek)->first();
        $heureDebut = ($horaire && $horaire->actif && $horaire->heure_debut) ? substr($horaire->heure_debut, 0, 5) : '08:00';
        $heureFin   = ($horaire && $horaire->actif && $horaire->heure_fin)   ? substr($horaire->heure_fin, 0, 5)   : '17:00';

        $reservations = Reservation::with(['client', 'vehicule'])
            ->whereDate('date_rdv', $date)
            ->whereIn('statut', ['planifie', 'honore'])
            ->orderBy('heure_rdv')
            ->get();

        $avecHeure = $reservations->filter(fn ($r) => $r->heure_rdv && $r->duree_estimee);
        $sansHeure = $reservations->filter(fn ($r) => !$r->heure_rdv || !$r->duree_estimee);

        $colonnes = self::colonnesPlanning();

        // Répartit chaque réservation dans sa colonne de service (cf. colonneDe()).
        // Une tâche "Autre" qui ne correspond à aucune entrée du catalogue (saisie
        // libre) va dans une colonne de secours, ajoutée seulement si elle est utilisée.
        $parColonne = [];
        foreach ($avecHeure as $r) {
            $cle = self::colonneDe($r);
            if (! isset($colonnes[$cle])) {
                $cle = self::COLONNE_AUTRE_NON_CATEGORISE;
                $colonnes[$cle] = 'Autre (non catégorisé)';
            }
            $parColonne[$cle][] = $r;
        }

        // Regroupe, par colonne, les réservations qui se chevauchent dans le temps
        // (au lieu de créer des sous-colonnes dynamiques, on les liste ensemble).
        $placements = [];
        foreach ($parColonne as $cle => $items) {
            $items = collect($items)->sortBy('heure_rdv')->values();
            $cluster = null;
            foreach ($items as $r) {
                $debut = Carbon::parse($r->heure_rdv);
                $fin   = $debut->copy()->addMinutes((float) $r->duree_estimee * 60);

                if ($cluster && $debut->lt($cluster['fin'])) {
                    $cluster['fin'] = $cluster['fin']->max($fin);
                    $cluster['items'][] = ['reservation' => $r, 'debut' => $debut->format('H:i'), 'fin' => $fin->format('H:i')];
                } else {
                    if ($cluster) $placements[] = ['colonne' => $cle, ...$cluster];
                    $cluster = ['debut' => $debut, 'fin' => $fin, 'items' => [['reservation' => $r, 'debut' => $debut->format('H:i'), 'fin' => $fin->format('H:i')]]];
                }
            }
            if ($cluster) $placements[] = ['colonne' => $cle, ...$cluster];
        }

        // Lignes de la grille (créneaux de 30 min entre heureDebut et heureFin)
        $rows = [];
        $curseur = Carbon::parse($heureDebut);
        $bornefin = Carbon::parse($heureFin);
        while ($curseur->lt($bornefin)) {
            $rows[] = $curseur->format('H:i');
            $curseur->addMinutes(self::PAS_GRILLE);
        }

        // Grille [ligne][colonne] = null | 'skip' (couvert par un rowspan au-dessus) | ['items'=>, 'rowspan'=>]
        $grid = [];
        foreach ($rows as $rowIndex => $label) {
            foreach ($colonnes as $cle => $nom) {
                $grid[$rowIndex][$cle] = null;
            }
        }
        $debutGrille = Carbon::parse($heureDebut);
        foreach ($placements as $p) {
            $startRow = max(0, (int) floor($debutGrille->diffInMinutes($p['debut'], false) / self::PAS_GRILLE));
            $endRow   = (int) ceil($debutGrille->diffInMinutes($p['fin'], false) / self::PAS_GRILLE);
            $span     = max(1, $endRow - $startRow);
            if ($startRow >= count($rows)) continue; // hors grille (avant ouverture ou après fermeture)
            $span = min($span, count($rows) - $startRow);

            $grid[$startRow][$p['colonne']] = ['items' => $p['items'], 'rowspan' => $span];
            for ($i = 1; $i < $span; $i++) {
                $grid[$startRow + $i][$p['colonne']] = 'skip';
            }
        }

        return [
            'heureDebut' => $heureDebut,
            'heureFin'   => $heureFin,
            'colonnes'   => $colonnes,
            'rows'       => $rows,
            'grid'       => $grid,
            'sansHeure'  => $sansHeure,
        ];
    }
}
