@extends('layouts.app')
@section('title', $depannage->numero)
@section('page-title', 'Dépannage ' . $depannage->numero)
@section('page-subtitle', $depannage->client->nom_complet . ' — ' . $depannage->vehicule->immatriculation)

@section('header-actions')
<div class="flex gap-2">
    @if($depannage->facture)
    <a href="{{ route('factures.show', $depannage->facture) }}"
       class="flex items-center gap-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg px-3 py-2 transition-colors">
        Facture {{ $depannage->facture->numero }} →
    </a>
    @endif
    @if($depannage->estAFacturer() && auth()->user()->hasPermission('creer_factures'))
    <form method="POST" action="{{ route('depannages.facturer', $depannage) }}"
          onsubmit="return confirm('Créer la facture du dépannage {{ $depannage->numero }} ?')">
        @csrf
        <button type="submit" class="flex items-center gap-2 text-sm bg-green-500 hover:bg-green-600 text-white font-bold rounded-lg px-3 py-2 transition-colors">
            Créer la facture
        </button>
    </form>
    @endif
    <a href="{{ route('depannages.index') }}"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Dépannages
    </a>
</div>
@endsection

@section('content')
@php
    $bc = $depannage->bonCommande;
    $raison = $depannage->raisonNonFacturable();
@endphp
<div class="max-w-4xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

@if($depannage->leasing)
<div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-700">
    Véhicule en <strong>leasing</strong> : pas de facture. Les pièces vont au récap de sa liste une fois le BC entièrement reçu (BT) ;
    la main-d'œuvre est couverte par le forfait mensuel.
</div>
@elseif(! $depannage->estFacture() && $raison)
<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">{{ $raison }}</div>
@endif

<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <div class="grid grid-cols-4 gap-4 text-sm">
        <div>
            <p class="text-xs text-slate-400 mb-1">Client</p>
            <p class="font-semibold text-slate-800">{{ $depannage->client->nom_complet }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Véhicule</p>
            <p class="font-mono font-bold text-slate-800">{{ $depannage->vehicule->immatriculation }}</p>
            <p class="text-xs text-slate-500">{{ $depannage->vehicule->marque }} {{ $depannage->vehicule->modele }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Date</p>
            <p class="text-slate-800">{{ $depannage->date_depannage->format('d/m/Y') }}</p>
            <p class="text-xs text-slate-500">par {{ $depannage->creePar?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Statut</p>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $depannage->getStatutColor() }}-100 text-{{ $depannage->getStatutColor() }}-700">{{ $depannage->getStatutLabel() }}</span>
        </div>
        @if($depannage->description)
        <div class="col-span-4">
            <p class="text-xs text-slate-400 mb-1">Description</p>
            <p class="text-slate-700">{{ $depannage->description }}</p>
        </div>
        @endif
    </div>
</div>

{{-- Pièces (lignes du BC) --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Pièces</h3>
        @if($bc)
        <a href="{{ route('bons-commande.show', $bc) }}" class="text-xs font-mono text-orange-500 hover:underline">
            {{ $bc->numero }} — {{ $bc->getStatutLabel() }}{{ $bc->bonTransfert ? ' — ' . $bc->bonTransfert->numero : '' }}
        </a>
        @endif
    </div>
    @if($bc)
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-xs text-slate-500">
                <th class="px-6 py-2 text-left">Désignation</th>
                <th class="px-3 py-2 text-left">Référence</th>
                <th class="px-3 py-2 text-right">Qté</th>
                <th class="px-3 py-2 text-right">Prix HT</th>
                <th class="px-6 py-2 text-right">Montant HT</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($bc->lignes as $l)
            <tr>
                <td class="px-6 py-2 text-slate-800">{{ $l->designation }}</td>
                <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ $l->reference ?: '—' }}</td>
                <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($l->quantite, 2, ',', ' '), '0'), ',') }}</td>
                <td class="px-3 py-2 text-right">{{ $l->prixUnitaireAffiche() !== null ? number_format($l->prixUnitaireAffiche(), 0, ',', ' ') : 'en attente du magasin' }}</td>
                <td class="px-6 py-2 text-right font-semibold">{{ $l->montantAffiche() !== null ? number_format($l->montantAffiche(), 0, ',', ' ') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="px-6 py-4 text-sm text-slate-400">Aucune pièce.</p>
    @endif
</div>

{{-- Main-d'œuvre --}}
@unless($depannage->leasing)
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Main-d'œuvre</h3>
    </div>
    @if($depannage->lignes->isNotEmpty())
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-xs text-slate-500">
                <th class="px-6 py-2 text-left">Désignation</th>
                <th class="px-3 py-2 text-right">Heures</th>
                <th class="px-3 py-2 text-right">Prix HT</th>
                <th class="px-6 py-2 text-right">Montant HT</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($depannage->lignes as $l)
            <tr>
                <td class="px-6 py-2 text-slate-800">{{ $l->designation }}</td>
                <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($l->quantite, 2, ',', ' '), '0'), ',') }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($l->prix_unitaire, 0, ',', ' ') }}</td>
                <td class="px-6 py-2 text-right font-semibold">{{ number_format($l->total(), 0, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="px-6 py-4 text-sm text-slate-400">Aucune main-d'œuvre.</p>
    @endif
</div>
@endunless

<div class="flex justify-end">
    <div class="bg-white rounded-2xl border border-gray-200 px-6 py-4 text-sm">
        <span class="text-slate-500">Total HT estimé :</span>
        <span class="font-bold text-slate-900 ml-2">{{ number_format($depannage->montantHt(), 0, ',', ' ') }} FDJ</span>
    </div>
</div>

</div>
@endsection
