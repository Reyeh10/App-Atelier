<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBonCommande extends Model
{
    protected $table = 'lignes_bon_commande';

    protected $fillable = [
        'bon_commande_id', 'ligne_devis_id', 'designation', 'reference', 'quantite', 'fournisseur', 'recu',
        'disponible', 'quantite_disponible', 'prix_unitaire', 'note',
    ];

    protected $casts = [
        'quantite'            => 'decimal:2',
        'recu'                => 'boolean',
        'disponible'          => 'boolean',
        'quantite_disponible' => 'decimal:2',
        'prix_unitaire'       => 'decimal:2',
    ];

    // Prix fournisseur toujours au franc (FDJ)
    public function setPrixUnitaireAttribute($valeur): void
    {
        $this->attributes['prix_unitaire'] = $valeur === null || $valeur === '' ? null : round((float) $valeur);
    }


    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    /** Ligne de devis d'origine — permet de reporter la réponse fournisseur sur le devis */
    public function ligneDevis(): BelongsTo
    {
        return $this->belongsTo(LigneDevis::class);
    }

    /**
     * Reporte la réponse du fournisseur (disponibilité, note, prix de vente)
     * sur la ligne de devis d'origine, puis recalcule les totaux du devis.
     * Le prix n'est mis à jour que si la pièce est disponible et qu'un prix
     * a été renvoyé — on ne touche jamais au prix d'une pièce indisponible.
     */
    public function propagerVersDevis(): void
    {
        $ligneDevis = $this->ligneDevis;
        if (! $ligneDevis) return;

        $data = [
            'disponible'       => $this->disponible,
            'note_fournisseur' => $this->note,
        ];

        if ($this->disponible && $this->prix_unitaire !== null) {
            $data['prix_unitaire'] = $this->prix_unitaire;
            $remise = (float) ($ligneDevis->remise ?? 0);
            $data['total_ht'] = round((float) $ligneDevis->quantite * (float) $this->prix_unitaire * (1 - $remise / 100), 2);
        }

        $ligneDevis->update($data);
        $ligneDevis->devis->recalculer();
    }
}
