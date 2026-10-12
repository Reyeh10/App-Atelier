@extends('layouts.app')
@section('title', 'Récap ' . mb_strtolower($recap->type_label))
@section('page-title', 'Récap ' . mb_strtolower($recap->type_label) . ' — ' . $recap->periode_label)
@section('page-subtitle', $recap->titre . ' — montants HT, sans TVA (ce n\'est pas une facture)')

@section('header-actions')
{{-- Mêmes boutons que la facture --}}
<div class="flex gap-2">
    <a href="{{ route(['tiers' => 'leasing-tiers.recaps', 'bus' => 'leasing-bus.recaps', 'transport' => 'bus-transport.recaps', 'service' => 'vehicules-service.recaps'][$recap->type] ?? 'leasing.recaps') }}"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Récaps
    </a>
    <a href="{{ route('leasing.recaps.imprimer', $recap) }}?apercu=1" target="_blank"
       class="flex items-center gap-2 text-sm border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        👁 Aperçu
    </a>
    <a href="{{ route('leasing.recaps.imprimer', $recap) }}" target="_blank"
       class="flex items-center gap-2 text-sm bg-orange-500 hover:bg-orange-600 text-white rounded-lg px-3 py-2 transition-colors">
        🖨 Imprimer
    </a>
    <a href="{{ route('leasing.recaps.excel', $recap) }}"
       class="flex items-center gap-2 text-sm bg-green-500 hover:bg-green-600 text-white rounded-lg px-3 py-2 transition-colors">
        Exporter Excel
    </a>
</div>
@endsection

@section('content')
@php $fmt = fn ($n) => (float) $n > 0 ? number_format((float) $n, 0, ',', ' ') : '-'; @endphp
<div class="max-w-7xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <p class="text-xs text-slate-500">Pièces détachées</p>
        <p class="text-xl font-bold text-slate-900">{{ number_format((float) $recap->total_pieces, 0, ',', ' ') }} FDJ</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <p class="text-xs text-slate-500">Forfaits ({{ $recap->nb_vehicules }} × {{ number_format($recap->forfait, 0, ',', ' ') }})</p>
        <p class="text-xl font-bold text-slate-900">{{ number_format((float) $recap->total_forfaits, 0, ',', ' ') }} FDJ</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <p class="text-xs text-slate-500">Total HT</p>
        <p class="text-xl font-bold text-orange-600">{{ number_format((float) $recap->total, 0, ',', ' ') }} FDJ</p>
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">N°</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Propriétaire</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Immatriculation</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ $recap->estBus() ? 'BC de la période' : 'OR de la période' }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">Pièces détachées</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">Forfait / main-d'œuvre</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recap->lignesGroupees() as $lignesGroupe)
                @foreach($lignesGroupe as $l)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-slate-400">{{ $l->position }}</td>
                    <td class="px-4 py-2 text-slate-700">{{ $l->proprietaire }}</td>
                    <td class="px-4 py-2 font-mono font-bold text-slate-800">{{ $l->immatriculation }}</td>
                    <td class="px-4 py-2 text-xs">
                        @foreach($l->bcs ?? [] as $b)
                        <a href="{{ route('bons-commande.show', $b['id']) }}" class="text-orange-500 hover:underline font-mono">{{ $b['numero'] }}</a>@if(! $loop->last || ! empty($l->ors)), @endif
                        @endforeach
                        @foreach($l->ors ?? [] as $o)
                        <a href="{{ route('ordres-reparations.show', $o['id']) }}" class="text-orange-500 hover:underline font-mono">{{ $o['numero'] }}</a>@if(! $loop->last), @endif
                        @endforeach
                        @if(empty($l->bcs) && empty($l->ors))
                        <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right text-slate-700">{{ $fmt($l->montant_pieces) }}</td>
                    <td class="px-4 py-2 text-right text-slate-700">{{ $fmt($l->forfait) }}</td>
                    <td class="px-4 py-2 text-right font-semibold text-slate-900">{{ $fmt($l->total) }}</td>
                </tr>
                @endforeach
                @if($recap->estGroupe())
                <tr class="bg-gray-50">
                    <td colspan="4" class="px-4 py-2 text-sm font-semibold text-slate-700">
                        Sous-total {{ $lignesGroupe->first()->proprietaire }}
                        @if($recap->estTiers() && $lignesGroupe->first()->client_id)
                        <a href="{{ route('leasing.recaps.client', [$recap, $lignesGroupe->first()->client_id]) }}" target="_blank" class="ml-2 text-xs text-orange-500 hover:underline font-normal">🖨 Imprimer sa partie</a>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right font-semibold text-slate-800">{{ number_format((float) $lignesGroupe->sum('montant_pieces'), 0, ',', ' ') }}</td>
                    <td class="px-4 py-2 text-right font-semibold text-slate-800">{{ number_format((float) $lignesGroupe->sum('forfait'), 0, ',', ' ') }}</td>
                    <td class="px-4 py-2 text-right font-bold text-slate-900">{{ number_format((float) $lignesGroupe->sum('total'), 0, ',', ' ') }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-sm font-bold text-slate-800">TOTAL</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-900">{{ number_format((float) $recap->total_pieces, 0, ',', ' ') }}</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-900">{{ number_format((float) $recap->total_forfaits, 0, ',', ' ') }}</td>
                    <td class="px-4 py-3 text-right font-bold text-orange-600">{{ number_format((float) $recap->total, 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<p class="text-xs text-slate-400">Généré le {{ $recap->updated_at?->format('d/m/Y à H:i') }} par {{ $recap->generePar?->name ?? '—' }}. Pour le recalculer, générez à nouveau la même période depuis <a href="{{ route(['tiers' => 'leasing-tiers.recaps', 'bus' => 'leasing-bus.recaps', 'transport' => 'bus-transport.recaps', 'service' => 'vehicules-service.recaps'][$recap->type] ?? 'leasing.recaps') }}" class="text-orange-500 hover:underline">Récaps leasing</a>.</p>

</div>
@endsection
