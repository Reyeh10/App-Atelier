<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bon de commande reçu depuis un système externe
 * comme app-atelier.
 */
class ExternalBonCommande extends Model
{
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */
    protected $table = 'external_bons_commande';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'numero',
        'source_system',

        'vehicule_marque',
        'vehicule_modele',
        'vehicule_immatriculation',
        'vehicule_vin',

        'client_nom',
        'client_telephone',

        'statut',

        'vente_id',

        'vu_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'vu_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION : LIGNES
    |--------------------------------------------------------------------------
    */
    public function lignes(): HasMany
    {
        return $this->hasMany(
            ExternalBonCommandeLigne::class,
            'external_bon_commande_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RELATION : VENTE
    |--------------------------------------------------------------------------
    |
    | Vente générée depuis le bon de commande lorsque toutes les pièces
    | sont disponibles.
    |
    */
    public function vente(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class,
            'vente_id'
        );m
    }

    /*
    |--------------------------------------------------------------------------
    | TOUTES LES PIÈCES DISPONIBLES
    |--------------------------------------------------------------------------
    |
    | Retourne true uniquement si :
    |
    | - le bon possède au moins une ligne ;
    | - chaque ligne possède un product_id ;
    | - chaque ligne est marquée disponible ;
    | - chaque ligne possède un depot_id.
    |
    */
    public function toutesPiecesDisponibles(): bool
    {
        /*
        |--------------------------------------------------------------------------
        | CHARGER LES LIGNES SI NÉCESSAIRE
        |--------------------------------------------------------------------------
        */
        if (!$this->relationLoaded('lignes')) {
            $this->load('lignes');
        }

        /*
        |--------------------------------------------------------------------------
        | AU MOINS UNE LIGNE
        |--------------------------------------------------------------------------
        */
        if ($this->lignes->isEmpty()) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | TOUTES LES LIGNES VALIDES
        |--------------------------------------------------------------------------
        */
        return $this->lignes->every(
            function (
                ExternalBonCommandeLigne $ligne
            ): bool {
                return
                    $ligne->product_id !== null
                    &&
                    $ligne->depot_id !== null
                    &&
                    $ligne->disponible === true;
            }
        );
    }
}
