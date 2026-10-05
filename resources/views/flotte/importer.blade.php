@extends('layouts.app')
@section('title', 'Importer une livraison flotte')
@section('page-title', 'Importer une livraison flotte')
@section('page-subtitle', 'Fichier Excel : plusieurs bus, une ligne par pièce')

@section('header-actions')
<a href="{{ route('flotte.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour
</a>
@endsection

@section('content')
<div class="max-w-3xl space-y-5">

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
    <ul class="space-y-1">
        @foreach($errors->all() as $e)
        <li class="text-sm text-red-700 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>{{ $e }}
        </li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-4 text-sm text-blue-800 space-y-2">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <p class="font-semibold">Format attendu</p>
        <a href="{{ route('flotte.modele') }}" class="inline-flex items-center gap-2 bg-white border border-blue-300 text-blue-700 hover:bg-blue-100 font-bold text-xs px-3 py-2 rounded-lg transition-colors">
            ⬇ Télécharger le modèle Excel
        </a>
    </div>
    <p><strong>Une ligne = une pièce</strong> (ou une main-d'œuvre). Un bus avec 5 pièces = 5 lignes, avec son immatriculation répétée sur chaque ligne.</p>
    <p>Colonnes : <strong>Date, Immatriculation, Type, Référence, Désignation, Quantité, Prix unitaire, Remise %, Kilométrage</strong> — obligatoires : Immatriculation, Désignation, Quantité.</p>
    <p>Le système crée <strong>une facture par bus et par date</strong>. Les bus doivent déjà exister dans les véhicules de la société.</p>
    <p>Main-d'œuvre : si le fichier n'en contient pas pour un bus, <strong>{{ number_format(\App\Models\ParametreAtelier::get()->main_oeuvre_flotte, 0, ',', ' ') }} FDJ</strong> sont ajoutés automatiquement à sa facture (Réglages atelier).</p>
</div>

<form method="POST" action="{{ route('flotte.importer.apercu') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Société cliente <span class="text-red-500">*</span></label>
        <select name="client_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">— Choisir —</option>
            @foreach($clients as $c)
            <option value="{{ $c->id }}" @selected(old('client_id', $clientId) == $c->id)>{{ $c->nom_complet }} ({{ $c->vehicules_count }} véhicule{{ $c->vehicules_count > 1 ? 's' : '' }})</option>
            @endforeach
        </select>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Date de livraison <span class="text-red-500">*</span></label>
            <input type="date" name="date_livraison" required value="{{ old('date_livraison', now()->format('Y-m-d')) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            <p class="text-xs text-slate-400 mt-1">Utilisée pour les lignes dont la colonne Date est vide.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Fichier Excel ou CSV <span class="text-red-500">*</span></label>
            <input type="file" name="fichier" required accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
        <input type="text" name="notes" maxlength="1000" value="{{ old('notes') }}" placeholder="Ex : livraison du vendredi"
               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
    </div>
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-6 rounded-xl text-sm transition-colors">
        Vérifier le fichier →
    </button>
    <p class="text-xs text-slate-400">Rien n'est enregistré à cette étape : un aperçu par bus s'affiche d'abord.</p>
</form>

</div>
@endsection
