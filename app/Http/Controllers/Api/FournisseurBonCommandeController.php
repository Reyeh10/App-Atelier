<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalBonCommande;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FournisseurBonCommandeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    |
    | Reçoit un bon de commande depuis app-atelier.
    |
    | Le même numéro peut être renvoyé plusieurs fois :
    | - si la quantité change ;
    | - si une pièce est ajoutée ;
    | - si une pièce est retirée ;
    | - si le vendeur a déjà identifié manuellement une pièce.
    |
    | Une ligne déjà liée à un product_id n'est jamais réinitialisée.
    |
    */
    public function store(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */
        $data = $request->validate(
            [
                'numero' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'vehicule.marque' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'vehicule.modele' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'vehicule.immatriculation' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'vehicule.vin' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'client.nom' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'client.telephone' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'pieces' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'pieces.*.reference' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'pieces.*.designation' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'pieces.*.quantite' => [
                    'required',
                    'numeric',
                    'min:0.01',
                ],
            ],
            [
                'numero.required' =>
                    'Le numéro du bon de commande est obligatoire.',

                'pieces.required' =>
                    'Le bon de commande doit contenir au moins une pièce.',

                'pieces.array' =>
                    'Le format des pièces est invalide.',

                'pieces.min' =>
                    'Le bon de commande doit contenir au moins une pièce.',

                'pieces.*.quantite.required' =>
                    'La quantité de chaque pièce est obligatoire.',

                'pieces.*.quantite.numeric' =>
                    'La quantité d\'une pièce doit être numérique.',

                'pieces.*.quantite.min' =>
                    'La quantité d\'une pièce doit être supérieure à zéro.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | TRAITEMENT TRANSACTIONNEL
        |--------------------------------------------------------------------------
        */
        $bc = DB::transaction(
            function () use ($data) {

                /*
                |--------------------------------------------------------------------------
                | BON DE COMMANDE
                |--------------------------------------------------------------------------
                */
                $bc = ExternalBonCommande::updateOrCreate(
                    [
                        'numero' =>
                            trim(
                                (string) $data['numero']
                            ),
                    ],
                    [
                        'source_system' =>
                            'app-atelier',

                        'vehicule_marque' =>
                            $data['vehicule']['marque']
                            ?? null,

                        'vehicule_modele' =>
                            $data['vehicule']['modele']
                            ?? null,

                        'vehicule_immatriculation' =>
                            $data['vehicule']['immatriculation']
                            ?? null,

                        'vehicule_vin' =>
                            $data['vehicule']['vin']
                            ?? null,

                        'client_nom' =>
                            $data['client']['nom']
                            ?? null,

                        'client_telephone' =>
                            $data['client']['telephone']
                            ?? null,

                        'statut' =>
                            'recu',
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | LIGNES EXISTANTES
                |--------------------------------------------------------------------------
                */
                $bc->load([
                    'lignes.product',
                ]);

                $lignesParPosition =
                    $bc->lignes
                        ->keyBy('position');

                $positionsGardees = [];

                /*
                |--------------------------------------------------------------------------
                | PARCOURIR LES PIÈCES
                |--------------------------------------------------------------------------
                */
                foreach (
                    array_values($data['pieces'])
                    as $position => $piece
                ) {
                    $reference =
                        trim(
                            (string) (
                                $piece['reference']
                                ?? ''
                            )
                        );

                    $reference =
                        $reference !== ''
                            ? $reference
                            : null;

                    $designation =
                        isset(
                            $piece['designation']
                        )
                            ? trim(
                                (string) $piece['designation']
                            )
                            : null;

                    $quantiteDemandee =
                        round(
                            (float) $piece['quantite'],
                            2
                        );

                    $existante =
                        $lignesParPosition
                            ->get($position);

                    /*
                    |--------------------------------------------------------------------------
                    | LIGNE DÉJÀ IDENTIFIÉE
                    |--------------------------------------------------------------------------
                    |
                    | Si product_id existe :
                    | - on conserve le produit choisi ;
                    | - on conserve aussi depot_id ;
                    | - on recalcule seulement la disponibilité.
                    |
                    */
                    if (
                        $existante
                        && $existante->product_id
                    ) {
                        $product =
                            $existante->product;

                        /*
                        |--------------------------------------------------------------------------
                        | PRODUIT SUPPRIMÉ / INEXISTANT
                        |--------------------------------------------------------------------------
                        */
                        if (!$product) {
                            $existante->update([
                                'designation' =>
                                    $designation
                                    ?: $existante->designation,

                                'quantite_demandee' =>
                                    $quantiteDemandee,

                                'quantite_disponible' =>
                                    0,

                                'disponible' =>
                                    false,
                            ]);

                            $positionsGardees[] =
                                $position;

                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | STOCK DISPONIBLE
                        |--------------------------------------------------------------------------
                        |
                        | Ici on conserve la logique basée sur products.quantity.
                        |
                        | Si vous utilisez maintenant les stocks par dépôt,
                        | ce calcul pourra ensuite être remplacé par
                        | product_depot_stocks selon depot_id.
                        |
                        */
                        $quantiteDisponible =
                            round(
                                (float) (
                                    $product->quantity
                                    ?? 0
                                ),
                                2
                            );

                        $existante->update([
                            'designation' =>
                                $designation
                                ?: $existante->designation,

                            'quantite_demandee' =>
                                $quantiteDemandee,

                            'quantite_disponible' =>
                                $quantiteDisponible,

                            'disponible' =>
                                $quantiteDisponible
                                >=
                                $quantiteDemandee,

                            'prix_unitaire' =>
                                $product->sale_price,
                        ]);

                        $positionsGardees[] =
                            $position;

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | AUCUNE RÉFÉRENCE
                    |--------------------------------------------------------------------------
                    |
                    | Exemple :
                    | - main d'œuvre ;
                    | - peinture ;
                    | - prestation ;
                    | - pièce inconnue du garage.
                    |
                    */
                    if ($reference === null) {
                        $bc->lignes()
                            ->updateOrCreate(
                                [
                                    'position' =>
                                        $position,
                                ],
                                [
                                    'product_id' =>
                                        null,

                                    'depot_id' =>
                                        null,

                                    'reference' =>
                                        null,

                                    'designation' =>
                                        $designation,

                                    'quantite_demandee' =>
                                        $quantiteDemandee,

                                    'quantite_disponible' =>
                                        null,

                                    'disponible' =>
                                        null,

                                    'prix_unitaire' =>
                                        null,
                                ]
                            );

                        $positionsGardees[] =
                            $position;

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | RECHERCHE PRODUIT PAR RÉFÉRENCE
                    |--------------------------------------------------------------------------
                    */
                    $product = Product::query()
                        ->where(
                            'reference',
                            $reference
                        )
                        ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | PRODUIT INTROUVABLE
                    |--------------------------------------------------------------------------
                    */
                    if (!$product) {
                        $bc->lignes()
                            ->updateOrCreate(
                                [
                                    'position' =>
                                        $position,
                                ],
                                [
                                    'product_id' =>
                                        null,

                                    'depot_id' =>
                                        null,

                                    'reference' =>
                                        $reference,

                                    'designation' =>
                                        $designation,

                                    'quantite_demandee' =>
                                        $quantiteDemandee,

                                    'quantite_disponible' =>
                                        0,

                                    'disponible' =>
                                        false,

                                    'prix_unitaire' =>
                                        null,
                                ]
                            );

                        $positionsGardees[] =
                            $position;

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | PRODUIT TROUVÉ
                    |--------------------------------------------------------------------------
                    */
                    $quantiteDisponible =
                        round(
                            (float) (
                                $product->quantity
                                ?? 0
                            ),
                            2
                        );

                    $bc->lignes()
                        ->updateOrCreate(
                            [
                                'position' =>
                                    $position,
                            ],
                            [
                                'product_id' =>
                                    $product->id,

                                /*
                                |--------------------------------------------------------------------------
                                | DÉPÔT
                                |--------------------------------------------------------------------------
                                |
                                | Pas de dépôt choisi automatiquement ici.
                                |
                                | Le vendeur pourra choisir le dépôt ensuite.
                                |
                                */
                                'depot_id' =>
                                    null,

                                'reference' =>
                                    $reference,

                                'designation' =>
                                    $designation
                                    ?: $product->designation,

                                'quantite_demandee' =>
                                    $quantiteDemandee,

                                'quantite_disponible' =>
                                    $quantiteDisponible,

                                'disponible' =>
                                    $quantiteDisponible
                                    >=
                                    $quantiteDemandee,

                                'prix_unitaire' =>
                                    $product->sale_price,
                            ]
                        );

                    $positionsGardees[] =
                        $position;
                }

                /*
                |--------------------------------------------------------------------------
                | SUPPRIMER LES LIGNES RETIRÉES
                |--------------------------------------------------------------------------
                */
                if (
                    count($positionsGardees) > 0
                ) {
                    $bc->lignes()
                        ->whereNotIn(
                            'position',
                            $positionsGardees
                        )
                        ->delete();
                } else {
                    /*
                    |--------------------------------------------------------------------------
                    | SÉCURITÉ
                    |--------------------------------------------------------------------------
                    |
                    | Normalement impossible puisque pieces min:1,
                    | mais on protège quand même le traitement.
                    |
                    */
                    $bc->lignes()
                        ->delete();
                }

                return $bc;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | RECHARGER LES LIGNES
        |--------------------------------------------------------------------------
        */
        $bc->load([
            'lignes.product',
        ]);

        /*
        |--------------------------------------------------------------------------
        | RÉPONSE JSON
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' =>
                true,

            'numero' =>
                $bc->numero,

            'statut' =>
                $bc->statut,

            'pieces' =>
                $bc->lignes
                    ->sortBy('position')
                    ->values()
                    ->map(
                        function ($ligne) {
                            return [
                                'index' =>
                                    (int) $ligne->position,

                                'reference' =>
                                    $ligne->reference,

                                'designation' =>
                                    $ligne->designation,

                                'product_id' =>
                                    $ligne->product_id,

                                'depot_id' =>
                                    $ligne->depot_id,

                                'disponible' =>
                                    $ligne->disponible,

                                'quantite_demandee' =>
                                    $ligne->quantite_demandee
                                    !== null
                                        ? (float) $ligne
                                            ->quantite_demandee
                                        : null,

                                'quantite_disponible' =>
                                    $ligne->quantite_disponible
                                    !== null
                                        ? (float) $ligne
                                            ->quantite_disponible
                                        : null,

                                'prix_unitaire' =>
                                    $ligne->prix_unitaire
                                    !== null
                                        ? (float) $ligne
                                            ->prix_unitaire
                                        : null,

                                'note' =>
                                    $ligne->note,
                            ];
                        }
                    ),
        ]);
    }
}
