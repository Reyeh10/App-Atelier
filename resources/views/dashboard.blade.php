@extends('layouts.app')
@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')
@section('page-subtitle', 'Bienvenue, ' . auth()->user()->name . ' — ' . now()->format('d/m/Y'))

@section('header-actions')
@if(auth()->user()->hasPermission('creer_dossiers'))
<a href="{{ route('reception.index') }}"
   class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    Nouvelle Réception
</a>
@endif
@endsection

@section('content')

{{-- ── Barre de recherche rapide ────────────────────────────── --}}
<div class="mb-5">
    <form method="GET" action="{{ route('recherche') }}" class="relative">
        <div class="flex items-center bg-white dark:bg-slate-800 border-2 border-gray-200 dark:border-slate-600
                    hover:border-orange-300 focus-within:border-orange-500 rounded-2xl transition-colors shadow-sm">
            <div class="pl-5 flex-shrink-0">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input type="text"
                   name="q"
                   placeholder="Rechercher un véhicule par immatriculation ou un client par nom…"
                   autofocus
                   class="flex-1 px-4 py-4 bg-transparent text-slate-800 dark:text-slate-100
                          placeholder-slate-400 text-sm focus:outline-none">
            <button type="submit"
                    class="flex-shrink-0 m-2 px-5 py-2.5 bg-orange-500 hover:bg-orange-600
                           text-white text-sm font-semibold rounded-xl transition-colors">
                Rechercher
            </button>
        </div>
    </form>
</div>

