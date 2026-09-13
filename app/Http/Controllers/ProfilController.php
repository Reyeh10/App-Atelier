<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Espace profil de l'utilisateur connecté.
 *
 * Accessible à tout compte authentifié (aucune permission particulière) : chacun
 * peut y changer son propre mot de passe et choisir sa langue d'affichage.
 * La réinitialisation d'un mot de passe par l'admin reste séparée
 * (cf. UtilisateurController::resetPassword).
 */
class ProfilController extends Controller
{
    /** Langues proposées — clé = code locale, valeur = libellé affiché. */
    public const LANGUES = [
        'fr' => 'Français',
        'en' => 'English',
        'ar' => 'العربية',
    ];

    /** Affiche la page profil (infos du compte, mot de passe, langue). */
    public function edit(Request $request)
    {
        return view('profil.edit', [
            'user'    => $request->user(),
            'langues' => self::LANGUES,
        ]);
    }

    /**
     * Change le mot de passe du compte connecté.
     * Exige le mot de passe actuel, un minimum de 8 caractères, une confirmation,
     * et un nouveau mot de passe différent de l'ancien.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required'         => 'Veuillez saisir votre mot de passe actuel.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.required'                 => 'Le nouveau mot de passe est obligatoire.',
            'password.min'                      => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'                => 'La confirmation du mot de passe ne correspond pas.',
            'password.different'                => 'Le nouveau mot de passe doit être différent de l\'actuel.',
        ]);

        $user = $request->user();
        $user->update(['password' => Hash::make($request->password)]);
        Activite::journaliser('modifier_mot_de_passe', "{$user->name} a modifié son mot de passe", $user);

        return back()->with('success', 'Votre mot de passe a été mis à jour.');
    }

    /**
     * Enregistre la préférence de langue. La bascule effective de l'interface
     * (traductions + RTL arabe) n'est pas encore active : seule la préférence
     * est mémorisée pour le moment.
     */
    public function updateLangue(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(self::LANGUES))],
        ], [
            'locale.required' => 'Veuillez choisir une langue.',
            'locale.in'       => 'Cette langue n\'est pas prise en charge.',
        ]);

        $request->user()->update(['locale' => $data['locale']]);

        return back()->with('success', 'Votre préférence de langue a été enregistrée. L\'interface reste en français pour le moment — la traduction complète sera activée prochainement.');
    }
}
