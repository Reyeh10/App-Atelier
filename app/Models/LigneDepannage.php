<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne de main-d'œuvre d'un dépannage (les pièces sont les lignes du BC) */
class LigneDepannage extends Model
{
    protected $table = 'lignes_depannage';

    protected $fillable = ['depannage_id', 'designation', 'quantite', 'prix_unitaire'];

    protected $casts = [
        'quantite'      => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
    ];

    // Prix au franc (FDJ) — la quantité (heures) garde ses décimales
    public function setPrixUnitaireAttribute($valeur): void
    {
        $this->attributes['prix_unitaire'] = round((float) $valeur);
    }

    public function depannage(): BelongsTo
    {
        return $this->belongsTo(Depannage::class);
    }

    public function total(): float
    {
        return round((float) $this->quantite * (float) $this->prix_unitaire);
    }
}
