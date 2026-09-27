@extends('layouts.app')
@section('title', $eg->numero)
@section('page-title', $eg->numero)
@section('page-subtitle', $eg->payeur_nom)

@section('header-actions')
<a href="{{ route('encaissements-globaux.index') }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour
</a>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

{{-- Statut + paiement --}}
<div class="bg-white rounded-2xl border-2 border-{{ $eg->getStatutColor() }}-300 p-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3 flex-wrap">
            <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-{{ $eg->getStatutColor() }}-100 text-{{ $eg->getStatutColor() }}-700">
                {{ $eg->getStatutLabel() }}
            </span>
            <span class="text-slate-500 text-sm">{{ $eg->getModePaiementLabel() }}</span>
            <span class="text-slate-500 text-sm">Créé le {{ $eg->date_emission->format('d/m/Y') }}</span>
            @if($eg->createdBy)
            <span class="text-slate-400 text-xs">par {{ $eg->createdBy->name }}</span>
            @endif
        </div>
        <div class="text-right">
            <p class="text-2xl font-black text-orange-500">{{ number_format($eg->montant_total, 0, ',', ' ') }} FDJ</p>
            <p class="text-xs text-slate-500">{{ $eg->factures->count() }} facture(s)</p>
        </div>
    </div>

    @if($eg->statut === 'paye')
    <div class="mt-4 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Payé le {{ $eg->date_paiement->format('d/m/Y') }} — toutes les factures ont été soldées.
    </div>
    @else
    {{-- Formulaire paiement --}}
    @if(auth()->user()->hasPermission('encaisser_factures'))
    <div class="mt-4 border-t border-gray-100 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Confirmer le paiement global</p>
        <form method="POST" action="{{ route('encaissements-globaux.payer', $eg) }}" class="flex gap-3 items-end flex-wrap">
            @csrf @method('PATCH')
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date de paiement</label>
                <input type="date" name="date_paiement" value="{{ now()->format('Y-m-d') }}"
                       class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <button type="submit"
                    class="bg-green-500 hover:bg-green-600 text-white font-bold text-sm px-6 py-2 rounded-xl transition-colors">
                ✓ Confirmer — {{ number_format($eg->montant_total, 0, ',', ' ') }} FDJ encaissés
            </button>
        </form>
        <p class="text-xs text-slate-400 mt-2">Cela marquera les {{ $eg->factures->count() }} facture(s) ci-dessous comme payées.</p>
    </div>
    @endif
    @endif
</div>

{{-- Liste des factures regroupées --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Factures incluses</h3>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($eg->factures as $facture)
        <div class="px-6 py-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('factures.show', $facture) }}"
                       class="font-mono font-bold text-orange-500 hover:underline text-sm">
                        {{ $facture->numero }}
                    </a>
                    <span class="text-xs text-slate-500">— OR {{ $facture->ordreReparation->numero }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold
                        @if($facture->statut === 'payee') bg-green-100 text-green-700
                        @else bg-blue-100 text-blue-700 @endif">
                        {{ $facture->getStatutLabel() }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Émise le {{ $facture->date_emission->format('d/m/Y') }}</p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="font-bold text-slate-800">{{ number_format($facture->totalGeneral(), 0, ',', ' ') }} FDJ</p>
            </div>
        </div>
        @endforeach
    </div>
    <div class="border-t-2 border-gray-200 bg-gray-50 px-6 py-4 flex justify-end">
        <div class="space-y-1 min-w-56">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Nombre de factures</span>
                <span class="font-semibold">{{ $eg->factures->count() }}</span>
            </div>
            <div class="flex justify-between font-bold text-base border-t border-gray-300 pt-2">
                <span>Total général</span>
                <span class="text-orange-500 text-lg">{{ number_format($eg->montant_total, 0, ',', ' ') }} FDJ</span>
            </div>
        </div>
    </div>
</div>

@if($eg->notes)
<div class="bg-white rounded-2xl border border-gray-200 p-5">
    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Notes</p>
    <p class="text-sm text-slate-600">{{ $eg->notes }}</p>
</div>
@endif

</div>
@endsection
