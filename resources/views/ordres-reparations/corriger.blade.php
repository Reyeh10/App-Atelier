@extends('layouts.app')
@section('title', 'Corriger ' . $or->numero)
@section('page-title', 'Corriger ' . $or->numero)
@section('page-subtitle', $or->client->nom_complet . ' — ' . $or->vehicule->immatriculation)

@section('header-actions')
<a href="{{ route('ordres-reparations.show', $or) }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour à l'OR
</a>
@endsection

@section('content')
@php
    $garantie = $or->type === 'garantie' || $or->statut_garantie !== null;
    $champ = 'w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white';
@endphp
<div class="max-w-3xl space-y-5">

<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
    Correction administrateur — pour rattraper une erreur de saisie de la réception. Chaque correction est enregistrée dans le journal d'activités.
    @if($or->facture) L'OR est déjà facturé : la facture, elle, ne change pas.@endif
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

<form method="POST" action="{{ route('ordres-reparations.corriger.enregistrer', $or) }}" class="space-y-5">
@csrf @method('PUT')

<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4">Réception</h3>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Type d'OR</label>
            @if($garantie)
            <p class="text-sm text-slate-700 bg-gray-50 rounded-xl px-3 py-2.5">{{ $or->getTypeLabel() }} — géré par l'équipe garantie</p>
            @else
            <select name="type" id="type" onchange="majEntretien()" class="{{ $champ }}">
                @foreach(['normal' => 'Normal', 'entretien' => 'Entretien périodique', 'sinistre' => 'Sinistre'] as $val => $label)
                <option value="{{ $val }}" {{ old('type', $or->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @endif
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Urgence</label>
            <select name="urgence" class="{{ $champ }}">
                @foreach(['normal' => 'Normal', 'urgent' => 'Urgent', 'tres_urgent' => 'Très urgent'] as $val => $label)
                <option value="{{ $val }}" {{ old('urgence', $or->urgence) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Kilométrage d'entrée <span class="text-red-500">*</span></label>
            <input type="number" name="kilometrage_entree" min="0" required value="{{ old('kilometrage_entree', $or->kilometrage_entree) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Niveau de carburant</label>
            <select name="niveau_carburant" class="{{ $champ }}">
                @foreach(['vide' => 'Vide', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4', 'plein' => 'Plein'] as $val => $label)
                <option value="{{ $val }}" {{ old('niveau_carburant', $or->niveau_carburant) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Date d'entrée <span class="text-red-500">*</span></label>
            <input type="date" name="date_entree" required value="{{ old('date_entree', $or->date_entree?->format('Y-m-d')) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Heure d'entrée</label>
            <input type="time" name="heure_entree" value="{{ old('heure_entree', $or->heure_entree ? substr($or->heure_entree, 0, 5) : '') }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Sortie prévue</label>
            <input type="date" name="date_sortie_prevue" value="{{ old('date_sortie_prevue', $or->date_sortie_prevue?->format('Y-m-d')) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Type de moteur du véhicule</label>
            <select name="type_moteur_id" class="{{ $champ }}">
                <option value="">— Non renseigné —</option>
                @foreach($typesMoteur as $tm)
                <option value="{{ $tm->id }}" {{ (int) old('type_moteur_id', $or->vehicule->type_moteur_id) === $tm->id ? 'selected' : '' }}>{{ $tm->modele }} — moteur {{ $tm->code }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mt-4">
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Motif de l'intervention</label>
        <textarea name="motif_entree" rows="2" class="{{ $champ }}">{{ old('motif_entree', $or->motif_entree) }}</textarea>
    </div>
    <div class="mt-4">
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Notes internes</label>
        <textarea name="notes_internes" rows="2" class="{{ $champ }}">{{ old('notes_internes', $or->notes_internes) }}</textarea>
    </div>
</div>

{{-- Entretien périodique : palier recalculé --}}
<div id="bloc-entretien" class="bg-white rounded-2xl border border-gray-200 p-6 {{ old('type', $or->type) === 'entretien' ? '' : 'hidden' }}">
    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-1">Palier d'entretien</h3>
    <p class="text-xs text-slate-400 mb-4">
        Actuel : {{ $or->entretien_km_seuil ? number_format($or->entretien_km_seuil, 0, ',', ' ') . ' km' : 'aucun (type de moteur manquant ?)' }}.
        Par défaut, il est recalculé comme à la réception (kilométrage OU délai en mois à la date d'entrée), à partir du type de moteur choisi ci-dessus.
    </p>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Palier</label>
            <select name="palier_manuel" class="{{ $champ }}">
                <option value="">Recalcul automatique</option>
                @foreach($paliers as $km)
                <option value="{{ $km }}" {{ (string) old('palier_manuel') === (string) $km ? 'selected' : '' }}>{{ number_format($km, 0, ',', ' ') }} km</option>
                @endforeach
            </select>
            @if($paliers->isEmpty())
            <p class="text-xs text-slate-400 mt-1">Choisissez d'abord le type de moteur et enregistrez : la liste des paliers apparaîtra.</p>
            @endif
        </div>
        <label class="flex items-start gap-2 cursor-pointer bg-gray-50 rounded-xl px-3 py-3 self-end">
            <input type="checkbox" name="ajouter_pieces_palier" value="1" {{ old('ajouter_pieces_palier') ? 'checked' : '' }} class="w-4 h-4 mt-0.5 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
            <span class="text-xs text-slate-600">Ajouter au devis les pièces du palier qui n'y sont pas encore (prix à venir du fournisseur).</span>
        </label>
    </div>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">
        Enregistrer la correction
    </button>
    <a href="{{ route('ordres-reparations.show', $or) }}"
       class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
        Annuler
    </a>
</div>
</form>
</div>

<script>
function majEntretien() {
    const type = document.getElementById('type');
    if (!type) return;
    document.getElementById('bloc-entretien').classList.toggle('hidden', type.value !== 'entretien');
}
</script>
@endsection
