<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une livraison flotte, telle que lue dans le fichier Excel. Une pièce
 * a sa ligne de BC (envoyée au magasin) ; la main-d'œuvre n'en a pas et va
 * directement sur la facture.
 */
class LigneLivraisonFlotte extends Model
{
    protected $table = 'lignes_livraison_flotte';

    protected $fillable = [
        'livraison_flotte_id', 'type', 'reference', 'designation', 'quantite', 'prix_unitaire', 'remise', 'ligne_bon_commande_id',
    ];

    protected $casts = [
        'quantite'      => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'remise'        => 'decimal:2',
    ];

    public function livraison(): BelongsTo
    {
        return $this->belongsTo(LivraisonFlotte::class, 'livraison_flotte_id');
    }

    public function ligneBonCommande(): BelongsTo
    {
        return $this->belongsTo(LigneBonCommande::class);
    }

    public function getTypeLabel(): string
    {
        return $this->type === 'main_oeuvre' ? "Main d'œuvre" : 'Pièce';
    }
}
