@extends('layouts.app')
@section('title', 'Aperçu de l\'import flotte')
@section('page-title', 'Aperçu de l\'import')
@section('page-subtitle', $client->nom_complet . ' — ' . $nomFichier)

@section('header-actions')
<a href="{{ route('flotte.importer.form', ['client_id' => $client->id]) }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Recharger un fichier
</a>
@endsection

@section('content')
@php
    $fmt    = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtQte = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ' '), '0'), ',');
    $groupes = $analyse['groupes'];
@endphp
<div class="max-w-5xl space-y-5">

@if($analyse['erreurs'])
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-4">
    <p class="text-sm font-bold text-red-700 mb-2">Le fichier contient {{ count($analyse['erreurs']) }} erreur(s) — rien n'a été importé. Corrigez le fichier puis rechargez-le.</p>
    <ul class="space-y-1">
        @foreach($analyse['erreurs'] as $e)
        <li class="text-sm text-red-700 flex items-start gap-2">
            <span class="w-1.5 h-1.5 mt-1 rounded-full bg-red-500 flex-shrink-0"></span>{{ $e }}
        </li>
        @endforeach
    </ul>
    <a href="{{ route('flotte.importer.form', ['client_id' => $client->id]) }}" class="inline-block mt-3 bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">Recharger le fichier corrigé</a>
</div>
@else

<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-800">
    Fichier correct : <strong>{{ count($groupes) }} bus / livraison(s)</strong>,
    {{ collect($groupes)->sum(fn ($g) => count($g['lignes'])) }} ligne(s).
    À la confirmation, un bon de commande par bus (pièces) part au magasin ; chaque bus aura ensuite sa propre facture.
</div>

@if($analyse['avertissements'])
<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
    <p class="text-sm font-bold text-amber-700 mb-1">À vérifier</p>
    <ul class="space-y-1">
        @foreach($analyse['avertissements'] as $a)
        <li class="text-sm text-amber-700">• {{ $a }}</li>
        @endforeach
    </ul>
</div>
@endif

@foreach($groupes as $groupe)
@php
    $totalConnu = collect($groupe['lignes'])->sum(fn ($l) => $l['prix_unitaire'] === null ? 0 : $l['quantite'] * $l['prix_unitaire'] * (1 - $l['remise'] / 100));
    $prixManquant = collect($groupe['lignes'])->contains(fn ($l) => $l['prix_unitaire'] === null);
@endphp
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3">
            <span class="font-mono font-bold text-slate-900">{{ $groupe['immatriculation'] }}</span>
            <span class="text-xs text-slate-500">{{ $groupe['designation_vehicule'] }}</span>
            <span class="text-xs text-slate-500">— {{ \Carbon\Carbon::parse($groupe['date'])->format('d/m/Y') }}</span>
            @if($groupe['kilometrage'])<span class="text-xs text-slate-500">— {{ $fmt($groupe['kilometrage']) }} km</span>@endif
        </div>
        <span class="text-sm font-bold text-slate-700">{{ $fmt($totalConnu) }} FDJ HT{{ $prixManquant ? ' + prix magasin' : '' }}</span>
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-gray-100">
            @foreach($groupe['lignes'] as $l)
            <tr>
                <td class="px-5 py-2 w-32"><span class="px-2 py-0.5 rounded text-xs font-medium {{ $l['type'] === 'piece' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' }}">{{ $l['type'] === 'piece' ? 'Pièce' : "Main d'œuvre" }}</span></td>
                <td class="px-5 py-2 font-mono text-xs text-slate-500 w-32">{{ $l['reference'] ?? '—' }}</td>
                <td class="px-5 py-2 text-slate-700">{{ $l['designation'] }}</td>
                <td class="px-5 py-2 text-right text-slate-600 w-20">{{ $fmtQte($l['quantite']) }}</td>
                <td class="px-5 py-2 text-right text-slate-600 w-32">{{ $l['prix_unitaire'] === null ? 'prix magasin' : $fmt($l['prix_unitaire']) }}</td>
                <td class="px-5 py-2 text-right text-slate-500 w-20">{{ $l['remise'] > 0 ? $fmtQte($l['remise']) . ' %' : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

<form method="POST" action="{{ route('flotte.importer') }}" class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
    @csrf
    <input type="hidden" name="jeton" value="{{ $jeton }}">
    @if($analyse['avertissements'])
    <label class="flex items-start gap-2 text-sm text-amber-800">
        <input type="checkbox" name="confirmer_doublons" value="1" required class="mt-0.5 rounded border-gray-300">
        J'ai vérifié les avertissements ci-dessus et je veux quand même importer ce fichier.
    </label>
    @endif
    <div class="flex gap-3">
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-6 rounded-xl text-sm transition-colors">
            ✓ Confirmer l'import et envoyer les BC au magasin
        </button>
        <a href="{{ route('flotte.index') }}" class="py-2.5 px-4 text-sm text-slate-600 hover:text-slate-900">Abandonner</a>
    </div>
</form>

@endif
</div>
@endsection