{{-- ── Centre d'alertes ─────────────────────────────────────── --}}
@php $nbAlertes = ($or_en_retard->isNotEmpty() ? 1 : 0) + ($stats['or_garantie'] > 0 ? 1 : 0) + ($entretien_en_retard->isNotEmpty() ? 1 : 0); @endphp
@if($nbAlertes > 0)
<div class="mb-5 bg-white rounded-2xl border border-gray-200 overflow-hidden">

    {{-- En-tête --}}
    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
            </span>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Centre d'alertes</h3>
        </div>
        <span class="text-xs font-bold bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full">{{ $nbAlertes }} alerte{{ $nbAlertes > 1 ? 's' : '' }}</span>
    </div>

    <div class="divide-y divide-gray-100">

        {{-- Alerte : ORs en retard --}}
        @if($or_en_retard->isNotEmpty())
        <div class="p-5">
            <div class="flex items-start gap-4">
                <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <p class="text-sm font-bold text-red-700">{{ $or_en_retard->count() }} OR en retard</p>
                            <p class="text-xs text-slate-500 mt-0.5">Ces véhicules sont en atelier depuis plus de 5 jours sans être terminés.</p>
                        </div>
                        <a href="{{ route('ordres-reparations.index') }}"
                           class="text-xs font-bold text-red-600 border border-red-200 rounded-lg px-3 py-1.5 hover:bg-red-50 transition-colors flex-shrink-0">
                            Voir tous →
                        </a>
                    </div>
                    <div class="rounded-xl overflow-hidden border border-red-100">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-red-50">
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">N° OR</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Client</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Véhicule</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Statut</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Délai</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Mécanicien</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-50 bg-white">
                                @foreach($or_en_retard as $or)
                                <tr class="hover:bg-red-50 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('ordres-reparations.show', $or) }}"
                                           class="font-mono font-bold text-orange-500 hover:underline">{{ $or->numero }}</a>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 font-medium">{{ $or->client->nom_complet }}</td>
                                    <td class="px-4 py-2.5 font-mono text-slate-600">{{ $or->vehicule->immatriculation }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="px-2 py-0.5 rounded-full font-medium bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700">
                                            {{ $or->getStatutLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-bold text-red-600">{{ $or->date_entree->diffInDays(now()) }} jours</span>
                                        <span class="text-slate-400"> ({{ $or->date_entree->format('d/m') }})</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-500">{{ $or->technicien?->name ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Alerte : Entretiens en retard (chef de garage / réceptionniste uniquement,
             disparaît du tableau de bord 1h après la création de l'OR — cf.
             DashboardController::index() — mais reste en permanence sur la fiche OR). --}}
        @if($entretien_en_retard->isNotEmpty())
        <div class="p-5">
            <div class="flex items-start gap-4">
                <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-red-700">{{ $entretien_en_retard->count() }} entretien{{ $entretien_en_retard->count() > 1 ? 's' : '' }} en retard</p>
                    <p class="text-xs text-slate-500 mt-0.5 mb-3">Palier d'entretien constructeur dépassé (km ou mois) — signalé à la réception du véhicule.</p>
                    <div class="rounded-xl overflow-hidden border border-red-100">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-red-50">
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">N° OR</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Client</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Véhicule</th>
                                    <th class="px-4 py-2 text-left font-semibold text-red-600">Dépassement</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-50 bg-white">
                                @foreach($entretien_en_retard as $or)
                                @php $retard = \App\Services\EntretienService::calculerRetard($or); @endphp
                                <tr class="hover:bg-red-50 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('ordres-reparations.show', $or) }}"
                                           class="font-mono font-bold text-orange-500 hover:underline">{{ $or->numero }}</a>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 font-medium">{{ $or->client->nom_complet }}</td>
                                    <td class="px-4 py-2.5 font-mono text-slate-600">{{ $or->vehicule->immatriculation }}</td>
                                    <td class="px-4 py-2.5 font-bold text-red-600">
                                        @if($retard['enRetardKm']){{ '+' . number_format($retard['depassementKm']) . ' km' }}@endif
                                        @if($retard['enRetardKm'] && $retard['enRetardMois']){{ ' / ' }}@endif
                                        @if($retard['enRetardMois']){{ '+' . $retard['depassementMois'] . ' mois' }}@endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Alerte : Garanties --}}
        @if($stats['or_garantie'] > 0)
        <div class="p-5">
            <div class="flex items-center gap-4">
                <div class="w-9 h-9 rounded-xl bg-yellow-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-yellow-700">{{ $stats['or_garantie'] }} OR en attente de validation garantie</p>
                    <p class="text-xs text-slate-500 mt-0.5">Ces véhicules ne peuvent pas être pris en charge sans décision de votre part.</p>
                </div>
                <a href="{{ route('ordres-reparations.index', ['type' => 'garantie']) }}"
                   class="text-xs font-bold text-yellow-700 border border-yellow-200 rounded-lg px-3 py-1.5 hover:bg-yellow-50 transition-colors flex-shrink-0">
                    Traiter →
                </a>
            </div>
        </div>
        @endif

    </div>
</div>
@endif

{{-- ── Stats principales ────────────────────────────────────── --}}
<div class="grid grid-cols-4 gap-4 mb-5">

    <a href="{{ route('ordres-reparations.index', ['statut' => 'en_cours']) }}"
       class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 hover:border-blue-300 hover:shadow-sm transition-all">
        <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-900">{{ $stats['or_en_cours'] }}</p>
            <p class="text-xs text-slate-500">OR en cours</p>
        </div>
    </a>

    <a href="{{ route('ordres-reparations.index', ['statut' => 'pret']) }}"
       class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 hover:border-green-300 hover:shadow-sm transition-all">
        <div class="w-11 h-11 bg-green-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-900">{{ $stats['or_prets'] }}</p>
            <p class="text-xs text-slate-500">Prêts à livrer</p>
        </div>
    </a>

    <a href="{{ route('factures.index', ['statut' => 'emise']) }}"
       class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 hover:border-orange-300 hover:shadow-sm transition-all">
        <div class="w-11 h-11 bg-orange-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-900">{{ $factures_non_payees_count }}</p>
            <p class="text-xs text-slate-500">Factures non payées</p>
            @if($factures_non_payees_montant > 0)
            <p class="text-xs font-bold text-orange-500 mt-0.5">{{ number_format($factures_non_payees_montant, 0, ',', ' ') }} FDJ</p>
            @endif
        </div>
    </a>

    @if(auth()->user()->isAdmin())
    <a href="{{ route('ordres-reparations.index', ['statut' => 'livre']) }}"
       class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 hover:border-slate-300 hover:shadow-sm transition-all">
        <div class="w-11 h-11 bg-slate-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-900">{{ $or_livres_mois }}</p>
            <p class="text-xs text-slate-500">Livrés ce mois</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ $or_livres_annee }} cette année</p>
        </div>
    </a>
    @else
    <a href="{{ route('clients.index') }}"
       class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 hover:border-blue-300 hover:shadow-sm transition-all">
        <div class="w-11 h-11 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-slate-900">{{ $stats['clients'] }}</p>
            <p class="text-xs text-slate-500">Clients</p>
        </div>
    </a>
    @endif

