<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use App\Models\Facture;
use App\Models\ImportFlotte;
use App\Models\LigneFacture;
use App\Models\LivraisonFlotte;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\ArrondiFdjService;
use App\Services\ImportFlotteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Flotte : pièces livrées à une société qui a son propre atelier (ex : bus).
 * Aucun passage au garage — ni réception, ni OR, ni affectation :
 *
 *   import Excel (plusieurs bus, plusieurs pièces par bus)
 *     → une livraison par bus et par date, avec son BC envoyé au magasin
 *     → le magasin valide et crée le BT
 *     → une facture par livraison (bouton « Facturer »)
 *
 * Chaque facture porte le bus : ses pièces apparaissent dans l'historique du
 * véhicule (fiche véhicule) et dans le rapport « Pièces par bus ».
 * Consultation : voir_factures. Import et facturation : creer_factures.
 */
class FlotteController extends Controller
{
    private const SESSION_APERCU = 'flotte_import_apercu';

    /** Livraisons à facturer / en attente du magasin, et liste des imports */
    public function index()
    {
        $livraisonsOuvertes = LivraisonFlotte::nonFacturees()
            ->with(['client', 'vehicule', 'import', 'lignes', 'factures', 'bonCommande.bonTransfert'])
            ->orderBy('date_livraison')
            ->get();

        $aFacturer = $livraisonsOuvertes->filter(fn ($l) => $l->statut() === 'a_facturer')->values();
        $attenteBt = $livraisonsOuvertes->filter(fn ($l) => $l->statut() === 'attente_bt')->values();

        $imports = ImportFlotte::with(['client', 'creePar'])
            ->withCount('livraisons')
            ->latest('id')
            ->paginate(20);

        return view('flotte.index', compact('aFacturer', 'attenteBt', 'imports'));
    }

    // ── Import ─────────────────────────────────────────────────────────

    public function importerForm(Request $request)
    {
        $this->autoriser('creer_factures');

        $clients = Client::where('type', '!=', 'particulier')
            ->withCount('vehicules')
            ->orderBy('raison_sociale')->orderBy('nom')
            ->get();

        return view('flotte.importer', [
            'clients'  => $clients,
            'clientId' => (int) $request->get('client_id') ?: null,
        ]);
    }

