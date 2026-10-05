@extends('layouts.app')
@section('title', 'Rapports & Analyses')
@section('page-title', 'Rapports & Analyses')
@section('page-subtitle', 'Performance de l\'atelier')

@section('header-actions')
<form method="GET" action="{{ route('rapports.index') }}" class="flex items-center gap-2">
    @foreach(['semaine' => 'Cette semaine', 'mois' => 'Ce mois', 'trimestre' => '3 derniers mois', 'annee' => 'Cette année', 'tout' => 'Tout'] as $val => $label)
    <button type="submit" name="periode" value="{{ $val }}"
            class="px-3 py-2 text-xs font-medium rounded-lg transition-colors
                   {{ $periode === $val ? 'bg-orange-500 text-white' : 'bg-white border border-gray-300 text-slate-600 hover:border-orange-400' }}">
        {{ $label }}
    </button>
    @endforeach
</form>
@endsection

@section('content')
<div class="space-y-6">

{{-- ── Stats globales ─────────────────────────────────── --}}
<div class="grid grid-cols-5 gap-4">
    @php
    $statsCards = [
        ['label' => 'Total ORs', 'value' => $total, 'color' => 'slate', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['label' => 'En cours', 'value' => $en_cours, 'color' => 'blue', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Terminés', 'value' => $termines, 'color' => 'green', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Annulés', 'value' => $annules, 'color' => 'red', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Garanties', 'value' => $garanties, 'color' => 'yellow', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
    ];
    @endphp
    @foreach($statsCards as $card)
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-9 h-9 bg-{{ $card['color'] }}-100 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-{{ $card['color'] }}-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-900">{{ $card['value'] }}</p>
        <p class="text-xs text-slate-500 mt-0.5">{{ $card['label'] }}</p>
        @if($total > 0 && $card['label'] !== 'Total ORs')
        <div class="mt-2 h-1 bg-gray-100 rounded-full">
            <div class="h-1 bg-{{ $card['color'] }}-400 rounded-full" style="width: {{ min(100, round($card['value'] / $total * 100)) }}%"></div>
        </div>
        <p class="text-xs text-slate-400 mt-1">{{ $total > 0 ? round($card['value'] / $total * 100) : 0 }}%</p>
        @endif
    </div>
    @endforeach
</div>

{{-- ── Section financière (admin uniquement) ───────────── --}}
@if(auth()->user()->isAdmin())
<div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl border border-green-200 p-6">
    <h3 class="text-sm font-bold text-green-800 uppercase tracking-wider mb-4 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Chiffre d'affaires
    </h3>
    <div class="grid grid-cols-3 gap-6">

        {{-- CA encaissé --}}
        <div class="bg-white rounded-2xl border border-green-200 p-5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-green-600 uppercase tracking-wider">Encaissé</span>
                <span class="text-xs bg-green-100 text-green-700 font-bold px-2 py-0.5 rounded-full">{{ $nbPayees }} facture(s)</span>
            </div>
            <p class="text-3xl font-black text-green-600 mt-2">{{ number_format($caEncaisse, 0, ',', ' ') }}</p>
            <p class="text-sm text-green-500 font-medium">FDJ</p>
            @if($caTotal > 0)
            <div class="mt-3 h-2 bg-green-100 rounded-full overflow-hidden">
                <div class="h-full bg-green-400 rounded-full" style="width: {{ round($caEncaisse / $caTotal * 100) }}%"></div>
            </div>
            <p class="text-xs text-green-500 mt-1">{{ round($caEncaisse / $caTotal * 100) }}% du total</p>
            @endif
        </div>

        {{-- CA en attente --}}
        <div class="bg-white rounded-2xl border border-blue-200 p-5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">En attente</span>
                <span class="text-xs bg-blue-100 text-blue-700 font-bold px-2 py-0.5 rounded-full">{{ $nbEmises }} facture(s)</span>
            </div>
            <p class="text-3xl font-black text-blue-600 mt-2">{{ number_format($caEnAttente, 0, ',', ' ') }}</p>
            <p class="text-sm text-blue-500 font-medium">FDJ</p>
            @if($caTotal > 0)
            <div class="mt-3 h-2 bg-blue-100 rounded-full overflow-hidden">
                <div class="h-full bg-blue-400 rounded-full" style="width: {{ round($caEnAttente / $caTotal * 100) }}%"></div>
            </div>
            <p class="text-xs text-blue-500 mt-1">{{ round($caEnAttente / $caTotal * 100) }}% du total</p>
            @endif
        </div>

        {{-- CA total --}}
        <div class="bg-white rounded-2xl border border-orange-200 p-5">
            <div class="mb-1">
                <span class="text-xs font-semibold text-orange-600 uppercase tracking-wider">Total facturé</span>
            </div>
            <p class="text-3xl font-black text-orange-500 mt-2">{{ number_format($caTotal, 0, ',', ' ') }}</p>
            <p class="text-sm text-orange-400 font-medium">FDJ</p>
            <div class="mt-3 pt-3 border-t border-gray-100 space-y-1">
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Ticket moyen (payées)</span>
                    <span class="font-bold text-slate-700">
                        {{ $nbPayees > 0 ? number_format($caEncaisse / $nbPayees, 0, ',', ' ') . ' FDJ' : '—' }}
                    </span>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Taux d'encaissement</span>
                    <span class="font-bold {{ $caTotal > 0 && ($caEncaisse/$caTotal) >= 0.8 ? 'text-green-600' : 'text-orange-600' }}">
                        {{ $caTotal > 0 ? round($caEncaisse / $caTotal * 100) . '%' : '—' }}
                    </span>
                </div>
            </div>
        </div>

    </div>

    {{-- Répartition pièces / main-d'œuvre (HT) — suit la période choisie en haut de page --}}
    @php
        $fmtQte        = fn ($q) => rtrim(rtrim(number_format($q, 2, ',', ' '), '0'), ',');
        $totalPiecesMo = $totalPiecesHt + $totalMoHt;
        $partPieces    = $totalPiecesMo > 0 ? round($totalPiecesHt / $totalPiecesMo * 100) : 0;
        $partMo        = $totalPiecesMo > 0 ? 100 - $partPieces : 0;
    @endphp
    <div class="mt-6 bg-white rounded-2xl border border-gray-200 px-5 py-4">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Répartition du facturé (HT)</p>
            <p class="text-xs text-slate-400">Hors TVA et frais de timbre</p>
        </div>
        <div class="mt-3 flex h-2 rounded-full overflow-hidden bg-gray-100">
            <div style="width: {{ $partPieces }}%; background:#7c3aed"></div>
            <div style="width: {{ $partMo }}%; background:#2563eb"></div>
        </div>
        <div class="grid grid-cols-2 gap-6 mt-4">
            <div class="flex items-start gap-3">
                <span class="w-2 h-2 rounded-full mt-2 flex-shrink-0" style="background:#7c3aed"></span>
                <div>
                    <p class="text-xs text-slate-500">Pièces</p>
                    <p class="text-xl font-bold text-slate-800">{{ number_format($totalPiecesHt, 0, ',', ' ') }} <span class="text-sm font-medium text-slate-400">FDJ</span></p>
                    <p class="text-xs text-slate-400">{{ $fmtQte($qtePieces) }} unité(s) · {{ $partPieces }} %</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-2 h-2 rounded-full mt-2 flex-shrink-0" style="background:#2563eb"></span>
                <div>
                    <p class="text-xs text-slate-500">Main-d'œuvre</p>
                    <p class="text-xl font-bold text-slate-800">{{ number_format($totalMoHt, 0, ',', ' ') }} <span class="text-sm font-medium text-slate-400">FDJ</span></p>
                    <p class="text-xs text-slate-400">{{ $fmtQte($heuresMo) }} h · {{ $partMo }} %</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Évolution CA sur 12 mois --}}
    <div class="mt-5">
        <p class="text-xs font-semibold text-green-700 uppercase tracking-wider mb-3">Évolution du CA encaissé — {{ now()->year }}</p>
        @php $maxCADisplay = max(array_column($evolutionCA, 'ca') ?: [1]); $barMax = 80; @endphp
        <div class="flex items-end gap-1.5" style="height:96px">
            @foreach($evolutionCA as $mois)
            @php $barPx = $maxCADisplay > 0 ? max(2, (int)round($mois['ca'] / $maxCADisplay * $barMax)) : 2; @endphp
            <div class="flex-1 flex flex-col items-center group relative">
                <div class="absolute left-1/2 -translate-x-1/2 bg-slate-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none"
                     style="bottom: calc({{ $barPx }}px + 18px)">
                    {{ number_format($mois['ca'], 0, ',', ' ') }} FDJ
                </div>
                <div class="w-full rounded-t-sm transition-all cursor-default"
                     style="height:{{ $barPx }}px; background-color:{{ $mois['ca'] > 0 ? '#10b981' : '#d1fae5' }}">
                </div>
                <span class="text-slate-500 whitespace-nowrap" style="font-size:9px">{{ substr($mois['label'], 0, 3) }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="grid grid-cols-3 gap-6">

    {{-- ── Évolution mensuelle ──────────────────────────── --}}
    <div class="col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-sm font-bold text-slate-700 mb-5 uppercase tracking-wider">Évolution des OR — {{ now()->year }}</h3>
        @php $evoMax = 120; @endphp
        <div class="flex items-end gap-2" style="height:160px">
            @foreach($evolution as $mois)
            @php $barPx = $maxEvo > 0 ? max(3, (int)round($mois['count'] / $maxEvo * $evoMax)) : 3; @endphp
            <div class="flex-1 flex flex-col items-center group relative">
                <span class="text-xs font-bold text-slate-700 opacity-0 group-hover:opacity-100 transition-opacity absolute"
                      style="bottom: calc({{ $barPx }}px + 4px)">{{ $mois['count'] }}</span>
                <div class="w-full bg-orange-400 rounded-t-lg transition-all hover:bg-orange-500 cursor-default"
                     style="height:{{ $barPx }}px" title="{{ $mois['label'] }} : {{ $mois['count'] }} ORs">
                </div>
                <span class="text-slate-400 whitespace-nowrap mt-1" style="font-size:9px">{{ substr($mois['label'], 0, 3) }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Taux complétion ──────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-sm font-bold text-slate-700 mb-5 uppercase tracking-wider">Taux de complétion</h3>
        @php
        $taux = $total > 0 ? round($termines / $total * 100) : 0;
        $circumference = 2 * pi() * 45;
        $offset = $circumference - ($taux / 100 * $circumference);
        @endphp
        <div class="flex flex-col items-center justify-center h-40">
            <div class="relative">
                <svg class="w-32 h-32 -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#f1f5f9" stroke-width="8"/>
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#f97316" stroke-width="8"
                            stroke-dasharray="{{ $circumference }}"
                            stroke-dashoffset="{{ $offset }}"
                            stroke-linecap="round"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-bold text-slate-900">{{ $taux }}%</span>
                    <span class="text-xs text-slate-400">terminés</span>
                </div>
            </div>
            <div class="flex gap-4 mt-3 text-xs text-slate-500">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-orange-400 inline-block"></span> Terminés {{ $termines }}</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-200 inline-block"></span> Reste {{ $total - $termines }}</span>
            </div>
        </div>
    </div>
</div>

{{-- ── Par technicien ──────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Performance par technicien</h3>
        <div class="flex items-center gap-3">
            @if($techniciens->count() > 5)
            <input type="text" placeholder="Rechercher un mécanicien…" oninput="filtrerTechniciens(this.value)"
                   class="w-64 px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            @endif
            <span class="text-xs text-slate-400">{{ $techniciens->count() }} mécaniciens</span>
        </div>
    </div>
    @if($techniciens->isEmpty())
    <div class="px-6 py-10 text-center text-slate-400 text-sm">Aucun mécanicien enregistré</div>
    @else
    {{-- Liste avec son propre défilement (comme le tableau de bord) --}}
    <div class="divide-y divide-gray-100 overflow-y-auto" style="max-height: 420px;">
        @foreach($techniciens->sortByDesc('total') as $tech)
        @php
        $tauxCharge = $maxOrTech > 0 ? round($tech['total'] / $maxOrTech * 100) : 0;
        $tauxTermine = $tech['total'] > 0 ? round($tech['termines'] / $tech['total'] * 100) : 0;
        @endphp
        <div class="ligne-technicien px-6 py-4" data-nom="{{ strtolower($tech['nom']) }}">
            <div class="flex items-center gap-4">
                <div class="w-9 h-9 rounded-full bg-orange-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    {{ strtoupper(substr($tech['nom'], 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-sm font-semibold text-slate-800">{{ $tech['nom'] }}</p>
                        <div class="flex items-center gap-4 text-xs text-slate-500">
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>
                                {{ $tech['actifs'] }} actifs
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                                {{ $tech['termines'] }} terminés
                            </span>
                            <span class="font-bold text-slate-700">{{ $tech['total'] }} total</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full flex">
                                <div class="bg-blue-400 h-full transition-all"
                                     style="width: {{ $tech['total'] > 0 ? round($tech['actifs']/$tech['total']*100) : 0 }}%"></div>
                                <div class="bg-green-400 h-full transition-all"
                                     style="width: {{ $tech['total'] > 0 ? round($tech['termines']/$tech['total']*100) : 0 }}%"></div>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-slate-600 w-16 text-right" title="Volume d'OR par rapport au technicien le plus chargé">{{ $tauxCharge }}% charge</span>
                        @php
                            $perf = $tech['performance_moyenne'];
                            $perfCouleur = $perf === null ? 'text-slate-400 bg-gray-50' : ($perf >= 100 ? 'text-green-700 bg-green-50' : ($perf >= 80 ? 'text-amber-700 bg-amber-50' : 'text-red-700 bg-red-50'));
                        @endphp
                        <span class="text-xs font-bold px-2 py-1 rounded-lg w-32 text-center flex-shrink-0 {{ $perfCouleur }}"
                              title="Durée estimée / durée réelle nette (hors pauses) — moyenne sur {{ $tech['nb_mesures'] }} OR pointé(s). 100% = dans les temps.">
                            {{ $perf === null ? 'Perf. — n/a' : $perf . '% perf. moy.' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ── Par service + Réceptionnistes ──────────────────── --}}
<div class="grid grid-cols-2 gap-6">

    {{-- Par service --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Occupation par service</h3>
        </div>
        <div class="p-6 space-y-4">
            @php
            $serviceColors = ['rapide'=>'blue','mecanique'=>'orange','electricite'=>'yellow','carrosserie'=>'purple','peinture'=>'pink'];
            @endphp
            @foreach($services_data as $svc)
            @php $pct = $maxOrService > 0 ? round($svc['total'] / $maxOrService * 100) : 0; @endphp
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-{{ $serviceColors[$svc['slug']] ?? 'gray' }}-400 inline-block"></span>
                        <span class="text-sm font-medium text-slate-700">{{ $svc['label'] }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <span class="text-blue-600">{{ $svc['actifs'] }} actifs</span>
                        <span class="text-green-600">{{ $svc['termines'] }} terminés</span>
                        <span class="font-bold text-slate-700 w-8 text-right">{{ $svc['total'] }}</span>
                    </div>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-{{ $serviceColors[$svc['slug']] ?? 'gray' }}-400 rounded-full transition-all"
                         style="width: {{ $pct }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Réceptionnistes --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Réceptions par agent</h3>
        </div>
        @if($conseillers->isEmpty())
        <div class="px-6 py-10 text-center text-slate-400 text-sm">Aucune réception enregistrée</div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($conseillers->sortByDesc('total') as $conseiller)
            <div class="px-6 py-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    {{ strtoupper(substr($conseiller['nom'], 0, 1)) }}
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-800">{{ $conseiller['nom'] }}</p>
                    <p class="text-xs text-slate-400">{{ $conseiller['role'] }}</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-slate-900">{{ $conseiller['total'] }}</p>
                    <p class="text-xs text-slate-400">total</p>
                </div>
                <div class="text-right pl-4 border-l border-gray-100">
                    <p class="text-sm font-bold text-orange-500">{{ $conseiller['ce_mois'] }}</p>
                    <p class="text-xs text-slate-400">ce mois</p>
                </div>
                <div class="text-right pl-4 border-l border-gray-100">
                    <p class="text-sm font-bold text-blue-500">{{ $conseiller['cette_sem'] }}</p>
                    <p class="text-xs text-slate-400">cette sem.</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ── Dernières réceptions ────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">10 dernières réceptions</h3>
        <div class="flex gap-2">
            <a href="{{ route('rapports.historique-vehicule') }}"
               class="text-xs text-orange-500 hover:underline font-medium">Historique véhicule →</a>
            <span class="text-gray-300">|</span>
            <a href="{{ route('rapports.historique-client') }}"
               class="text-xs text-orange-500 hover:underline font-medium">Historique client →</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">N° OR</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Véhicule</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Réceptionné par</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($dernieres_receptions as $or)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <a href="{{ route('ordres-reparations.show', $or) }}" class="font-mono font-bold text-orange-500 hover:underline">{{ $or->numero }}</a>
                    </td>
                    <td class="px-5 py-3 text-slate-700">{{ $or->client->nom_complet }}</td>
                    <td class="px-5 py-3 font-mono text-slate-600 text-xs">{{ $or->vehicule->immatriculation }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $or->conseiller?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-500 text-xs">{{ $or->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700">
                            {{ $or->getStatutLabel() }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

</div>
@push('scripts')
<script>
function filtrerTechniciens(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.ligne-technicien').forEach(el => {
        el.style.display = !q || el.dataset.nom.includes(q) ? '' : 'none';
    });
}
</script>
@endpush
@endsection
