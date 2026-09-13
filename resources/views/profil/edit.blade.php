@extends('layouts.app')

@section('title', 'Mon profil')
@section('page-title', 'Mon profil')
@section('page-subtitle', 'Mot de passe et préférences personnelles')

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- ── Informations du compte ──────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-4">Mon compte</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <p class="text-xs text-slate-400 mb-1">Nom</p>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $user->name }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 mb-1">Email</p>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 break-all">{{ $user->email }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 mb-1">Rôle</p>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $user->getRoleLabel() }}</p>
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-4">Pour changer votre nom, votre email ou votre rôle, contactez un administrateur.</p>
    </div>

    {{-- ── Changer mon mot de passe ────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-4">Changer mon mot de passe</h3>

        @if($errors->has('current_password') || $errors->has('password'))
        <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3 mb-4">
            <ul class="space-y-1">
                @foreach(array_merge($errors->get('current_password'), $errors->get('password')) as $e)
                <li class="text-sm text-red-700 dark:text-red-300 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>{{ $e }}
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('profil.password') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Mot de passe actuel <span class="text-red-500">*</span></label>
                <input type="password" name="current_password" autocomplete="current-password" required
                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('current_password') border-red-400 @enderror">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Nouveau mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" name="password" autocomplete="new-password" required minlength="8"
                           class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('password') border-red-400 @enderror">
                    <p class="text-xs text-slate-400 mt-1">8 caractères minimum.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Confirmer le nouveau mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required minlength="8"
                           class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
            </div>

            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors shadow-sm text-sm">
                Mettre à jour le mot de passe
            </button>
        </form>
    </div>

    {{-- ── Langue du système ──────────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-4">Langue du système</h3>

        @if($errors->has('locale'))
        <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3 mb-4">
            @foreach($errors->get('locale') as $e)
            <p class="text-sm text-red-700 dark:text-red-300">{{ $e }}</p>
            @endforeach
        </div>
        @endif

        <div class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-xl px-4 py-3 mb-4">
            <p class="text-sm text-amber-700 dark:text-amber-300">
                Bientôt disponible — l'interface reste en français pour le moment. Votre choix est enregistré et sera appliqué dès que la traduction sera activée.
            </p>
        </div>

        <form method="POST" action="{{ route('profil.langue') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="max-w-xs">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Langue d'affichage</label>
                <select name="locale"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                    @foreach($langues as $code => $libelle)
                    <option value="{{ $code }}" {{ old('locale', $user->locale ?? 'fr') === $code ? 'selected' : '' }}>
                        {{ $libelle }}{{ $code === 'fr' ? ' (par défaut)' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors shadow-sm text-sm">
                Enregistrer la langue
            </button>
        </form>
    </div>

</div>
@endsection
