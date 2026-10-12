<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Récapitulatif leasing d'une période (31 jours au plus) : pour chaque véhicule
 * du parc, les pièces de ses OR entrés dans la période + le forfait mensuel.
 * Montants HT, sans TVA. Ce n'est pas une facture (pas de suivi de paiement) :
 * il est enregistré pour être retrouvé, imprimé ou exporté.
 */
class RecapLeasing extends Model
{
    protected $table = 'recaps_leasing';

    protected $fillable = [
        'type', 'date_debut', 'date_fin', 'forfait', 'nb_vehicules',
        'total_pieces', 'total_forfaits', 'total', 'genere_par',
    ];

    protected $casts = [
        'date_debut'     => 'date',
        'date_fin'       => 'date',
        'total_pieces'   => 'decimal:2',
        'total_forfaits' => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneRecapLeasing::class)->orderBy('position');
    }

    public function generePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    /** Récap « Leasing tiers » (véhicules de particuliers loués à STCD), sinon parc STCD */
    public function estTiers(): bool
    {
        return $this->type === 'tiers';
    }

    /** Récap de bus (leasing ou transport public) : forfait par bus + pièces de ses BC directs (pas d'OR) */
    public function estBus(): bool
    {
        return isset(VehiculeLeasing::GROUPES_BUS[$this->type]);
    }

    /** Récap « Véhicules de service » : regroupé par affectation (service, directeur…) */
    public function estService(): bool
    {
        return $this->type === 'service';
    }

    /** Récap avec une section + sous-total par groupe (propriétaire ou affectation) */
    public function estGroupe(): bool
    {
        return $this->estTiers() || $this->estService();
    }

    /** Lignes par section : propriétaire (leasing tiers), affectation (véhicules de service), sinon une seule */
    public function lignesGroupees(): \Illuminate\Support\Collection
    {
        return match (true) {
            $this->estTiers()   => $this->lignes->groupBy('client_id'),
            $this->estService() => $this->lignes->groupBy('proprietaire'),
            default             => collect([$this->lignes]),
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match (true) {
            $this->type === 'tiers' => 'Leasing tiers',
            $this->estService()     => 'Véhicules de service',
            $this->estBus()         => VehiculeLeasing::GROUPES_BUS[$this->type]['libelle'],
            default                 => 'Parc leasing STCD',
        };
    }

    /** Titre imprimé du récap */
    public function getTitreAttribute(): string
    {
        return match (true) {
            $this->type === 'tiers' => 'Leasing tiers',
            $this->estService()     => 'Véhicules de service',
            $this->estBus()         => VehiculeLeasing::GROUPES_BUS[$this->type]['titre'],
            default                 => 'Leasing véhicules légers',
        };
    }

    /** « du 01/06/2026 au 30/06/2026 » */
    public function getPeriodeLabelAttribute(): string
    {
        return 'du ' . $this->date_debut->format('d/m/Y') . ' au ' . $this->date_fin->format('d/m/Y');
    }
}
