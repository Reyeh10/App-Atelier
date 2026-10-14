<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneDevis extends Model
{
    /** Pièce en rupture au magasin : prix de vente connu, mais pas encore disponible (affiché dans le système seulement, jamais sur le devis imprimé) */
    public function estEnRupture(): bool
    {
        return $this->type === 'piece' && $this->disponible === false && (float) $this->prix_unitaire > 0;
    }

    protected $table = 'lignes_devis';

    protected $fillable = [
        'devis_id', 'type', 'designation', 'reference', 'quantite', 'prix_unitaire', 'prix_base', 'prix_manuel', 'remise', 'total_ht', 'groupe',
        'disponible', 'note_fournisseur',
    ];

    protected $casts = [
        'quantite'      => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'prix_base'     => 'decimal:2',
        'prix_manuel'   => 'boolean',
        'remise'        => 'decimal:2',
        'total_ht'      => 'decimal:2',
        'disponible'    => 'boolean',
    ];

    /**
     * Vrai quand Devis::recalculer() pose le prix final (majoration assurance +
     * arrondi FDJ) : ce prix-là ne remplace pas le prix de base.
     */
    public bool $prixFinal = false;

    protected static function booted(): void
    {
        // Tout prix écrit ailleurs que par le recalcul du devis (saisie, magasin,
        // barème, devis en avance...) est un prix de base, avant majoration
        static::saving(function (LigneDevis $ligne) {
            if (! $ligne->prixFinal && ! $ligne->isDirty('prix_base')
                && ($ligne->isDirty('prix_unitaire') || $ligne->prix_base === null)) {
                $ligne->prix_base = $ligne->prix_unitaire;
            }
            $ligne->prixFinal = false;
        });
    }

    /** Prix avant majoration assurance (prix du magasin ou saisi) */
    public function prixBase(): float
    {
        return (float) ($this->prix_base ?? $this->prix_unitaire);
    }

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
