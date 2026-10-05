<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\OrdreReparation;
use App\Models\Technicien;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur des Rapports et statistiques.
 *
 * Fournit trois types de vues :
 *   1. Tableau de bord statistique global (OR, techniciens, services, finances)
 *   2. Historique complet d'un véhicule (tous les OR par immatriculation)
 *   3. Historique complet d'un client (tous ses OR, tous véhicules confondus)
 */
class RapportController extends Controller
{
    /**
     * Page principale des rapports avec statistiques sur une période sélectionnable.
     * Périodes disponibles : semaine, mois, trimestre, année (ou toutes).
     * Sections couvertes :
     *   - Stats globales : total, en cours, terminés, annulés, garanties
     *   - Par technicien : OR total, actifs, terminés
     *   - Par service : rapide, mécanique, électricité, carrosserie, peinture
     *   - Par réceptionniste : OR créés ce mois, cette semaine, au total
     *   - Évolution mensuelle sur 12 mois (nombre d'OR et chiffre d'affaires)
     *   - Financier : CA encaissé, en attente, nombre de factures payées/émises
     */
    public function index(Request $request)
    {
        $periode = $request->get('periode', 'mois');

        // Calcul de la date de début de période pour filtrer les OR
        $debut = match($periode) {
            'semaine'   => now()->startOfWeek(),
            'mois'      => now()->startOfMonth(),
            'trimestre' => now()->subMonths(3)->startOfDay(),
            'annee'     => now()->startOfYear(),
            default     => null,  // Pas de filtre = toutes les données
        };

        // Fonction utilitaire : base de requête OR filtrée par période
        $baseQuery = fn() => $debut
            ? OrdreReparation::where('created_at', '>=', $debut)
            : OrdreReparation::query();

        // ── Stats globales ─────────────────────────────────────────
        $total     = $baseQuery()->count();
        $en_cours  = $baseQuery()->whereIn('statut', ['ouvert','diagnostic','devis_envoye','devis_accepte','en_cours','controle_qualite'])->count();
        $termines  = $baseQuery()->whereIn('statut', ['pret','facture','livre'])->count();
        $annules   = $baseQuery()->where('statut', 'annule')->count();
        $garanties = $baseQuery()->where('type', 'garantie')->count();

        // ── Par technicien : charge de travail et taux de complétion ──
        $techniciens = Technicien::where('actif', true)
            ->orderBy('nom')
            ->get()
            ->map(function ($tech) use ($baseQuery) {
                $q        = $baseQuery()->where('technicien_id', $tech->id);
                $total    = $q->count();
                $actifs   = (clone $q)->whereIn('statut', ['en_cours','diagnostic','controle_qualite','devis_accepte'])->count();
                $termines = (clone $q)->whereIn('statut', ['pret','facture','livre'])->count();

                // Performance réelle = durée estimée / durée nette (hors pauses) des travaux
                // pointés — 100% = dans les temps, plus = plus rapide que prévu, moins = retard.
                // "Charge" (ci-dessus) ne mesure que le volume d'OR, pas la vitesse d'exécution :
                // ce chiffre-là répond à "qui est le plus performant".
                // On exclut les travaux dont la durée nette est inférieure à 6 minutes : un OR
                // démarré puis terminé quasi instantanément n'est jamais une vraie réparation
                // (erreur de manipulation ou test) et fausserait la moyenne (ex: 5000%).
                $performances = (clone $q)
                    ->whereNotNull('heure_debut_travaux')->whereNotNull('heure_fin_travaux')->whereNotNull('duree_estimee')
                    ->get()
                    ->filter(fn ($or) => $or->getDureeNetteHeures() >= 0.1)
                    ->map->getPerformance()
                    ->filter(fn ($p) => $p !== null);

                return [
                    'id'                  => $tech->id,
                    'nom'                 => $tech->name,
                    'total'               => $total,
                    'actifs'              => $actifs,
                    'termines'            => $termines,
                    'performance_moyenne' => $performances->isNotEmpty() ? (int) round($performances->avg()) : null,
                    'nb_mesures'          => $performances->count(),
                ];
            });

        // Pour la barre de progression : on a besoin du maximum pour calculer le ratio
        $maxOrTech = $techniciens->max('total') ?: 1;

        // ── Par service : répartition des OR par spécialité d'atelier ─
        $services_data = [];
        foreach (['rapide','mecanique','electricite','carrosserie','peinture'] as $svc) {
            $q          = $baseQuery()->where('service', $svc);
            $total_s    = $q->count();
            $actifs_s   = (clone $q)->whereIn('statut', ['en_cours','diagnostic','controle_qualite'])->count();
            $termines_s = (clone $q)->whereIn('statut', ['pret','facture','livre'])->count();
            $services_data[] = [
                'slug'     => $svc,
                'label'    => (new OrdreReparation(['service' => $svc]))->getServiceLabel(),
                'total'    => $total_s,
                'actifs'   => $actifs_s,
                'termines' => $termines_s,
            ];
        }
        $maxOrService = max(array_column($services_data, 'total') ?: [1]);

        // ── Par réceptionniste : qui a créé combien d'OR ──────────────
        $conseillers = User::whereIn('role', ['receptionniste','admin'])
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($baseQuery) {
                $q = $baseQuery()->where('conseiller_id', $user->id);
                return [
                    'nom'       => $user->name,
                    'role'      => $user->getRoleLabel(),
                    'total'     => $q->count(),
                    // Ces deux compteurs ignorent le filtre période (toujours sur mois/semaine courants)
                    'ce_mois'   => OrdreReparation::where('conseiller_id', $user->id)
                                    ->where('created_at', '>=', now()->startOfMonth())->count(),
                    'cette_sem' => OrdreReparation::where('conseiller_id', $user->id)
                                    ->where('created_at', '>=', now()->startOfWeek())->count(),
                ];
            })->filter(fn($c) => $c['total'] > 0)->values();

