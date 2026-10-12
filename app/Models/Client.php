<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle Client.
 *
 * Trois types de clients :
 *   - particulier : personne physique (nom + prénom)
 *   - societe     : entreprise (raison sociale, peut avoir un compte crédit)
 *   - assurance   : compagnie d'assurance (traitement identique à société)
 *
 * Le système de compte crédit permet à une société de payer ses factures
 * en différé jusqu'à un plafond défini. Géré par l'admin uniquement.
 */
class Client extends Model
{
    protected $fillable = [
        'type', 'nom', 'prenom', 'raison_sociale', 'rc', 'nif',
        'contact_nom', 'telephone', 'telephone2', 'email',
        'adresse', 'ville', 'wilaya', 'notes',
        'compte_actif', 'plafond_compte',
    ];

    protected $casts = [
        'compte_actif'   => 'boolean',
        'plafond_compte' => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    /** Véhicules appartenant à ce client */
    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    /** Tous les ordres de réparation de ce client */
    public function ordresReparations(): HasMany
    {
        return $this->hasMany(OrdreReparation::class);
    }

    /** Toutes les factures émises pour ce client */
    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    // ── Compte crédit (sociétés) ────────────────────────────────────────

    /**
     * Montant total des factures non payées sur le compte crédit.
     * Accessible via $client->solde_compte (attribut calculé).
     */
    public function getSoldeCompteAttribute(): float
    {
        return (float) Facture::where(fn ($q) => $q->where(fn ($p) => $p->where('client_id', $this->id)->whereNull('assurance_id'))
                ->orWhere('assurance_id', $this->id))
            ->where('statut', 'emise')->where('credit_accorde', true)->sum('montant_ttc');
    }

    /**
     * Montant restant disponible sur le compte crédit avant d'atteindre le plafond.
     * Retourne 0 si le compte n'est pas actif ou si aucun plafond n'est défini.
     */
    public function getDisponibleCompteAttribute(): float
    {
        if (! $this->compte_actif || ! $this->plafond_compte) return 0;
        return max(0, (float) $this->plafond_compte - $this->solde_compte);
    }

    /**
     * Indique si le client peut encore se faire facturer sur son compte crédit.
     * Retourne false si le compte n'est pas actif ou si le plafond est déjà atteint.
     */
    public function peutFacturerSurCompte(float $montantAdditionnel = 0): bool
    {
        if (! $this->compte_actif || ! $this->plafond_compte) return false;
        return ($this->solde_compte + $montantAdditionnel) <= (float) $this->plafond_compte;
    }

    // ── Attributs calculés ─────────────────────────────────────────────

    /**
     * Nom complet du client selon son type.
     * - Particulier : "Prénom Nom"
     * - Société / Assurance : raison sociale
     * Accessible via $client->nom_complet.
     */
    public function getNomCompletAttribute(): string
    {
        return match($this->type) {
            'particulier' => trim(($this->prenom ? $this->prenom . ' ' : '') . $this->nom),
            'societe'     => $this->raison_sociale ?? $this->nom,
            'assurance'   => $this->raison_sociale ?? $this->nom,
            default       => $this->nom,
        };
    }

    // ── Labels et couleurs ─────────────────────────────────────────────

    /** Retourne le libellé français du type de client */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'particulier' => 'Particulier',
            'societe'     => 'Société',
            'assurance'   => 'Assurance',
            default       => 'Inconnu',
        };
    }

    /** Retourne la couleur Tailwind pour le badge de type */
    public function getTypeBadgeColor(): string
    {
        return match($this->type) {
            'particulier' => 'blue',
            'societe'     => 'purple',
            'assurance'   => 'green',
            default       => 'gray',
        };
    }
}
