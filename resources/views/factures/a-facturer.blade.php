@extends('layouts.app')
@section('title', 'Véhicules à facturer')
@section('page-title', 'À facturer')
@section('page-subtitle', 'Véhicules prêts en attente de facture')

@section('content')
<div class="space-y-4">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

{{-- Tableau --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Ordre de réparation</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Véhicule</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Entrée</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Devis TTC</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Statut</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($orsAFacturer as $or)
                @php $devis = $or->allDevis->last(); @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <a href="{{ route('ordres-reparations.show', $or) }}" class="font-mono text-orange-500 hover:underline text-xs">{{ $or->numero }}</a>
                    </td>
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs text-slate-500">{{ $or->vehicule->immatriculation }}</span>
                                            </td>
                    <td class="px-5 py-3 text-slate-700 font-medium">{{ $or->client->nom_complet }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $or->date_entree->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">
                        {{ $devis ? number_format($devis->montant_ttc, 0, ',', ' ') . ' FDJ' : '—' }}
                    </td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">À facturer</span>
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('factures.create', $or) }}"
                           class="text-xs bg-green-500 hover:bg-green-600 text-white font-bold px-4 py-1.5 rounded-lg transition-colors">
                            Créer la facture
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                        Aucun véhicule à facturer.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>
@endsection
