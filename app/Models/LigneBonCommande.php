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

    /** Pièce en rupture : connue du magasin (prix de vente renvoyé) mais pas disponible */
    public function estEnRupture(): bool
    {
        return $this->disponible === false && (float) $this->prix_unitaire > 0;
    }

    /**
     * Prix unitaire affiché sur le BC et le BT : celui du devis (arrondi FDJ compris),
     * pour que le total des pièces soit le même partout ; sans devis (BC flotte),
     * le prix renvoyé par le magasin.
     */
    public function prixUnitaireAffiche(): ?float
    {
        if ($this->ligneDevis) {
            return (float) $this->ligneDevis->prix_unitaire;
        }
        return $this->prix_unitaire !== null ? (float) $this->prix_unitaire : null;
    }

    /** Montant de la pièce (quantité du BC, ou celle livrée par le BT) — même montant que la ligne du devis */
    public function montantAffiche(?float $quantite = null): ?float
    {
        $prix = $this->prixUnitaireAffiche();
        if ($prix === null) {
            return null;
        }
        $quantite ??= (float) $this->quantite;
        if ($this->ligneDevis && abs($quantite - (float) $this->ligneDevis->quantite) < 0.001) {
            return (float) $this->ligneDevis->total_ht;
        }
        return round($quantite * $prix * (1 - (float) ($this->ligneDevis?->remise ?? 0) / 100));
    }

    /**
     * Reporte la réponse du fournisseur (disponibilité, note, prix de vente)
     * sur la ligne de devis d'origine, puis recalcule les totaux du devis.
     * Le prix est repris dès que le magasin en renvoie un — y compris pour une
     * pièce EN RUPTURE (connue du magasin mais sans stock) : le devis montre le
     * vrai montant au client, les travaux attendent l'arrivée de la pièce (BT).
     * Pièce introuvable au magasin : pas de prix, on n'y touche pas.
     */
    public function propagerVersDevis(): void
    {
        $ligneDevis = $this->ligneDevis;
        if (! $ligneDevis) return;

        $data = [
            'disponible'       => $this->disponible,
            'note_fournisseur' => $this->note,
        ];

        if ($this->prix_unitaire !== null && (float) $this->prix_unitaire > 0 && ! $ligneDevis->prix_manuel) {
            $data['prix_unitaire'] = $this->prix_unitaire;
            $remise = (float) ($ligneDevis->remise ?? 0);
            $data['total_ht'] = round((float) $ligneDevis->quantite * (float) $this->prix_unitaire * (1 - $remise / 100), 2);
        }

        $ligneDevis->update($data);
        $ligneDevis->devis->recalculer();
    }
}
