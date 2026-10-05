<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Modèle Activite — Journal d'audit de toutes les actions dans l'application.
 *
 * Chaque action importante (création d'OR, paiement, modification de permissions...)
 * est enregistrée ici avec l'utilisateur, la date, l'IP et l'objet concerné.
 * Ce journal est en lecture seule — on ne modifie jamais une entrée existante.
 *
 * Note : timestamps = false car on n'a besoin que de created_at (pas updated_at).
 */
class Activite extends Model
{
    public $timestamps = false;  // Seul created_at est géré, pas updated_at

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'action',
        'description', 'subject_type', 'subject_id', 'subject_label', 'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * L'heure est écrite par l'application (fuseau Africa/Djibouti) et non par
     * MySQL : la valeur par défaut de la colonne (CURRENT_TIMESTAMP) suit le
     * fuseau du serveur MySQL, en UTC chez l'hébergeur — 3 h de retard.
     */
    protected static function booted(): void
    {
        static::creating(function (Activite $activite) {
            $activite->created_at ??= now();
        });
    }

    // ── Relations ──────────────────────────────────────────────────────

    /** Utilisateur qui a effectué l'action (peut être null si action système) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Méthode d'enregistrement ───────────────────────────────────────

    /**
     * Enregistre une action dans le journal d'audit.
     *
     * @param string     $action      Code de l'action (ex: 'creer_or', 'payer_facture')
     * @param string     $description Texte lisible décrivant l'action
     * @param Model|null $sujet       Objet concerné par l'action (OR, Facture, Client...) ou null
     *
     * Le nom et le rôle de l'utilisateur sont dénormalisés (copiés directement en base)
     * pour que le journal reste lisible même si l'utilisateur change de nom ou de rôle.
     */
    public static function journaliser(string $action, string $description, ?Model $sujet = null): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        self::create([
            'user_id'       => $user?->id,
            'user_name'     => $user?->name ?? 'Système',  // "Système" si action automatique
            'user_role'     => $user?->role ?? '',
            'action'        => $action,
            'description'   => $description,
            'subject_type'  => $sujet ? class_basename($sujet) : null,  // Nom de la classe (ex: "Facture")
            'subject_id'    => $sujet?->id,
            'subject_label' => $sujet ? self::labelDuSujet($sujet) : null,
            'ip_address'    => request()->ip(),
        ]);
    }

    /**
     * Détermine le label d'un objet pour le journal (numéro, nom, etc.).
     * Cherche les attributs dans un ordre de priorité.
     */
    private static function labelDuSujet(Model $sujet): string
    {
        if (property_exists($sujet, 'numero'))      return $sujet->numero ?? '';
        if (property_exists($sujet, 'nom_complet')) return $sujet->nom_complet ?? '';
        if (property_exists($sujet, 'name'))        return $sujet->name ?? '';
        return (string) $sujet->id;
    }

    // ── Couleur pour l'affichage ───────────────────────────────────────

    /**
     * Retourne la couleur Tailwind associée au type d'action.
     * Utilisé pour coloriser les entrées du journal d'activité.
     */
    public function getActionColor(): string
    {
        return match(true) {
            str_starts_with($this->action, 'connexion')   => 'green',
            str_starts_with($this->action, 'deconnexion') => 'slate',
            str_starts_with($this->action, 'creer')       => 'blue',
            str_starts_with($this->action, 'modifier')    => 'orange',
            str_starts_with($this->action, 'supprimer')   => 'red',
            str_starts_with($this->action, 'accepter')    => 'green',
            str_starts_with($this->action, 'refuser')     => 'red',
            str_starts_with($this->action, 'payer')       => 'green',
            default                                        => 'gray',
        };
    }
}
