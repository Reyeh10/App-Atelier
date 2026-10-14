@extends('layouts.app')
@section('title', 'Nouveau dépannage')
@section('page-title', 'Nouveau dépannage')
@section('page-subtitle', 'Pièces et main-d\'œuvre sans réception ni devis')

@section('header-actions')
<a href="{{ route('depannages.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Dépannages
</a>
@endsection

@section('content')
@php $champ = 'w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white'; @endphp
<form method="POST" action="{{ route('depannages.store') }}" class="max-w-5xl space-y-5" id="form-depannage">
@csrf

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

{{-- Client et véhicule --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Client et véhicule</h3>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Client <span class="text-red-500">*</span></label>
            <select name="client_id" id="client_id" required onchange="chargerVehicules(this.value)" class="{{ $champ }}">
                <option value="">— Choisir un client —</option>
                @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>{{ $c->nom_complet }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Véhicule <span class="text-red-500">*</span></label>
            <select name="vehicule_id" id="vehicule_id" required onchange="vehiculeChoisi()" class="{{ $champ }}">
                <option value="">— Choisir d'abord le client —</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Date du dépannage <span class="text-red-500">*</span></label>
            <input type="date" name="date_depannage" required value="{{ old('date_depannage', now()->format('Y-m-d')) }}" class="{{ $champ }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Description (optionnel)</label>
            <input type="text" name="description" value="{{ old('description') }}" placeholder="Ex : panne sur la route, batterie changée sur place..." class="{{ $champ }}">
        </div>
    </div>
    <div id="info-leasing" class="hidden bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-700">
        Véhicule en <strong>leasing</strong> : pas de facture. Après réception du BT, les pièces vont au récap de sa liste ;
        la main-d'œuvre est couverte par le forfait mensuel et n'est pas comptée.
    </div>
</div>

{{-- Pièces --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Pièces</h3>
            <p class="text-xs text-slate-400 mt-0.5">Envoyées au magasin par un bon de commande — le prix vient du magasin</p>
        </div>
        <button type="button" onclick="ajouterPiece()" class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors">+ Pièce</button>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-slate-500">
                <th class="px-3 py-2 text-left">Désignation</th>
                <th class="px-3 py-2 text-left w-48">Référence</th>
                <th class="px-3 py-2 text-right w-28">Quantité</th>
                <th class="px-3 py-2 w-10"></th>
            </tr>
        </thead>
        <tbody id="corps-pieces"></tbody>
    </table>
</div>

{{-- Main-d'œuvre --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden" id="bloc-mo">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Main-d'œuvre</h3>
            <p class="text-xs text-slate-400 mt-0.5">Nombre d'heures × prix horaire HT</p>
        </div>
        <button type="button" onclick="ajouterMainOeuvre()" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors">+ Main d'œuvre</button>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-slate-500">
                <th class="px-3 py-2 text-left">Désignation</th>
                <th class="px-3 py-2 text-right w-28">Heures</th>
                <th class="px-3 py-2 text-right w-36">Prix HT (FDJ)</th>
                <th class="px-3 py-2 w-10"></th>
            </tr>
        </thead>
        <tbody id="corps-mo"></tbody>
    </table>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">
        Enregistrer le dépannage
    </button>
    <a href="{{ route('depannages.index') }}" class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">Annuler</a>
</div>
</form>

@push('scripts')
<script>
const URL_VEHICULES = @json(url('/api/clients'));
const champ = 'w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-orange-500';
let vehiculesClient = [];
let indexPiece = 0, indexMo = 0;

function boutonSupprimer() {
    return `<td class="px-3 py-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600">✕</button></td>`;
}

function ajouterPiece(p = {}) {
    const i = indexPiece++;
    const tr = document.createElement('tr');
    tr.className = 'border-b border-gray-100';
    tr.innerHTML = `
        <td class="px-3 py-2"><input type="text" name="pieces[${i}][designation]" required class="${champ}" placeholder="Ex : Batterie 70 Ah"></td>
        <td class="px-3 py-2"><input type="text" name="pieces[${i}][reference]" class="${champ} font-mono" placeholder="Réf."></td>
        <td class="px-3 py-2"><input type="number" name="pieces[${i}][quantite]" value="1" min="0.01" step="0.01" required class="${champ} text-right"></td>
        ${boutonSupprimer()}`;
    document.getElementById('corps-pieces').appendChild(tr);
    tr.querySelector('[name$="[designation]"]').value = p.designation || '';
    tr.querySelector('[name$="[reference]"]').value   = p.reference || '';
    if (p.quantite) tr.querySelector('[name$="[quantite]"]').value = p.quantite;
}

function ajouterMainOeuvre(m = {}) {
    const i = indexMo++;
    const tr = document.createElement('tr');
    tr.className = 'border-b border-gray-100';
    tr.innerHTML = `
        <td class="px-3 py-2"><input type="text" name="main_oeuvre[${i}][designation]" required class="${champ}" placeholder="Ex : Remplacement batterie"></td>
        <td class="px-3 py-2"><input type="number" name="main_oeuvre[${i}][quantite]" value="1" min="0.01" step="0.01" required class="${champ} text-right"></td>
        <td class="px-3 py-2"><input type="number" name="main_oeuvre[${i}][prix_unitaire]" value="0" min="0" step="1" required class="${champ} text-right"></td>
        ${boutonSupprimer()}`;
    document.getElementById('corps-mo').appendChild(tr);
    tr.querySelector('[name$="[designation]"]').value = m.designation || '';
    if (m.quantite) tr.querySelector('[name$="[quantite]"]').value = m.quantite;
    if (m.prix_unitaire) tr.querySelector('[name$="[prix_unitaire]"]').value = m.prix_unitaire;
}

function chargerVehicules(clientId) {
    const select = document.getElementById('vehicule_id');
    select.innerHTML = '<option value="">Chargement…</option>';
    vehiculeChoisi();
    if (!clientId) { select.innerHTML = '<option value="">— Choisir d\'abord le client —</option>'; return; }
    fetch(URL_VEHICULES + '/' + clientId + '/vehicules', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(liste => {
            vehiculesClient = liste;
            select.innerHTML = '<option value="">— Choisir un véhicule —</option>' + liste.map(v =>
                '<option value="' + v.id + '">' + v.immatriculation + ' — ' + (v.marque || '') + ' ' + (v.modele || '') + (v.leasing ? ' (leasing)' : '') + '</option>'
            ).join('');
            const ancien = @json(old('vehicule_id'));
            if (ancien) select.value = ancien;
            vehiculeChoisi();
        });
}

// Véhicule en leasing : pas de main-d'œuvre (couverte par le forfait), pièces au récap
function vehiculeChoisi() {
    const id = document.getElementById('vehicule_id').value;
    const v = vehiculesClient.find(x => String(x.id) === String(id));
    const leasing = !!(v && v.leasing);
    document.getElementById('info-leasing').classList.toggle('hidden', !leasing);
    document.getElementById('bloc-mo').classList.toggle('hidden', leasing);
    document.querySelectorAll('#corps-mo input').forEach(i => i.disabled = leasing);
}

// Après une erreur de validation : on remet les lignes saisies
Object.values(@json(old('pieces', []))).forEach(p => ajouterPiece(p));
Object.values(@json(old('main_oeuvre', []))).forEach(m => ajouterMainOeuvre(m));
if (!document.querySelector('#corps-pieces tr')) ajouterPiece();
if (document.getElementById('client_id').value) chargerVehicules(document.getElementById('client_id').value);
</script>
@endpush
@endsection
