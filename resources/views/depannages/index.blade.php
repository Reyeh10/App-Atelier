@extends('layouts.app')
@section('title', 'Dépannages')
@section('page-title', 'Dépannages')
@section('page-subtitle', $depannages->total() . ' dépannage(s) — pièces et main-d\'œuvre sans réception ni devis')

@section('header-actions')
@if(auth()->user()->hasPermission('creer_depannages'))
<a href="{{ route('depannages.create') }}"
   class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    Nouveau dépannage
</a>
@endif
@endsection

@section('content')

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700 mb-5">{{ session('success') }}</div>
@endif

<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="recherche" value="{{ $recherche }}" placeholder="N° dépannage, client, téléphone, immatriculation..."
           class="flex-1 min-w-0 max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
    <button type="submit" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">Rechercher</button>
    @if($recherche !== '')
    <a href="{{ route('depannages.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-slate-600 hover:bg-gray-50 transition-colors">Réinitialiser</a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    @if($depannages->isEmpty())
    <div class="flex flex-col items-center justify-center py-20">
        <p class="text-slate-600 font-medium">Aucun dépannage</p>
    </div>
    @else
    <div class="overflow-x-auto">
    <table class="w-full">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <th class="text-left px-6 py-3">N° Dépannage</th>
                <th class="text-left px-6 py-3">Client / Véhicule</th>
                <th class="text-left px-6 py-3">Date</th>
                <th class="text-left px-6 py-3">BC</th>
                <th class="text-right px-6 py-3">Montant HT</th>
                <th class="text-left px-6 py-3">Statut</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($depannages as $d)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                    <a href="{{ route('depannages.show', $d) }}" class="text-sm font-bold text-slate-900 font-mono hover:text-orange-600 transition-colors">{{ $d->numero }}</a>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm font-medium text-slate-800">{{ $d->client->nom_complet }}</p>
                    <p class="text-xs font-mono text-slate-500">{{ $d->vehicule->immatriculation }}
                        @if($d->leasing)<span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-sans font-semibold">Leasing</span>@endif
                    </p>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">{{ $d->date_depannage->format('d/m/Y') }}</td>
                <td class="px-6 py-4 text-xs font-mono text-slate-600">{{ $d->bonCommande?->numero ?? '—' }}</td>
                <td class="px-6 py-4 text-right text-sm font-semibold text-slate-800">{{ number_format($d->montantHt(), 0, ',', ' ') }} FDJ</td>
                <td class="px-6 py-4">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $d->getStatutColor() }}-100 text-{{ $d->getStatutColor() }}-700">{{ $d->getStatutLabel() }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">{{ $depannages->links() }}</div>
    @endif
</div>
@endsection
