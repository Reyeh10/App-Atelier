<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EncaissementGlobal;
use App\Models\Facture;
use App\Models\MarqueGarantie;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur des Encaissements Globaux.
 *
 * Un encaissement global permet de regrouper plusieurs factures d'un même payeur
 * en un seul paiement — soit un client (société ou assurance avec compte crédit),
 * soit une marque garantie constructeur (elle aussi soumise à un plafond de crédit,
 * cf. MarqueGarantie::plafond_credit, pour les factures des pannes couvertes par
 * la garantie). Exemple : la société X (ou la marque GWM) a 5 factures impayées →
 * on crée un encaissement global qui les regroupe toutes, et lorsqu'il est marqué
 * payé, les 5 factures passent à "payée".
 *
 * Ce module est destiné aux clients à compte crédit actif et aux marques garantie
 * actives uniquement — jamais les deux à la fois sur un même encaissement.
 */
class EncaissementGlobalController extends Controller
{
    /**
     * Liste tous les encaissements globaux, du plus récent au plus ancien.
     */
    public function index()
    {
      /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_encaissements')) {
            abort(403);
        }

        $encaissements = EncaissementGlobal::with('client', 'marqueGarantie')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('encaissements-globaux.index', compact('encaissements'));
    }

    /**
     * Affiche le formulaire de création d'un encaissement global.
     * Pré-sélectionne le payeur (client ou marque garantie) si son ID est fourni
     * en paramètre URL. Charge uniquement les factures émises et non encore
     * regroupées dans un autre encaissement.
     */
    public function create(Request $request)
    {
      //  if (! auth()->user()->hasPermission('gerer_encaissements')) abort(403);
      /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_encaissements')) {
            abort(403);
        }

        $clientId         = $request->query('client_id');
        $marqueGarantieId = $request->query('marque_garantie_id');
        $client           = null;
        $marqueGarantie   = null;

        if ($clientId) {
            // On s'assure que le client a bien un compte crédit actif
            $client = Client::where('id', $clientId)->where('compte_actif', true)->firstOrFail();
        } elseif ($marqueGarantieId) {
            $marqueGarantie = MarqueGarantie::where('id', $marqueGarantieId)->where('actif', true)->firstOrFail();
        }

        // Factures émises (non payées) et non déjà rattachées à un autre encaissement
        $facturesDisponibles = collect();
        if ($client) {
            $facturesDisponibles = Facture::where('client_id', $client->id)
                     ->whereNull('marque_garantie_id')
                     ->where('statut', 'emise')
                     ->whereNull('encaissement_global_id')
                     ->with('ordreReparation')
                     ->orderBy('date_emission')
                     ->get();
        } elseif ($marqueGarantie) {
            $facturesDisponibles = Facture::where('marque_garantie_id', $marqueGarantie->id)
                     ->where('statut', 'emise')
                     ->whereNull('encaissement_global_id')
                     ->with('ordreReparation')
                     ->orderBy('date_emission')
                     ->get();
        }

        // Listes pour le sélecteur : clients à compte crédit actif + marques garantie actives
        $clients = Client::where('compte_actif', true)->orderBy('nom')->get();
        $marques = MarqueGarantie::where('actif', true)->orderBy('nom')->get();

        return view('encaissements-globaux.create', compact('client', 'marqueGarantie', 'facturesDisponibles', 'clients', 'marques'));
    }

    /**
     * Crée un encaissement global à partir des factures sélectionnées.
     * Double vérification que les factures appartiennent bien au payeur choisi et sont
     * éligibles (statut émise, pas encore dans un autre encaissement) pour éviter les
     * incohérences. Le montant total est calculé automatiquement à partir des factures
     * sélectionnées. Le payeur est soit un client, soit une marque garantie — jamais les deux.
     */
    public function store(Request $request)
    {
       // if (! auth()->user()->hasPermission('gerer_encaissements')) abort(403);

       /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_encaissements')) {
            abort(403);
        }

        $request->validate([
            'client_id'          => 'nullable|required_without:marque_garantie_id|exists:clients,id',
            'marque_garantie_id' => 'nullable|required_without:client_id|exists:marques_garantie,id',
            'facture_ids'        => 'required|array|min:1',
            'facture_ids.*'      => 'exists:factures,id',
            'mode_paiement'      => 'required|string',
            'notes'              => 'nullable|string|max:500',
        ]);

        $client = $marqueGarantie = null;

        if ($request->marque_garantie_id) {
            $marqueGarantie = MarqueGarantie::where('id', $request->marque_garantie_id)->where('actif', true)->firstOrFail();
            $factures = Facture::whereIn('id', $request->facture_ids)
                               ->where('marque_garantie_id', $marqueGarantie->id)
                               ->where('statut', 'emise')
                               ->whereNull('encaissement_global_id')
                               ->get();
        } else {
            $client = Client::where('id', $request->client_id)->where('compte_actif', true)->firstOrFail();
            $factures = Facture::whereIn('id', $request->facture_ids)
                               ->where('client_id', $client->id)
                               ->whereNull('marque_garantie_id')
                               ->where('statut', 'emise')
                               ->whereNull('encaissement_global_id')
                               ->get();
        }

        if ($factures->isEmpty()) {
            return back()->withErrors(['facture_ids' => 'Aucune facture valide sélectionnée.']);
        }

        // Somme de toutes les factures (montant TTC + frais de timbre éventuels)
        $montantTotal = $factures->sum(fn($f) => $f->totalGeneral());

        $eg = EncaissementGlobal::create([
            'numero'             => EncaissementGlobal::genererNumero(),
            'client_id'          => $client?->id,
            'marque_garantie_id' => $marqueGarantie?->id,
            'montant_total'      => $montantTotal,
            'statut'             => 'emis',
            'mode_paiement'      => $request->mode_paiement,
            'date_emission'      => now()->toDateString(),
            'notes'              => $request->notes,
            'created_by_id'      => Auth::id(),
        ]);

        // Rattachement des factures à cet encaissement global
        Facture::whereIn('id', $factures->pluck('id'))
               ->update(['encaissement_global_id' => $eg->id]);

        return redirect()->route('encaissements-globaux.show', $eg)
                         ->with('success', 'Encaissement groupé créé — ' . $factures->count() . ' facture(s) regroupées.');
    }

    /**
     * Affiche la fiche détaillée d'un encaissement global avec ses factures liées.
     */
    public function show(EncaissementGlobal $encaissementsGlobaux)
    {
        $eg = $encaissementsGlobaux->load('client', 'marqueGarantie', 'factures.ordreReparation', 'createdBy');
        return view('encaissements-globaux.show', compact('eg'));
    }

    /**
     * Marque l'encaissement global comme payé et solde toutes ses factures en une opération.
     * Toutes les factures rattachées passent au statut "payée" avec le même montant payé = montant TTC.
     * Bloqué si l'encaissement est déjà marqué payé.
     */
    public function marquerPaye(Request $request, EncaissementGlobal $encaissementsGlobaux)
    {
        $eg = $encaissementsGlobaux;

        // Protection contre un double paiement accidentel
        if ($eg->statut === 'paye') {
            return back()->withErrors(['statut' => 'Cet encaissement est déjà payé.']);
        }

        $request->validate([
            'date_paiement' => 'required|date',
        ]);

        $eg->update([
            'statut'        => 'paye',
            'date_paiement' => $request->date_paiement,
        ]);

        // Toutes les factures liées sont soldées en une seule requête SQL (performant)
        $eg->factures()->update([
            'statut'        => 'payee',
            'date_paiement' => $request->date_paiement,
            'montant_paye' => DB::raw('montant_ttc'),  // montant_paye = montant_ttc pour chaque facture
        ]);

        return redirect()->route('encaissements-globaux.show', $eg)
                         ->with('success', 'Encaissement marqué payé — ' . $eg->factures()->count() . ' facture(s) soldées.');
    }
}
