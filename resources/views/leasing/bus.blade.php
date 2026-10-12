@extends('layouts.app')
@section('title', $g['libelle'])
@section('page-title', $g['libelle'])
@section('page-subtitle', 'Bus réparés dans un autre garage : bons de commande directs, pas de facture — forfait bus + pièces dans le récap de chaque mois')

@section('header-actions')
<div class="flex gap-2">
    <a href="{{ route($g['route'] . '.bc.create') }}"
       class="flex items-center gap-2 text-sm bg-orange-500 hover:bg-orange-600 text-white rounded-lg px-3 py-2 transition-colors">
        + Bon de commande bus
    </a>
</div>
@endsection

@section('content')
<div class="max-w-7xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

{{-- Import de la liste des bus (administrateur) --}}
@if($estAdmin)
<form method="POST" action="{{ route($g['route'] . '.parc.apercu') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
    @csrf
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-sm font-bold text-slate-700">Importer la liste des bus</h3>
        <a href="{{ route($g['route'] . '.parc.modele') }}" class="text-xs text-orange-500 hover:underline font-semibold">⬇ Modèle Excel (Immatriculation, Marque, Modèle, N° de châssis)</a>
    </div>
    <div class="grid grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Client propriétaire des bus</label>
            <select name="client_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(old('client_id', $clientParDefaut) == $c->id)>{{ $c->nom_complet }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fichier Excel ou CSV</label>
            <input type="file" name="fichier" required accept=".xlsx,.xls,.csv"
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded-xl transition-colors">
            Vérifier le fichier →
        </button>
    </div>
    <p class="text-xs text-slate-400">Rien n'est enregistré à cette étape : un aperçu s'affiche d'abord, avec les erreurs éventuelles. Les bus absents des véhicules sont créés au nom du client choisi.</p>
</form>
@endif

<div class="grid grid-cols-2 gap-5">

    {{-- Liste des bus --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-sm font-bold text-slate-700">{{ $total }} {{ $g['liste'] }}</h3>
            <form method="GET" action="{{ route($g['route'] . '.index') }}" class="flex gap-2 items-center">
                <input type="text" name="recherche" value="{{ request('recherche') }}" placeholder="Rechercher un bus"
                       class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                @if(request('recherche'))
                <a href="{{ route($g['route'] . '.index') }}" class="text-xs text-slate-400 hover:text-slate-600">✕ Effacer</a>
                @endif
            </form>
        </div>
        @if($bus->isEmpty())
        <p class="px-5 py-6 text-sm text-slate-400">Aucun bus{{ request('recherche') ? ' trouvé' : ' pour le moment — importez la liste des bus' }}.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Immatriculation</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Bus</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($bus as $b)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono font-bold text-slate-800">{{ $b->immatriculation }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ trim(($b->fiche?->marque ?? '') . ' ' . ($b->fiche?->modele ?? '')) ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            <a href="{{ route($g['route'] . '.bc.create', ['bus' => $b->id]) }}" class="text-xs text-orange-500 hover:underline font-semibold">+ BC</a>
                            @if($estAdmin)
                            <form method="POST" action="{{ route($g['route'] . '.destroy', $b) }}" class="inline ml-3" onsubmit="return confirm('Retirer le bus {{ $b->immatriculation }} de la liste ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-semibold">Retirer</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Derniers bons de commande des bus --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="text-sm font-bold text-slate-700">Derniers bons de commande des bus</h3>
        </div>
        @if($bcs->isEmpty())
        <p class="px-5 py-6 text-sm text-slate-400">Aucun bon de commande pour le moment.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">N° BC</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Bus</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Pièces</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($bcs as $bc)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5"><a href="{{ route('bons-commande.show', $bc) }}" class="font-mono font-bold text-orange-500 hover:underline">{{ $bc->numero }}</a></td>
                        <td class="px-4 py-2.5 font-mono text-slate-700">{{ $bc->vehiculeDirect?->immatriculation ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $bc->lignes->count() }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                {{ $bc->statut === 'recu' ? 'bg-green-100 text-green-700' : ($bc->statut === 'annule' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                                {{ ['en_attente' => 'En attente', 'commande' => 'Commandé', 'recu' => 'Reçu', 'annule' => 'Annulé'][$bc->statut] ?? $bc->statut }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $bc->created_at?->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

<p class="text-xs text-slate-400">Chaque mois, le <a href="{{ route($g['route'] . '.recaps') }}" class="text-orange-500 hover:underline">récap « {{ $g['libelle'] }} »</a> reprend pour chaque bus le forfait ({{ number_format($forfait, 0, ',', ' ') }} FDJ) + les pièces de ses bons de commande reçus. Ces bons de commande ne sont jamais facturés.</p>

</div>
@endsection
