@extends('layouts.app')
@section('title', 'Corriger ' . $reservation->numero)
@section('page-title', 'Corriger ' . $reservation->numero)
@section('page-subtitle', $reservation->client->nom_complet . ' — ' . $reservation->vehicule->immatriculation)

@section('header-actions')
<a href="{{ route('reservations.show', $reservation) }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour à la réservation
</a>
@endsection

@section('content')
@php $champ = 'w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white'; @endphp
<div class="max-w-3xl space-y-5">

<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
    Correction administrateur — sans les contrôles de capacité du planning. Enregistrée dans le journal d'activités.
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

<form method="POST" action="{{ route('reservations.corriger.enregistrer', $reservation) }}" class="space-y-5">
@csrf @method('PUT')
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Type</label>
            <select name="canal_service" id="canal_service" onchange="majService()" class="{{ $champ }}">
                <option value="entretien_periodique" {{ old('canal_service', $reservation->canal_service) === 'entretien_periodique' ? 'selected' : '' }}>Entretien périodique</option>
                <option value="autre" {{ old('canal_service', $reservation->canal_service) === 'autre' ? 'selected' : '' }}>Autre service rapide</option>
            </select>
        </div>
        <div id="bloc-service" class="{{ old('canal_service', $reservation->canal_service) === 'autre' ? '' : 'hidden' }}">
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Service</label>
            <select name="service_cle" class="{{ $champ }}">
                <option value="">— Choisir —</option>
                @foreach($services as $cle => $service)
                <option value="{{ $cle }}" {{ old('service_cle', $reservation->service_cle) === $cle ? 'selected' : '' }}>{{ $service['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Date du rendez-vous <span class="text-red-500">*</span></label>
            <input type="date" name="date_rdv" required value="{{ old('date_rdv', $reservation->date_rdv->format('Y-m-d')) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Heure <span class="text-red-500">*</span></label>
            <input type="time" name="heure_rdv" required value="{{ old('heure_rdv', $reservation->heure_rdv ? substr($reservation->heure_rdv, 0, 5) : '') }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Durée estimée (h) <span class="text-red-500">*</span></label>
            <input type="number" name="duree_estimee" required min="0.25" max="8" step="0.25" value="{{ old('duree_estimee', (float) $reservation->duree_estimee) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Statut</label>
            <select name="statut" class="{{ $champ }}">
                @foreach(['planifie' => 'Planifié', 'honore' => 'Honoré', 'annule' => 'Annulé', 'no_show' => 'Absent (no-show)'] as $val => $label)
                <option value="{{ $val }}" {{ old('statut', $reservation->statut) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mt-4">
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Tâche prévue <span class="text-red-500">*</span></label>
        <input type="text" name="tache" required maxlength="255" value="{{ old('tache', $reservation->tache) }}" class="{{ $champ }}">
    </div>
    <div class="mt-4">
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Notes</label>
        <textarea name="notes" rows="2" class="{{ $champ }}">{{ old('notes', $reservation->notes) }}</textarea>
    </div>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">Enregistrer la correction</button>
    <a href="{{ route('reservations.show', $reservation) }}" class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">Annuler</a>
</div>
</form>
</div>

<script>
function majService() {
    document.getElementById('bloc-service').classList.toggle('hidden', document.getElementById('canal_service').value !== 'autre');
}
</script>
@endsection
