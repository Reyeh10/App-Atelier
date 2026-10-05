@extends('layouts.app')
@section('title', $import->numero)
@section('page-title', 'Import flotte ' . $import->numero)
@section('page-subtitle', $import->client?->nom_complet . ' — importé le ' . $import->created_at->format('d/m/Y H:i') . ($import->creePar ? ' par ' . $import->creePar->name : ''))

@section('header-actions')
<div class="flex gap-2">
    @if($import->fichier_url)
    <a href="{{ $import->fichier_url }}" class="flex items-center gap-2 text-sm border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        ⬇ Fichier importé
    </a>
    @endif
    <a href="{{ route('flotte.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Livraisons flotte
    </a>
</div>
@endsection

@section('content')
@php
    $fmt    = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtQte = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ' '), '0'), ',');
@endphp
<div class="max-w-5xl space-y-5">

@if($import->notes)
<div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-slate-600">{{ $import->notes }}</div>
@endif

@foreach($import->livraisons as $livraison)
@php
    $statut  = $livraison->statut();
    $facture = $livraison->factureActive();
    $bc      = $livraison->bonCommande;
@endphp
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            @if($livraison->vehicule)
            <a href="{{ route('vehicules.show', $livraison->vehicule) }}#pieces" class="font-mono font-bold text-slate-900 hover:underline">{{ $livraison->vehicule->immatriculation }}</a>
            @endif
            <span class="text-xs text-slate-500">{{ $livraison->date_livraison->format('d/m/Y') }}</span>
            @if($livraison->kilometrage)<span class="text-xs text-slate-500">{{ $fmt($livraison->kilometrage) }} km</span>@endif
            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $livraison->getStatutClasses() }}">{{ $livraison->getStatutLabel() }}</span>
        </div>
        <div class="flex items-center gap-3 text-xs">
            @if($bc)
            <span class="text-slate-500">BC <a href="{{ route('bons-commande.show', $bc) }}" class="font-mono font-bold text-orange-500 hover:underline">{{ $bc->numero }}</a></span>
            <span class="text-slate-500">BT <span class="font-mono font-bold text-slate-700">{{ $bc->bonTransfert?->numero ?? 'en attente' }}</span></span>
            @endif
            @if($facture)
            <a href="{{ route('factures.show', $facture) }}" class="font-mono font-bold text-green-600 hover:underline">Facture {{ $facture->numero }}</a>
            @elseif($statut === 'a_facturer' && auth()->user()->hasPermission('creer_factures'))
            <a href="{{ route('flotte.facturer.form', $livraison) }}" class="inline-flex bg-green-500 hover:bg-green-600 text-white font-bold px-3 py-1.5 rounded-lg transition-colors">Facturer</a>
            @endif
        </div>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100">
                <th class="px-5 py-2 text-left text-xs font-semibold text-slate-400">Type</th>
                <th class="px-5 py-2 text-left text-xs font-semibold text-slate-400">Référence</th>
                <th class="px-5 py-2 text-left text-xs font-semibold text-slate-400">Désignation</th>
                <th class="px-5 py-2 text-right text-xs font-semibold text-slate-400">Qté demandée</th>
                <th class="px-5 py-2 text-left text-xs font-semibold text-slate-400">Magasin</th>
                <th class="px-5 py-2 text-right text-xs font-semibold text-slate-400">Prix fichier</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($livraison->lignes as $ligne)
            @php $lbc = $bc?->lignes->firstWhere('id', $ligne->ligne_bon_commande_id); @endphp
            <tr>
                <td class="px-5 py-2"><span class="px-2 py-0.5 rounded text-xs font-medium {{ $ligne->type === 'piece' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' }}">{{ $ligne->getTypeLabel() }}</span></td>
                <td class="px-5 py-2 font-mono text-xs text-slate-500">{{ $ligne->reference ?? '—' }}</td>
                <td class="px-5 py-2 text-slate-700">{{ $ligne->designation }}</td>
                <td class="px-5 py-2 text-right text-slate-600">{{ $fmtQte($ligne->quantite) }}</td>
                <td class="px-5 py-2 text-xs">
                    @if($ligne->type !== 'piece')
                    <span class="text-slate-400">—</span>
                    @elseif(! $lbc || is_null($lbc->disponible))
                    <span class="text-slate-400">En attente</span>
                    @elseif($lbc->disponible)
                    <span class="text-green-600 font-medium">Disponible{{ $lbc->prix_unitaire ? ' — ' . $fmt($lbc->prix_unitaire) . ' FDJ' : '' }}</span>
                    @else
                    <span class="text-red-600 font-medium">Indisponible</span>
                    @endif
                </td>
                <td class="px-5 py-2 text-right text-slate-600">{{ $ligne->prix_unitaire !== null ? $fmt($ligne->prix_unitaire) : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

</div>
@endsection
