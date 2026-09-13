<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ExternalBonCommande;
use App\Models\ExternalBonCommandeLigne;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Vehicle;
use App\Services\AppAtelierApiService;
use Illuminate\Http\Request;

/**
 * Affiche les bons de commande reçus en temps réel depuis app-atelier
 * (le garage), avec la disponibilité déjà calculée à la réception.
 *
 * Certaines lignes arrivent sans référence pièce (le garage ne la connaît
 * pas toujours — main d'œuvre, peinture...) : le vendeur peut alors
 * retrouver lui-même la pièce correspondante (marque/modèle + désignation)
 * et indiquer manuellement si elle est disponible.
 */
class FournisseurCommandeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $dispo = $request->get('dispo', '');

        $commandes = ExternalBonCommande::withCount('lignes')
            ->with(['lignes' => fn ($q) => $q->orderBy('position')])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                      ->orWhere('client_nom', 'like', "%{$search}%")
                      ->orWhere('vehicule_marque', 'like', "%{$search}%")
                      ->orWhere('vehicule_modele', 'like', "%{$search}%")
                      ->orWhere('vehicule_immatriculation', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        if ($dispo !== '') {
            $commandes = $commandes->filter(function ($commande) use ($dispo) {
                return $this->categorieDisponibilite($commande) === $dispo;
            })->values();
        }

        return view('fournisseur-commandes.index', [
            'commandes' => $commandes,
            'search'    => $search,
            'dispo'     => $dispo,
        ]);
    }

    /**
     * Génère le prochain code client séquentiel au format ClXXX (ex: Cl003),
     * en suivant la même convention que la création manuelle de client.
     */
    private function prochainCodeClient(): string
    {
        $dernier = Customer::where('code', 'like', 'Cl%')
            ->get()
            ->map(fn (Customer $c) => (int) preg_replace('/\D/', '', $c->code))
            ->max();

        return 'Cl' . str_pad((string) (($dernier ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Catégorise la disponibilité globale d'un bon de commande, pour
     * l'affichage et le filtre : en_attente / tout / rien / partiel.
     */
    private function categorieDisponibilite(ExternalBonCommande $commande): string
    {
        $total = $commande->lignes->count();
        $repondues = $commande->lignes->whereNotNull('disponible')->count();
        $disponibles = $commande->lignes->where('disponible', true)->count();

        if ($total === 0 || $repondues === 0) return 'en_attente';
        if ($disponibles === $total) return 'tout';
        if ($disponibles === 0) return 'rien';
        return 'partiel';
    }

    public function show(ExternalBonCommande $fournisseurCommande)
    {
        if (! $fournisseurCommande->vu_at) {
            $fournisseurCommande->update(['vu_at' => now()]);
        }

        $fournisseurCommande->load(['lignes' => fn ($q) => $q->orderBy('position'), 'lignes.product']);

        $products = Product::with(['brand', 'model'])
            ->orderBy('designation')
            ->get();

        return view('fournisseur-commandes.show', [
            'commande' => $fournisseurCommande,
            'products' => $products,
        ]);
    }

    /**
     * Le vendeur associe une ligne sans référence à une pièce trouvée dans
     * le stock (ou la marque manuellement indisponible s'il n'a rien trouvé).
     */
    public function updateLigne(Request $request, ExternalBonCommande $fournisseurCommande, ExternalBonCommandeLigne $ligne)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'note'       => 'nullable|string|max:255',
        ], [
            'note.max' => 'La note ne doit pas dépasser 255 caractères.',
        ]);

        if (! empty($data['product_id'])) {
            $product = Product::findOrFail($data['product_id']);

            $ligne->update([
                'product_id'          => $product->id,
                'reference'           => $product->reference,
                'quantite_disponible' => $product->quantity,
                'disponible'          => $product->quantity >= $ligne->quantite_demandee,
                'prix_unitaire'       => $product->sale_price,
                'note'                => $data['note'] ?? null,
            ]);
        } else {
            // Le vendeur a cherché et n'a rien trouvé de correspondant.
            $ligne->update([
                'product_id'          => null,
                'quantite_disponible' => 0,
                'disponible'          => false,
                'prix_unitaire'       => null,
                'note'                => $data['note'] ?? null,
            ]);
        }

        app(AppAtelierApiService::class)->envoyerDisponibilite($ligne);

        return back()->with('success', 'Disponibilité mise à jour et transmise au garage.');
    }

    /**
     * Convertit le bon de commande en vente réelle, une fois toutes les
     * pièces disponibles : crée/retrouve le client et le véhicule, puis
     * délègue à SaleController::store() pour la facture, la déduction de
     * stock et les mouvements de stock (même logique qu'une vente normale).
     */
    public function creerVente(ExternalBonCommande $fournisseurCommande, SaleController $saleController)
    {
        $fournisseurCommande->load('lignes.product');

        if ($fournisseurCommande->vente_id) {
            return redirect()->route('sales.show', $fournisseurCommande->vente_id);
        }

        if (! $fournisseurCommande->toutesPiecesDisponibles()) {
            return back()->with('error', "Impossible : toutes les pièces ne sont pas encore identifiées et disponibles pour {$fournisseurCommande->numero}.");
        }

        $telephone = trim((string) $fournisseurCommande->client_telephone);

        $customer = $telephone !== ''
            ? Customer::firstOrCreate(
                ['phone' => $telephone],
                ['code' => $this->prochainCodeClient(), 'name' => $fournisseurCommande->client_nom ?: 'Client app-atelier']
            )
            : Customer::create(['code' => $this->prochainCodeClient(), 'name' => $fournisseurCommande->client_nom ?: 'Client app-atelier']);

        $plaque = trim((string) $fournisseurCommande->vehicule_immatriculation);

        $vehicle = $plaque !== ''
            ? Vehicle::firstOrNew(['plate_number' => $plaque])
            : new Vehicle();

        $vehicle->customer_id = $customer->id;
        $vehicle->brand = $vehicle->brand ?: $fournisseurCommande->vehicule_marque;
        $vehicle->model = $vehicle->model ?: $fournisseurCommande->vehicule_modele;
        if ($plaque === '') {
            $vehicle->plate_number = 'INCONNU-' . $fournisseurCommande->numero;
        }
        $vehicle->save();

        $items = $fournisseurCommande->lignes->map(fn (ExternalBonCommandeLigne $ligne) => [
            'product_id' => $ligne->product_id,
            'quantity'   => (float) $ligne->quantite_demandee,
        ])->values()->all();

        $saleRequest = Request::create('', 'POST', [
            'customer_id'  => $customer->id,
            'vehicle_id'   => $vehicle->id,
            'payment_type' => 'bon_commande',
            'items'        => $items,
        ]);
        $saleRequest->setUserResolver(fn () => auth()->user());
        $saleRequest->setLaravelSession(request()->session());

        $avantId = (int) Sale::max('id');

        $response = $saleController->store($saleRequest);

        $sale = Sale::where('id', '>', $avantId)
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->first();

        if (! $sale) {
            // La création a échoué (ex: stock insuffisant détecté à la dernière minute) :
            // on relaie le message d'erreur renvoyé par SaleController::store().
            return $response;
        }

        $fournisseurCommande->update(['vente_id' => $sale->id]);

        return redirect()->route('sales.show', $sale)
            ->with('success', "Vente créée à partir de {$fournisseurCommande->numero}.");
    }
}
