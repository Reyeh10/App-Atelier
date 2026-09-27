@extends('layouts.app')
@section('title', 'Encaissements groupés')
@section('page-title', 'Encaissements groupés')
@section('page-subtitle', 'Regroupement de factures par client ou par marque garantie')

@section('header-actions')
@if(auth()->user()->hasPermission('encaisser_factures'))
<a href="{{ route('encaissements-globaux.create') }}"
   class="flex items-center gap-2 text-sm bg-orange-500 hover:bg-orange-600 text-white rounded-lg px-4 py-2 transition-colors font-medium">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    Nouvel encaissement groupé
</a>
@endif
@endsection

@section('content')
<div class="space-y-4">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Numéro</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Payeur</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Mode</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500">Factures</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Total</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Statut</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($encaissements as $eg)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ $eg->numero }}</td>
                    <td class="px-5 py-3 text-slate-700 font-medium">
                        {{ $eg->payeur_nom }}
                        @if($eg->marque_garantie_id)
                        <span class="ml-1 text-xs bg-indigo-100 text-indigo-700 font-semibold px-1.5 py-0.5 rounded">Garantie</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-500">{{ $eg->date_emission->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $eg->getModePaiementLabel() }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                            {{ $eg->factures()->count() }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right font-bold text-slate-800">{{ number_format($eg->montant_total, 0, ',', ' ') }} FDJ</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold
                            @if($eg->statut === 'paye') bg-green-100 text-green-700
                            @else bg-blue-100 text-blue-700 @endif">
                            {{ $eg->getStatutLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('encaissements-globaux.show', $eg) }}"
                           class="text-xs text-orange-500 hover:underline font-medium">Voir</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-12 text-center text-slate-400">Aucun encaissement groupé.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($encaissements->hasPages())
    <div class="px-5 py-3 border-t border-gray-200">{{ $encaissements->links() }}</div>
    @endif
</div>

</div>
@endsection
