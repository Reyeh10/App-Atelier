<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle Facture.
 *
 * Une facture est émise une fois le véhicule prêt, avant sa restitution au client.
 * Cycle de vie : emise → payee (ou annulee).
 *
 * Deux chemins pour autoriser la restitution :
 *   1. Paiement complet → statut "payee"
 *   2. Crédit accordé par le caissier → credit_accorde = true (paiement différé)
 *
 * Monnaie : FDJ (Francs Djiboutiens). TVA par défaut : 10%.
 * Format du numéro : XXXX/GARA/AAAA (ex: 0001/GARA/2026).
 */
class Facture extends Model
{
    protected $fillable = [
        'numero', 'or_id', 'devis_id', 'client_id', 'marque_garantie_id', 'encaissement_global_id',
        'statut', 'mode_paiement',
        'numero_bon_commande_client', 'bon_commande_client_chemin', 'bon_commande_client_nom_original',
        'date_emission', 'date_echeance', 'date_paiement', 'notes', 'frais_timbre',
        'montant_ht', 'taux_tva', 'montant_tva', 'montant_ttc', 'montant_paye',
        'credit_accorde', 'credit_accorde_at', 'credit_accorde_par',
    ];

    protected $casts = [
        'date_emission'     => 'date',
        'date_echeance'     => 'date',
        'date_paiement'     => 'date',
        'montant_ht'        => 'decimal:2',
        'montant_tva'       => 'decimal:2',
        'montant_ttc'       => 'decimal:2',
        'montant_paye'      => 'decimal:2',
        'taux_tva'          => 'decimal:2',
        'frais_timbre'      => 'decimal:2',
        'credit_accorde'    => 'boolean',
        'credit_accorde_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    /** OR auquel cette facture est rattachée */
    public function ordreReparation(): BelongsTo
    {
        return $this->belongsTo(OrdreReparation::class, 'or_id');
    }

    /** Devis d'origine (si la facture a été créée depuis un devis) */
    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }

    /** Client facturé (le propriétaire du véhicule — reste renseigné même si payeur = marque garantie) */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Compte garantie constructeur facturé (renseigné uniquement si la panne était couverte par la garantie) */
    public function marqueGarantie(): BelongsTo
    {
        return $this->belongsTo(MarqueGarantie::class);
    }

    /** Nom de l'entité qui paie réellement cette facture (marque garantie, sinon le client) */
    public function getPayeurNomAttribute(): string
    {
        return $this->marque_garantie_id
            ? $this->marqueGarantie->nom . ' (garantie constructeur)'
            : $this->client->nom_complet;
    }

