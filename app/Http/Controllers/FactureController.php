<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Avoir;
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
        // Classées par numéro, le plus grand en premier : année puis numéro de la
        // facture (« 101/GARA/2026 » → 2026 puis 101), comparés comme des nombres
        $query = Facture::with(['client', 'marqueGarantie', 'ordreReparation', 'vehicule'])
            ->orderByRaw("CAST(SUBSTRING_INDEX(numero, '/', -1) AS UNSIGNED) DESC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(numero, '/', 1) AS UNSIGNED) DESC");

        // Recherche : n° de facture, client (nom, téléphone), n° d'OR, immatriculation,
        // marque garantie ou n° de bon de commande client
        if ($recherche = trim((string) $request->get('recherche'))) {
            $query->where(function ($q) use ($recherche) {
                $q->where('numero', 'like', "%{$recherche}%")
                  ->orWhere('numero_bon_commande_client', 'like', "%{$recherche}%")
                  ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%")
                      ->orWhere('prenom', 'like', "%{$recherche}%")
                      ->orWhere('raison_sociale', 'like', "%{$recherche}%")
                      ->orWhere('telephone', 'like', "%{$recherche}%"))
                  ->orWhereHas('ordreReparation', fn ($o) => $o->where('numero', 'like', "%{$recherche}%"))
                  ->orWhereHas('vehicule', fn ($v) => $v->where('immatriculation', 'like', "%{$recherche}%"))
                  ->orWhereHas('ordreReparation.vehicule', fn ($v) => $v->where('immatriculation', 'like', "%{$recherche}%"))
                  ->orWhereHas('marqueGarantie', fn ($m) => $m->where('nom', 'like', "%{$recherche}%"));
            });
        }

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
        $orsAFacturer = OrdreReparation::with(['client', 'vehicule', 'allDevis', 'photosOr'])
            ->aFacturer()
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

        $query = Facture::with(['client', 'ordreReparation', 'vehicule'])
            ->where('mode_paiement', 'bon_commande')
            ->latest('date_paiement')
            ->latest('id');

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
        if ($ordresReparation->facture) {
            return redirect()->route('factures.show', $ordresReparation->facture)
                ->with('error', "Cet OR a déjà la facture {$ordresReparation->facture->numero}.");
        }
        // Même règle que la liste « À facturer », plus les pièces reçues
        if ($raison = $ordresReparation->raisonNonFacturable()) {
            return redirect()->route('ordres-reparations.show', $ordresReparation)->with('error', $raison);
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

        // Une seule facture en vigueur par OR (une facture annulée par avoir ne compte plus)
        if ($ordresReparation->facture) {
            return redirect()->route('factures.show', $ordresReparation->facture)
                ->with('error', "Cet OR a déjà la facture {$ordresReparation->facture->numero}.");
        }
        if ($raison = $ordresReparation->raisonNonFacturable()) {
            return redirect()->route('ordres-reparations.show', $ordresReparation)->with('error', $raison);
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
            // Dossier de preuves garantie (VIN, tableau de bord, pièce endommagée,
            // pièce neuve, référence pièce — vidéo du bruit facultative) obligatoire
            // avant de facturer, pour pouvoir le joindre à la réclamation constructeur.
            $categoriesManquantes = $ordresReparation->categoriesGarantieManquantes();
            if (! empty($categoriesManquantes)) {
                return back()->with('error', 'Impossible de créer la facture : le dossier de preuves garantie est incomplet — il manque : ' . implode(', ', $categoriesManquantes) . '. Ajoutez ces photos depuis la fiche de l\'OR.');
            }

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

            // L'OR passe au statut "facturé" et on enregistre la date de sortie réelle —
            // sauf s'il a déjà été restitué (refacturation après un avoir d'annulation)
            if ($ordresReparation->statut !== 'livre') {
                $ordresReparation->update([
                    'statut'             => 'facture',
                    'date_sortie_reelle' => now(),
                ]);
            }

            return $facture;
        });

        Activite::journaliser('creer_facture', "Création facture {$facture->numero} — {$facture->payeur_nom} — " . number_format($facture->montant_ttc, 0, ',', ' ') . " FDJ TTC", $facture);

        return redirect()->route('factures.show', $facture)
            ->with('success', "Facture {$facture->numero} créée avec succès.");
    }

    /**
     * Affiche la fiche détaillée d'une facture avec ses lignes.
     */
    public function show(Facture $facture)
    {
        $facture->load(['client', 'marqueGarantie', 'ordreReparation.vehicule', 'vehicule', 'livraisonFlotte.import', 'livraisonFlotte.bonCommande.bonTransfert', 'lignes', 'avoir.factureRemplacement', 'avoirOrigine.facture']);
        return view('factures.show', compact('facture'));
    }

    /**
     * Génère la page d'impression de la facture (format A4, avec montant en lettres).
     * S'ouvre dans un nouvel onglet et lance l'impression automatiquement.
     */
    public function imprimer(Facture $facture)
    {
        $facture->load(['client', 'marqueGarantie', 'lignes', 'ordreReparation.vehicule', 'ordreReparation.devis.bonCommande', 'vehicule', 'livraisonFlotte.bonCommande.bonTransfert', 'avoir', 'avoirOrigine.facture']);
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
        if ($facture->statut === 'annulee') {
            return back()->with('error', "Facture {$facture->numero} annulée par avoir : aucun paiement ne peut plus y être enregistré.");
        }

        // Paiement en plusieurs fois : le montant saisi est le versement du jour,
        // il s'ajoute à ce qui a déjà été payé (il ne le remplace pas)
        $resteAvant = round($facture->getMontantRestant());
        if ($resteAvant <= 0) {
            return back()->with('error', "Facture {$facture->numero} déjà entièrement payée.");
        }

        $request->validate([
            'montant_paye'   => ['required', 'numeric', 'gt:0', 'max:' . $resteAvant],
            'date_paiement'  => ['required', 'date'],
            'mode_paiement'  => ['required', 'in:especes,cheque,waafi,cac,carte,virement,bon_commande'],
            'numero_bon_commande_client' => ['required_if:mode_paiement,bon_commande', 'nullable', 'string', 'max:100'],
            'bon_commande_scan'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ], [
            'montant_paye.required'  => 'Le montant payé est obligatoire.',
            'montant_paye.numeric'   => 'Le montant payé doit être un nombre.',
            'montant_paye.gt'         => 'Le montant reçu doit être supérieur à zéro.',
            'montant_paye.max'        => 'Le montant reçu dépasse le reste à payer (' . number_format($resteAvant, 0, ',', ' ') . ' FDJ).',
            'date_paiement.required'  => 'La date de paiement est obligatoire.',
            'date_paiement.date'      => 'La date de paiement n\'est pas valide.',
            'mode_paiement.required'  => 'Veuillez sélectionner le mode de paiement.',
            'mode_paiement.in'        => 'Mode de paiement invalide.',
            'numero_bon_commande_client.required_if' => 'Le numéro du bon de commande est obligatoire.',
            'bon_commande_scan.mimes' => 'Le scan doit être une image (JPG, PNG) ou un PDF.',
            'bon_commande_scan.max'   => 'Le scan ne doit pas dépasser 10 Mo.',
        ]);

        $versement  = (float) $request->montant_paye;
        $totalPaye  = (float) $facture->montant_paye + $versement;

        $data = [
            'montant_paye'  => $totalPaye,
            'date_paiement' => $request->date_paiement,
            'mode_paiement' => $request->mode_paiement ?? $facture->mode_paiement,
            // Facture payée seulement quand le total général (TTC + frais de timbre) est couvert
            'statut'        => $totalPaye >= $facture->totalGeneral() ? 'payee' : 'emise',
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

        $fmt = fn ($m) => number_format($m, 0, ',', ' ');
        Activite::journaliser('payer_facture', "Versement de {$fmt($versement)} FDJ ({$facture->getModePaiementLabel()}) sur facture {$facture->numero} — payé {$fmt($totalPaye)} / {$fmt($facture->totalGeneral())} FDJ", $facture);

        return back()->with('success', $facture->statut === 'payee'
            ? 'Facture entièrement payée. Le réceptionniste peut maintenant restituer le véhicule.'
            : "Versement de {$fmt($versement)} FDJ enregistré — reste à payer : {$fmt($facture->getMontantRestant())} FDJ.");
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

        if ($facture->statut === 'annulee') {
            return back()->with('error', "Facture {$facture->numero} annulée par avoir.");
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

    // ── Avoirs : annulation / correction d'une facture (administrateur) ──

    /**
     * Annule une facture par un avoir (administrateur uniquement).
     * La facture n'est jamais supprimée : un avoir du même montant est émis
     * automatiquement, la facture passe au statut "annulee" et l'OR revient
     * dans « À facturer ». Le montant déjà encaissé est indiqué sur l'avoir
     * (à rembourser au client ou à déduire de la nouvelle facture).
     */
    public function annuler(Request $request, Facture $facture)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }
        if ($facture->statut === 'annulee') {
            return back()->with('error', 'Cette facture est déjà annulée.');
        }

        $request->validate(
            ['motif' => ['required', 'string', 'max:1000']],
            ['motif.required' => "Indiquez le motif de l'annulation."]
        );

        $avoir = DB::transaction(function () use ($request, $facture) {
            $this->retirerDeLEncaissementNonPaye($facture);

            $avoir = Avoir::pourAnnuler($facture, 'annulation', $request->motif, (float) $facture->montant_paye);
            $this->marquerAnnulee($facture);

            // Le véhicule redevient « à facturer » (s'il a déjà été restitué, il reste
            // restitué mais réapparaît quand même dans « À facturer »). Facture flotte :
            // pas d'OR — la livraison redevient d'elle-même « À facturer ».
            $or = $facture->ordreReparation;
            if ($or && $or->statut === 'facture') {
                $or->update(['statut' => 'pret', 'date_sortie_reelle' => null]);
            }

            return $avoir;
        });

        Activite::journaliser('avoir_facture', "Facture {$facture->numero} annulée par l'avoir {$avoir->numero} — motif : {$request->motif}", $facture);

        $message = "Facture {$facture->numero} annulée — avoir {$avoir->numero} émis.";
        if ($avoir->montant_a_rembourser > 0) {
            $message .= ' Montant déjà encaissé : ' . number_format($avoir->montant_a_rembourser, 0, ',', ' ') . ' FDJ (à rembourser ou à déduire de la nouvelle facture).';
        }

        return redirect()->route('factures.show', $facture)->with('success', $message);
    }

    /**
     * Formulaire de correction d'une facture (administrateur uniquement) —
     * reprend les lignes de la facture, modifiables.
     */
    public function corriger(Facture $facture)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }
        if ($facture->statut === 'annulee') {
            return redirect()->route('factures.show', $facture)->with('error', 'Cette facture est déjà annulée.');
        }

        if (! $facture->ordreReparation) {
            return redirect()->route('factures.show', $facture)->with('error', "Facture flotte : annulez-la par avoir, puis refacturez la livraison depuis l'import flotte.");
        }

        $facture->load(['lignes', 'client', 'marqueGarantie']);
        $or = $facture->ordreReparation->load(['client', 'vehicule', 'allDevis.lignes']);

        return view('factures.create', ['or' => $or, 'factureACorriger' => $facture]);
    }

    /**
     * Corrige une facture (administrateur uniquement) : un avoir annule la
     * facture en totalité, puis une nouvelle facture est émise avec les lignes
     * corrigées. Le paiement déjà reçu et le crédit accordé sont reportés sur la
     * nouvelle facture ; un éventuel trop-perçu est indiqué sur l'avoir.
     */
    public function enregistrerCorrection(Request $request, Facture $facture)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }
        if ($facture->statut === 'annulee') {
            return redirect()->route('factures.show', $facture)->with('error', 'Cette facture est déjà annulée.');
        }
        if (! $facture->or_id) {
            return redirect()->route('factures.show', $facture)->with('error', "Facture flotte : annulez-la par avoir, puis refacturez la livraison depuis l'import flotte.");
        }

        $request->validate([
            'motif'                  => ['required', 'string', 'max:1000'],
            'frais_timbre'           => ['nullable', 'boolean'],
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
            'motif.required'                => 'Indiquez le motif de la correction.',
            'lignes.required'               => 'La facture doit contenir au moins une ligne.',
            'lignes.min'                    => 'La facture doit contenir au moins une ligne.',
            'lignes.*.designation.required' => 'La désignation est obligatoire pour chaque ligne.',
            'lignes.*.quantite.min'         => 'La quantité doit être supérieure à zéro.',
            'lignes.*.prix_unitaire.min'    => 'Le prix unitaire ne peut pas être négatif.',
            'lignes.*.remise.max'           => 'La remise ne peut pas dépasser 100%.',
        ]);

        [$avoir, $nouvelle] = DB::transaction(function () use ($request, $facture) {
            $this->retirerDeLEncaissementNonPaye($facture);

            $lignes = [];
            foreach ($request->lignes as $ligne) {
                $remise   = $ligne['remise'] ?? 0;
                $lignes[] = [
                    'type'          => $ligne['type'],
                    'designation'   => $ligne['designation'],
                    'reference'     => ($ligne['type'] === 'piece') ? ($ligne['reference'] ?? null) : null,
                    'unite'         => $ligne['unite'] ?? null,
                    'quantite'      => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'remise'        => $remise,
                    'total_ht'      => round($ligne['quantite'] * $ligne['prix_unitaire'] * (1 - $remise / 100), 2),
                ];
            }

            // Même taux et même arrondi FDJ que pour une facture normale
            $tauxTva = 10;
            [$montantHt, $tva, $ttc] = ArrondiFdjService::arrondir($lignes, $tauxTva);
            $timbre = $request->boolean('frais_timbre') ? 1000 : 0;

            // Paiement déjà reçu : reporté sur la nouvelle facture, dans la limite
            // de son montant — le surplus éventuel est à rembourser (noté sur l'avoir)
            $dejaPaye  = (float) $facture->montant_paye;
            $reporte   = min($dejaPaye, $ttc + $timbre);
            $tropPercu = round(max(0, $dejaPaye - ($ttc + $timbre)), 2);
            $estPayee  = $reporte > 0 && $reporte >= $ttc + $timbre;

            // Crédit accordé (compte client ou garantie constructeur) : reporté aussi
            $credit = [
                'credit_accorde'     => (bool) $facture->credit_accorde,
                'credit_accorde_at'  => $facture->credit_accorde_at,
                'credit_accorde_par' => $facture->credit_accorde_par,
            ];

            $avoir = Avoir::pourAnnuler($facture, 'correction', $request->motif, $tropPercu);
            $this->marquerAnnulee($facture);

            $nouvelle = Facture::create(array_merge([
                'numero'                           => Facture::genererNumero(),
                'or_id'                            => $facture->or_id,
                'devis_id'                         => $facture->devis_id,
                'client_id'                        => $facture->client_id,
                'marque_garantie_id'               => $facture->marque_garantie_id,
                'statut'                           => $estPayee ? 'payee' : 'emise',
                'mode_paiement'                    => $reporte > 0 ? $facture->mode_paiement : null,
                'numero_bon_commande_client'       => $facture->numero_bon_commande_client,
                'bon_commande_client_chemin'       => $facture->bon_commande_client_chemin,
                'bon_commande_client_nom_original' => $facture->bon_commande_client_nom_original,
                'date_emission'                    => now(),
                'date_echeance'                    => $facture->date_echeance,
                'date_paiement'                    => $reporte > 0 ? $facture->date_paiement : null,
                'notes'                            => $request->notes,
                'frais_timbre'                     => $timbre,
                'montant_ht'                       => $montantHt,
                'taux_tva'                         => $tauxTva,
                'montant_tva'                      => $tva,
                'montant_ttc'                      => $ttc,
                'montant_paye'                     => $reporte,
            ], $credit));

            foreach ($lignes as $l) {
                LigneFacture::create(array_merge($l, ['facture_id' => $nouvelle->id]));
            }

            $avoir->update(['facture_remplacement_id' => $nouvelle->id]);

            return [$avoir, $nouvelle];
        });

        Activite::journaliser('avoir_facture', "Facture {$facture->numero} corrigée : avoir {$avoir->numero} + nouvelle facture {$nouvelle->numero} (" . number_format($nouvelle->montant_ttc, 0, ',', ' ') . " FDJ TTC) — motif : {$request->motif}", $nouvelle);

        $message = "Facture {$facture->numero} annulée par l'avoir {$avoir->numero} et remplacée par la facture {$nouvelle->numero}.";
        if ($avoir->montant_a_rembourser > 0) {
            $message .= ' Trop-perçu à rembourser au client : ' . number_format($avoir->montant_a_rembourser, 0, ',', ' ') . ' FDJ.';
        }

        return redirect()->route('factures.show', $nouvelle)->with('success', $message);
    }

    /** Liste des avoirs émis */
    public function avoirs()
    {
        $avoirs = Avoir::with(['client', 'marqueGarantie', 'facture', 'factureRemplacement', 'ordreReparation', 'creePar'])
            ->latest('id')
            ->paginate(30);

        return view('factures.avoirs', compact('avoirs'));
    }

    /** Impression d'un avoir (format A4, même présentation que la facture) */
    public function imprimerAvoir(Avoir $avoir)
    {
        $avoir->load(['client', 'marqueGarantie', 'lignes', 'facture.vehicule', 'facture.livraisonFlotte.bonCommande', 'factureRemplacement', 'ordreReparation.vehicule']);
        return view('factures.avoir-print', compact('avoir'));
    }

    /**
     * Enregistre le remboursement au client du montant indiqué sur l'avoir
     * (ou sa déduction sur la nouvelle facture) — solde l'avoir.
     */
    public function rembourserAvoir(Request $request, Avoir $avoir)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('encaisser_factures')) {
            abort(403);
        }
        if (! $avoir->resteARembourser()) {
            return back()->with('error', "Rien à rembourser sur l'avoir {$avoir->numero}.");
        }

        $request->validate([
            'rembourse_le'       => ['required', 'date'],
            'mode_remboursement' => ['required', 'in:especes,cheque,waafi,virement,deduit'],
        ], [
            'rembourse_le.required'       => 'La date du remboursement est obligatoire.',
            'mode_remboursement.required' => 'Choisissez le mode de remboursement.',
        ]);

        $avoir->update($request->only('rembourse_le', 'mode_remboursement'));

        Activite::journaliser('avoir_rembourse', "Avoir {$avoir->numero} : " . number_format($avoir->montant_a_rembourser, 0, ',', ' ') . " FDJ remboursés ({$avoir->getModeRemboursementLabel()})", $avoir->facture);

        return back()->with('success', "Remboursement de l'avoir {$avoir->numero} enregistré.");
    }

    /** Passe la facture au statut "annulee" (le crédit accordé ne compte plus) */
    private function marquerAnnulee(Facture $facture): void
    {
        $facture->update([
            'statut'             => 'annulee',
            'credit_accorde'     => false,
            'credit_accorde_at'  => null,
            'credit_accorde_par' => null,
        ]);
    }

    /**
     * Retire la facture d'un encaissement groupé encore non payé (sinon le
     * paiement de l'encaissement la repasserait en "payée") et en déduit le
     * montant. Un encaissement déjà payé garde la facture dans son historique.
     */
    private function retirerDeLEncaissementNonPaye(Facture $facture): void
    {
        $eg = $facture->encaissementGlobal;
        if ($eg && $eg->statut !== 'paye') {
            $eg->update(['montant_total' => max(0, (float) $eg->montant_total - $facture->totalGeneral())]);
            $facture->update(['encaissement_global_id' => null]);
        }
    }
}
