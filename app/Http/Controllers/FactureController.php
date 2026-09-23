<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\MarqueGarantie;
use App\Models\OrdreReparation;
use App\Services\ArrondiFdjService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur des Factures.
 *
 * Gère tout le cycle de facturation :
 *   - Création de la facture à partir d'un OR (pièces + main d'œuvre)
 *   - Enregistrement du paiement par le caissier
 *   - Accord/révocation de crédit pour permettre la restitution sans paiement immédiat
 * Monnaie : FDJ (Francs Djiboutiens). TVA : 10% par défaut.
 */
class FactureController extends Controller
{
    /**
     * Liste toutes les factures avec filtres par statut et période.
     * Calcule aussi les totaux (montants émis et payés) pour l'affichage en en-tête.
     */
    public function index(\Illuminate\Http\Request $request)
    {
        $query = Facture::with(['client', 'marqueGarantie', 'ordreReparation'])
            ->latest('date_emission');

        // Filtre par statut de la facture (emise, payee, annulee...)
        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        // Filtre par période : jour, mois ou année
        if ($periode = $request->get('periode')) {
            $date = $request->get('date_ref') ? \Carbon\Carbon::parse($request->get('date_ref')) : now();
            match($periode) {
                'jour'  => $query->whereDate('date_emission', $date->toDateString()),
                'mois'  => $query->whereYear('date_emission', $date->year)->whereMonth('date_emission', $date->month),
                'annee' => $query->whereYear('date_emission', $date->year),
                default => null,
            };
        }

        // On clone la requête pour calculer les totaux sans modifier la pagination
        $baseQuery   = clone $query;
        $totalEmises = (clone $baseQuery)->where('statut', 'emise')->sum('montant_ttc');
        $totalPayees  = (clone $baseQuery)->where('statut', 'payee')->sum('montant_ttc');
        $nbEmises     = (clone $baseQuery)->where('statut', 'emise')->count();
        $nbPayees     = (clone $baseQuery)->where('statut', 'payee')->count();

        $factures = $query->paginate(30)->withQueryString();

        return view('factures.index', compact('factures', 'totalEmises', 'totalPayees', 'nbEmises', 'nbPayees'));
    }

    /**
     * Véhicules terminés (statut "prêt") qui attendent d'être facturés. C'est ici —
     * et uniquement ici — que la caissière crée la facture (avec ou sans frais de
     * timbre). Un service gratuit n'est jamais facturé.
     */
    public function aFacturer()
    {
        $orsAFacturer = OrdreReparation::with(['client', 'vehicule', 'allDevis'])
            ->where('statut', 'pret')
            ->where('service_gratuit', false)
            ->whereDoesntHave('facture')
            ->orderBy('date_entree')
            ->get();

        return view('factures.a-facturer', compact('orsAFacturer'));
    }