    /** Télécharge le modèle Excel à remplir */
    public function modele()
    {
        $classeur = app(ImportFlotteService::class)->modele();

        return response()->streamDownload(function () use ($classeur) {
            IOFactory::createWriter($classeur, 'Xlsx')->save('php://output');
        }, 'modele-livraison-flotte.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Lit le fichier et affiche ce qui va être créé — rien n'est encore enregistré */
    public function apercu(Request $request, ImportFlotteService $service)
    {
        $this->autoriser('creer_factures');

        $request->validate([
            'client_id'      => ['required', 'exists:clients,id'],
            'date_livraison' => ['required', 'date'],
            'fichier'        => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ], [
            'client_id.required' => 'Choisissez la société cliente.',
            'fichier.required'   => 'Sélectionnez le fichier Excel à importer.',
            'fichier.mimes'      => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
            'fichier.max'        => 'Le fichier ne doit pas dépasser 5 Mo.',
        ]);

        $client  = Client::findOrFail($request->client_id);
        $fichier = $request->file('fichier');

        $lignes = $service->lire($fichier->getRealPath(), strtolower($fichier->getClientOriginalExtension()));
        if ($lignes === null) {
            return back()->withInput()->with('error', 'Le fichier est vide ou illisible.');
        }

        $analyse = $service->analyser($lignes, $client, $request->date_livraison);

        // Un aperçu précédent non confirmé : son fichier n'a plus lieu d'être
        $this->oublierApercu();

        $jeton = null;
        if (! $analyse['erreurs']) {
            $jeton  = (string) Str::uuid();
            $chemin = $fichier->storeAs('imports-flotte', $jeton . '.' . strtolower($fichier->getClientOriginalExtension()), 'public');
            session([self::SESSION_APERCU => [
                'jeton'       => $jeton,
                'client_id'   => $client->id,
                'chemin'      => $chemin,
                'nom'         => $fichier->getClientOriginalName(),
                'notes'       => $request->notes,
                'groupes'     => $analyse['groupes'],
                'doublons'    => ! empty($analyse['avertissements']),
            ]]);
        }

        return view('flotte.apercu', [
            'client'         => $client,
            'analyse'        => $analyse,
            'jeton'          => $jeton,
            'nomFichier'     => $fichier->getClientOriginalName(),
        ]);
    }

    /** Confirme l'aperçu : crée les livraisons, les BC et les envoie au magasin */
    public function importer(Request $request, ImportFlotteService $service)
    {
        $this->autoriser('creer_factures');

        $apercu = session(self::SESSION_APERCU);
        if (! $apercu || $apercu['jeton'] !== $request->jeton) {
            return redirect()->route('flotte.importer.form')->with('error', "L'aperçu a expiré ou a déjà été importé. Rechargez le fichier.");
        }
        if ($apercu['doublons'] && ! $request->boolean('confirmer_doublons')) {
            return redirect()->route('flotte.importer.form')->with('error', "Import non effectué : cochez la case de confirmation des avertissements, ou vérifiez le fichier.");
        }

        $client = Client::findOrFail($apercu['client_id']);
        session()->forget(self::SESSION_APERCU);

        $import = $service->creer($client, $apercu['groupes'], $apercu['chemin'], $apercu['nom'], $apercu['notes']);
        $import->load('livraisons.bonCommande');

        $nbBc = $import->livraisons->whereNotNull('bon_commande_id')->count();
        Activite::journaliser('import_flotte', "Import flotte {$import->numero} — {$client->nom_complet} — {$import->livraisons->count()} livraison(s), {$nbBc} BC envoyé(s) au magasin", $import);

        return redirect()->route('flotte.show', $import)
            ->with('success', "Import {$import->numero} enregistré : {$import->livraisons->count()} livraison(s), {$nbBc} bon(s) de commande envoyé(s) au magasin.");
    }

    public function show(ImportFlotte $import)
    {
        $import->load(['client', 'creePar', 'livraisons.vehicule', 'livraisons.lignes', 'livraisons.factures', 'livraisons.bonCommande.bonTransfert', 'livraisons.bonCommande.lignes']);

        return view('flotte.show', compact('import'));
    }

    // ── Facturation ────────────────────────────────────────────────────

    /** Formulaire de facture d'une livraison, prérempli (quantités du BT, prix du fichier) */
    public function facturerForm(LivraisonFlotte $livraison)
    {
        $this->autoriser('creer_factures');

        if ($redirection = $this->verifierFacturable($livraison)) {
            return $redirection;
        }

        $livraison->load(['client', 'vehicule', 'import', 'bonCommande.bonTransfert']);

        return view('flotte.facturer', [
            'livraison' => $livraison,
            'lignes'    => $livraison->lignesProposees(),
        ]);
    }

    /** Crée la facture de la livraison (sans OR) — même calcul que la facture d'un OR */
    public function facturer(Request $request, LivraisonFlotte $livraison)
    {
        $this->autoriser('creer_factures');

        $request->validate([
            'frais_timbre'           => ['nullable', 'boolean'],
            'date_echeance'          => ['nullable', 'date'],
            'notes'                  => ['nullable', 'string'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.type'          => ['required', 'in:main_oeuvre,piece'],
            'lignes.*.designation'   => ['required', 'string'],
            'lignes.*.reference'     => ['nullable', 'string', 'max:100'],
            'lignes.*.quantite'      => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
            'lignes.*.remise'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'lignes.required'                 => 'La facture doit contenir au moins une ligne.',
            'lignes.min'                      => 'La facture doit contenir au moins une ligne.',
            'lignes.*.designation.required'   => 'La désignation est obligatoire pour chaque ligne.',
            'lignes.*.quantite.required'      => 'La quantité est obligatoire pour chaque ligne.',
            'lignes.*.quantite.min'           => 'La quantité doit être supérieure à zéro.',
            'lignes.*.prix_unitaire.required' => 'Le prix unitaire est obligatoire pour chaque ligne.',
            'lignes.*.prix_unitaire.min'      => 'Le prix unitaire ne peut pas être négatif.',
            'lignes.*.remise.max'             => 'La remise ne peut pas dépasser 100%.',
        ]);

        // Lignes et montants calculés avant la création : le plafond du compte est
        // vérifié avec le montant de cette facture, comme « Mettre sur le compte »
        $lignes = [];
        foreach ($request->lignes as $ligne) {
            $remise   = $ligne['remise'] ?? 0;
            $lignes[] = [
                'type'          => $ligne['type'],
                'designation'   => $ligne['designation'],
                'reference'     => $ligne['type'] === 'piece' ? ($ligne['reference'] ?? null) : null,
                'unite'         => null,
                'quantite'      => $ligne['quantite'],
                'prix_unitaire' => $ligne['prix_unitaire'],
                'remise'        => $remise,
                'total_ht'      => round($ligne['quantite'] * $ligne['prix_unitaire'] * (1 - $remise / 100), 2),
            ];
        }

        // Même taux et même arrondi FDJ (total multiple de 5) que les factures d'OR
        $tauxTva = 10;
        [$montantHt, $tva, $ttc] = ArrondiFdjService::arrondir($lignes, $tauxTva);

        // Société à compte crédit : la facture flotte part sur le compte (comptée
        // dans le solde), dans la limite du plafond
        $client    = $livraison->client;
        $surCompte = $client->compte_actif && $client->plafond_compte;
        if ($surCompte && ! $client->peutFacturerSurCompte($ttc)) {
            $plafond = number_format($client->plafond_compte, 0, ',', ' ');
            $solde   = number_format($client->solde_compte,   0, ',', ' ');
            $montant = number_format($ttc, 0, ',', ' ');
            return back()->withInput()->with('error', "Impossible de créer la facture : elle ({$montant} FDJ) dépasserait le plafond de compte crédit de {$client->nom_complet} ({$solde} FDJ utilisés sur {$plafond} FDJ autorisés). Veuillez encaisser les factures en attente avant de continuer.");
        }

        $facture = DB::transaction(function () use ($request, $livraison, $lignes, $montantHt, $tauxTva, $tva, $ttc, $surCompte) {
            // Verrou : deux clics sur « Facturer » ne doivent pas créer deux factures
            $livraison = LivraisonFlotte::whereKey($livraison->id)->lockForUpdate()->firstOrFail();
            if ($this->verifierFacturable($livraison)) {
                return null;
            }

            $facture = Facture::create([
                'numero'              => Facture::genererNumero(),
                'or_id'               => null,
                'devis_id'            => null,
                'client_id'           => $livraison->client_id,
                'vehicule_id'         => $livraison->vehicule_id,
                'livraison_flotte_id' => $livraison->id,
                'marque_garantie_id'  => null,
                'statut'              => 'emise',
                'mode_paiement'       => null,
                'date_emission'       => now(),
                'date_echeance'       => $request->date_echeance,
                'notes'               => $request->notes,
                'frais_timbre'        => $request->boolean('frais_timbre') ? 1000 : 0,
                'montant_ht'          => $montantHt,
                'taux_tva'            => $tauxTva,
                'montant_tva'         => $tva,
                'montant_ttc'         => $ttc,
                'montant_paye'        => 0,
                'date_paiement'       => null,
                'credit_accorde'      => $surCompte,
            ]);

            foreach ($lignes as $l) {
                LigneFacture::create(array_merge($l, ['facture_id' => $facture->id]));
            }

            // Les pièces sont remises à la société, pas réceptionnées au garage :
            // le BC est clos pour ne pas rester dans « Suivi des pièces »
            if ($livraison->bonCommande && $livraison->bonCommande->statut !== 'recu') {
                $livraison->bonCommande->update(['statut' => 'recu']);
                $livraison->bonCommande->lignes()->update(['recu' => true]);
            }

            return $facture;
        });

        if (! $facture) {
            return $this->verifierFacturable($livraison->fresh()) ?? redirect()->route('flotte.index');
        }

        Activite::journaliser('creer_facture', "Création facture flotte {$facture->numero} — {$facture->payeur_nom} — bus {$livraison->vehicule?->immatriculation} — " . number_format($facture->montant_ttc, 0, ',', ' ') . " FDJ TTC", $facture);

        return redirect()->route('factures.show', $facture)
            ->with('success', "Facture {$facture->numero} créée pour le bus {$livraison->vehicule?->immatriculation}" . ($facture->credit_accorde ? ' — mise sur le compte de la société.' : '.'));
    }

    // ── Rapport ────────────────────────────────────────────────────────

    /**
     * Pièces et main-d'œuvre facturées par bus, pour une société et une
     * période (factures flotte et factures d'OR confondues).
     */
    public function rapport(Request $request)
    {
        $request->validate([
            'client_id'  => ['nullable', 'integer'],
            'date_debut' => ['nullable', 'date'],
            'date_fin'   => ['nullable', 'date'],
        ]);

        $clients = Client::whereIn('id', ImportFlotte::select('client_id'))
            ->orderBy('raison_sociale')->orderBy('nom')
            ->get();

        $client  = $request->filled('client_id') ? Client::find($request->client_id) : null;
        $filtres = [
            'date_debut' => $request->get('date_debut', now()->startOfMonth()->toDateString()),
            'date_fin'   => $request->get('date_fin', now()->toDateString()),
            'type'       => '',
        ];

        $parBus = collect();
        if ($client) {
            $parBus = LigneFacture::historique(null, $client->id, $filtres)
                ->groupBy(fn ($l) => $l->facture->vehiculeConcerne()?->id ?? 0)
                ->map(function ($lignes) {
                    $vehicule = $lignes->first()->facture->vehiculeConcerne();
                    return [
                        'vehicule'    => $vehicule,
                        'nb_factures' => $lignes->pluck('facture_id')->unique()->count(),
                        'qte_pieces'  => $lignes->where('type', 'piece')->sum('quantite'),
                        'pieces_ht'   => $lignes->where('type', 'piece')->sum('total_ht'),
                        'mo_ht'       => $lignes->where('type', 'main_oeuvre')->sum('total_ht'),
                        'autres_ht'   => $lignes->whereNotIn('type', ['piece', 'main_oeuvre'])->sum('total_ht'),
                        'total_ht'    => $lignes->sum('total_ht'),
                    ];
                })
                ->sortBy(fn ($b) => $b['vehicule']?->immatriculation)
                ->values();
        }

        return view('flotte.rapport', compact('clients', 'client', 'filtres', 'parBus'));
    }

    // ── Outils ─────────────────────────────────────────────────────────

    private function autoriser(string $permission): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403);
        }
    }

    /** Redirection si la livraison ne peut pas (ou plus) être facturée, sinon null */
    private function verifierFacturable(LivraisonFlotte $livraison)
    {
        $livraison->load(['factures', 'lignes', 'bonCommande.bonTransfert']);

        if ($facture = $livraison->factureActive()) {
            return redirect()->route('factures.show', $facture)
                ->with('error', "Cette livraison est déjà facturée ({$facture->numero}).");
        }
        if ($livraison->statut() === 'attente_bt') {
            return redirect()->route('flotte.show', $livraison->import_flotte_id)
                ->with('error', "Impossible de facturer : le magasin n'a pas encore envoyé le bon de transfert (BT) pour ce bus. Il peut aussi être saisi à la main depuis le bon de commande.");
        }

        return null;
    }

    /** Supprime le fichier d'un aperçu non confirmé */
    private function oublierApercu(): void
    {
        $ancien = session(self::SESSION_APERCU);
        if ($ancien && ! empty($ancien['chemin']) && ! ImportFlotte::where('fichier_chemin', $ancien['chemin'])->exists()) {
            Storage::disk('public')->delete($ancien['chemin']);
        }
        session()->forget(self::SESSION_APERCU);
    }
}

