@extends('layouts.app')
@section('title', 'Pièces par bus')
@section('page-title', 'Pièces et main-d\'œuvre par bus')
@section('page-subtitle', 'Ce qui a été facturé pour chaque bus d\'une société, sur une période')

@section('content')
@php
    $fmt    = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtQte = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ' '), '0'), ',');
@endphp
<div class="max-w-7xl space-y-5">

<form method="GET" action="{{ route('flotte.rapport') }}" class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-56">
        <label class="block text-xs font-medium text-slate-600 mb-1">Société</label>
        <select name="client_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white">
            <option value="">— Choisir —</option>
            @foreach($clients as $c)
            <option value="{{ $c->id }}" @selected($client?->id === $c->id)>{{ $c->nom_complet }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
        <input type="date" name="date_debut" value="{{ $filtres['date_debut'] }}" class="px-3 py-2 border border-gray-300 rounded-xl text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
        <input type="date" name="date_fin" value="{{ $filtres['date_fin'] }}" class="px-3 py-2 border border-gray-300 rounded-xl text-sm">
    </div>
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded-xl transition-colors">Afficher</button>
</form>

@if($clients->isEmpty())
<p class="text-sm text-slate-400">Aucune société n'a encore de livraison flotte importée.</p>
@elseif($client)
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700">{{ $client->nom_complet }} — du {{ \Carbon\Carbon::parse($filtres['date_debut'])->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($filtres['date_fin'])->format('d/m/Y') }}</h3>
        <p class="text-xs text-slate-500 mt-0.5">Montants HT, par date de facture. Livraisons flotte et passages à l'atelier (OR) confondus ; factures annulées par avoir exclues. Cliquez sur un bus pour le détail pièce par pièce.</p>
    </div>
    @if($parBus->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">Aucune facture sur cette période.</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Bus</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Factures</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Nb pièces</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Pièces HT</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Main-d'œuvre HT</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Autres HT</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Total HT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($parBus as $bus)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        @if($bus['vehicule'])
                        <a href="{{ route('vehicules.show', ['vehicule' => $bus['vehicule'], 'date_debut' => $filtres['date_debut'], 'date_fin' => $filtres['date_fin']]) }}#pieces" class="font-mono font-bold text-orange-500 hover:underline">{{ $bus['vehicule']->immatriculation }}</a>
                        <span class="text-xs text-slate-500 ml-1">{{ $bus['vehicule']->marque }} {{ $bus['vehicule']->modele }}</span>
                        @else — @endif
                    </td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ $bus['nb_factures'] }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ $fmtQte($bus['qte_pieces']) }}</td>
                    <td class="px-5 py-3 text-right text-slate-700">{{ $fmt($bus['pieces_ht']) }}</td>
                    <td class="px-5 py-3 text-right text-slate-700">{{ $fmt($bus['mo_ht']) }}</td>
                    <td class="px-5 py-3 text-right text-slate-500">{{ $bus['autres_ht'] > 0 ? $fmt($bus['autres_ht']) : '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-slate-900">{{ $fmt($bus['total_ht']) }} FDJ</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold">
                    <td class="px-5 py-3 text-slate-700">Total ({{ $parBus->count() }} bus)</td>
                    <td class="px-5 py-3 text-right">{{ $parBus->sum('nb_factures') }}</td>
                    <td class="px-5 py-3 text-right">{{ $fmtQte($parBus->sum('qte_pieces')) }}</td>
                    <td class="px-5 py-3 text-right">{{ $fmt($parBus->sum('pieces_ht')) }}</td>
                    <td class="px-5 py-3 text-right">{{ $fmt($parBus->sum('mo_ht')) }}</td>
                    <td class="px-5 py-3 text-right">{{ $fmt($parBus->sum('autres_ht')) }}</td>
                    <td class="px-5 py-3 text-right text-orange-500">{{ $fmt($parBus->sum('total_ht')) }} FDJ</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>
@endif

</div>
@endsection
