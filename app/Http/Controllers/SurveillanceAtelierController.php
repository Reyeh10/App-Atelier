<?php

namespace App\Http\Controllers;

use App\Models\DossierReception;
use App\Models\OrdreReparation;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Surveillance de l'atelier : vue d'ensemble, en un écran, des véhicules
 * présents à l'atelier (OR ni livrés ni annulés, et dossiers de réception
 * dont le devis n'est pas encore accepté), regroupés par étape.
 * Un clic sur un véhicule ouvre un aperçu (client, réception, techniciens,
 * feuilles de travail, devis, pièces, facture) avec un lien vers l'OR.
 */
class SurveillanceAtelierController extends Controller
{
    /** Étapes affichées en colonnes, dans l'ordre du parcours du véhicule */
    public const ETAPES = [
        'reception'  => ['label' => 'Réception / diagnostic', 'statuts' => ['ouvert', 'diagnostic']],
        'devis'      => ['label' => 'Devis',                  'statuts' => ['devis_envoye', 'devis_accepte']],
        'reparation' => ['label' => 'En réparation',          'statuts' => ['en_cours']],
        'controle'   => ['label' => 'Contrôle / lavage',      'statuts' => ['controle_qualite', 'lavage']],
        'sortie'     => ['label' => 'Prêt / à restituer',     'statuts' => ['pret', 'facture']],
    ];

    /**
     * Dossiers de réception encore sans OR (devis pas encore accepté) : le
     * véhicule est déjà à l'atelier. Statut du dossier => étape affichée.
     * « En attente du client » (devis refusé, dossier en relance) n'y figure
     * pas : le véhicule n'est en principe plus à l'atelier.
     */
    public const ETAPES_DOSSIER = [
        'nouveau'        => 'reception',
        'diagnostic'     => 'reception',
        'devis_en_cours' => 'devis',
    ];

    /** Au-delà de ce nombre de jours à l'atelier, le véhicule est signalé en retard (comme le tableau de bord) */
    public const JOURS_RETARD = 5;

    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_ordres')) {
            abort(403);
        }

        $ors = OrdreReparation::with([
                'client', 'vehicule', 'technicien', 'conseiller', 'facture',
                'allDevis.technicien', 'allDevis.bonCommande',
            ])
            ->whereNotIn('statut', ['livre', 'annule'])
            ->orderBy('date_entree')
            ->orderBy('id')
            ->get();

        $parEtape = collect(self::ETAPES)->map(fn ($etape) => $ors->whereIn('statut', $etape['statuts'])->values());

        $dossiers = DossierReception::with(['client', 'vehicule', 'conseiller', 'devis'])
            ->whereNull('or_id')
            ->whereIn('statut', array_keys(self::ETAPES_DOSSIER))
            ->orderBy('date_entree')
            ->orderBy('id')
            ->get();

        $dossiersParEtape = collect(self::ETAPES)->map(fn ($etape, $cle) => $dossiers
            ->filter(fn (DossierReception $d) => self::ETAPES_DOSSIER[$d->statut] === $cle)
            ->values());

        $stats = [
            'total'      => $ors->count() + $dossiers->count(),
            'en_cours'   => $ors->where('statut', 'en_cours')->count(),
            'pieces'     => $ors->filter(fn ($or) => $or->allDevis->contains(fn ($d) => $d->statut === 'accepte' && $d->attendPieces()))->count(),
            'prets'      => $parEtape['sortie']->count(),
            'en_retard'  => $ors->filter(fn ($or) => self::estEnRetard($or))->count()
                + $dossiers->filter(fn ($d) => self::dossierEnRetard($d))->count(),
        ];

        $techniciens = Technicien::where('actif', true)->orderBy('nom')->get();

        return view('surveillance-atelier.index', [
            'etapes'      => self::ETAPES,
            'parEtape'    => $parEtape,
            'dossiersParEtape' => $dossiersParEtape,
            'stats'       => $stats,
            'techniciens' => $techniciens,
        ]);
    }

    /** Véhicule à l'atelier depuis trop longtemps, ou date de sortie prévue dépassée */
    public static function estEnRetard(OrdreReparation $or): bool
    {
        if (in_array($or->statut, ['pret', 'facture'], true)) {
            return false;
        }

        return (int) $or->date_entree->diffInDays(now()) > self::JOURS_RETARD
            || ($or->date_sortie_prevue && $or->date_sortie_prevue->lt(today()));
    }

    /** Dossier de réception resté trop longtemps sans OR */
    public static function dossierEnRetard(DossierReception $dossier): bool
    {
        return $dossier->date_entree && (int) $dossier->date_entree->diffInDays(now()) > self::JOURS_RETARD;
    }

    /**
     * Feuilles de travail de l'OR, pour l'aperçu : la feuille 1 utilise les
     * champs de l'OR, les suivantes ceux de chaque devis complémentaire.
     *
     * @return array<int, array{numero:int, technicien:?string, service:string, etat:string, debut:mixed, fin:mixed, estimee:mixed, pieces:bool}>
     */
    public static function feuilles(OrdreReparation $or): array
    {
        $feuilles  = [];
        $principal = $or->devisPrincipal();

        $etat = fn ($tech, $debut, $fin) => $fin ? 'terminee' : ($debut ? 'en_cours' : ($tech ? 'affectee' : 'a_affecter'));

        if ($principal || $or->technicien_id) {
            $feuilles[] = [
                'numero'     => 1,
                'devis'      => $principal?->numero,
                'technicien' => $or->technicien?->name,
                'service'    => $or->getServiceLabel(),
                'etat'       => $etat($or->technicien_id, $or->heure_debut_travaux, $or->heure_fin_travaux),
                'debut'      => $or->heure_debut_travaux,
                'fin'        => $or->heure_fin_travaux,
                'estimee'    => $or->duree_estimee,
                'pieces'     => (bool) $principal?->attendPieces(),
            ];
        }

        foreach ($or->devisComplementaires() as $i => $d) {
            $feuilles[] = [
                'numero'     => $i + 2,
                'devis'      => $d->numero,
                'technicien' => $d->technicien?->name,
                'service'    => $d->getServiceLabel(),
                'etat'       => $etat($d->technicien_id, $d->heure_debut_travaux, $d->heure_fin_travaux),
                'debut'      => $d->heure_debut_travaux,
                'fin'        => $d->heure_fin_travaux,
                'estimee'    => $d->duree_estimee,
                'pieces'     => $d->attendPieces(),
            ];
        }

        return $feuilles;
    }
}
