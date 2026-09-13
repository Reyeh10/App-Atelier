<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalBonCommandeLigne extends Model
{
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    |
    | Laravel utiliserait normalement :
    | external_bon_commande_lignes
    |
    | On l'indique explicitement pour éviter toute ambiguïté.
    |
    */
    protected $table = 'external_bon_commande_lignes';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'external_bon_commande_id',

        'product_id',
        'depot_id',

        'position',

        'reference',
        'designation',

        'quantite_demandee',
        'quantite_disponible',
        'disponible',

        'prix_unitaire',
        'note',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'position' => 'integer',

        'quantite_demandee' => 'decimal:2',

        'quantite_disponible' => 'decimal:2',

        'disponible' => 'boolean',

        'prix_unitaire' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | BON DE COMMANDE
    |--------------------------------------------------------------------------
    |
    | Chaque ligne appartient à un bon de commande externe.
    |
    */
    public function externalBonCommande(): BelongsTo
    {
        return $this->belongsTo(
            ExternalBonCommande::class,
            'external_bon_commande_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUIT
    |--------------------------------------------------------------------------
    |
    | Le produit peut être null lorsqu'une ligne provenant d'app-atelier
    | n'a pas encore été associée à une pièce du magasin.
    |
    */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DÉPÔT
    |--------------------------------------------------------------------------
    |
    | Le dépôt représente l'endroit depuis lequel la pièce sera prélevée.
    |
    | Une même pièce pouvant être disponible dans plusieurs dépôts,
    | cette relation est indispensable lors de la conversion du bon
    | de commande en vente.
    |
    */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(
            Depot::class,
            'depot_id'
        );
    }
}