    /**
     * Liste toutes les factures payées par bon de commande client (sociétés,
     * administrations...), avec le numéro de BC et le lien vers le scan joint
     * si un fichier a été téléversé au moment de l'encaissement.
     */
    public function bonsCommandeClients(Request $request)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_factures')) {
            abort(403);
        }

        $query = Facture::with(['client', 'ordreReparation'])
            ->where('mode_paiement', 'bon_commande')
            ->latest('date_paiement');

        if ($recherche = $request->get('recherche')) {
            $query->where(function ($q) use ($recherche) {
                $q->where('numero_bon_commande_client', 'like', "%{$recherche}%")
                  ->orWhere('numero', 'like', "%{$recherche}%")
                  ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%"));
            });
        }

        $totalBonsCommande = (clone $query)->sum('montant_ttc');
        $factures = $query->paginate(30)->withQueryString();

        return view('factures.bons-commande-clients', compact('factures', 'totalBonsCommande'));
    }

    /**
     * Affiche le formulaire de création d'une facture pour un OR donné.
     * Réservé aux utilisateurs avec la permission 'creer_factures' (caissier, admin).
     * Charge tous les devis de l'OR pour permettre de reprendre les lignes.
     */
    public function create(OrdreReparation $ordresReparation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('creer_factures')) {
            abort(403);
        }
        $ordresReparation->load(['client', 'vehicule', 'allDevis.lignes']);
        return view('factures.create', ['or' => $ordresReparation]);
    }

    /**
     * Enregistre une nouvelle facture en base de données.
     * Réservé aux utilisateurs avec la permission 'creer_factures'.
     * Étapes :
     *   1. Validation des données (lignes, TVA, mode de paiement)
     *   2. Vérification du plafond si paiement sur compte société
     *   3. Calcul des totaux HT / TVA / TTC dans une transaction atomique
     *   4. Création de la facture et de ses lignes
     *   5. Passage de l'OR au statut "facture"
     */
    public function store(Request $request, OrdreReparation $ordresReparation)
    {
      /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('creer_factures')) {
            abort(403);
        }

        $isSociete = in_array($ordresReparation->client->type, ['societe', 'assurance']);

        $request->validate([
            'frais_timbre'           => ['nullable', 'boolean'],
            'date_echeance'          => ['nullable', 'date'],
            'notes'                  => ['nullable', 'string'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.type'          => ['required', 'in:main_oeuvre,piece,forfait,autre'],
            'lignes.*.designation'   => ['required', 'string'],
            'lignes.*.reference'     => ['nullable', 'string', 'max:100'],
            'lignes.*.unite'         => ['nullable', 'string', 'max:20'],
            'lignes.*.quantite'      => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
            'lignes.*.remise'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'mode_paiement.required'       => 'Veuillez sélectionner le mode de paiement.',
            'mode_paiement.in'             => 'Le mode de paiement sélectionné est invalide.',
            'date_echeance.date'           => 'La date d\'échéance n\'est pas valide.',
            'lignes.required'              => 'La facture doit contenir au moins une ligne.',
            'lignes.min'                   => 'La facture doit contenir au moins une ligne.',
            'lignes.*.type.required'       => 'Veuillez sélectionner le type pour chaque ligne.',
            'lignes.*.type.in'             => 'Le type de ligne sélectionné est invalide.',
            'lignes.*.designation.required'=> 'La désignation est obligatoire pour chaque ligne.',
            'lignes.*.quantite.required'   => 'La quantité est obligatoire pour chaque ligne.',
            'lignes.*.quantite.min'        => 'La quantité doit être supérieure à zéro.',
            'lignes.*.prix_unitaire.required' => 'Le prix unitaire est obligatoire pour chaque ligne.',
            'lignes.*.prix_unitaire.min'   => 'Le prix unitaire ne peut pas être négatif.',
            'lignes.*.remise.min'          => 'La remise ne peut pas être négative.',
            'lignes.*.remise.max'          => 'La remise ne peut pas dépasser 100%.',
        ]);

        // Panne couverte par la garantie constructeur → la facture est adressée au
        // compte de la marque (ex: GWM) et non au client, jusqu'à son plafond.
        $estGarantieApprouvee = $ordresReparation->type === 'garantie' && $ordresReparation->statut_garantie === 'approuve';
        $marqueGarantie = null;

        if ($estGarantieApprouvee) {
            $marqueGarantie = MarqueGarantie::pourMarque($ordresReparation->vehicule->marque);
            if (! $marqueGarantie) {
                return back()->with('error', "Impossible de créer la facture : aucun compte garantie constructeur n'est configuré pour la marque « {$ordresReparation->vehicule->marque} ». Ajoutez-le dans Réglages atelier → Garantie constructeur avant de facturer.");
            }
            if (! $marqueGarantie->peutFacturer()) {
                $plafond = number_format($marqueGarantie->plafond_credit, 0, ',', ' ');
                $solde   = number_format($marqueGarantie->solde_utilise,  0, ',', ' ');
                return back()->with('error', "Impossible de créer la facture : le plafond du compte garantie {$marqueGarantie->nom} est atteint ({$solde} FDJ utilisés sur {$plafond} FDJ autorisés).");
            }
        } else {
            // Bloquer si le client a un compte crédit mais a dépassé son plafond
            $client = $ordresReparation->client;
            if ($client->compte_actif && $client->plafond_compte && ! $client->peutFacturerSurCompte()) {
                $plafond = number_format($client->plafond_compte, 0, ',', ' ');
                $solde   = number_format($client->solde_compte,   0, ',', ' ');
                return back()->with('error', "Impossible de créer la facture : le plafond de compte crédit de {$client->nom_complet} est atteint ({$solde} FDJ utilisés sur {$plafond} FDJ autorisés). Veuillez encaisser les factures en attente avant de continuer.");
            }
        }

        // Transaction : si une étape échoue, tout est annulé (aucune donnée partielle en base)
        $facture = DB::transaction(function () use ($request, $ordresReparation, $marqueGarantie) {
            $montantHt = 0;
            $lignes    = [];

            // Calcul du total HT pour chaque ligne (quantité × prix unitaire, avec remise)
            foreach ($request->lignes as $ligne) {
                $remise   = $ligne['remise'] ?? 0;
                $totalHt  = round($ligne['quantite'] * $ligne['prix_unitaire'] * (1 - $remise / 100), 2);
                $montantHt += $totalHt;
                $lignes[]  = [
                    'type'          => $ligne['type'],
                    'designation'   => $ligne['designation'],
                    'reference'     => ($ligne['type'] === 'piece') ? ($ligne['reference'] ?? null) : null,
                    'unite'         => $ligne['unite'] ?? null,
                    'quantite'      => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'remise'        => $remise,
                    'total_ht'      => $totalHt,
                ];
            }

            // Taux fixe imposé par la direction — non modifiable par le formulaire.
            $tauxTva = 10;

            // Ajuste le prix unitaire de la ligne la plus chère pour que le TTC final
            // soit un multiple de 5 FDJ (pas de coupure de 1 ni 2 en circulation).
            [$montantHt, $tva, $ttc] = ArrondiFdjService::arrondir($lignes, $tauxTva);
            $client = $ordresReparation->client;

            // Panne garantie approuvée : le client n'a rien à payer et n'a pas à
            // attendre le caissier — le crédit sur le compte de la marque est
            // accordé automatiquement pour permettre la restitution immédiate.
            $creditAutoGarantie = (bool) $marqueGarantie;

            // Création de la facture principale (toujours en statut "émise" — non payée)
            $facture = Facture::create([
                'numero'             => Facture::genererNumero(),
                'or_id'              => $ordresReparation->id,
                'devis_id'           => null,
                'client_id'          => $ordresReparation->client_id,
                'marque_garantie_id' => $marqueGarantie?->id,
                'statut'             => 'emise',
                'mode_paiement'      => null,
                'date_emission'      => now(),
                'notes'              => $request->notes,
                // Optionnel : ajouté seulement si la caissière a coché la case (montant fixe).
                'frais_timbre'       => $request->boolean('frais_timbre') ? 1000 : 0,
                'montant_ht'         => $montantHt,
                'taux_tva'           => $tauxTva,
                'montant_tva'        => $tva,
                'montant_ttc'        => $ttc,
                'montant_paye'       => 0,
                'date_paiement'      => null,
                // Le crédit n'est jamais accordé automatiquement pour un client — c'est le
                // caissier qui clique "Mettre sur le compte client". Exception : garantie
                // constructeur approuvée, cf. commentaire $creditAutoGarantie ci-dessus.
                'credit_accorde'     => $creditAutoGarantie,
                'credit_accorde_at'  => $creditAutoGarantie ? now() : null,
                'credit_accorde_par' => $creditAutoGarantie ? Auth::id() : null,
            ]);

            // Création des lignes de détail de la facture
            foreach ($lignes as $l) {
                LigneFacture::create(array_merge($l, ['facture_id' => $facture->id]));
            }

            // L'OR passe au statut "facturé" et on enregistre la date de sortie réelle
            $ordresReparation->update([
                'statut'             => 'facture',
                'date_sortie_reelle' => now(),
            ]);

            return $facture;
        });

        Activite::journaliser('creer_facture', "Création facture {$facture->numero} — {$facture->payeur_nom} — {$facture->montant_ttc} FDJ TTC", $facture);

        return redirect()->route('factures.show', $facture)
            ->with('success', "Facture {$facture->numero} créée avec succès.");
    }

    /**
     * Affiche la fiche détaillée d'une facture avec ses lignes.
     */
    public function show(Facture $facture)
    {
        $facture->load(['client', 'marqueGarantie', 'ordreReparation.vehicule', 'lignes']);
        return view('factures.show', compact('facture'));
    }

    /**
     * Génère la page d'impression de la facture (format A4, avec montant en lettres).
     * S'ouvre dans un nouvel onglet et lance l'impression automatiquement.
     */
    public function imprimer(Facture $facture)
    {
        $facture->load(['client', 'marqueGarantie', 'lignes', 'ordreReparation.vehicule', 'ordreReparation.devis.bonCommande']);
        return view('factures.print', compact('facture'));
    }

    /**
     * Enregistre le paiement d'une facture (caissier/admin uniquement).
     * Si le montant payé couvre le total, la facture passe à "payée".
     * Un paiement partiel laisse la facture en statut "émise".
     * Note : le passage à "livré" de l'OR se fait via le workflow de restitution,
     * pas automatiquement ici (pour ne pas contourner la vérification d'état du véhicule).
     */
    public function marquerPayee(Request $request, Facture $facture)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('encaisser_factures')) {
            abort(403);
        }
        $request->validate([
            'montant_paye'   => ['required', 'numeric', 'min:0'],
            'date_paiement'  => ['required', 'date'],
            'mode_paiement'  => ['required', 'in:especes,cheque,waafi,cac,carte,virement,bon_commande'],
            'numero_bon_commande_client' => ['required_if:mode_paiement,bon_commande', 'nullable', 'string', 'max:100'],
            'bon_commande_scan'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ], [
            'montant_paye.required'  => 'Le montant payé est obligatoire.',
            'montant_paye.numeric'   => 'Le montant payé doit être un nombre.',
            'montant_paye.min'        => 'Le montant payé ne peut pas être négatif.',
            'date_paiement.required'  => 'La date de paiement est obligatoire.',
            'date_paiement.date'      => 'La date de paiement n\'est pas valide.',
            'mode_paiement.required'  => 'Veuillez sélectionner le mode de paiement.',
            'mode_paiement.in'        => 'Mode de paiement invalide.',
            'numero_bon_commande_client.required_if' => 'Le numéro du bon de commande est obligatoire.',
            'bon_commande_scan.mimes' => 'Le scan doit être une image (JPG, PNG) ou un PDF.',
            'bon_commande_scan.max'   => 'Le scan ne doit pas dépasser 10 Mo.',
        ]);

        $data = [
            'montant_paye'  => $request->montant_paye,
            'date_paiement' => $request->date_paiement,
            'mode_paiement' => $request->mode_paiement ?? $facture->mode_paiement,
            // Facture payée seulement si le montant payé couvre le total
            'statut'        => $request->montant_paye >= $facture->montant_ttc ? 'payee' : 'emise',
        ];

        if ($request->mode_paiement === 'bon_commande') {
            $data['numero_bon_commande_client'] = $request->numero_bon_commande_client;

            if ($request->hasFile('bon_commande_scan')) {
                $file = $request->file('bon_commande_scan');
                $data['bon_commande_client_chemin']       = $file->store("bons-commande-clients/{$facture->id}", 'public');
                $data['bon_commande_client_nom_original'] = $file->getClientOriginalName();
            }
        }

        $facture->update($data);

        Activite::journaliser('payer_facture', "Paiement de {$request->montant_paye} FDJ enregistré sur facture {$facture->numero}", $facture);
        return back()->with('success', 'Paiement enregistré. Le réceptionniste peut maintenant restituer le véhicule.');
    }

    /**
     * Accorde un crédit sur une facture non payée (caissier/admin uniquement).
     * Cela permet au réceptionniste de restituer le véhicule même si la facture n'est pas encore réglée.
     * Le crédit est une autorisation temporaire — le paiement reste dû.
     */
    public function accorderCredit(Request $request, Facture $facture)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_compte_credit')) {
            abort(403);
        }

        $montantRestant = $facture->getMontantRestant();

        if ($facture->marque_garantie_id) {
            $marque = $facture->marqueGarantie;
            if (! $marque->peutFacturer($montantRestant)) {
                $plafond = number_format($marque->plafond_credit, 0, ',', ' ');
                $solde   = number_format($marque->solde_utilise,  0, ',', ' ');
                $montant = number_format($montantRestant,         0, ',', ' ');
                return back()->with('error', "Impossible de mettre sur le compte : cette facture ({$montant} FDJ) dépasserait le plafond du compte garantie {$marque->nom} (solde actuel {$solde} FDJ / plafond {$plafond} FDJ).");
            }
        } else {
            $client = $facture->client;
            if ($client->compte_actif && $client->plafond_compte && ! $client->peutFacturerSurCompte($montantRestant)) {
                $plafond   = number_format($client->plafond_compte, 0, ',', ' ');
                $solde     = number_format($client->solde_compte,   0, ',', ' ');
                $montant   = number_format($montantRestant,         0, ',', ' ');
                return back()->with('error', "Impossible de mettre sur le compte : cette facture ({$montant} FDJ) dépasserait le plafond de {$client->nom_complet} (solde actuel {$solde} FDJ / plafond {$plafond} FDJ).");
            }
        }

        $facture->update([
            'credit_accorde'     => true,
            'credit_accorde_at'  => now(),
            'credit_accorde_par' => Auth::id(),
        ]);

        Activite::journaliser('credit_facture', "Crédit accordé sur facture {$facture->numero}", $facture);
        return back()->with('success', 'Crédit accordé — le réceptionniste peut restituer le véhicule.');
    }

    /**
     * Révoque le crédit accordé sur une facture (caissier/admin uniquement).
     * Cela rebloque la restitution du véhicule si elle n'a pas encore eu lieu.
     */
    public function revoquerCredit(Facture $facture)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_compte_credit')) {
            abort(403);
        }

        $facture->update([
            'credit_accorde'     => false,
            'credit_accorde_at'  => null,
            'credit_accorde_par' => null,
        ]);

        Activite::journaliser('credit_facture_revoque', "Crédit révoqué sur facture {$facture->numero}", $facture);
        return back()->with('success', 'Crédit révoqué.');
    }
}
