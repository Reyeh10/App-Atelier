<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneFacture extends Model
{
    protected $table = 'lignes_facture';

    protected $fillable = [
        'facture_id', 'type', 'designation', 'reference', 'unite', 'quantite', 'prix_unitaire', 'remise', 'total_ht',
    ];

    protected $casts = [
        'quantite'      => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'remise'        => 'decimal:2',
        'total_ht'      => 'decimal:2',
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

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    /**
     * Lignes facturées (factures annulées par avoir exclues) d'un véhicule et/ou
     * d'un client, filtrées par date de facture et par type — fiche véhicule et
     * fiche client, section « Pièces et main-d'œuvre facturées ».
     *
     * @param  array{date_debut:?string, date_fin:?string, type:string}  $filtres
     */
    public static function historique(?int $vehiculeId, ?int $clientId, array $filtres): \Illuminate\Support\Collection
    {
        return self::query()
            ->select('lignes_facture.*')
            ->join('factures', 'factures.id', '=', 'lignes_facture.facture_id')
            // Facture d'OR (véhicule de l'OR) ou facture flotte sans OR (véhicule sur la facture)
            ->leftJoin('ordres_reparations', 'ordres_reparations.id', '=', 'factures.or_id')
            ->where('factures.statut', '!=', 'annulee')
            ->when($vehiculeId, fn ($q, $v) => $q->whereRaw('COALESCE(factures.vehicule_id, ordres_reparations.vehicule_id) = ?', [$v]))
            ->when($clientId, fn ($q, $c) => $q->where('factures.client_id', $c))
            ->when($filtres['date_debut'] ?? null, fn ($q, $d) => $q->whereDate('factures.date_emission', '>=', $d))
            ->when($filtres['date_fin'] ?? null, fn ($q, $d) => $q->whereDate('factures.date_emission', '<=', $d))
            ->when($filtres['type'] ?? null, fn ($q, $t) => $q->where('lignes_facture.type', $t))
            ->with(['facture.ordreReparation.vehicule', 'facture.vehicule'])
            ->orderByDesc('factures.date_emission')
            ->orderByDesc('factures.id')
            ->orderBy('lignes_facture.id')
            ->get();
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
