<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\OrdreReparation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur de gestion des utilisateurs (comptes du personnel).
 *
 * Permet à l'admin de :
 *   - Voir la liste des utilisateurs avec leurs rôles
 *   - Modifier le nom, l'email ou le rôle d'un utilisateur
 *   - Réinitialiser un mot de passe
 *   - Supprimer un compte (avec nettoyage des références dans les OR)
 */
class UtilisateurController extends Controller
{
    /**
     * Liste tous les utilisateurs du système, triés par nom.
     */
    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_utilisateurs')) {
            abort(403);
        }

        $utilisateurs = User::orderBy('name')->get();

        return view('utilisateurs.index', compact('utilisateurs'));
    }

    /**
     * Met à jour le nom, l'email et le rôle d'un utilisateur.
     * L'email doit être unique parmi tous les utilisateurs (sauf l'utilisateur lui-même).
     */
    public function update(Request $request, User $utilisateur)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_utilisateurs')) {
            abort(403);
        }

        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($utilisateur->id)],
            'role'  => ['required', 'in:admin,chef_garage,receptionniste,caissier,responsable_garantie'],
        ], [
            'name.required'  => 'Le nom de l\'utilisateur est obligatoire.',
            'name.max'       => 'Le nom ne doit pas dépasser 255 caractères.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email'    => 'L\'adresse e-mail saisie n\'est pas valide.',
            'email.unique'   => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'role.required'  => 'Veuillez sélectionner un rôle.',
            'role.in'        => 'Le rôle sélectionné est invalide.',
        ]);

        $utilisateur->update($request->only('name', 'email', 'role'));
        Activite::journaliser('modifier_utilisateur', "Modification du compte de {$utilisateur->name} (rôle : {$utilisateur->getRoleLabel()})", $utilisateur);

        return back()->with('success', "Compte de {$utilisateur->name} mis à jour.");
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur.
     * Le mot de passe est haché avant stockage (jamais en clair en base).
     * Minimum 8 caractères, confirmation obligatoire.
     */
    public function resetPassword(Request $request, User $utilisateur)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_utilisateurs')) {
            abort(403);
        }

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required'  => 'Le nouveau mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $utilisateur->update(['password' => Hash::make($request->password)]);

        return back()->with('success', "Mot de passe de {$utilisateur->name} réinitialisé.");
    }

    /**
     * Supprime définitivement un compte utilisateur.
     * Règles de sécurité :
     *   - Un utilisateur ne peut pas supprimer son propre compte
     *   - Avant suppression, les références à cet utilisateur dans les OR sont nullifiées
     *     (conseiller, chef) pour ne pas laisser de références brisées en base
     */
    public function destroy(User $utilisateur)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_utilisateurs')) {
            abort(403);
        }

       if ($utilisateur->id === Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $nom  = $utilisateur->name;
        $role = $utilisateur->getRoleLabel();

        // Transaction : nullification des OR liés + suppression, tout en bloc atomique
        DB::transaction(function () use ($utilisateur) {
            OrdreReparation::where('conseiller_id', $utilisateur->id)->update(['conseiller_id' => null]);
            OrdreReparation::where('chef_id',        $utilisateur->id)->update(['chef_id'        => null]);
            $utilisateur->delete();
        });
        Activite::journaliser('supprimer_utilisateur', "Suppression du compte de {$nom} ({$role})");

        return back()->with('success', 'Compte supprimé avec succès.');
    }
}
