<?php

namespace App\Models;

use App\Services\PermissionService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Modèle User — Compte utilisateur du personnel.
 *
 * Rôles disponibles et leurs responsabilités :
 *   - admin                  : accès total, gestion des utilisateurs et permissions
 *   - chef_garage            : gestion des OR, affectation des techniciens, devis
 *   - receptionniste         : création des OR, restitution des véhicules
 *   - caissier               : facturation, encaissement, accord de crédit
 *   - responsable_garantie   : traitement des demandes de garantie (approbation / refus)
 *
 * Les techniciens (mécaniciens) ne sont pas des comptes utilisateur — voir
 * App\Models\Technicien : ils n'utilisent jamais le système eux-mêmes.
 *
 * Le système de permissions fonctionne en deux couches :
 *   1. Permissions par défaut selon le rôle (PermissionService::$roleDefaults)
 *   2. Surcharges individuelles stockées dans la table user_permissions
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'locale',
    ];

    // Les champs sensibles ne sont jamais exposés dans les réponses JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',  // Le mot de passe est automatiquement haché si modifié
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────

    /** Surcharges de permissions individuelles pour cet utilisateur */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /** Notifications internes destinées à cet utilisateur (cloche en haut de l'écran) */
    public function notificationsInternes(): HasMany
    {
        return $this->hasMany(NotificationInterne::class, 'destinataire_id');
    }

    // ── Système de permissions ─────────────────────────────────────────

    /**
     * Vérifie si l'utilisateur possède une permission donnée.
     * Priorité : admin (tout) > surcharge individuelle > défaut du rôle.
     */
    public function hasPermission(string $permission): bool
    {
        // L'admin a toujours tous les droits sans exception
        if ($this->isAdmin()) {
            return true;
        }

        // On cherche d'abord une surcharge individuelle pour cet utilisateur
        $override = $this->userPermissions->firstWhere('permission_key', $permission);
        if ($override !== null) {
            return $override->granted;
        }

        // Sinon on applique les droits par défaut du rôle
        return in_array($permission, PermissionService::defaultsForRole($this->role));
    }

    // ── Helpers de rôle ────────────────────────────────────────────────

    /** Vérifie si l'utilisateur est administrateur système */
    public function isAdmin(): bool         { return $this->role === 'admin'; }

    /** Vérifie si l'utilisateur est chef de garage */
    public function isChefGarage(): bool    { return $this->role === 'chef_garage'; }

    /** Vérifie si l'utilisateur est réceptionniste */
    public function isReceptionniste(): bool { return $this->role === 'receptionniste'; }

    /** Vérifie si l'utilisateur est caissier */
    public function isCaissier(): bool      { return $this->role === 'caissier'; }

    /** Vérifie si l'utilisateur est responsable garantie */
    public function isResponsableGarantie(): bool { return $this->role === 'responsable_garantie'; }

    // ── Droits métier spéciaux ─────────────────────────────────────────

    /** Admin OU chef de garage : gestion complète de l'atelier (OR, affectations) */
    public function canManageWorkshop(): bool
    {
        return in_array($this->role, ['admin', 'chef_garage']);
    }

    /** Peut créer/modifier des clients société ou assurance (compte crédit) */
    public function peutGererClientSociete(): bool
    {
        return $this->isAdmin() || $this->hasPermission('gerer_compte_credit');
    }

    /** Peut voir le suivi des bons de commande pièces (disponibilité fournisseur) */
    public function peutVoirBonsCommande(): bool
    {
        return $this->isAdmin() || $this->hasPermission('voir_bons_commande');
    }

    /**
     * Peut valider un devis au client : marquer envoyé/accepté/refusé, uploader
     * le devis signé. Séparé de gerer_devis (créer/modifier/supprimer) pour
     * pouvoir confier ce droit à quelqu'un sans lui donner la main sur le
     * contenu du devis lui-même (ex: un réceptionniste de confiance).
     */
    public function peutValiderDevis(): bool
    {
        return $this->isAdmin() || $this->hasPermission('gerer_devis') || $this->hasPermission('valider_devis');
    }

    /** Peut marquer un bon de commande comme reçu (pièces arrivées au garage) */
    public function peutGererBonsCommande(): bool
    {
        return $this->isAdmin() || $this->hasPermission('gerer_bons_commande');
    }

    /**
     * Peut voir les alertes d'entretien en retard (fiche OR + tableau de
     * bord) — concerne le chef de garage et le réceptionniste, qui gèrent la
     * prise en charge du véhicule, mais pas le caissier dont le rôle se
     * limite à la facturation.
     */
    public function peutVoirAlerteEntretien(): bool
    {
        return $this->isAdmin() || in_array($this->role, ['chef_garage', 'receptionniste'], true);
    }

    // ── Labels et couleurs pour l'affichage ────────────────────────────

    /** Retourne le libellé français du rôle */
    public function getRoleLabel(): string
    {
        return match($this->role) {
            'admin'                => 'Administrateur',
            'chef_garage'          => 'Chef de garage',
            'receptionniste'       => 'Réceptionniste',
            'caissier'             => 'Caissier',
            'responsable_garantie' => 'Responsable Garantie',
            default                => 'Inconnu',
        };
    }

    /** Retourne la couleur Tailwind associée au rôle (pour les badges) */
    public function getRoleColor(): string
    {
        return match($this->role) {
            'admin'                => 'orange',
            'chef_garage'          => 'purple',
            'receptionniste'       => 'green',
            'caissier'             => 'indigo',
            'responsable_garantie' => 'yellow',
            default                => 'gray',
        };
    }

    /** Nombre de notifications non lues */
    public function notificationsNonLues(): int
    {
        return \App\Models\NotificationInterne::where('destinataire_id', $this->id)
            ->whereNull('lu_at')
            ->count();
    }
}
