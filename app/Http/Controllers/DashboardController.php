<?php

namespace App\Http\Controllers;

use App\Models\BonCommande;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\OrdreReparation;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\EntretienService;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur du tableau de bord principal.
 *
 * Affiche une vue synthétique de l'activité de l'atelier :
 *   - Statistiques globales (clients, véhicules, OR en cours, prêts...)
 *   - Alertes (OR en retard, factures non payées)
 *   - Listes adaptées à chaque rôle (chef de garage, caissier, admin)
 */
class DashboardController extends Controller
{
    /**
     * Prépare toutes les données du tableau de bord.
     * Chaque section est commentée pour indiquer à quel rôle elle est destinée.
     */
    public function index()
    {
        // ── Compteurs globaux affichés en en-tête ────────────────────
        $stats = [
            'clients'      => Client::count(),
            'vehicules'    => Vehicule::count(),
            'or_en_cours'  => OrdreReparation::whereIn('statut', ['ouvert', 'diagnostic', 'devis_envoye', 'devis_accepte', 'en_cours'])->count(),
            'or_prets'     => OrdreReparation::where('statut', 'pret')->count(),
            'or_garantie'  => OrdreReparation::where('type', 'garantie')->where('statut_garantie', 'en_attente')->count(),
            'utilisateurs' => User::count(),
        ];

        // ── Factures en attente de paiement (alerte caissier) ────────
        $factures_non_payees_count   = Facture::where('statut', 'emise')->count();
        $factures_non_payees_montant = (float) Facture::where('statut', 'emise')->sum('montant_ttc');

        // ── OR livrés ce mois et cette année (suivi de production) ───
        $or_livres_mois  = OrdreReparation::where('statut', 'livre')
                               ->whereYear('updated_at', now()->year)
                               ->whereMonth('updated_at', now()->month)
                               ->count();
        $or_livres_annee = OrdreReparation::where('statut', 'livre')
                               ->whereYear('updated_at', now()->year)
                               ->count();

        // ── Devis envoyés en attente de réponse client ────────────────
        $devis_en_attente = Devis::with(['ordreReparation.client', 'ordreReparation.vehicule'])
            ->where('statut', 'envoye')
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        // ── Bons de commande pièces non encore reçus ──────────────────
        $bons_commande_en_attente = BonCommande::with(['ordreReparation'])
            ->whereIn('statut', ['brouillon', 'commande'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // ── OR ouverts depuis plus de 5 jours et non terminés (alerte) ──
        $or_en_retard = OrdreReparation::with(['client', 'vehicule', 'technicien'])
            ->whereNotIn('statut', ['livre', 'annule', 'facture'])
            ->where('date_entree', '<', now()->subDays(5))
            ->orderBy('date_entree')
            ->limit(5)
            ->get();

        // ── Entretiens en retard signalés récemment (alerte chef de garage /
        // réceptionniste uniquement — pas le caissier, cf. User::peutVoirAlerteEntretien()).
        // Limitée à l'heure suivant la création de l'OR : passé ce délai, l'alerte
        // disparaît du tableau de bord mais reste affichée en permanence sur la
        // fiche de l'OR concerné (cf. ordres-reparations/show.blade.php).
       $entretien_en_retard = collect();

        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->peutVoirAlerteEntretien()) {
            $entretien_en_retard = OrdreReparation::with(['client', 'vehicule'])
                ->where('type', 'entretien')
                ->whereNotNull('entretien_km_seuil')
                ->where('created_at', '>=', now()->subHour())
                ->orderByDesc('created_at')
                ->get()
                ->filter(fn (OrdreReparation $or) => EntretienService::calculerRetard($or)['enRetard'])
                ->values();
        }

        // ── OR en attente de devis ou d'affectation (chef de garage) ─
        $or_attente_devis = OrdreReparation::with(['client', 'vehicule'])
            ->whereIn('statut', ['ouvert', 'diagnostic', 'devis_envoye'])
            ->orderBy('date_entree')
            ->get();

        // ── OR en cours de travaux avec mécanicien affecté (chef garage) ──
        $or_affectes = OrdreReparation::with(['client', 'vehicule', 'technicien'])
            ->whereNotNull('technicien_id')
            ->whereIn('statut', ['devis_accepte', 'en_cours', 'controle_qualite', 'lavage', 'pret'])
            ->orderBy('date_entree')
            ->get();

        // ── OR prêts à être facturés, sans facture encore (caissier) ─
        $or_prets_facturer = OrdreReparation::with(['client', 'vehicule'])
            ->where('statut', 'pret')
            ->whereDoesntHave('facture')
            ->orderBy('date_entree')
            ->get();

        // ── Les 8 derniers OR actifs (vue générale récente) ───────────
        $derniers_or = OrdreReparation::with(['client', 'vehicule'])
            ->whereNotIn('statut', ['livre', 'annule'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('dashboard', compact(
            'stats', 'derniers_or',
            'factures_non_payees_count', 'factures_non_payees_montant',
            'or_livres_mois', 'or_livres_annee',
            'devis_en_attente',
            'bons_commande_en_attente',
            'or_en_retard', 'entretien_en_retard',
            'or_attente_devis', 'or_affectes', 'or_prets_facturer'
        ));
    }
}