</div>

{{-- ── Vue caissier ─────────────────────────────────────────── --}}
@if(auth()->user()->isCaissier() && $or_prets_facturer->isNotEmpty())
<div class="mb-5 bg-white rounded-2xl border border-green-200 p-5">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Véhicules prêts à facturer</h3>
        </div>
        <span class="text-xs font-bold text-green-600 bg-green-100 px-2 py-0.5 rounded-full">{{ $or_prets_facturer->count() }}</span>
    </div>
    <div class="space-y-2 max-h-64 overflow-y-auto">
        @foreach($or_prets_facturer as $or)
        <a href="{{ route('factures.a-facturer') }}"
           class="flex items-center justify-between p-3 rounded-xl bg-green-50 hover:bg-green-100 transition-colors">
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-bold text-green-700">{{ $or->vehicule->immatriculation }}</span>
                    <span class="text-xs text-slate-500">{{ $or->client->nom_complet }}</span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">{{ $or->numero }} — entré le {{ $or->date_entree->format('d/m/Y') }}</p>
            </div>
            <span class="text-xs font-bold text-white bg-green-500 px-3 py-1 rounded-lg">Facturer →</span>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- ── Vue chef de garage ───────────────────────────────────── --}}
@if(auth()->user()->canManageWorkshop())
<div class="grid grid-cols-2 gap-5 mb-5">

    <div class="bg-white rounded-2xl border border-amber-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-800 text-sm">En attente de devis</h3>
                    <p class="text-xs text-amber-600">{{ $or_attente_devis->count() }} véhicule(s)</p>
                </div>
            </div>
            <a href="{{ route('ordres-reparations.index', ['statut' => 'ouvert']) }}" class="text-xs text-amber-600 font-medium hover:underline">Voir tout →</a>
        </div>
        @if($or_attente_devis->isEmpty())
        <p class="text-slate-400 text-sm text-center py-6">Aucun véhicule en attente.</p>
        @else
        <div class="space-y-2 max-h-72 overflow-y-auto">
            @foreach($or_attente_devis as $or)
            <a href="{{ route('ordres-reparations.show', $or) }}"
               class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-amber-200 hover:bg-amber-50 transition-colors">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-bold text-slate-900 font-mono">{{ $or->numero }}</span>
                        <span class="text-xs bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700 px-1.5 py-0.5 rounded-full">{{ $or->getStatutLabel() }}</span>
                    </div>
                    <p class="text-xs text-slate-500 truncate mt-0.5">{{ $or->vehicule->immatriculation }} — {{ $or->client->nom_complet }}</p>
                </div>
                <p class="text-xs text-slate-400 flex-shrink-0">{{ $or->date_entree->format('d/m') }}</p>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-purple-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-800 text-sm">Affectés à un mécanicien</h3>
                    <p class="text-xs text-purple-600">{{ $or_affectes->count() }} véhicule(s)</p>
                </div>
            </div>
            <a href="{{ route('ordres-reparations.index', ['statut' => 'en_cours']) }}" class="text-xs text-purple-600 font-medium hover:underline">Voir tout →</a>
        </div>
        @if($or_affectes->isEmpty())
        <p class="text-slate-400 text-sm text-center py-6">Aucun véhicule affecté.</p>
        @else
        <div class="space-y-2 max-h-72 overflow-y-auto">
            @foreach($or_affectes as $or)
            <a href="{{ route('ordres-reparations.show', $or) }}"
               class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition-colors">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-bold text-slate-900 font-mono">{{ $or->numero }}</span>
                        <span class="text-xs bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700 px-1.5 py-0.5 rounded-full">{{ $or->getStatutLabel() }}</span>
                    </div>
                    <p class="text-xs text-slate-500 truncate mt-0.5">{{ $or->vehicule->immatriculation }} — Méc. {{ $or->technicien?->name ?? '—' }}</p>
                </div>
                <p class="text-xs text-slate-400 flex-shrink-0">{{ $or->date_entree->format('d/m') }}</p>
            </a>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endif

