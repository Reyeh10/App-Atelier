@extends('layouts.app')
@section('title', 'Avoirs')
@section('page-title', 'Avoirs')
@section('page-subtitle', 'Factures annulées ou corrigées')

@section('header-actions')
<a href="{{ route('factures.index') }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Factures
</a>
@endsection

@section('content')
<div class="space-y-4">

<div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-600">
    Un avoir annule une facture émise en totalité (mêmes lignes, en négatif). Il a sa propre numérotation et ne se supprime jamais.
    En cas de correction, une nouvelle facture est émise à la place.
</div>

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Numéro</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Type</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Facture annulée</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Remplacée par</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Montant</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Remboursement</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($avoirs as $avoir)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ $avoir->numero }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $avoir->date_emission->format('d/m/Y') }}</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $avoir->type === 'correction' ? 'bg-indigo-100 text-indigo-700' : 'bg-red-100 text-red-700' }}">{{ $avoir->getTypeLabel() }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-slate-700 font-medium">{{ $avoir->payeur_nom }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $avoir->motif }} — {{ $avoir->creePar?->name ?? '—' }}</p>
                    </td>
                    <td class="px-5 py-3">
                        <a href="{{ route('factures.show', $avoir->facture) }}" class="font-mono text-orange-500 hover:underline text-xs">{{ $avoir->facture->numero }}</a>
                    </td>
                    <td class="px-5 py-3">
                        @if($avoir->factureRemplacement)
                        <a href="{{ route('factures.show', $avoir->factureRemplacement) }}" class="font-mono text-orange-500 hover:underline text-xs">{{ $avoir->factureRemplacement->numero }}</a>
                        @else
                        <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-bold text-red-600">- {{ number_format($avoir->totalGeneral(), 0, ',', ' ') }} FDJ</td>
                    <td class="px-5 py-3 text-xs">
                        @if($avoir->montant_a_rembourser > 0)
                            @if($avoir->resteARembourser())
                            <span class="text-amber-700 font-semibold">{{ number_format($avoir->montant_a_rembourser, 0, ',', ' ') }} FDJ à rembourser</span>
                            @else
                            <span class="text-green-600">✓ Remboursé le {{ $avoir->rembourse_le->format('d/m/Y') }}</span>
                            @endif
                        @else
                        <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('avoirs.imprimer', $avoir) }}?apercu=1" target="_blank" class="text-xs text-orange-500 hover:underline font-medium">Voir</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-5 py-12 text-center text-slate-400">Aucun avoir émis.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($avoirs->hasPages())
    <div class="px-5 py-3 border-t border-gray-200">{{ $avoirs->links() }}</div>
    @endif
</div>

</div>
@endsection
