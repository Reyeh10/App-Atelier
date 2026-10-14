<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\BonCommande;
use App\Models\Client;
use App\Models\Depannage;
use App\Models\Facture;
use App\Models\LigneBonCommande;
use App\Models\LigneDepannage;
use App\Models\LigneFacture;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\ArrondiFdjService;
use App\Services\FournisseurApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dépannage : pièces (+ main-d'œuvre) prises sans réception ni devis.
 * Les pièces partent au magasin par un BC ; après réception du BT, un véhicule
 * leasing passe au récap de sa liste (pièces seulement), un client normal est
 * facturé depuis la fiche du dépannage. Cf. App\Models\Depannage.
 */
class DepannageController extends Controller
{
    private function autoriser(string $permission): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403);
        }

        return $user;
    }

    public function index(Request $request)
    {
        $this->autoriser('voir_depannages');

        $recherche = trim((string) $request->get('recherche', ''));
        $depannages = Depannage::with(['client', 'vehicule', 'bonCommande.lignes', 'lignes', 'facture'])
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    $q->where('numero', 'like', "%{$recherche}%")
                        ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%")
                            ->orWhere('raison_sociale', 'like', "%{$recherche}%")
                            ->orWhere('telephone', 'like', "%{$recherche}%"))
                        ->orWhereHas('vehicule', fn ($v) => $v->where('immatriculation', 'like', "%{$recherche}%"));
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('depannages.index', compact('depannages', 'recherche'));
    }

    public function create()
    {
        $this->autoriser('creer_depannages');

        $clients = Client::orderBy('nom')->get(['id', 'type', 'nom', 'prenom', 'raison_sociale']);

        return view('depannages.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $this->autoriser('creer_depannages');

        $data = $request->validate([
            'client_id'                => ['required', 'exists:clients,id'],
            'vehicule_id'              => ['required', 'exists:vehicules,id'],
            'date_depannage'           => ['required', 'date'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'pieces'                   => ['nullable', 'array'],
            'pieces.*.designation'     => ['required', 'string', 'max:255'],
            'pieces.*.reference'       => ['nullable', 'string', 'max:100'],
            'pieces.*.quantite'        => ['required', 'numeric', 'gt:0'],
            'main_oeuvre'              => ['nullable', 'array'],
            'main_oeuvre.*.designation'=> ['required', 'string', 'max:255'],
            'main_oeuvre.*.quantite'   => ['required', 'numeric', 'gt:0'],
            'main_oeuvre.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ], [
            'client_id.required'                 => 'Choisissez le client.',
            'vehicule_id.required'               => 'Choisissez le véhicule.',
            'pieces.*.designation.required'      => 'La désignation de chaque pièce est obligatoire.',
            'pieces.*.quantite.gt'               => 'La quantité de chaque pièce doit être supérieure à zéro.',
            'main_oeuvre.*.designation.required' => 'La désignation de chaque main-d\'œuvre est obligatoire.',
            'main_oeuvre.*.quantite.gt'          => 'Le nombre d\'heures doit être supérieur à zéro.',
            'main_oeuvre.*.prix_unitaire.required' => 'Le prix de chaque main-d\'œuvre est obligatoire.',
        ]);

        $vehicule = Vehicule::findOrFail($data['vehicule_id']);
        if ((int) $vehicule->client_id !== (int) $data['client_id']) {
            return back()->withInput()->with('error', "Le véhicule {$vehicule->immatriculation} n'appartient pas à ce client.");
        }

        $leasing = Depannage::vehiculeEnLeasing($vehicule);
        $pieces  = array_values($data['pieces'] ?? []);
        // Leasing : la main-d'œuvre est couverte par le forfait mensuel, jamais comptée
        $mainOeuvre = $leasing ? [] : array_values($data['main_oeuvre'] ?? []);

        if (! $pieces && ! $mainOeuvre) {
            return back()->withInput()->with('error', $leasing
                ? 'Ajoutez au moins une pièce (véhicule en leasing : la main-d\'œuvre est couverte par le forfait).'
                : 'Ajoutez au moins une pièce ou une main-d\'œuvre.');
        }

        $depannage = DB::transaction(function () use ($data, $vehicule, $leasing, $pieces, $mainOeuvre) {
            $depannage = Depannage::create([
                'numero'         => Depannage::genererNumero(),
                'client_id'      => $vehicule->client_id,
                'vehicule_id'    => $vehicule->id,
                'cree_par_id'    => Auth::id(),
                'date_depannage' => $data['date_depannage'],
                'description'    => $data['description'] ?? null,
                'leasing'        => $leasing,
            ]);

            foreach ($mainOeuvre as $ligne) {
                LigneDepannage::create([
                    'depannage_id'  => $depannage->id,
                    'designation'   => $ligne['designation'],
                    'quantite'      => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                ]);
            }

            if ($pieces) {
                $bc = BonCommande::create([
                    'numero'       => BonCommande::genererNumero(),
                    'client_id'    => $vehicule->client_id,
                    'vehicule_id'  => $vehicule->id,
                    'depannage_id' => $depannage->id,
                    'statut'       => 'en_attente',
                    'notes'        => "Dépannage {$depannage->numero} — {$vehicule->immatriculation}",
                ]);
                foreach ($pieces as $ligne) {
                    LigneBonCommande::create([
                        'bon_commande_id' => $bc->id,
                        'designation'     => $ligne['designation'],
                        'reference'       => ($ligne['reference'] ?? null) ?: null,
                        'quantite'        => $ligne['quantite'],
                    ]);
                }
            }

            return $depannage;
        });

        if ($depannage->bonCommande) {
            app(FournisseurApiService::class)->envoyerBonCommande($depannage->bonCommande);
        }

        Activite::journaliser('creer_depannage', "Dépannage {$depannage->numero} — {$depannage->client->nom_complet} / {$vehicule->immatriculation}"
            . ($depannage->bonCommande ? " — BC {$depannage->bonCommande->numero}" : ''), $depannage);

        return redirect()->route('depannages.show', $depannage)->with('success', "Dépannage {$depannage->numero} créé"
            . ($depannage->bonCommande ? " — bon de commande {$depannage->bonCommande->numero} envoyé au magasin." : '.'));
    }

    public function show(Depannage $depannage)
    {
        $this->autoriser('voir_depannages');

        $depannage->load(['client', 'vehicule', 'creePar', 'lignes', 'facture', 'bonCommande.lignes', 'bonCommande.bonTransfert']);

        return view('depannages.show', compact('depannage'));
    }

    /**
     * Facture d'un dépannage (client normal, pièces reçues) : pièces reprises du
     * BC / BT (quantités livrées, mêmes montants) + main-d'œuvre, TVA 10 %, sans
     * arrondi — comme la facture d'un OR reprend ses devis.
     */
    public function facturer(Depannage $depannage)
    {
        $this->autoriser('creer_factures');

        $depannage->load(['client', 'vehicule', 'lignes', 'bonCommande.lignes']);
        if ($raison = $depannage->raisonNonFacturable()) {
            return back()->with('error', $raison);
        }

        $client = $depannage->client;
        if ($client->compte_actif && $client->plafond_compte && ! $client->peutFacturerSurCompte()) {
            $plafond = number_format($client->plafond_compte, 0, ',', ' ');
            $solde   = number_format($client->solde_compte, 0, ',', ' ');
            return back()->with('error', "Impossible de créer la facture : le plafond de compte crédit de {$client->nom_complet} est atteint ({$solde} FDJ utilisés sur {$plafond} FDJ autorisés).");
        }

        $facture = DB::transaction(function () use ($depannage) {
            // Pièces : reprises du BT (quantités vraiment livrées), à défaut du BC — mêmes
            // prix et mêmes montants que le BC et le BT, sans aucun arrondi
            $lignesBc = $depannage->bonCommande?->lignes->values() ?? collect();
            $livrees  = $depannage->bonCommande?->bonTransfert
                ? collect($depannage->bonCommande->bonTransfert->lignes)->map(fn ($l, $i) => [
                    'bc'       => $lignesBc->get($l['index'] ?? $i),
                    'quantite' => isset($l['quantite']) ? (float) $l['quantite'] : null,
                ])->filter(fn ($l) => $l['bc'])
                : $lignesBc->map(fn ($l) => ['bc' => $l, 'quantite' => null]);

            $lignes = [];
            foreach ($livrees as $livree) {
                $l        = $livree['bc'];
                $quantite = $livree['quantite'] ?? (float) $l->quantite;
                $lignes[] = [
                    'type'          => 'piece',
                    'designation'   => $l->designation,
                    'reference'     => $l->reference,
                    'unite'         => 'PCS',
                    'quantite'      => $quantite,
                    'prix_unitaire' => (float) $l->prixUnitaireAffiche(),
                    'remise'        => 0,
                    'total_ht'      => (float) $l->montantAffiche($quantite),
                ];
            }
            foreach ($depannage->lignes as $l) {
                $lignes[] = [
                    'type'          => 'main_oeuvre',
                    'designation'   => $l->designation,
                    'reference'     => null,
                    'unite'         => 'H',
                    'quantite'      => (float) $l->quantite,
                    'prix_unitaire' => (float) $l->prix_unitaire,
                    'remise'        => 0,
                    'total_ht'      => $l->total(),
                ];
            }

            $tauxTva = 10;
            // Pas d'arrondi sur la facture : elle reprend le BC / BT et la main-d'œuvre tels quels
            [$montantHt, $tva, $ttc] = ArrondiFdjService::totauxSansAjustement($lignes, $tauxTva);

            $facture = Facture::create([
                'numero'        => Facture::genererNumero(),
                'or_id'         => null,
                'client_id'     => $depannage->client_id,
                'vehicule_id'   => $depannage->vehicule_id,
                'depannage_id'  => $depannage->id,
                'statut'        => 'emise',
                'date_emission' => now(),
                'notes'         => "Dépannage {$depannage->numero}",
                'frais_timbre'  => 0,
                'montant_ht'    => $montantHt,
                'taux_tva'      => $tauxTva,
                'montant_tva'   => $tva,
                'montant_ttc'   => $ttc,
                'montant_paye'  => 0,
            ]);
            foreach ($lignes as $l) {
                LigneFacture::create(array_merge($l, ['facture_id' => $facture->id]));
            }
            $depannage->update(['facture_id' => $facture->id]);

            return $facture;
        });

        Activite::journaliser('creer_facture', "Création facture {$facture->numero} — dépannage {$depannage->numero} — {$facture->payeur_nom} — "
            . number_format($facture->montant_ttc, 0, ',', ' ') . ' FDJ TTC', $facture);

        return redirect()->route('factures.show', $facture)->with('success', "Facture {$facture->numero} créée pour le dépannage {$depannage->numero}.");
    }
}