    /** Lignes de détail de la facture (pièces + main d'œuvre) */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFacture::class);
    }

    /** Encaissement global auquel cette facture peut être rattachée (clients société) */
    public function encaissementGlobal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EncaissementGlobal::class, 'encaissement_global_id');
    }

    // ── Génération de numéro ────────────────────────────────────────────

    /**
     * Génère un numéro de facture séquentiel au format XXXX/GARA/AAAA.
     * Exemple : 0001/GARA/2026, 0002/GARA/2026...
     */
    public static function genererNumero(): string
    {
        $annee = now()->year;
        $seq   = self::whereYear('created_at', $annee)->count() + 1;
        return sprintf('%d/GARA/%d', $seq, $annee);
    }

    // ── Calculs de montants ─────────────────────────────────────────────

    /**
     * Total général = montant TTC + frais de timbre.
     * C'est ce total qui est affiché sur la facture et demandé au client.
     */
    public function totalGeneral(): float
    {
        return (float) $this->montant_ttc + (float) ($this->frais_timbre ?? 0);
    }

    /**
     * Montant restant à payer (différence entre total et montant déjà reçu).
     * Ne peut pas être négatif (minimum 0).
     */
    public function getMontantRestant(): float
    {
        return max(0, (float) $this->montant_ttc - (float) $this->montant_paye);
    }

    /**
     * Indique si le véhicule peut être restitué au client.
     * Deux conditions suffisantes (OU logique) :
     *   - La facture est payée en totalité
     *   - Le caissier a accordé un crédit (paiement différé autorisé)
     */
    public function peutEtreRestitue(): bool
    {
        return $this->statut === 'payee' || $this->credit_accorde;
    }

    // ── Conversion du montant en lettres ───────────────────────────────

    /**
     * Retourne le montant total en toutes lettres en français.
     * Exemple : 15 000 FDJ → "Quinze mille Francs Djiboutiens"
     */
    public function montantEnLettres(): string
    {
        $total = (int) round($this->totalGeneral());
        return ucfirst(self::nombreEnLettres($total)) . ' Francs Djiboutiens';
    }

    /**
     * Convertit un entier en lettres françaises (algorithme récursif).
     * Supporte les millions. Gère les cas particuliers du français
     * (soixante-dix, quatre-vingts, quatre-vingt-dix...).
     */
    private static function nombreEnLettres(int $n): string
    {
        if ($n === 0) return 'zéro';
        if ($n < 0)  return 'moins ' . self::nombreEnLettres(-$n);

        $unites   = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
                     'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
                     'dix-sept', 'dix-huit', 'dix-neuf'];
        $dizaines = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante'];

        $res = '';

        if ($n >= 1000000) {
            $m    = intdiv($n, 1000000);
            $res .= self::nombreEnLettres($m) . ' million' . ($m > 1 ? 's ' : ' ');
            $n   %= 1000000;
        }
        if ($n >= 1000) {
            $k    = intdiv($n, 1000);
            $res .= ($k === 1 ? '' : self::nombreEnLettres($k) . ' ') . 'mille ';
            $n   %= 1000;
        }
        if ($n >= 100) {
            $c    = intdiv($n, 100);
            $res .= ($c === 1 ? '' : $unites[$c] . ' ') . 'cent' . ($c > 1 && $n % 100 === 0 ? 's ' : ' ');
            $n   %= 100;
        }
        if ($n > 0) {
            if ($n < 20) {
                $res .= $unites[$n];
            } elseif ($n < 70) {
                $d    = intdiv($n, 10);
                $u    = $n % 10;
                $res .= $dizaines[$d] . ($u === 0 ? '' : ($u === 1 ? '-et-un' : '-' . $unites[$u]));
            } elseif ($n < 80) {
                // 60 à 79 : soixante-dix, soixante-et-onze...
                $res .= ($n === 71) ? 'soixante-et-onze' : 'soixante-' . $unites[$n - 60];
            } elseif ($n === 80) {
                $res .= 'quatre-vingts';
            } else {
                // 81 à 99 : quatre-vingt-un, quatre-vingt-deux...
                $res .= 'quatre-vingt-' . $unites[$n - 80];
            }
        }

        return trim($res);
    }

    // ── Labels ─────────────────────────────────────────────────────────

    /** Retourne le libellé français du statut de la facture */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'brouillon' => 'Brouillon',
            'emise'     => 'Émise',
            'payee'     => 'Payée',
            'annulee'   => 'Annulée',
            default     => $this->statut,
        };
    }

    /** Retourne la couleur Tailwind associée au statut (pour les badges) */
    public function getStatutColor(): string
    {
        return match($this->statut) {
            'brouillon' => 'gray',
            'emise'     => 'blue',
            'payee'     => 'green',
            'annulee'   => 'red',
            default     => 'gray',
        };
    }

    /** Retourne le libellé du mode de paiement */
    public function getModePaiementLabel(): ?string
    {
        return match($this->mode_paiement) {
            'especes'      => 'Espèces',
            'cheque'       => 'Chèque',
            'waafi'        => 'Waafi',
            'cac'          => 'CAC',
            'carte'        => 'Carte',
            'virement'     => 'Virement',
            'bon_commande' => 'Bon de commande',
            null           => 'En attente d\'encaissement',
            default        => $this->mode_paiement,
        };
    }

    /**
     * URL publique du scan/photo du bon de commande client joint au paiement,
     * ou null si aucun fichier n'a été téléversé (le numéro peut être renseigné
     * sans scan).
     */
    public function getBonCommandeClientUrlAttribute(): ?string
    {
        return $this->bon_commande_client_chemin ? asset('storage/' . $this->bon_commande_client_chemin) : null;
    }
}
