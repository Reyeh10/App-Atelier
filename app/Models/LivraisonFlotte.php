<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Livraison flotte : les pièces (et éventuelle main-d'œuvre) d'un bus à une
 * date, issues d'un import Excel. C'est l'unité facturée — une facture par
 * livraison, sans OR ni réception.
 *
 * Cycle : attente_bt (BC envoyé au magasin, BT pas encore reçu)
 *       → a_facturer (BT reçu, ou livraison sans pièce)
 *       → facturee. Une facture annulée par avoir la remet « à facturer ».
 */
class LivraisonFlotte extends Model
{
    protected $table = 'livraisons_flotte';

    protected $fillable = [
        'import_flotte_id', 'client_id', 'vehicule_id', 'date_livraison', 'kilometrage', 'bon_commande_id',
    ];

    protected $casts = [
        'date_livraison' => 'date',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function import(): BelongsTo
    {
        return $this->belongsTo(ImportFlotte::class, 'import_flotte_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneLivraisonFlotte::class)->orderBy('id');
    }

    /** Toutes les factures de la livraison (y compris celles annulées par avoir) */
    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    // ── Statut ─────────────────────────────────────────────────────────

    /** Pas encore facturée (une facture annulée par avoir ne compte pas) */
    public function scopeNonFacturees($query)
    {
        return $query->whereDoesntHave('factures', fn ($q) => $q->where('statut', '!=', 'annulee'));
    }

    /** Prêtes à facturer : BT du magasin reçu, ou livraison sans pièce (main-d'œuvre seule) */
    public function scopeAFacturer($query)
    {
        return $query->nonFacturees()->where(fn ($q) => $q
            ->whereHas('bonCommande.bonTransfert')
            ->orWhereDoesntHave('lignes', fn ($l) => $l->where('type', 'piece')));
    }

    /** Facture en vigueur (une facture annulée par avoir ne compte plus) */
    public function factureActive(): ?Facture
    {
        return $this->factures->where('statut', '!=', 'annulee')->sortByDesc('id')->first();
    }

    public function aDesPieces(): bool
    {
        return $this->lignes->contains('type', 'piece');
    }

    public function statut(): string
    {
        if ($this->factureActive()) {
            return 'facturee';
        }
        if ($this->aDesPieces() && ! $this->bonCommande?->bonTransfert) {
            return 'attente_bt';
        }
        return 'a_facturer';
    }

    public function getStatutLabel(): string
    {
        return match ($this->statut()) {
            'facturee'   => 'Facturée',
            'attente_bt' => 'En attente du BT magasin',
            default      => 'À facturer',
        };
    }

    public function getStatutClasses(): string
    {
        return match ($this->statut()) {
            'facturee'   => 'bg-green-100 text-green-700',
            'attente_bt' => 'bg-yellow-100 text-yellow-700',
            default      => 'bg-blue-100 text-blue-700',
        };
    }

    // ── Préparation de la facture ──────────────────────────────────────

    /**
     * Lignes proposées pour la facture : pour une pièce, la quantité réellement
     * sortie du magasin (celle du BT) et le prix du fichier Excel — à défaut le
     * prix renvoyé par le magasin ; la main-d'œuvre telle qu'importée.
     * Une pièce non sortie (quantité 0) est retirée.
     *
     * @return array<int, array{type: string, reference: ?string, designation: string, quantite: float, quantite_demandee: float, prix_unitaire: ?float, remise: float}>
     */
    public function lignesProposees(): array
    {
        $this->loadMissing('lignes.ligneBonCommande', 'bonCommande.lignes', 'bonCommande.bonTransfert');

        $bc        = $this->bonCommande;
        $lignesBc  = $bc ? $bc->lignes->values() : collect();
        $lignesBt  = collect($bc?->bonTransfert?->lignes ?? []);
        $proposees = [];

        foreach ($this->lignes as $ligne) {
            $demandee = (float) $ligne->quantite;
            $quantite = $demandee;
            $prix     = $ligne->prix_unitaire !== null && (float) $ligne->prix_unitaire > 0 ? (float) $ligne->prix_unitaire : null;

            if ($ligne->type === 'piece' && $ligne->ligneBonCommande) {
                $ligneBc = $ligne->ligneBonCommande;
                $index   = $lignesBc->search(fn ($l) => $l->id === $ligneBc->id);

                if ($lignesBt->isNotEmpty()) {
                    // BT détaillé du magasin : on facture ce qui est réellement sorti
                    $ligneBt = $lignesBt->first(fn ($l) => isset($l['index']) && is_numeric($l['index']) && (int) $l['index'] === $index)
                        ?? $lignesBt->first(fn ($l) => ! empty($l['designation']) && mb_strtolower(trim($l['designation'])) === mb_strtolower(trim($ligneBc->designation)));
                    $quantite = $ligneBt ? (float) ($ligneBt['quantite'] ?? $demandee) : 0;
                } elseif ($ligneBc->disponible === false) {
                    $quantite = 0;
                } elseif ($ligneBc->quantite_disponible !== null) {
                    $quantite = min($demandee, (float) $ligneBc->quantite_disponible);
                }

                $prix ??= $ligneBc->prix_unitaire !== null && (float) $ligneBc->prix_unitaire > 0 ? (float) $ligneBc->prix_unitaire : null;
            }

            if ($quantite <= 0) {
                continue;
            }

            $proposees[] = [
                'type'              => $ligne->type,
                'reference'         => $ligne->reference,
                'designation'       => $ligne->designation,
                'quantite'          => $quantite,
                'quantite_demandee' => $demandee,
                'prix_unitaire'     => $prix,
                'remise'            => (float) $ligne->remise,
            ];
        }

        // Main-d'œuvre flotte (Réglages atelier), comme sur les autres factures —
        // sauf si le fichier en contient déjà une pour ce bus
        $mainOeuvre = (int) ParametreAtelier::get()->main_oeuvre_flotte;
        if ($mainOeuvre > 0 && ! $this->lignes->contains('type', 'main_oeuvre')) {
            $proposees[] = [
                'type'              => 'main_oeuvre',
                'reference'         => null,
                'designation'       => "Main d'œuvre",
                'quantite'          => 1.0,
                'quantite_demandee' => 1.0,
                'prix_unitaire'     => (float) $mainOeuvre,
                'remise'            => 0.0,
            ];
        }

        return $proposees;
    }
}
