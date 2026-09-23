@extends('layouts.app')
@section('title', 'Nouvelle réservation')
@section('page-title', 'Nouvelle réservation')
@section('page-subtitle', 'RDV Service Rapide')

@section('header-actions')
<a href="{{ route('reception.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour
</a>
@endsection

@section('content')
<form method="POST" action="{{ route('reservations.store') }}" id="form-reservation">
@csrf

@if($errors->any())
<div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
    <ul class="space-y-1">
        @foreach($errors->all() as $e)
        <li class="text-sm text-red-700 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>{{ $e }}
        </li>
        @endforeach
    </ul>
</div>
@endif

<div class="max-w-3xl space-y-5">

{{-- Client & Véhicule --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Client & Véhicule</h3>
    </div>
    <div class="p-6 grid grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Client <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                <select name="client_id" id="client_id"
                        class="flex-1 min-w-0 px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white @error('client_id') border-red-400 @enderror"
                        onchange="chargerVehicules(this.value)">
                    <option value="">— Sélectionner —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ old('client_id', $clientPreselectionneId) == $c->id ? 'selected' : '' }}>{{ $c->nom_complet }}</option>
                    @endforeach
                </select>
                <button type="button" onclick="ouvrirModalClient()"
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl transition-colors">
                    + Nouveau
                </button>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Véhicule <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                <select name="vehicule_id" id="vehicule_id"
                        class="flex-1 min-w-0 px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white @error('vehicule_id') border-red-400 @enderror">
                    <option value="">— D'abord sélectionner un client —</option>
                </select>
                <button type="button" id="btn_nouveau_vehicule" onclick="ouvrirModalVehicule()" disabled
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    + Nouveau
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Détails RDV --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Détails du rendez-vous</h3>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Type de service <span class="text-red-500">*</span></label>
            <select name="canal_service" id="canal_service_select" onchange="toggleServicesAutre()" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="entretien_periodique" {{ old('canal_service', $canalService) === 'entretien_periodique' ? 'selected' : '' }}>Entretien périodique</option>
                <option value="autre" {{ old('canal_service', $canalService) === 'autre' ? 'selected' : '' }}>Autre (filtres, pneus, lavage...)</option>
            </select>
        </div>
        <div id="bloc_services_autre" class="{{ old('canal_service', $canalService) === 'autre' ? '' : 'hidden' }}">
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Choisir un service (optionnel — remplit la tâche et la durée)</label>
            <select id="service_catalogue_select" onchange="choisirServiceCatalogue()"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="">— Ou saisir librement ci-dessous —</option>
                @foreach($servicesRapides as $cle => $s)
                <option value="{{ $s['label'] }}" data-cle="{{ $cle }}" data-duree="{{ \App\Services\ReservationService::dureeDefautHeures($cle) }}"
                        {{ old('service_cle', request('service_cle')) === $cle ? 'selected' : '' }}>
                    {{ $s['label'] }} ({{ $s['duree_min'] }}–{{ $s['duree_max'] }} min)
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Tâche prévue <span class="text-red-500">*</span></label>
            <input type="text" name="tache" id="tache_input" value="{{ old('tache', request('tache')) }}" placeholder="Ex: Vidange + filtres, changement pneus avant..."
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('tache') border-red-400 @enderror">
            <input type="hidden" name="service_cle" id="service_cle_input" value="{{ old('service_cle', request('service_cle')) }}">
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Date <span class="text-red-500">*</span></label>
                <input type="date" name="date_rdv" value="{{ old('date_rdv', request('date', date('Y-m-d'))) }}" min="{{ date('Y-m-d') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('date_rdv') border-red-400 @enderror">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Heure <span class="text-red-500">*</span></label>
                <input type="time" name="heure_rdv" value="{{ old('heure_rdv', request('heure_rdv')) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('heure_rdv') border-red-400 @enderror">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Durée estimée (h) <span class="text-red-500">*</span></label>
                <input type="number" name="duree_estimee" id="duree_estimee_input" value="{{ old('duree_estimee', request('duree_estimee', 0.5)) }}" min="0.25" max="8" step="0.25"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('duree_estimee') border-red-400 @enderror">
            </div>
        </div>
        @if($capacite)
        <p class="text-xs text-slate-400">Capacité Service Rapide : {{ $capacite }} véhicule(s) en même temps. <a href="{{ route('reservations.planning') }}" class="text-orange-500 hover:underline">Voir le planning</a></p>
        @endif
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none">{{ old('notes') }}</textarea>
        </div>
    </div>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">
        Enregistrer la réservation
    </button>
    <a href="{{ route('reception.index') }}" class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
        Annuler
    </a>
