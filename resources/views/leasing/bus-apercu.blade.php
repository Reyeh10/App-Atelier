@extends('layouts.app')
@section('title', $mode === 'parc' ? 'Aperçu — liste des bus' : 'Aperçu — bons de commande bus')
@section('page-title', $mode === 'parc' ? 'Aperçu de la liste des bus' : 'Aperçu des bons de commande bus')
@section('page-subtitle', 'Fichier « ' . $nomFichier . ' » — rien n\'est encore enregistré')

@section('header-actions')
<a href="{{ $mode === 'parc' ? route($g['route'] . '.index') : route($g['route'] . '.bc.create') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour
</a>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

@if($analyse['erreurs'])
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-4">
    <p class="text-sm font-bold text-red-700 mb-2">{{ count($analyse['erreurs']) }} erreur(s) — corrigez le fichier puis rechargez-le. Rien n'a été importé.</p>
    <ul class="space-y-1">
        @foreach($analyse['erreurs'] as $e)
        <li class="text-sm text-red-700">• {{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(! empty($analyse['avertissements']))
<div class="bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-3 space-y-1">
    @foreach($analyse['avertissements'] as $a)
    <p class="text-sm text-yellow-800">⚠ {{ $a }}</p>
    @endforeach
</div>
@endif

@if($mode === 'parc' && $analyse['bus'])
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700">{{ count($analyse['bus']) }} véhicule(s) — créés au nom de {{ $client->nom_complet }}</h3>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Immatriculation</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Marque / modèle</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">N° de châssis</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Mise en circulation</th>
                @if(collect($analyse['bus'])->whereNotNull('affectation')->isNotEmpty())
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Affecté à</th>
                @endif
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Ce qui sera fait</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($analyse['bus'] as $b)
            <tr>
                <td class="px-4 py-2.5 font-mono font-bold text-slate-800">{{ $b['immatriculation'] }}</td>
                <td class="px-4 py-2.5 text-slate-600">{{ trim($b['marque'] . ' ' . $b['modele']) }}</td>
                <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $b['vin'] ?: '—' }}</td>
                <td class="px-4 py-2.5 text-xs text-slate-500">{{ ! empty($b['date']) ? \Carbon\Carbon::parse($b['date'])->format('d/m/Y') : '—' }}</td>
                @if(collect($analyse['bus'])->whereNotNull('affectation')->isNotEmpty())
                <td class="px-4 py-2.5 text-slate-700">{{ $b['affectation'] ?? '—' }}</td>
                @endif
                <td class="px-4 py-2.5 text-xs">
                    @if($b['action'] === 'creer')<span class="text-green-700 font-semibold">Nouveau véhicule + ajouté à la liste</span>
                    @elseif($b['action'] === 'ajouter')<span class="text-blue-700 font-semibold">Véhicule existant, ajouté à la liste</span>
                    @elseif($b['action'] === 'completer')<span class="text-green-700 font-semibold">Déjà dans la liste — fiche véhicule créée</span>
                    @else<span class="text-slate-400">Déjà dans la liste — ignoré</span>@endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($mode === 'bc' && $analyse['groupes'])
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700">{{ count($analyse['groupes']) }} bon(s) de commande — un par bus</h3>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($analyse['groupes'] as $grp)
        <div class="px-5 py-3">
            <p class="font-mono font-bold text-slate-800 mb-1">{{ $grp['immatriculation'] }} <span class="font-sans font-normal text-xs text-slate-400">— {{ count($grp['lignes']) }} pièce(s)</span></p>
            <ul class="text-sm text-slate-600 space-y-0.5">
                @foreach($grp['lignes'] as $l)
                <li>• {{ rtrim(rtrim(number_format($l['quantite'], 2, ',', ' '), '0'), ',') }} × {{ $l['designation'] }}{{ $l['reference'] ? ' (réf. ' . $l['reference'] . ')' : '' }}</li>
                @endforeach
            </ul>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($jeton)
<form method="POST" action="{{ $mode === 'parc' ? route($g['route'] . '.parc.importer') : route($g['route'] . '.bc.importer') }}">
    @csrf
    <input type="hidden" name="jeton" value="{{ $jeton }}">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-6 rounded-xl text-sm transition-colors">
        {{ $mode === 'parc' ? 'Importer la liste' : 'Importer : créer les bons de commande et les envoyer au magasin' }}
    </button>
</form>
@endif

</div>
@endsection
