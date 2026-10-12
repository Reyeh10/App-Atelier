<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne du récap leasing : un véhicule du parc (pièces de la période + forfait) */
class LigneRecapLeasing extends Model
{
    protected $table = 'lignes_recap_leasing';

    public $timestamps = false;

    protected $fillable = [
        'recap_leasing_id', 'client_id', 'vehicule_id', 'position', 'immatriculation', 'proprietaire',
        'montant_pieces', 'forfait', 'total', 'ors', 'bcs',
    ];

    protected $casts = [
        'montant_pieces' => 'decimal:2',
        'forfait'        => 'decimal:2',
        'total'          => 'decimal:2',
        'ors'            => 'array',
        'bcs'            => 'array',
    ];

    public function recap(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RecapLeasing::class, 'recap_leasing_id');
    }

    /** Propriétaire du véhicule (leasing tiers) */
    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