{{-- ── Devis en attente + Bons de commande ─────────────────── --}}
@php $showDevis = auth()->user()->hasPermission('voir_devis') && $devis_en_attente->isNotEmpty(); @endphp
@php $showBC = auth()->user()->hasPermission('voir_bons_commande') && $bons_commande_en_attente->isNotEmpty(); @endphp

@if($showDevis || $showBC)
<div class="grid grid-cols-2 gap-5 mb-5">

    @if($showDevis)
    <div class="bg-white rounded-2xl border border-blue-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-800 text-sm">Devis envoyés</h3>
                    <p class="text-xs text-blue-600">{{ $devis_en_attente->count() }} en attente de réponse</p>
                </div>
            </div>
            <a href="{{ route('devis.index') }}" class="text-xs text-blue-600 font-medium hover:underline">Voir tout →</a>
        </div>
        <div class="space-y-2">
            @foreach($devis_en_attente as $devis)
            <a href="{{ route('devis.show', $devis) }}"
               class="flex items-center justify-between p-3 rounded-xl bg-blue-50 hover:bg-blue-100 transition-colors">
                <div>
                    <p class="text-xs font-bold text-slate-800 font-mono">{{ $devis->numero }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $devis->parent->client->nom_complet }} — {{ $devis->parent->vehicule->immatriculation }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-xs font-bold text-blue-600">{{ number_format($devis->montant_ttc, 0, ',', ' ') }} FDJ</p>
                    <p class="text-xs text-slate-400">{{ $devis->created_at->format('d/m') }}</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    @if($showBC)
    <div class="bg-white rounded-2xl border border-orange-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-800 text-sm">Bons de commande</h3>
                    <p class="text-xs text-orange-600">{{ $bons_commande_en_attente->count() }} en attente</p>
                </div>
            </div>
            <a href="{{ route('bons-commande.index') }}" class="text-xs text-orange-600 font-medium hover:underline">Voir tout →</a>
        </div>
        <div class="space-y-2">
            @foreach($bons_commande_en_attente as $bc)
            <a href="{{ route('bons-commande.show', $bc) }}"
               class="flex items-center justify-between p-3 rounded-xl bg-orange-50 hover:bg-orange-100 transition-colors">
                <div>
                    <p class="text-xs font-bold text-slate-800 font-mono">{{ $bc->numero }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">OR {{ $bc->ordreReparation->numero }}</p>
                </div>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full
                    {{ $bc->statut === 'commande' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $bc->statut === 'commande' ? 'Commandé' : 'Brouillon' }}
                </span>
            </a>
            @endforeach
        </div>
    </div>
    @elseif($showDevis)
    {{-- Colonne vide si pas de BC mais devis présent --}}
    <div></div>
    @endif

</div>
@endif

{{-- ── Interventions actives + Accès rapides ────────────────── --}}
<div class="grid grid-cols-3 gap-5">

    <div class="col-span-2 bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800">Interventions actives</h3>
            <a href="{{ route('ordres-reparations.index') }}" class="text-sm text-orange-500 hover:text-orange-600 font-medium">Voir tout →</a>
        </div>
        @if($derniers_or->isEmpty())
        <div class="text-center py-10">
            <p class="text-slate-400 text-sm">Aucune intervention en cours.</p>
        </div>
        @else
        <div class="space-y-2">
            @foreach($derniers_or as $or)
            <a href="{{ route('ordres-reparations.show', $or) }}"
               class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50 transition-colors">
                <div class="w-2 h-2 rounded-full bg-{{ $or->getStatutColor() }}-500 flex-shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-bold text-slate-900 font-mono">{{ $or->numero }}</span>
                        <span class="text-xs bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700 px-2 py-0.5 rounded-full">{{ $or->getStatutLabel() }}</span>
                        @if($or->urgence === 'tres_urgent')
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Urgent</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 truncate mt-0.5">
                        {{ $or->client->nom_complet }} — {{ $or->vehicule->immatriculation }} {{ $or->vehicule->marque }}
                    </p>
                </div>
                <p class="text-xs text-slate-400 flex-shrink-0">{{ $or->date_entree->format('d/m') }}</p>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    <div class="col-span-1 space-y-4">

        {{-- Accès rapides --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-slate-800 mb-3 text-sm">Accès rapides</h3>
            <div class="space-y-2">
                @php
                $liens = [];
                if(auth()->user()->hasPermission('gerer_clients'))
                    $liens[] = ['href' => route('clients.create'),            'label' => 'Nouveau client',     'color' => 'blue'];
                if(auth()->user()->hasPermission('gerer_vehicules'))
                    $liens[] = ['href' => route('vehicules.create'),          'label' => 'Nouveau véhicule',   'color' => 'purple'];
                if(auth()->user()->hasPermission('creer_dossiers'))
                    $liens[] = ['href' => route('reception.index'), 'label' => 'Nouvelle Réception', 'color' => 'orange'];
                if(auth()->user()->hasPermission('voir_factures'))
                    $liens[] = ['href' => route('factures.index', ['statut' => 'emise']), 'label' => 'Factures à encaisser', 'color' => 'green'];
                @endphp
                @foreach($liens as $lien)
                <a href="{{ $lien['href'] }}"
                   class="flex items-center gap-3 px-4 py-2.5 bg-{{ $lien['color'] }}-50 hover:bg-{{ $lien['color'] }}-100 border border-{{ $lien['color'] }}-100 rounded-xl transition-colors">
                    <div class="w-5 h-5 bg-{{ $lien['color'] }}-500 rounded-md flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-{{ $lien['color'] }}-700">{{ $lien['label'] }}</span>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Admin panel --}}
        @if(auth()->user()->isAdmin())
        <div class="bg-slate-800 rounded-2xl p-5 text-white">
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-3">Administration</p>
            <div class="space-y-2">
                <a href="{{ route('utilisateurs.index') }}" class="flex items-center gap-2 text-sm text-slate-300 hover:text-white transition-colors">
                    <span class="text-slate-500">→</span> Gérer les utilisateurs
                </a>
                <a href="{{ route('register') }}" class="flex items-center gap-2 text-sm text-slate-300 hover:text-white transition-colors">
                    <span class="text-slate-500">→</span> Créer un compte
                </a>
                <a href="{{ route('rapports.index') }}" class="flex items-center gap-2 text-sm text-slate-300 hover:text-white transition-colors">
                    <span class="text-slate-500">→</span> Rapports & analyses
                </a>
                <a href="{{ route('activites.index') }}" class="flex items-center gap-2 text-sm text-slate-300 hover:text-white transition-colors">
                    <span class="text-slate-500">→</span> Journal d'activité
                </a>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-700 grid grid-cols-2 gap-2">
                <div>
                    <p class="text-lg font-bold text-white">{{ $stats['clients'] }}</p>
                    <p class="text-xs text-slate-500">clients</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-white">{{ $stats['utilisateurs'] }}</p>
                    <p class="text-xs text-slate-500">utilisateurs</p>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection
