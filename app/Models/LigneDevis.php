<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneDevis extends Model
{
    protected $table = 'lignes_devis';

    protected $fillable = [
        'devis_id', 'type', 'designation', 'reference', 'quantite', 'prix_unitaire', 'remise', 'total_ht',
        'disponible', 'note_fournisseur',
    ];

    protected $casts = [
        'quantite'      => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'remise'        => 'decimal:2',
        'total_ht'      => 'decimal:2',
        'disponible'    => 'boolean',
    ];

    // Prix et totaux toujours au franc (FDJ) — la quantité garde ses décimales
    public function setPrixUnitaireAttribute($valeur): void
    {
        $this->attributes['prix_unitaire'] = $valeur === null || $valeur === '' ? 0 : round((float) $valeur);
    }

    public function setTotalHtAttribute($valeur): void
    {
        $this->attributes['total_ht'] = round((float) $valeur);
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'main_oeuvre' => "Main d'œuvre",
            'piece'       => 'Pièce',
            'forfait'     => 'Forfait',
            'autre'       => 'Autre',
            default       => $this->type,
        };
    }
}