        // ── Évolution mensuelle du nombre d'OR (année civile en cours) ───
        $annee = now()->year;
        $evolution = [];
        for ($m = 1; $m <= 12; $m++) {
            $evolution[] = [
                'label' => \Carbon\Carbon::createFromDate($annee, $m, 1)->translatedFormat('M'),
                'count' => OrdreReparation::whereYear('created_at', $annee)
                               ->whereMonth('created_at', $m)
                               ->count(),
            ];
        }
        $maxEvo = max(array_column($evolution, 'count') ?: [1]);

        // ── Financier : CA encaissé et en attente ─────────────────────
        $factureBase = fn() => $debut
            ? Facture::where('date_emission', '>=', $debut)
            : Facture::query();

        $caEncaisse  = (float) $factureBase()->where('statut', 'payee')->sum('montant_ttc');
        $caEnAttente = (float) $factureBase()->where('statut', 'emise')->sum('montant_ttc');
        $nbPayees    = $factureBase()->where('statut', 'payee')->count();
        $nbEmises    = $factureBase()->where('statut', 'emise')->count();
        $caTotal     = $caEncaisse + $caEnAttente;

        // Totaux pièces / main-d'œuvre : lignes des factures de la période
        // (émises ou payées — les factures annulées par avoir ne comptent pas), en HT
        $lignesPeriode = fn () => LigneFacture::query()
            ->join('factures', 'factures.id', '=', 'lignes_facture.facture_id')
            ->whereIn('factures.statut', ['emise', 'payee'])
            ->when($debut, fn ($q) => $q->where('factures.date_emission', '>=', $debut));

        $totalPiecesHt = (float) $lignesPeriode()->where('lignes_facture.type', 'piece')->sum('lignes_facture.total_ht');
        $qtePieces     = (float) $lignesPeriode()->where('lignes_facture.type', 'piece')->sum('lignes_facture.quantite');
        $totalMoHt     = (float) $lignesPeriode()->where('lignes_facture.type', 'main_oeuvre')->sum('lignes_facture.total_ht');
        $heuresMo      = (float) $lignesPeriode()->where('lignes_facture.type', 'main_oeuvre')->sum('lignes_facture.quantite');

        // Évolution du CA encaissé mois par mois (année civile en cours)
        $evolutionCA = [];
        for ($m = 1; $m <= 12; $m++) {
            $evolutionCA[] = [
                'label' => \Carbon\Carbon::createFromDate($annee, $m, 1)->translatedFormat('M'),
                'ca'    => (float) Facture::where('statut', 'payee')
                               ->whereYear('date_paiement', $annee)
                               ->whereMonth('date_paiement', $m)
                               ->sum('montant_ttc'),
            ];
        }
        $maxCA = max(array_column($evolutionCA, 'ca') ?: [1]);

        // ── Dernières réceptions (10 OR les plus récents) ─────────────
        $dernieres_receptions = OrdreReparation::with(['client','vehicule','conseiller'])
            ->latest()->limit(10)->get();

        return view('rapports.index', compact(
            'periode',
            'total', 'en_cours', 'termines', 'annules', 'garanties',
            'techniciens', 'maxOrTech',
            'services_data', 'maxOrService',
            'conseillers',
            'evolution', 'maxEvo',
            'caEncaisse', 'caEnAttente', 'caTotal', 'nbPayees', 'nbEmises',
            'totalPiecesHt', 'qtePieces', 'totalMoHt', 'heuresMo',
            'evolutionCA', 'maxCA',
            'dernieres_receptions'
        ));
    }

    /**
     * Historique complet d'un véhicule par son immatriculation.
     * Permet de retrouver tous les OR d'un véhicule en tapant son numéro de plaque.
     */
    public function historiqueVehicule(Request $request)
    {
        $immat    = $request->get('immat');
        $vehicule = null;
        $historique = collect();

        if ($immat) {
            $vehicule = Vehicule::with('client')->where('immatriculation', 'like', "%{$immat}%")->first();
            $historique = $vehicule
                ? OrdreReparation::with(['conseiller','technicien'])
                    ->where('vehicule_id', $vehicule->id)
                    ->latest()->get()
                : collect();
        }

        return view('rapports.historique-vehicule', compact('immat', 'vehicule', 'historique'));
    }

    /**
     * Historique complet d'un client : tous ses OR pour tous ses véhicules.
     * La recherche se fait d'abord par nom/téléphone, puis on sélectionne le client précis.
     * Fonctionnement en deux étapes : recherche → sélection → affichage historique.
     */
    public function historiqueClient(Request $request)
    {
        $search     = $request->get('q');
        $client     = null;
        $historique = collect();
        $clients    = collect();

        if ($search) {
            // Étape 1 : liste des clients correspondant à la recherche
            $clients = Client::where('nom', 'like', "%{$search}%")
                ->orWhere('prenom', 'like', "%{$search}%")
                ->orWhere('raison_sociale', 'like', "%{$search}%")
                ->orWhere('telephone', 'like', "%{$search}%")
                ->limit(10)->get();

            // Étape 2 : si un client a été sélectionné, on charge son historique complet
            if ($request->get('client_id')) {
                $client = Client::find($request->get('client_id'));
                $historique = $client
                    ? OrdreReparation::with(['vehicule','conseiller','technicien'])
                        ->where('client_id', $client->id)
                        ->latest()->get()
                    : collect();
            }
        }

        return view('rapports.historique-client', compact('search', 'client', 'historique', 'clients'));
    }
}
