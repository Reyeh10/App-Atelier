@extends('layouts.app')
@section('title', 'Livraisons flotte')
@section('page-title', 'Livraisons flotte')
@section('page-subtitle', 'Pièces livrées aux sociétés qui ont leur propre atelier — sans réception ni OR')

@section('header-actions')
<div class="flex gap-2">
    @if(auth()->user()->hasPermission('creer_factures'))
    <a href="{{ route('flotte.modele') }}"
       class="flex items-center gap-2 text-sm border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        ⬇ Modèle Excel
    </a>
    <a href="{{ route('flotte.importer.form') }}"
       class="flex items-center gap-2 text-sm bg-orange-500 hover:bg-orange-600 text-white rounded-lg px-3 py-2 transition-colors">
        ⬆ Importer une livraison
    </a>
    @endif
</div>
@endsection

@section('content')
<div class="space-y-5">

<div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-800">
    <p><strong>1.</strong> Importez le fichier Excel (plusieurs bus, une ligne par pièce) →
       <strong>2.</strong> un bon de commande par bus part au magasin →
       <strong>3.</strong> le magasin valide et envoie le bon de transfert (BT) →
       <strong>4.</strong> « Facturer » crée une facture par bus.</p>
</div>

{{-- À facturer --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">À facturer <span class="ml-1 text-green-600">({{ $aFacturer->count() }})</span></h3>
    </div>
    @if($aFacturer->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">Aucune livraison prête à facturer.</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Bus</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Société</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">BC / BT</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Lignes</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($aFacturer as $livraison)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-slate-600">{{ $livraison->date_livraison->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 font-mono font-bold text-slate-800">{{ $livraison->vehicule?->immatriculation }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $livraison->client?->nom_complet }}</td>
                    <td class="px-5 py-3 text-xs font-mono text-slate-500">
                        @if($livraison->bonCommande)
                        <a href="{{ route('bons-commande.show', $livraison->bonCommande) }}" class="text-orange-500 hover:underline">{{ $livraison->bonCommande->numero }}</a>
                        / {{ $livraison->bonCommande->bonTransfert?->numero ?? '—' }}
                        @else
                        Main-d'œuvre seule
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ $livraison->lignes->count() }}</td>
                    <td class="px-5 py-3 text-right">
                        @if(auth()->user()->hasPermission('creer_factures'))
                        <a href="{{ route('flotte.facturer.form', $livraison) }}" class="inline-flex bg-green-500 hover:bg-green-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-colors">Facturer</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- En attente du magasin --}}
@if($attenteBt->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">En attente du BT magasin <span class="ml-1 text-yellow-600">({{ $attenteBt->count() }})</span></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Bus</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Société</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Bon de commande</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Import</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($attenteBt as $livraison)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-slate-600">{{ $livraison->date_livraison->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 font-mono font-bold text-slate-800">{{ $livraison->vehicule?->immatriculation }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $livraison->client?->nom_complet }}</td>
                    <td class="px-5 py-3 text-xs font-mono">
                        @if($livraison->bonCommande)
                        <a href="{{ route('bons-commande.show', $livraison->bonCommande) }}" class="text-orange-500 hover:underline">{{ $livraison->bonCommande->numero }}</a>
                        @else — @endif
                    </td>
                    <td class="px-5 py-3 text-xs font-mono"><a href="{{ route('flotte.show', $livraison->import_flotte_id) }}" class="text-orange-500 hover:underline">{{ $livraison->import?->numero }}</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Imports --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Imports</h3>
    </div>
    @if($imports->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">Aucun import pour l'instant. Téléchargez le modèle Excel, remplissez-le, puis importez-le.</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">N°</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Société</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Fichier</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Livraisons (bus)</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Importé le</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Par</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($imports as $import)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3"><a href="{{ route('flotte.show', $import) }}" class="font-mono font-bold text-orange-500 hover:underline">{{ $import->numero }}</a></td>
                    <td class="px-5 py-3 text-slate-700">{{ $import->client?->nom_complet }}</td>
                    <td class="px-5 py-3 text-xs text-slate-500">{{ $import->fichier_nom_original ?? '—' }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ $import->livraisons_count }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $import->creePar?->name ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $imports->links() }}</div>
    @endif
</div>

</div>
@endsection
