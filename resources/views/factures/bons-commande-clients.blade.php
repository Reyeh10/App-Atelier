@extends('layouts.app')
@section('title', 'Bons de commande clients')
@section('page-title', 'Bons de commande clients')
@section('page-subtitle', 'Factures réglées par bon de commande')

@section('header-actions')
<a href="{{ route('factures.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour aux factures
</a>
@endsection

@section('content')
<div class="space-y-4">

{{-- Stats --}}
<div class="bg-white rounded-2xl border border-orange-200 p-5 flex items-center gap-4">
    <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    </div>
    <div>
        <p class="text-2xl font-black text-orange-600">{{ number_format($totalBonsCommande, 0, ',', ' ') }} FDJ</p>
        <p class="text-sm text-slate-500">{{ $factures->total() }} facture(s) réglée(s) par bon de commande</p>
    </div>
</div>

{{-- Recherche --}}
<form method="GET" action="{{ route('factures.bons-commande-clients') }}" class="bg-white rounded-2xl border border-gray-200 p-4">
    <div class="flex gap-3 items-end">
        <div class="flex-1 max-w-sm">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Recherche</label>
            <input type="text" name="recherche" value="{{ request('recherche') }}" placeholder="N° de BC, n° de facture, client..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
            Rechercher
        </button>
        @if(request('recherche'))
        <a href="{{ route('factures.bons-commande-clients') }}" class="text-xs text-slate-400 hover:text-slate-600 pb-2.5">✕ Effacer</a>
        @endif
    </div>
</form>

{{-- Tableau --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">N° Bon de commande</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Facture</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">OR</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date de paiement</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">TTC</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Scan</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($factures as $facture)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ $facture->numero_bon_commande_client }}</td>
                    <td class="px-5 py-3 font-mono text-slate-600">{{ $facture->numero }}</td>
                    <td class="px-5 py-3 text-slate-700 font-medium">{{ $facture->payeur_nom }}</td>
                    <td class="px-5 py-3">
                        <a href="{{ route('ordres-reparations.show', $facture->ordreReparation) }}"
                           class="font-mono text-orange-500 hover:underline text-xs">
                            {{ $facture->ordreReparation->numero }}
                        </a>
                    </td>
                    <td class="px-5 py-3 text-slate-500">{{ $facture->date_paiement?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-slate-800">{{ number_format($facture->montant_ttc, 0, ',', ' ') }} FDJ</td>
                    <td class="px-5 py-3">
                        @if($facture->bon_commande_client_url)
                        <a href="{{ $facture->bon_commande_client_url }}" target="_blank" class="text-xs text-orange-500 hover:underline font-medium">Voir le scan</a>
                        @else
                        <span class="text-xs text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('factures.show', $facture) }}" class="text-xs text-orange-500 hover:underline font-medium">Voir</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-12 text-center text-slate-400">Aucune facture réglée par bon de commande pour cette sélection.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($factures->hasPages())
    <div class="px-5 py-3 border-t border-gray-200">{{ $factures->links() }}</div>
    @endif
</div>

</div>
@endsection
