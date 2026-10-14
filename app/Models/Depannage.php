<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Dépannage : pièces (+ main-d'œuvre) prises sans réception ni devis.
 *
 * Les pièces partent au magasin par un BC (prix du magasin, BT comme d'habitude).
 * Une fois le BC entièrement reçu :
 *  - véhicule d'une liste leasing (parc STCD, tiers, service, bus) : pas de
 *    facture, les pièces vont au récap de sa liste (la main-d'œuvre est couverte
 *    par le forfait mensuel) — cf. RecapLeasingService ;
 *  - client normal : facture créée depuis le dépannage (pièces + main-d'œuvre).
 */
class Depannage extends Model
{
    protected $fillable = [
        'numero', 'client_id', 'vehicule_id', 'cree_par_id', 'date_depannage', 'description', 'leasing', 'facture_id',
    ];

    protected $casts = [
        'date_depannage' => 'date',
        'leasing'        => 'boolean',
    ];

    public function client(): BelongsTo      { return $this->belongsTo(Client::class); }
    public function vehicule(): BelongsTo    { return $this->belongsTo(Vehicule::class); }
    public function creePar(): BelongsTo     { return $this->belongsTo(User::class, 'cree_par_id'); }
    public function facture(): BelongsTo     { return $this->belongsTo(Facture::class); }
    public function bonCommande(): HasOne    { return $this->hasOne(BonCommande::class); }
    public function lignes(): HasMany        { return $this->hasMany(LigneDepannage::class)->orderBy('id'); }

    /** Véhicule d'une liste leasing (parc, tiers, service, bus) : pas de facture, pièces au récap */
    public static function vehiculeEnLeasing(Vehicule $vehicule): bool
    {
        return (bool) $vehicule->leasing_tiers || VehiculeLeasing::contient($vehicule->immatriculation);
    }

    public static function genererNumero(): string
    {
        $annee   = now()->year;
        $dernier = self::where('numero', 'like', "DEP-{$annee}-%")->max('numero');
        $seq     = $dernier ? (int) substr($dernier, -4) + 1 : 1;

        return sprintf('DEP-%d-%04d', $annee, $seq);
    }

    /** Facture en vigueur (une facture annulée par avoir permet de refacturer) */
    public function estFacture(): bool
    {
        return $this->facture_id && $this->facture && $this->facture->statut !== 'annulee';
    }

    /** Pièces encore attendues : BC pas encore entièrement reçu */
    public function attendPieces(): bool
    {
        return $this->bonCommande && $this->bonCommande->statut !== 'recu';
    }

    /** Client normal, pièces reçues, pas encore facturé */
    public function estAFacturer(): bool
    {
        return ! $this->leasing && ! $this->estFacture() && ! $this->attendPieces();
    }

    /** Pourquoi la facture ne peut pas encore être créée (null si elle le peut) */
    public function raisonNonFacturable(): ?string
    {
        return match (true) {
            $this->leasing             => "Le dépannage {$this->numero} concerne un véhicule en leasing : pas de facture, les pièces vont au récap de sa liste.",
            $this->estFacture()        => "Le dépannage {$this->numero} est déjà facturé.",
            $this->attendPieces()      => "Les pièces du dépannage {$this->numero} ne sont pas encore toutes reçues (BT) : la facture se fait après réception.",
            $this->lignesPieces()->contains(fn ($l) => $l->prixUnitaireAffiche() === null || $l->prixUnitaireAffiche() <= 0)
                                       => "Une pièce du dépannage {$this->numero} n'a pas encore de prix du magasin.",
            default                    => null,
        };
    }

    /** Lignes de pièces (celles du BC) */
    public function lignesPieces()
    {
        return $this->bonCommande?->lignes ?? collect();
    }

    public function getStatutLabel(): string
    {
        return match (true) {
            $this->estFacture()      => 'Facturé',
            $this->attendPieces()    => 'Pièces attendues',
            $this->leasing           => 'Au récap leasing',
            default                  => 'À facturer',
        };
    }

    public function getStatutColor(): string
    {
        return match (true) {
            $this->estFacture()      => 'green',
            $this->attendPieces()    => 'amber',
            $this->leasing           => 'indigo',
            default                  => 'orange',
        };
    }

    /** Montant HT estimé : pièces (prix du magasin) + main-d'œuvre — main-d'œuvre exclue en leasing */
    public function montantHt(): float
    {
        $pieces = (float) $this->lignesPieces()->sum(fn ($l) => $l->montantAffiche() ?? 0);
        $mo     = $this->leasing ? 0.0 : (float) $this->lignes->sum(fn (LigneDepannage $l) => $l->total());

        return round($pieces + $mo);
    }
}
