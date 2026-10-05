@extends('layouts.app')
@section('title', 'Devis en avance')
@section('page-title', 'Devis en avance')
@section('page-subtitle', $reservation ? 'Réservation ' . $reservation->numero . ' — ' . $reservation->client->nom_complet : 'Devis libre — sans réception')

@section('header-actions')
<a href="{{ $reservation ? route('reservations.show', $reservation) : route('devis.index') }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour
</a>
@endsection

@section('content')
@php
    $typeDefaut = old('type', $reservation
        ? ($reservation->canal_service === 'entretien_periodique' ? 'entretien_periodique' : 'service_rapide')
        : 'entretien_periodique');
@endphp
<div class="max-w-3xl space-y-5">

@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
    <ul class="space-y-1">
        @foreach($errors->all() as $e)
        <li class="text-sm text-red-700">• {{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('devis-avance.store') }}" class="space-y-5">
@csrf

{{-- Client & véhicule --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4">Client & véhicule</h3>

    @if($reservation)
    <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
    <div class="grid grid-cols-3 gap-4 text-sm">
        <div>
            <p class="text-xs text-slate-400 mb-1">Client</p>
            <p class="font-semibold text-slate-800">{{ $reservation->client->nom_complet }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Véhicule</p>
            <p class="font-mono font-bold text-slate-800">{{ $reservation->vehicule->immatriculation }}</p>
            <p class="text-xs text-slate-500">{{ $reservation->vehicule->marque }} {{ $reservation->vehicule->modele }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Rendez-vous</p>
            <p class="font-semibold text-slate-800">{{ $reservation->date_rdv->format('d/m/Y') }}@if($reservation->heure_rdv) à {{ substr($reservation->heure_rdv, 0, 5) }}@endif</p>
            <p class="text-xs text-slate-500">{{ $reservation->getCanalServiceLabel() }}</p>
        </div>
    </div>
    @if($reservation->vehicule->date_mise_circulation)
    <p class="text-xs text-slate-400 mt-3">Mise en circulation : {{ $reservation->vehicule->date_mise_circulation->format('d/m/Y') }} — le délai en mois du barème est compté jusqu'à la date du rendez-vous.</p>
    @endif
    @else
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Client <span class="text-red-500">*</span></label>
            <select name="client_id" id="client_id" required onchange="chargerVehicules(this.value)"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                <option value="">— Choisir un client —</option>
                @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>{{ $c->nom_complet }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Véhicule <span class="text-red-500">*</span></label>
            <select name="vehicule_id" id="vehicule_id" required onchange="vehiculeChoisi()"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                <option value="">— Choisir d'abord le client —</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Date prévue de la venue</label>
            <input type="date" name="date_prevue" value="{{ old('date_prevue', now()->format('Y-m-d')) }}"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            <p class="text-xs text-slate-400 mt-1">Sert à calculer le délai en mois du barème d'entretien.</p>
        </div>
        <div id="info-vehicule" class="text-xs text-slate-500 self-center"></div>
    </div>
    @endif
</div>

{{-- Type de prestation --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4">Prestation</h3>
    <input type="hidden" name="type" id="type" value="{{ $typeDefaut }}">
    <div class="grid grid-cols-3 gap-3 mb-5">
        @foreach(['entretien_periodique' => ['Entretien périodique', 'Palier du barème + pièces'], 'service_rapide' => ['Service Rapide', 'Tarif fixe du service'], 'libre' => ['Autres travaux', 'Lignes saisies à la main']] as $val => [$titre, $aide])
        <button type="button" onclick="choisirType('{{ $val }}')" data-type="{{ $val }}"
                class="type-btn text-left border-2 rounded-xl p-3 transition-all {{ $typeDefaut === $val ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-gray-300' }}">
            <p class="text-sm font-bold text-slate-800">{{ $titre }}</p>
            <p class="text-xs text-slate-500">{{ $aide }}</p>
        </button>
        @endforeach
    </div>

    {{-- Entretien périodique --}}
    <div id="bloc-entretien_periodique" class="bloc-type space-y-4 {{ $typeDefaut === 'entretien_periodique' ? '' : 'hidden' }}">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Kilométrage prévu le jour du RDV <span class="text-red-500">*</span></label>
                <input type="number" name="kilometrage_prevu" id="kilometrage_prevu" min="0" step="1" value="{{ old('kilometrage_prevu') }}"
                       placeholder="ex : 20 000"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-slate-400 mt-1" id="km-actuel">
                    @if($reservation && $reservation->vehicule->kilometrage)
                    Dernier kilométrage connu : {{ number_format($reservation->vehicule->kilometrage, 0, ',', ' ') }} km
                    @endif
                </p>
            </div>
            <div id="bloc-type-moteur" class="{{ $reservation && $reservation->vehicule->type_moteur_id ? 'hidden' : '' }}">
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Type de moteur</label>
                <select name="type_moteur_id"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                    <option value="">— Choisir —</option>
                    @foreach($typesMoteur as $tm)
                    <option value="{{ $tm->id }}" {{ old('type_moteur_id') == $tm->id ? 'selected' : '' }}>{{ $tm->modele }} — moteur {{ $tm->code }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Nécessaire si le véhicule n'a pas encore de type de moteur.</p>
            </div>
        </div>
        <p class="text-xs text-slate-500 bg-gray-50 rounded-xl p-3">
            Le palier est trouvé comme à la réception : le kilométrage prévu <strong>ou</strong> le délai en mois depuis la mise en circulation (compté jusqu'à la date du RDV), le premier atteint. Le devis contient la main-d'œuvre au tarif fixe et les pièces du palier ; le prix des pièces est complété dès la réponse du fournisseur.
        </p>
    </div>

    {{-- Service Rapide --}}
    <div id="bloc-service_rapide" class="bloc-type {{ $typeDefaut === 'service_rapide' ? '' : 'hidden' }}">
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Service <span class="text-red-500">*</span></label>
        <select name="service_cle"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
            <option value="">— Choisir un service —</option>
            @foreach($services as $cle => $service)
            <option value="{{ $cle }}" {{ old('service_cle', $reservation?->service_cle) === $cle ? 'selected' : '' }}>
                {{ $service['label'] }} — {{ number_format(\App\Services\ReservationService::tarif($cle), 0, ',', ' ') }} FDJ
            </option>
            @endforeach
        </select>
    </div>

    {{-- Autres travaux --}}
    <div id="bloc-libre" class="bloc-type {{ $typeDefaut === 'libre' ? '' : 'hidden' }}">
        <p class="text-xs text-slate-500 bg-gray-50 rounded-xl p-3">Le devis est créé vide : le formulaire s'ouvre ensuite pour saisir les lignes de main-d'œuvre et de pièces.</p>
    </div>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">
        Créer le devis
    </button>
    <a href="{{ $reservation ? route('reservations.show', $reservation) : route('devis.index') }}"
       class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
        Annuler
    </a>
</div>
</form>
</div>

<script>
function choisirType(val) {
    document.getElementById('type').value = val;
    document.querySelectorAll('.type-btn').forEach(btn => {
        const actif = btn.dataset.type === val;
        btn.classList.toggle('border-orange-500', actif);
        btn.classList.toggle('bg-orange-50', actif);
        btn.classList.toggle('border-gray-200', !actif);
    });
    document.querySelectorAll('.bloc-type').forEach(b => b.classList.add('hidden'));
    document.getElementById('bloc-' + val).classList.remove('hidden');
}

let vehiculesClient = [];

function chargerVehicules(clientId) {
    const select = document.getElementById('vehicule_id');
    select.innerHTML = '<option value="">Chargement…</option>';
    if (!clientId) { select.innerHTML = '<option value="">— Choisir d\'abord le client —</option>'; return; }
    fetch('/api/clients/' + clientId + '/vehicules', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(liste => {
            vehiculesClient = liste;
            select.innerHTML = '<option value="">— Choisir un véhicule —</option>' + liste.map(v =>
                '<option value="' + v.id + '">' + v.immatriculation + ' — ' + (v.marque || '') + ' ' + (v.modele || '') + '</option>'
            ).join('');
            const ancien = @json(old('vehicule_id'));
            if (ancien) { select.value = ancien; vehiculeChoisi(); }
        });
}

function vehiculeChoisi() {
    const id = document.getElementById('vehicule_id').value;
    const v = vehiculesClient.find(x => String(x.id) === String(id));
    const info = document.getElementById('info-vehicule');
    const kmActuel = document.getElementById('km-actuel');
    if (!v) { info.textContent = ''; kmActuel.textContent = ''; return; }
    info.textContent = v.date_mise_circulation ? 'Mise en circulation : ' + v.date_mise_circulation.split('-').reverse().join('/') : 'Date de mise en circulation inconnue';
    kmActuel.textContent = v.kilometrage ? 'Dernier kilométrage connu : ' + Number(v.kilometrage).toLocaleString('fr-FR') + ' km' : '';
    document.getElementById('bloc-type-moteur').classList.toggle('hidden', !!v.type_moteur_id);
}

@if(! $reservation && old('client_id'))
document.addEventListener('DOMContentLoaded', () => chargerVehicules(@json(old('client_id'))));
@endif
</script>
@endsection
