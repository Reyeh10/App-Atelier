<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle Avoir (facture d'avoir).
 *
 * Une facture émise n'est jamais supprimée ni modifiée : pour l'annuler ou la
 * corriger, l'administrateur émet un avoir qui la reprend en totalité (mêmes
 * lignes, mêmes montants, en négatif). La facture passe alors au statut
 * "annulee".
 *   - type "annulation" : l'OR revient dans « À facturer » ;
 *   - type "correction" : une nouvelle facture (facture_remplacement_id) est
 *     émise aussitôt avec les montants corrigés et reprend le paiement déjà reçu.
 *
 * Les montants sont stockés en positif et affichés précédés d'un « - ».
 * Format du numéro : N/AVOIR/AAAA (ex: 1/AVOIR/2026), séquence propre aux avoirs.
 */
class Avoir extends Model
{
    protected $fillable = [
        'numero', 'facture_id', 'facture_remplacement_id', 'or_id', 'client_id', 'marque_garantie_id',
        'type', 'motif', 'date_emission',
        'montant_ht', 'taux_tva', 'montant_tva', 'montant_ttc', 'frais_timbre',
        'montant_deja_paye', 'montant_a_rembourser', 'rembourse_le', 'mode_remboursement', 'cree_par',
    ];

    protected $casts = [
        'date_emission'        => 'date',
        'rembourse_le'         => 'date',
        'montant_ht'           => 'decimal:2',
        'taux_tva'             => 'decimal:2',
        'montant_tva'          => 'decimal:2',
        'montant_ttc'          => 'decimal:2',
        'frais_timbre'         => 'decimal:2',
        'montant_deja_paye'    => 'decimal:2',
        'montant_a_rembourser' => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    /** Facture annulée par cet avoir */
    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    /** Nouvelle facture émise à la place (correction uniquement) */
    public function factureRemplacement(): BelongsTo
    {
        return $this->belongsTo(Facture::class, 'facture_remplacement_id');
    }

    public function ordreReparation(): BelongsTo
    {
        return $this->belongsTo(OrdreReparation::class, 'or_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function marqueGarantie(): BelongsTo
    {
        return $this->belongsTo(MarqueGarantie::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneAvoir::class);
    }

    /** Nom de l'entité créditée (marque garantie, sinon le client) */
    public function getPayeurNomAttribute(): string
    {
        return $this->marque_garantie_id
            ? $this->marqueGarantie->nom . ' (garantie constructeur)'
            : $this->client->nom_complet;
    }

    // ── Numérotation ───────────────────────────────────────────────────

    /**
     * Numéro séquentiel propre aux avoirs, qui repart à 1 chaque année —
     * même logique que Facture::genererNumero() (plus grand numéro + 1).
     */
    public static function genererNumero(): string
    {
        $annee      = now()->year;
        $dernierSeq = self::whereYear('created_at', $annee)
            ->get(['numero'])
            ->map(fn ($a) => (int) explode('/', $a->numero)[0])
            ->max();

        return sprintf('%d/AVOIR/%d', ($dernierSeq ?? 0) + 1, $annee);
    }

    // ── Calculs ────────────────────────────────────────────────────────

    /** Total de l'avoir = TTC + frais de timbre repris de la facture */
    public function totalGeneral(): float
    {
        return (float) $this->montant_ttc + (float) $this->frais_timbre;
    }

    /** Un montant reste à rendre au client tant que le remboursement n'est pas enregistré */
    public function resteARembourser(): bool
    {
        return (float) $this->montant_a_rembourser > 0 && ! $this->rembourse_le;
    }

    public function getModeRemboursementLabel(): ?string
    {
        return match($this->mode_remboursement) {
            'especes'  => 'Espèces',
            'cheque'   => 'Chèque',
            'waafi'    => 'Waafi',
            'virement' => 'Virement',
            'deduit'   => 'Déduit de la nouvelle facture',
            null       => null,
            default    => $this->mode_remboursement,
        };
    }

    /** Montant total en toutes lettres (même conversion que la facture) */
    public function montantEnLettres(): string
    {
        return (new Facture(['montant_ttc' => $this->montant_ttc, 'frais_timbre' => $this->frais_timbre]))->montantEnLettres();
    }

    public function getTypeLabel(): string
    {
        return $this->type === 'correction' ? 'Correction' : 'Annulation';
    }

    /**
     * Crée l'avoir qui annule entièrement $facture (lignes et montants
     * recopiés à l'identique). À appeler dans une transaction.
     */
    public static function pourAnnuler(Facture $facture, string $type, string $motif, float $montantARembourser = 0): self
    {
        $avoir = self::create([
            'numero'               => self::genererNumero(),
            'facture_id'           => $facture->id,
            'or_id'                => $facture->or_id,
            'client_id'            => $facture->client_id,
            'marque_garantie_id'   => $facture->marque_garantie_id,
            'type'                 => $type,
            'motif'                => $motif,
            'date_emission'        => now(),
            'montant_ht'           => $facture->montant_ht,
            'taux_tva'             => $facture->taux_tva,
            'montant_tva'          => $facture->montant_tva,
            'montant_ttc'          => $facture->montant_ttc,
            'frais_timbre'         => $facture->frais_timbre ?? 0,
            'montant_deja_paye'    => $facture->montant_paye,
            'montant_a_rembourser' => $montantARembourser,
            'cree_par'             => \Illuminate\Support\Facades\Auth::id(),
        ]);

        foreach ($facture->lignes as $ligne) {
            $avoir->lignes()->create($ligne->only([
                'type', 'designation', 'reference', 'unite', 'quantite', 'prix_unitaire', 'remise', 'total_ht',
            ]));
        }

        return $avoir;
    }
}
