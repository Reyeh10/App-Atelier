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
        'numero', 'or_id', 'dossier_id', 'statut',
        // Devis en avance (réservation ou devis libre, sans OR ni dossier)
        'reservation_id', 'client_id', 'vehicule_id', 'kilometrage_prevu', 'date_prevue', 'entretien_km_seuil', 'date_envoi', 'date_validation',
        'fichier_signe', 'notes', 'montant_ht', 'taux_tva', 'montant_tva', 'montant_ttc',
        // Feuille de travail propre à un devis complémentaire (cf. OrdreReparation::devisComplementaires())
        'technicien_id', 'service', 'chef_id', 'date_affectation', 'duree_estimee',
        'heure_debut_travaux', 'heure_fin_travaux',
    ];

    protected $casts = [
        'date_envoi'      => 'date',
        'date_validation' => 'date',
        'montant_ht'      => 'decimal:2',
        'montant_tva'     => 'decimal:2',
        'montant_ttc'     => 'decimal:2',
        'taux_tva'        => 'decimal:2',
        'date_prevue'         => 'date',
        'date_affectation'    => 'datetime',
        'heure_debut_travaux' => 'datetime',
        'heure_fin_travaux'   => 'datetime',
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

    /** Réservation pour laquelle ce devis a été établi à l'avance (le cas échéant) */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /** Client d'un devis en avance (réservation ou devis libre) */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** Véhicule d'un devis en avance (réservation ou devis libre) */
    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id');
    }

    /** Origine du devis, en toutes lettres (en-têtes et listes) */
    public function getOrigineLabel(): string
    {
        return match (true) {
            (bool) $this->or_id          => 'Ordre de réparation',
            (bool) $this->dossier_id     => 'Dossier de réception',
            (bool) $this->reservation_id => 'Réservation (devis en avance)',
            default                      => 'Devis libre',
        };
    }

    /** Lien vers ce à quoi le devis est rattaché (OR, dossier, réservation, ou lui-même) */
    public function getParentUrl(): string
    {
        return match (true) {
            (bool) $this->or_id          => route('ordres-reparations.show', $this->or_id),
            (bool) $this->dossier_id     => route('dossiers-reception.show', $this->dossier_id),
            (bool) $this->reservation_id => route('reservations.show', $this->reservation_id),
            default                      => route('devis.show', $this),
        };
    }

    /**
     * Qui peut encore modifier / supprimer ce devis :
     *   - brouillon ou envoyé : toute personne qui gère les devis ;
     *   - accepté ou refusé : l'administrateur seulement, pour corriger une
     *     erreur — sauf si l'OR est déjà facturé (le devis reste alors figé,
     *     comme la facture).
     */
    public function estModifiablePar(?User $user): bool
    {
        if (! $user) return false;
        if (in_array($this->statut, ['brouillon', 'envoye'], true)) {
            return $user->hasPermission('gerer_devis');
        }

        return $user->isAdmin() && ! $this->estFige();
    }

    /**
     * La décision du client n'est pas encore prise (brouillon ou envoyé) : seul ce
     * cas permet d'envoyer, d'accepter ou de refuser — même règle que les boutons.
     * Un devis accepté ou refusé garde sa décision (l'admin peut seulement en
     * corriger les lignes).
     */
    public function attendDecision(): bool
    {
        return in_array($this->statut, ['brouillon', 'envoye'], true);
    }

    /**
     * Pourquoi l'administrateur ne peut pas passer ce devis à la décision donnée
     * (« accepte » ou « refuse ») — null si c'est possible. Jamais une fois l'OR
     * facturé (avoir), ni pour refuser un devis dont les travaux ont commencé.
     */
    public function raisonChangementDecisionImpossible(string $decision): ?string
    {
        if (! in_array($this->statut, ['accepte', 'refuse'], true)) {
            return "Le devis {$this->numero} n'a pas encore de décision : utilisez « Marquer accepté » ou « Refusé ».";
        }
        if ($this->statut === $decision) {
            return "Le devis {$this->numero} est déjà " . ($decision === 'accepte' ? 'accepté' : 'refusé') . '.';
        }
        if ($this->estFige()) {
            return "L'OR du devis {$this->numero} est déjà facturé : corrigez la facture par un avoir.";
        }
        if ($this->estEnAvance()) {
            return "Le devis {$this->numero} est un devis en avance : il est repris et accepté le jour de la réception.";
        }
        if ($decision === 'refuse' && ($or = $this->ordreReparation)) {
            $principal = $or->devisPrincipal()?->is($this);
            $commence  = $principal
                ? ($or->heure_debut_travaux || $or->devisComplementaires()->contains(fn (Devis $d) => $d->heure_debut_travaux))
                : (bool) $this->heure_debut_travaux;
            if ($commence) {
                return "Les travaux du devis {$this->numero} ont déjà commencé : il ne peut plus être refusé.";
            }
        }

        return null;
    }

    /** L'OR de ce devis est déjà facturé : le devis ne bouge plus, même pour l'admin */
    public function estFige(): bool
    {
        return (bool) $this->ordreReparation?->facture;
    }

    /** Devis établi avant toute réception (pas encore d'OR ni de dossier) */
    public function estEnAvance(): bool
    {
        return ! $this->or_id && ! $this->dossier_id;
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

    /** Technicien affecté à la feuille de travail de ce devis complémentaire */
    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class, 'technicien_id');
    }

    /** Utilisateur qui a affecté le technicien à cette feuille */
    public function chef(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_id');
    }

    /** Indique si la feuille de travail de ce devis a un technicien et un service affectés */
    public function isAffecte(): bool
    {
        return $this->technicien_id !== null && $this->service !== null;
    }

    /** Le bon de commande pièces de ce devis bloque-t-il encore l'affectation ? */
    public function attendPieces(): bool
    {
        return $this->bonCommande !== null
            && in_array($this->bonCommande->statut, ['en_attente', 'commande'], true)
            && $this->bonCommande->lignes()->exists();
    }

    /** Durée brute des travaux de cette feuille, en heures (null tant qu'elle n'est pas terminée) */
    public function getDureeReelleHeures(): ?float
    {
        if (! $this->heure_debut_travaux || ! $this->heure_fin_travaux) return null;
        return round($this->heure_debut_travaux->diffInMinutes($this->heure_fin_travaux) / 60, 2);
    }

    /** Libellé du service affecté à cette feuille */
    public function getServiceLabel(): string
    {
        return match ($this->service) {
            'rapide'      => 'Service Rapide',
            'mecanique'   => 'Mécanique',
            'electricite' => 'Électricité',
            'carrosserie' => 'Carrosserie',
            'peinture'    => 'Peinture',
            default       => 'Non défini',
        };
    }

    /**
     * Retourne l'OR si le devis y est déjà rattaché, sinon le dossier de
     * réception. Pratique pour les vues partagées entre les deux flux.
     */
    public function getParentAttribute(): OrdreReparation|DossierReception|Reservation|Devis|null
    {
        // Devis en avance : la réservation, ou le devis lui-même pour un devis
        // libre (il porte alors son propre numéro, client et véhicule).
        return $this->ordreReparation ?: ($this->dossier ?: ($this->reservation ?: ($this->client_id ? $this : null)));
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
        // Montants au franc (FDJ), comme les lignes
        $ht  = round((float) $this->lignes()->sum('total_ht'));
        $tva = round($ht * $this->taux_tva / 100);
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
