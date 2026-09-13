@extends('layouts.auth')

@section('title', 'Connexion')

@section('content')
<style>
@keyframes spin-cw  { to { transform: rotate(360deg);  } }
@keyframes spin-ccw { to { transform: rotate(-360deg); } }
.gr-a { animation: spin-cw  24s linear infinite; }
.gr-b { animation: spin-ccw 15s linear infinite; }
.gr-c { animation: spin-cw   9s linear infinite; }
</style>

@php
$cog = 'M495.9 166.6c3.2 8.7.5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6.3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c13.4-10.3 28.2-18.9 44-25.4l12.5-57.1c2-9.1 9-16.3 18.2-17.8C227.3 1.2 241.5 0 256 0s28.7 1.2 42.5 3.5c9.2 1.5 16.2 8.7 18.2 17.8l12.5 57.1c15.8 6.5 30.6 15.1 44 25.4l55.7-17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z';
@endphp

<div class="min-h-screen flex">

    {{-- ════════ PANNEAU GAUCHE — BRANDING ════════ --}}
    {{-- overflow-y-auto + my-auto sur le contenu : le bloc reste centré quand la
         fenêtre est assez haute, et devient défilable sans chevauchement quand
         elle est courte (le pied de page ne recouvre plus la liste). --}}
    <div class="hidden lg:flex lg:w-7/12 relative overflow-y-auto overflow-x-hidden flex-col items-center py-12"
         style="background: linear-gradient(145deg, #0f172a 0%, #1e293b 60%, #0c1525 100%);">

        {{-- Engrenages en fond --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <svg class="gr-a absolute" viewBox="0 0 512 512"
                 style="bottom:-100px;right:-100px;width:360px;height:360px;opacity:.05;">
                <path fill="#f97316" d="{{ $cog }}"/>
            </svg>
            <svg class="gr-b absolute" viewBox="0 0 512 512"
                 style="top:-70px;left:-70px;width:260px;height:260px;opacity:.04;">
                <path fill="#f97316" d="{{ $cog }}"/>
            </svg>
            <svg class="gr-c absolute" viewBox="0 0 512 512"
                 style="top:50%;left:65%;width:130px;height:130px;opacity:.04;">
                <path fill="#f97316" d="{{ $cog }}"/>
            </svg>
        </div>

        {{-- Contenu centré --}}
        <div class="relative z-10 flex flex-col items-center text-center px-16 max-w-2xl my-auto">

            {{-- Logo --}}
            <div class="mb-8" style="padding:12px;background:rgba(255,255,255,.06);border-radius:24px;border:1px solid rgba(249,115,22,.25);">
                <img src="{{ asset('logo-stcd.jpg') }}" alt="STCD Motors"
                     class="h-28 w-28 object-contain rounded-2xl">
            </div>

            {{-- Nom & Slogan --}}
            <h1 style="font-size:2.5rem;font-weight:800;color:#ffffff;letter-spacing:.02em;margin-bottom:.4rem;">STCD Motors</h1>
            <p style="color:#fb923c;font-size:1.1rem;font-style:italic;margin-bottom:2rem;">Your Dream Car, One Visit Away</p>

            {{-- Séparateur --}}
            <div style="width:80px;height:3px;background:linear-gradient(90deg,transparent,#f97316,transparent);border-radius:9px;margin-bottom:2rem;"></div>

            {{-- Slogan principal --}}
            <h2 style="font-size:3rem;font-weight:800;color:#ffffff;line-height:1.15;margin-bottom:.5rem;text-shadow:0 2px 20px rgba(0,0,0,.5);">
                Gérez votre atelier
            </h2>
            <p style="font-size:3rem;font-weight:800;color:#f97316;line-height:1.15;margin-bottom:2rem;text-shadow:0 2px 20px rgba(249,115,22,.3);">
                en toute simplicité
            </p>

            <p style="color:#94a3b8;font-size:1.05rem;line-height:1.7;margin-bottom:2.5rem;">
                Clients, véhicules, réparations, devis et factures —<br>
                tout dans un seul outil conçu pour les ateliers mécaniques.
            </p>

            {{-- Features --}}
            <div style="width:100%;display:flex;flex-direction:column;gap:1rem;">
                @foreach([
                    ['icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z','txt'=>'Gestion des clients et véhicules'],
                    ['icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4','txt'=>'Suivi des réparations en temps réel'],
                    ['icon'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z','txt'=>'Devis et facturation intégrés'],
                ] as $f)
                <div style="display:flex;align-items:center;gap:1rem;text-align:left;">
                    <div style="width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(249,115,22,.22);border:1px solid rgba(249,115,22,.45);">
                        <svg style="width:22px;height:22px;color:#f97316;stroke:#f97316;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $f['icon'] }}"/>
                        </svg>
                    </div>
                    <span style="color:#e2e8f0;font-size:1rem;font-weight:500;">{{ $f['txt'] }}</span>
                </div>
                @endforeach
            </div>

        </div>

        {{-- Footer — en flux normal (plus en absolute) pour ne jamais recouvrir le contenu --}}
        <p class="relative z-10 flex-shrink-0 mt-8 text-slate-500 text-sm text-center">
            © {{ date('Y') }} STCD Motors — Tous droits réservés
        </p>
    </div>

    {{-- ════════ PANNEAU DROIT — FORMULAIRE ════════ --}}
    <div class="w-full lg:w-5/12 flex items-center justify-center bg-white px-10 py-12">
        <div class="w-full max-w-sm">

            {{-- Logo mobile --}}
            <div class="flex lg:hidden flex-col items-center gap-2 mb-10">
                <img src="{{ asset('logo-stcd.jpg') }}" alt="STCD Motors" class="h-16 w-16 object-contain rounded-xl shadow">
                <p class="font-bold text-slate-900 text-lg">STCD Motors</p>
                <p class="text-slate-400 text-xs italic">Your Dream Car, One Visit Away</p>
            </div>

            {{-- Titre --}}
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-900 mb-1">Connexion</h1>
                <p class="text-slate-500 text-sm">Bienvenue, veuillez entrer vos identifiants.</p>
            </div>

            {{-- Session expirée --}}
            @if(session('timeout'))
            <div class="mb-5 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-amber-800">{{ session('timeout') }}</p>
            </div>
            @endif

            {{-- Erreurs --}}
            @if($errors->any())
            <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm text-red-700">{{ $errors->first('email') }}</p>
            </div>
            @endif

            {{-- Formulaire --}}
            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Adresse email</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email') }}" autocomplete="email" autofocus
                           placeholder="exemple@atelier.com"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl text-slate-900 placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition @error('email') border-red-400 bg-red-50 @enderror">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Mot de passe</label>
                    <div class="relative">
                        <input type="password" id="password" name="password"
                               autocomplete="current-password" placeholder="••••••••"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl text-slate-900 placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition @error('password') border-red-400 bg-red-50 @enderror">
                        <button type="button" onclick="togglePassword()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember"
                               class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                        <label for="remember" class="ml-2 text-sm text-slate-600">Se souvenir de moi</label>
                    </div>
                    <a href="{{ route('password.request') }}"
                       class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                        Mot de passe oublié ?
                    </a>
                </div>

                <button type="submit"
                        class="w-full text-white font-semibold py-3 px-6 rounded-xl transition-colors shadow-sm focus:outline-none"
                        style="background:#f97316;"
                        onmouseover="this.style.background='#ea580c'" onmouseout="this.style.background='#f97316'">
                    Se connecter
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-slate-400">
                Vous n'avez pas de compte ? Contactez votre administrateur.
            </p>

        </div>
    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
(function() {
    if (sessionStorage.getItem('session_expired')) {
        sessionStorage.removeItem('session_expired');
        var div = document.createElement('div');
        div.className = 'mb-5 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-start gap-3';
        div.innerHTML = '<svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><p class="text-sm text-amber-800">Votre session a expiré après 10 minutes d\'inactivité. Veuillez vous reconnecter.</p>';
        var form = document.querySelector('form');
        form && form.parentNode.insertBefore(div, form);
    }
})();
</script>
@endsection
