@extends('layouts.app')
@section('title', 'Corriger ' . $dossier->numero)
@section('page-title', 'Corriger ' . $dossier->numero)
@section('page-subtitle', $dossier->client->nom_complet . ' — ' . $dossier->vehicule->immatriculation)

@section('header-actions')
<a href="{{ route('dossiers-reception.show', $dossier) }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour au dossier
</a>
@endsection

@section('content')
@php $champ = 'w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white'; @endphp
<div class="max-w-3xl space-y-5">

<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
    Correction administrateur — enregistrée dans le journal d'activités.
    @if($dossier->or_id) Un OR a déjà été créé à partir de ce dossier : il n'est pas modifié ici (bouton « Corriger » sur l'OR).@endif
</div>

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
    <ul class="space-y-1">
        @foreach($errors->all() as $e)
        <li class="text-sm text-red-700">• {{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('dossiers-reception.corriger.enregistrer', $dossier) }}" class="space-y-5">
@csrf @method('PUT')
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Kilométrage d'entrée <span class="text-red-500">*</span></label>
            <input type="number" name="kilometrage_entree" min="0" required value="{{ old('kilometrage_entree', $dossier->kilometrage_entree) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Niveau de carburant</label>
            <select name="niveau_carburant" class="{{ $champ }}">
                @foreach(['vide' => 'Vide', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4', 'plein' => 'Plein'] as $val => $label)
                <option value="{{ $val }}" {{ old('niveau_carburant', $dossier->niveau_carburant) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Date d'entrée <span class="text-red-500">*</span></label>
            <input type="date" name="date_entree" required value="{{ old('date_entree', $dossier->date_entree?->format('Y-m-d')) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Heure d'entrée</label>
            <input type="time" name="heure_entree" value="{{ old('heure_entree', $dossier->heure_entree ? substr($dossier->heure_entree, 0, 5) : '') }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Urgence</label>
            <select name="urgence" class="{{ $champ }}">
                @foreach(['normal' => 'Normal', 'urgent' => 'Urgent', 'tres_urgent' => 'Très urgent'] as $val => $label)
                <option value="{{ $val }}" {{ old('urgence', $dossier->urgence) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Type de moteur du véhicule</label>
            <select name="type_moteur_id" class="{{ $champ }}">
                <option value="">— Non renseigné —</option>
                @foreach($typesMoteur as $tm)
                <option value="{{ $tm->id }}" {{ (int) old('type_moteur_id', $dossier->vehicule->type_moteur_id) === $tm->id ? 'selected' : '' }}>{{ $tm->modele }} — moteur {{ $tm->code }}</option>
                @endforeach
            </select>
            @if($dossier->canal_service === 'entretien_periodique')
            <p class="text-xs text-slate-400 mt-1">Entretien périodique : le palier est recalculé à l'enregistrement (actuel : {{ $dossier->entretien_km_seuil ? number_format($dossier->entretien_km_seuil, 0, ',', ' ') . ' km' : 'aucun' }}).</p>
            @endif
        </div>
    </div>
    <div class="mt-4">
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Motif</label>
        <textarea name="motif_entree" rows="2" class="{{ $champ }}">{{ old('motif_entree', $dossier->motif_entree) }}</textarea>
    </div>
    <div class="mt-4">
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Notes internes</label>
        <textarea name="notes_internes" rows="2" class="{{ $champ }}">{{ old('notes_internes', $dossier->notes_internes) }}</textarea>
    </div>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">Enregistrer la correction</button>
    <a href="{{ route('dossiers-reception.show', $dossier) }}" class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">Annuler</a>
</div>
</form>
</div>
@endsection
