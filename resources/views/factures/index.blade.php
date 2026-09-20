@extends('layouts.app')
@section('title', 'Factures')
@section('page-title', 'Factures')
@section('page-subtitle', 'Suivi des encaissements')

@section('header-actions')
<a href="{{ route('factures.bons-commande-clients') }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    📋 Bons de commande clients
</a>
@endsection

@section('content')
<div class="space-y-4">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl border border-blue-200 p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-black text-blue-600">{{ number_format($totalEmises, 0, ',', ' ') }} FDJ</p>
            <p class="text-sm text-slate-500">{{ $nbEmises }} facture(s) non payée(s)</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-green-200 p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-black text-green-600">{{ number_format($totalPayees, 0, ',', ' ') }} FDJ</p>
            <p class="text-sm text-slate-500">{{ $nbPayees }} facture(s) payée(s)</p>
        </div>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" action="{{ route('factures.index') }}" class="bg-white rounded-2xl border border-gray-200 p-4">
    <div class="flex flex-wrap gap-3 items-end">

        {{-- Statut --}}
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Statut</label>
            <div class="flex gap-1.5">
                @foreach(['' => 'Toutes', 'emise' => 'Non payées', 'payee' => 'Payées'] as $val => $label)
                <a href="{{ route('factures.index', array_merge(request()->except('statut','page'), $val ? ['statut' => $val] : [])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all
                          {{ request('statut', '') === $val
                             ? ($val === 'payee' ? 'border-green-500 bg-green-50 text-green-700' : ($val === 'emise' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-orange-500 bg-orange-50 text-orange-700'))
                             : 'border-gray-200 text-slate-600 hover:border-gray-300' }}">
                    {{ $label }}
                </a>
                @endforeach
            </div>
        </div>

        <div class="w-px h-8 bg-gray-200 self-center"></div>

        {{-- Période --}}
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Période</label>
            <div class="flex gap-1.5">
                @foreach(['' => 'Toutes', 'jour' => 'Jour', 'mois' => 'Mois', 'annee' => 'Année'] as $val => $label)
                <button type="submit" name="periode" value="{{ $val }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all
                               {{ request('periode', '') === $val ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-slate-600 hover:border-gray-300' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Date de référence --}}
        @if(request('periode'))
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                @if(request('periode') === 'jour') Jour
                @elseif(request('periode') === 'mois') Mois
                @else Année
                @endif
            </label>
            @if(request('periode') === 'annee')
            <input type="number" name="date_ref" value="{{ request('date_ref', now()->year) }}"
                   min="2020" max="2099" placeholder="2026"
                   class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 w-24">
            @elseif(request('periode') === 'mois')
            <input type="month" name="date_ref" value="{{ request('date_ref', now()->format('Y-m')) }}"
                   class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            @else
            <input type="date" name="date_ref" value="{{ request('date_ref', now()->format('Y-m-d')) }}"
                   class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            @endif
            <input type="hidden" name="statut" value="{{ request('statut') }}">
        </div>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-1.5 rounded-lg transition-colors self-end">
            Filtrer
        </button>
        @endif

        @if(request()->hasAny(['statut', 'periode', 'date_ref']))
        <a href="{{ route('factures.index') }}" class="text-xs text-slate-400 hover:text-slate-600 self-end pb-1.5">
            ✕ Effacer
        </a>
        @endif

    </div>
</form>

{{-- Tableau --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Numéro</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">OR</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Émise le</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Mode</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">TTC</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Statut</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Payée le</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($factures as $facture)
                <tr class="hover:bg-gray-50 {{ $facture->statut === 'payee' ? 'opacity-70' : '' }}">
                    <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ $facture->numero }}</td>
                    <td class="px-5 py-3 text-slate-700 font-medium">
                        {{ $facture->payeur_nom }}
                        @if($facture->marque_garantie_id)
                        <span class="ml-1 text-xs bg-indigo-100 text-indigo-700 font-semibold px-1.5 py-0.5 rounded">Garantie</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <a href="{{ route('ordres-reparations.show', $facture->ordreReparation) }}"
                           class="font-mono text-orange-500 hover:underline text-xs">
                            {{ $facture->ordreReparation->numero }}
                        </a>
                    </td>
                    <td class="px-5 py-3 text-slate-500">{{ $facture->date_emission->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-slate-500 text-xs">{{ $facture->getModePaiementLabel() }}</td>
                    <td class="px-5 py-3 text-right font-bold text-slate-800">{{ number_format($facture->montant_ttc, 0, ',', ' ') }} FDJ</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold
                            @if($facture->statut === 'payee') bg-green-100 text-green-700
                            @elseif($facture->statut === 'emise') bg-blue-100 text-blue-700
                            @else bg-gray-100 text-gray-600 @endif">
                            {{ $facture->getStatutLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-slate-400 text-xs">
                        {{ $facture->date_paiement ? $facture->date_paiement->format('d/m/Y') : '—' }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        @if($facture->statut === 'emise' && auth()->user()->hasPermission('encaisser_factures'))
                        <a href="{{ route('factures.show', $facture) }}"
                           class="text-xs bg-green-500 hover:bg-green-600 text-white font-bold px-3 py-1 rounded-lg transition-colors">
                            Encaisser
                        </a>
                        @else
                        <a href="{{ route('factures.show', $facture) }}"
                           class="text-xs text-orange-500 hover:underline font-medium">Voir</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-5 py-12 text-center text-slate-400">Aucune facture pour cette sélection.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($factures->hasPages())
    <div class="px-5 py-3 border-t border-gray-200">{{ $factures->links() }}</div>
    @endif
</div>

</div>
@endsection
