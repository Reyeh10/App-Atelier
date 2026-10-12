@extends('layouts.app')
@section('title', ['tiers' => 'Récaps leasing tiers', 'bus' => 'Récaps bus leasing', 'transport' => 'Récaps bus transport public', 'service' => 'Récaps véhicules de service'][$type] ?? 'Récaps leasing')
@section('page-title', ['tiers' => 'Récaps leasing tiers', 'bus' => 'Récaps bus leasing', 'transport' => 'Récaps bus transport public', 'service' => 'Récaps véhicules de service'][$type] ?? 'Récaps leasing — parc STCD')
@section('page-subtitle', [
    'tiers' => 'Véhicules de particuliers loués à STCD : forfait + pièces, regroupés par propriétaire — HT, sans TVA',
    'bus'   => 'Bus réparés dans un autre garage : forfait bus + pièces de leurs BC reçus, par période (au plus un mois) — HT, sans TVA',
    'service'   => 'Véhicules affectés à un service ou à un directeur / responsable : pièces de leurs OR (+ forfait), regroupés par affectation — HT, sans TVA',
    'transport' => 'Bus du transport public, réparés dans un autre garage : forfait bus + pièces de leurs BC reçus, par période (au plus un mois) — HT, sans TVA',
][$type] ?? 'Forfait + pièces de chaque véhicule du parc, par période (au plus un mois) — HT, sans TVA')

@section('content')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp
<div class="max-w-5xl space-y-5">

@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

{{-- Générer un récap --}}
<form method="POST" action="{{ route('leasing.recaps.generer') }}" class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-wrap gap-4 items-end">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
        <input type="date" name="date_debut" required value="{{ old('date_debut', now()->subMonthNoOverflow()->format('Y-m-d')) }}"
               class="px-3 py-2 border border-gray-300 rounded-xl text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
        <input type="date" name="date_fin" required value="{{ old('date_fin', now()->format('Y-m-d')) }}"
               class="px-3 py-2 border border-gray-300 rounded-xl text-sm">
    </div>
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded-xl transition-colors">
        Générer le récap
    </button>
    <p class="w-full text-xs text-slate-400">
        Au plus un mois (ex. du 25 au 25 du mois suivant). {{ $nbVehicules }} {{ \App\Models\VehiculeLeasing::GROUPES_BUS[$type]['liste'] ?? 'véhicule(s)' }} {{ ['tiers' => 'en leasing tiers (cochés sur la fiche de leurs propriétaires)', 'bus' => '', 'transport' => '', 'service' => 'de service'][$type] ?? 'dans le parc' }}. Une période déjà générée est recalculée ;
        {{ isset(\App\Models\VehiculeLeasing::GROUPES_BUS[$type]) ? "un BC déjà repris dans un autre récap n'est jamais compté deux fois (un BC reçu après la fin de la période passe au récap suivant)." : "un OR déjà repris dans un autre récap n'est jamais compté deux fois." }}
    </p>
</form>

{{-- Récaps enregistrés --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700">Récaps enregistrés</h3>
    </div>
    @if($recaps->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">Aucun récap généré pour le moment.</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Période</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Véhicules</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Pièces</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Forfaits</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Total HT</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Généré</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recaps as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 font-medium text-slate-800">{{ $r->periode_label }}</td>
                    <td class="px-5 py-2.5 text-right text-slate-700">{{ $r->nb_vehicules }}</td>
                    <td class="px-5 py-2.5 text-right text-slate-700">{{ $fmt($r->total_pieces) }}</td>
                    <td class="px-5 py-2.5 text-right text-slate-700">{{ $fmt($r->total_forfaits) }}</td>
                    <td class="px-5 py-2.5 text-right font-bold text-slate-900">{{ $fmt($r->total) }} FDJ</td>
                    <td class="px-5 py-2.5 text-xs text-slate-500">{{ $r->updated_at?->format('d/m/Y H:i') }} — {{ $r->generePar?->name ?? '—' }}</td>
                    <td class="px-5 py-2.5 text-right whitespace-nowrap">
                        <a href="{{ route('leasing.recaps.show', $r) }}" class="text-xs text-orange-500 hover:underline font-semibold">Voir</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

</div>
@endsection