</div>

</div>
</form>

{{-- ══ MODAL : Nouveau client ══════════════════════════════════ --}}
<div id="modal_client" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="fermerModalClient()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-bold text-slate-800">Nouveau client</h3>
                <button type="button" onclick="fermerModalClient()" class="text-slate-400 hover:text-slate-700">×</button>
            </div>
            <div class="p-6 space-y-4">
                <div id="modal_client_error" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nom <span class="text-red-500">*</span></label>
                        <input type="text" id="mc_nom" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Prénom</label>
                        <input type="text" id="mc_prenom" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Téléphone <span class="text-red-500">*</span></label>
                    <input type="tel" id="mc_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
            </div>
            <div class="px-6 pb-5 flex gap-3">
                <button type="button" onclick="sauvegarderClient()" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">Créer le client</button>
                <button type="button" onclick="fermerModalClient()" class="px-5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors">Annuler</button>
            </div>
        </div>
    </div>
</div>

{{-- ══ MODAL : Nouveau véhicule ═════════════════════════════════ --}}
<div id="modal_vehicule" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="fermerModalVehicule()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-bold text-slate-800">Nouveau véhicule</h3>
                <button type="button" onclick="fermerModalVehicule()" class="text-slate-400 hover:text-slate-700">×</button>
            </div>
            <div class="p-6 space-y-4">
                <div id="modal_vehicule_error" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Immatriculation <span class="text-red-500">*</span></label>
                        <input type="text" id="mv_immat" style="text-transform:uppercase" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Marque <span class="text-red-500">*</span></label>
                        <input type="text" id="mv_marque" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Modèle <span class="text-red-500">*</span></label>
                        <input type="text" id="mv_modele" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Date de mise en circulation <span class="text-red-500">*</span></label>
                        <input type="date" id="mv_date_mise_circulation" max="{{ date('Y-m-d') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Motorisation</label>
                        <select id="mv_motorisation" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="diesel">Diesel</option>
                            <option value="essence">Essence</option>
                            <option value="hybride">Hybride</option>
                            <option value="electrique">Électrique</option>
                            <option value="gpl">GPL</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie</label>
                        <select id="mv_categorie" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">Autre / non précisé</option>
                            <option value="pick-up">Pick-up</option>
                            <option value="suv">SUV</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Sous garantie constructeur ? <span class="text-red-500">*</span></label>
                        <select id="mv_sous_garantie" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Choix définitif — si "Non", ce véhicule ne pourra jamais être affecté à l'équipe garantie.</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">VIN / N° Châssis <span class="text-red-500">*</span></label>
                    <input type="text" id="mv_vin" placeholder="VF1…" maxlength="17" minlength="17"
                           style="text-transform:uppercase"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <p class="text-xs text-slate-400 mt-1">Exactement 17 caractères alphanumériques (ni plus, ni moins).</p>
                </div>
            </div>
            <div class="px-6 pb-5 flex gap-3">
                <button type="button" onclick="sauvegarderVehicule()" class="flex-1 bg-slate-700 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">Créer le véhicule</button>
                <button type="button" onclick="fermerModalVehicule()" class="px-5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors">Annuler</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';

function toggleServicesAutre() {
    const isAutre = document.getElementById('canal_service_select').value === 'autre';
    document.getElementById('bloc_services_autre').classList.toggle('hidden', !isAutre);
    if (!isAutre) document.getElementById('service_cle_input').value = '';
}

function choisirServiceCatalogue() {
    const sel = document.getElementById('service_catalogue_select');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) {
        document.getElementById('service_cle_input').value = '';
        return;
    }
    document.getElementById('tache_input').value = opt.value;
    document.getElementById('duree_estimee_input').value = opt.dataset.duree;
    document.getElementById('service_cle_input').value = opt.dataset.cle;
}

// Véhicule déjà connu (reporté depuis le wizard de réception) — sélectionné
// automatiquement une fois la liste des véhicules du client chargée ci-dessous.
const vehiculePreselectionneId = {{ old('vehicule_id', $vehiculePreselectionneId) ?? 'null' }};

