<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modèle Devis.
 *
 * Le devis liste les travaux prévus avec leurs coûts (pièces + main d'œuvre).
 * Il est présenté au client pour validation avant de démarrer les travaux.
 *
 * Cycle de vie : brouillon → envoye → accepte (ou refuse).
 * Quand un devis est accepté, un bon de commande pièces est généré automatiquement
 * (si le devis contient des pièces détachées).
 *
 * Format du numéro : N°/GARA/AAAA (ex: 1/GARA/2026) — même format que la
 * facture (cf. Facture::genererNumero()), sur demande explicite.
 */
class Devis extends Model
{
    protected $table = 'devis';

    protected $fillable = [
        'numero', 'or_id', 'dossier_id', 'statut', 'date_envoi', 'date_validation',
        'fichier_signe', 'notes', 'montant_ht', 'taux_tva', 'montant_tva', 'montant_ttc',
    ];

    protected $casts = [
        'date_envoi'      => 'date',
        'date_validation' => 'date',
        'montant_ht'      => 'decimal:2',
        'montant_tva'     => 'decimal:2',
        'montant_ttc'     => 'decimal:2',
        'taux_tva'        => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    /** OR auquel ce devis est rattaché (une fois le dossier transformé) */
    public function ordreReparation(): BelongsTo
    {
        return $this->belongsTo(OrdreReparation::class, 'or_id');
    }

    /** Dossier de réception auquel ce devis est rattaché, tant que l'OR n'existe pas encore */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierReception::class, 'dossier_id');
    }

    /** Lignes de détail du devis (pièces, main d'œuvre, forfaits) */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneDevis::class);
    }

    /** Bon de commande pièces généré depuis ce devis (s'il existe) */
    public function bonCommande(): HasOne
    {
        return $this->hasOne(BonCommande::class, 'devis_id');
    }

    /**
     * Retourne l'OR si le devis y est déjà rattaché, sinon le dossier de
     * réception. Pratique pour les vues partagées entre les deux flux.
     */
    public function getParentAttribute(): OrdreReparation|DossierReception|null
    {
        return $this->ordreReparation ?: $this->dossier;
    }

    /**
     * Le client ne doit pas pouvoir valider un devis tant que le fournisseur
     * (stcd-magasin) n'a pas statué sur la disponibilité de chaque pièce —
     * une pièce dont `disponible` est encore null (aucune réponse reçue) bloque
     * l'acceptation. Un devis sans pièce (main d'œuvre seule) n'est jamais bloqué.
     */
    public function attendReponseFournisseur(): bool
    {
        return $this->lignes->where('type', 'piece')->contains(fn (LigneDevis $l) => is_null($l->disponible));
    }

    // ── Calculs ────────────────────────────────────────────────────────

    /**
     * Recalcule et met à jour les montants HT, TVA et TTC du devis
     * à partir de la somme des lignes en base.
     * Appelé après ajout/modification des lignes.
     */
    public function recalculer(): void
    {
        $ht  = $this->lignes()->sum('total_ht');
        $tva = round($ht * $this->taux_tva / 100, 2);
        $this->update([
            'montant_ht'  => $ht,
            'montant_tva' => $tva,
            'montant_ttc' => $ht + $tva,
        ]);
    }

    /**
     * Génère un numéro de devis séquentiel au format N°/GARA/AAAA (ex:
     * 1/GARA/2026) — même format que la facture. La séquence repart à 1
     * chaque année. Le numéro le plus élevé de l'année est déterminé en PHP
     * (et non via MAX() SQL) car un tri alphabétique sur "N/GARA/AAAA" serait
     * faux dès que le nombre de chiffres de N change (ex: "10/..." < "9/..."
     * en tri texte).
     */
    public static function genererNumero(): string
    {
        $annee      = now()->year;
        $dernierSeq = self::whereYear('created_at', $annee)
            ->get(['numero'])
            ->map(fn ($d) => (int) explode('/', $d->numero)[0])
            ->max();

        $seq = ($dernierSeq ?? 0) + 1;

        return sprintf('%d/GARA/%d', $seq, $annee);
    }

    // ── Labels et couleurs ─────────────────────────────────────────────

    /** Retourne le libellé français du statut du devis */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'brouillon' => 'Brouillon',
            'envoye'    => 'Envoyé',
            'accepte'   => 'Accepté',
            'refuse'    => 'Refusé',
            default     => $this->statut,
        };
    }

    /** Retourne la couleur Tailwind pour le badge de statut */
    public function getStatutColor(): string
    {
        return match($this->statut) {
            'brouillon' => 'gray',
            'envoye'    => 'blue',
            'accepte'   => 'green',
            'refuse'    => 'red',
            default     => 'gray',
        };
    }
}