function chargerVehicules(clientId) {
    const sel = document.getElementById('vehicule_id');
    const btnV = document.getElementById('btn_nouveau_vehicule');
    sel.innerHTML = '<option value="">Chargement...</option>';
    if (!clientId) {
        sel.innerHTML = '<option value="">— D\'abord sélectionner un client —</option>';
        btnV.disabled = true;
        return;
    }
    btnV.disabled = false;
    fetch(`/api/clients/${clientId}/vehicules`)
        .then(r => r.json())
        .then(vehicules => {
            sel.innerHTML = '<option value="">— Sélectionner un véhicule —</option>';
            vehicules.forEach(v => {
                const o = document.createElement('option');
                o.value = v.id;
                o.textContent = `${v.immatriculation} — ${v.marque} ${v.modele}`;
                sel.appendChild(o);
            });
            if (vehiculePreselectionneId) sel.value = vehiculePreselectionneId;
            if (vehicules.length === 0) {
                const o = document.createElement('option');
                o.disabled = true;
                o.textContent = '— Aucun véhicule — cliquez "+ Nouveau" —';
                sel.appendChild(o);
            }
        });
}

// Si un client était déjà sélectionné (retour après erreur de validation avec
// old('client_id')), on recharge ses véhicules et on active le bouton "+ Nouveau" —
// sinon le formulaire réaffiché reste bloqué avec le bouton désactivé.
document.addEventListener('DOMContentLoaded', () => {
    const clientId = document.getElementById('client_id').value;
    if (clientId) chargerVehicules(clientId);
});

function ouvrirModalClient() { document.getElementById('modal_client').classList.remove('hidden'); }
function fermerModalClient() { document.getElementById('modal_client').classList.add('hidden'); }
async function sauvegarderClient() {
    const errBox = document.getElementById('modal_client_error');
    errBox.classList.add('hidden');
    const body = {
        type: 'particulier',
        nom: document.getElementById('mc_nom').value.trim(),
        prenom: document.getElementById('mc_prenom').value.trim(),
        telephone: document.getElementById('mc_telephone').value.trim(),
    };
    try {
        const res = await fetch('/api/clients/rapide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            errBox.textContent = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Erreur.');
            errBox.style.whiteSpace = 'pre-line';
            errBox.classList.remove('hidden');
            return;
        }
        const sel = document.getElementById('client_id');
        const opt = document.createElement('option');
        opt.value = data.id;
        opt.textContent = data.nom_complet;
        sel.appendChild(opt);
        sel.value = data.id;
        chargerVehicules(data.id);
        fermerModalClient();
    } catch (e) {
        errBox.textContent = 'Erreur réseau. Réessayez.';
        errBox.classList.remove('hidden');
    }
}

function ouvrirModalVehicule() { document.getElementById('modal_vehicule').classList.remove('hidden'); }
function fermerModalVehicule() { document.getElementById('modal_vehicule').classList.add('hidden'); }
async function sauvegarderVehicule() {
    const errBox = document.getElementById('modal_vehicule_error');
    errBox.classList.add('hidden');
    const body = {
        client_id: document.getElementById('client_id').value,
        immatriculation: document.getElementById('mv_immat').value.trim().toUpperCase(),
        marque: document.getElementById('mv_marque').value.trim(),
        modele: document.getElementById('mv_modele').value.trim(),
        date_mise_circulation: document.getElementById('mv_date_mise_circulation').value,
        motorisation: document.getElementById('mv_motorisation').value,
        categorie: document.getElementById('mv_categorie').value || null,
        sous_garantie: document.getElementById('mv_sous_garantie').value,
        vin: document.getElementById('mv_vin').value.trim().toUpperCase(),
    };
    try {
        const res = await fetch('/api/vehicules/rapide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            errBox.textContent = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Erreur.');
            errBox.style.whiteSpace = 'pre-line';
            errBox.classList.remove('hidden');
            return;
        }
        const sel = document.getElementById('vehicule_id');
        const opt = document.createElement('option');
        opt.value = data.id;
        opt.textContent = `${data.immatriculation} — ${data.marque} ${data.modele}`;
        sel.appendChild(opt);
        sel.value = data.id;
        fermerModalVehicule();
    } catch (e) {
        errBox.textContent = 'Erreur réseau. Réessayez.';
        errBox.classList.remove('hidden');
    }
}
</script>
@endsection
