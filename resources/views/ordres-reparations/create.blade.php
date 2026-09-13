@extends('layouts.app')
@section('title', 'Fiche de Réception')
@section('page-title', 'Fiche de Réception')
@section('page-subtitle', 'Enregistrement d\'un véhicule à l\'atelier')

@section('header-actions')
<a href="{{ route('ordres-reparations.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 transition-colors border border-gray-300 rounded-lg px-3 py-2">
    ← Retour
</a>
@endsection

@section('content')
<form method="POST" action="{{ route('ordres-reparations.store') }}" id="fiche-reception" enctype="multipart/form-data" onsubmit="return validerPhotosReception()">
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

<div class="max-w-5xl space-y-5">

{{-- ══════════════════════════════════════════════════════
     SECTION 1 — EN-TÊTE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="bg-slate-800 px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <span class="text-white font-bold text-sm tracking-wide uppercase">Fiche de Réception — STCD Motors</span>
        </div>
        <span class="text-slate-400 text-xs">N° sera généré automatiquement</span>
    </div>

    <div class="px-6 py-4 grid grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Date d'entrée <span class="text-red-500">*</span></label>
            <input type="date" name="date_entree" value="{{ old('date_entree', date('Y-m-d')) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('date_entree') border-red-400 @enderror">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Heure</label>
            <input type="time" name="heure_entree" value="{{ old('heure_entree', date('H:i')) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Réceptionniste</label>
            <input type="text" value="{{ auth()->user()->name }}" disabled
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-slate-500">
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SECTION 2 — CLIENT & VÉHICULE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Informations Client & Véhicule</h3>
    </div>
    <div class="p-6 grid grid-cols-2 gap-6">

        {{-- Client --}}
        <div class="space-y-4">
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-gray-100 pb-2">Client</h4>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nom / Raison sociale <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <select name="client_id" id="client_id"
                            class="flex-1 min-w-0 px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white @error('client_id') border-red-400 @enderror"
                            onchange="chargerInfoClient(this.value)">
                        <option value="">— Sélectionner —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}"
                                    data-tel="{{ $c->telephone }}"
                                    data-adresse="{{ $c->adresse }}"
                                    data-type="{{ $c->getTypeLabel() }}"
                                    {{ old('client_id', $clientSelectionne?->id) == $c->id ? 'selected' : '' }}>
                                {{ $c->nom_complet }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" onclick="ouvrirModalClient()"
                            class="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Nouveau
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Téléphone</label>
                    <input type="text" id="client_tel" readonly
                           value="{{ $clientSelectionne?->telephone }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Type</label>
                    <input type="text" id="client_type" readonly
                           value="{{ $clientSelectionne?->getTypeLabel() }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 text-slate-600">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Adresse</label>
                <input type="text" id="client_adresse" readonly
                       value="{{ $clientSelectionne?->adresse }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 text-slate-600">
            </div>
        </div>

        {{-- Véhicule --}}
        <div class="space-y-4">
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-gray-100 pb-2">Véhicule</h4>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Sélectionner le véhicule <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <select name="vehicule_id" id="vehicule_id"
                            class="flex-1 min-w-0 px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white @error('vehicule_id') border-red-400 @enderror"
                            onchange="chargerInfoVehicule(this)">
                        <option value="">{{ $clientSelectionne ? ($clientSelectionne->vehicules->isEmpty() ? '— Aucun véhicule — cliquez "+ Nouveau" —' : '— Sélectionner un véhicule —') : '— D\'abord sélectionner un client —' }}</option>
                        @if($clientSelectionne)
                            @foreach($clientSelectionne->vehicules as $v)
                                <option value="{{ $v->id }}"
                                        data-immat="{{ $v->immatriculation }}"
                                        data-marque="{{ $v->marque }}"
                                        data-modele="{{ $v->modele }}"
                                        data-vin="{{ $v->vin }}"
                                        data-km="{{ $v->kilometrage }}"
                                        data-garantie="{{ $v->estEligibleGarantie() ? '1' : '0' }}"
                                        data-garantie-sortie="{{ $v->garantie_sortie_le ? '1' : '0' }}"
                                        data-type-moteur-id="{{ $v->type_moteur_id }}"
                                        {{ old('vehicule_id', $vehiculeSelectionne?->id) == $v->id ? 'selected' : '' }}>
                                    {{ $v->immatriculation }} — {{ $v->marque }} {{ $v->modele }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <button type="button" id="btn_nouveau_vehicule" onclick="ouvrirModalVehicule()"
                            class="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            disabled title="Sélectionnez d'abord un client">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Nouveau
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Immatriculation</label>
                    <input type="text" id="v_immat" readonly
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 font-mono font-bold text-slate-800">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Marque</label>
                    <input type="text" id="v_marque" readonly
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Modèle</label>
                    <input type="text" id="v_modele" readonly
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">VIN / Châssis</label>
                    <input type="text" id="v_vin" readonly
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50 font-mono text-slate-500 text-xs">
                </div>
            </div>
            <div id="garantie_badge" class="hidden bg-green-50 border border-green-200 rounded-xl px-3 py-2">
                <p class="text-sm text-green-700 font-medium">✓ Véhicule éligible à la garantie constructeur</p>
            </div>
            <div id="garantie_sortie_badge" class="hidden bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                <p class="text-sm text-red-700 font-medium">✗ Ce véhicule a été signalé définitivement sorti de la garantie</p>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SECTION 3 — TYPE & MOTIF
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Motif de la visite / Réclamation du client</h3>
    </div>
    <div class="p-6 space-y-4">
        <input type="hidden" name="type" id="type_value" value="{{ old('type', request('type', 'normal')) }}">
        <div class="grid grid-cols-4 gap-3">
            @php $selectedType = old('type', request('type', 'normal')); @endphp
            @foreach(['normal' => 'Normal', 'garantie' => 'Garantie', 'sinistre' => 'Sinistre', 'entretien' => 'Entretien périodique'] as $val => $label)
            <button type="button" onclick="selectType('{{ $val }}')" data-type="{{ $val }}"
                    class="type-btn border-2 rounded-xl px-3 py-2.5 text-center text-sm font-medium transition-all
                           {{ $selectedType === $val ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-slate-600 hover:border-gray-300' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <div id="bloc_type_moteur" class="{{ $selectedType === 'entretien' ? '' : 'hidden' }}">
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Type de moteur <span class="text-red-500">*</span></label>
            <select name="type_moteur_id" id="type_moteur_id"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white @error('type_moteur_id') border-red-400 @enderror">
                <option value="">— Sélectionner le type de moteur —</option>
                @foreach(\App\Models\TypeMoteur::orderBy('modele')->get() as $tm)
                <option value="{{ $tm->id }}"
                        data-marque="{{ $tm->marque }}"
                        data-modele="{{ $tm->modele }}"
                        {{ old('type_moteur_id') == $tm->id ? 'selected' : '' }}>{{ $tm->modele }} — moteur {{ $tm->code }}</option>
                @endforeach
            </select>
            <p id="type_moteur_note" class="text-xs text-slate-400 mt-1">Le système déterminera automatiquement les pièces à remplacer et les points à contrôler selon le barème constructeur et le kilométrage.</p>
        </div>

        <div>
            <textarea name="motif_entree" rows="4"
                      placeholder="Décrivez précisément le motif de la visite et/ou la réclamation du client..."
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none @error('motif_entree') border-red-400 @enderror">{{ old('motif_entree') }}</textarea>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SECTION 4 — ÉTAT DU VÉHICULE À LA RÉCEPTION
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Véhicule à la Réception</h3>
    </div>
    <div class="p-6">

        {{-- Tableau état --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 mb-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-700 text-white">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold tracking-wider">ÉTAT</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">KILOMÉTRAGE</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">PROPRETÉ INTERNE</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">PROPRETÉ EXTERNE</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">CARBURANT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-gray-200">
                        <td class="px-4 py-3 font-semibold text-slate-700 bg-gray-50">À l'ENTRÉE</td>
                        <td class="px-4 py-3 text-center">
                            <input type="number" name="kilometrage_entree" value="{{ old('kilometrage_entree') }}"
                                   id="km_input" min="0" placeholder="0"
                                   class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-orange-500 @error('kilometrage_entree') border-red-400 @enderror">
                            <span class="text-xs text-slate-400 ml-1">km</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <select name="proprete_interne" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                                @foreach(['bon' => 'Bon', 'acceptable' => 'Acceptable', 'mauvais' => 'Mauvais'] as $v => $l)
                                    <option value="{{ $v }}" {{ old('proprete_interne', 'bon') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <select name="proprete_externe" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                                @foreach(['bon' => 'Bon', 'acceptable' => 'Acceptable', 'mauvais' => 'Mauvais'] as $v => $l)
                                    <option value="{{ $v }}" {{ old('proprete_externe', 'bon') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            {{-- Jauge carburant visuelle --}}
                            <input type="hidden" name="niveau_carburant" id="carburant_value" value="{{ old('niveau_carburant', '1/2') }}">
                            <div class="flex flex-col items-center gap-1">
                                <div class="flex items-center gap-1">
                                    @php $selectedCarburant = old('niveau_carburant', '1/2'); @endphp
                                    @foreach(['vide' => 'V', '1/4' => '¼', '1/2' => '½', '3/4' => '¾', 'plein' => 'F'] as $val => $label)
                                    <button type="button" onclick="selectCarburant('{{ $val }}')" data-carburant="{{ $val }}"
                                            class="carburant-btn w-8 h-8 border-2 rounded flex items-center justify-center text-xs font-bold transition-all
                                                   {{ $selectedCarburant === $val ? 'border-orange-500 bg-orange-500 text-white' : 'border-gray-300 text-gray-400 hover:border-orange-300' }}">
                                        {{ $label }}
                                    </button>
                                    @endforeach
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                    <div id="jauge_bar" class="bg-orange-500 h-2 rounded-full transition-all" style="width: {{ ['vide'=>'5%','1/4'=>'25%','1/2'=>'50%','3/4'=>'75%','plein'=>'100%'][old('niveau_carburant','1/2')] }}"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Observations extérieures --}}
        <div class="mb-6">
            <label class="block text-sm font-medium text-slate-700 mb-2">Observations / État extérieur</label>
            <textarea name="etat_exterieur" rows="2"
                      placeholder="Rayures, bosses, bris de glace, impacts... noter tout dommage visible"
                      class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none">{{ old('etat_exterieur') }}</textarea>
        </div>

        {{-- Schéma voiture interactif --}}
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-3">
                Schéma des dommages
                <span class="text-xs text-slate-400 font-normal ml-2">— Cliquer sur les zones endommagées</span>
            </label>
            <input type="hidden" name="dommages_carrosserie" id="dommages_data" value="{{ old('dommages_carrosserie', '[]') }}">
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex gap-8 justify-center items-center">

                {{-- Vue de dessus --}}
                <div class="relative">
                    <style>
                        .damage-zone { fill: transparent; cursor: pointer; transition: fill 0.12s; }
                        .damage-zone:hover { fill: rgba(251,146,60,0.25); }
                        .damage-zone.damaged { fill: rgba(239,68,68,0.50) !important; stroke: rgba(185,28,28,0.6); stroke-width: 1px; }
                        .damage-zone.damaged:hover { fill: rgba(185,28,28,0.62) !important; }
                        .zone-label { pointer-events: none; user-select: none; }
                    </style>
                    <p class="text-xs text-center text-slate-400 mb-2 font-medium">Vue de dessus — cliquer sur une zone</p>
                    <svg width="200" height="330" viewBox="0 0 200 330" class="car-schema">

                        <!-- ── Carrosserie de base ── -->
                        <rect x="52" y="24" width="96" height="282" rx="22" fill="#dbeafe" stroke="#93c5fd" stroke-width="2"/>
                        <!-- Toit (zone centrale intérieure) -->
                        <rect x="64" y="90" width="72" height="138" rx="7" fill="#bfdbfe" stroke="#93c5fd" stroke-width="1.2"/>
                        <!-- Pare-brise avant -->
                        <rect x="62" y="64" width="76" height="28" rx="4" fill="#e0f2fe" stroke="#7dd3fc" stroke-width="1.2"/>
                        <!-- Lunette arrière -->
                        <rect x="62" y="226" width="76" height="22" rx="4" fill="#e0f2fe" stroke="#7dd3fc" stroke-width="1.2"/>
                        <!-- Roues avant -->
                        <rect x="24" y="34"  width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>
                        <rect x="150" y="34" width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>
                        <!-- Roues arrière -->
                        <rect x="24" y="240"  width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>
                        <rect x="150" y="240" width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>

                        <!-- ── Séparateurs de zones (lignes pointillées) ── -->
                        <!-- Horizontales -->
                        <line x1="52"  y1="52"  x2="148" y2="52"  stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                        <line x1="52"  y1="92"  x2="148" y2="92"  stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                        <line x1="52"  y1="164" x2="148" y2="164" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                        <line x1="52"  y1="228" x2="148" y2="228" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                        <line x1="52"  y1="268" x2="148" y2="268" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                        <!-- Verticales (entre y=52 et y=268) -->
                        <line x1="74"  y1="52"  x2="74"  y2="268" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                        <line x1="126" y1="52"  x2="126" y2="268" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>

                        <!-- ── Zones cliquables (13 zones précises) ── -->

                        <!-- 1. Pare-choc avant -->
                        <rect id="z_pc_avant" x="52" y="24" width="96" height="28" rx="16"
                              class="damage-zone" data-zone="Pare-choc avant" onclick="toggleDamage(this)"/>

                        <!-- 2. Aile avant gauche -->
                        <rect id="z_aile_avant_gauche" x="52" y="52" width="22" height="40"
                              class="damage-zone" data-zone="Aile avant gauche" onclick="toggleDamage(this)"/>

                        <!-- 3. Capot -->
                        <rect id="z_capot" x="74" y="52" width="52" height="40"
                              class="damage-zone" data-zone="Capot" onclick="toggleDamage(this)"/>

                        <!-- 4. Aile avant droite -->
                        <rect id="z_aile_avant_droite" x="126" y="52" width="22" height="40"
                              class="damage-zone" data-zone="Aile avant droite" onclick="toggleDamage(this)"/>

                        <!-- 5. Porte avant gauche -->
                        <rect id="z_porte_avant_gauche" x="52" y="92" width="22" height="72"
                              class="damage-zone" data-zone="Porte avant gauche" onclick="toggleDamage(this)"/>

                        <!-- 6. Toit (centre) -->
                        <rect id="z_toit" x="74" y="92" width="52" height="136"
                              class="damage-zone" data-zone="Toit" onclick="toggleDamage(this)"/>

                        <!-- 7. Porte avant droite -->
                        <rect id="z_porte_avant_droite" x="126" y="92" width="22" height="72"
                              class="damage-zone" data-zone="Porte avant droite" onclick="toggleDamage(this)"/>

                        <!-- 8. Porte arrière gauche -->
                        <rect id="z_porte_arriere_gauche" x="52" y="164" width="22" height="64"
                              class="damage-zone" data-zone="Porte arrière gauche" onclick="toggleDamage(this)"/>

                        <!-- 9. Porte arrière droite -->
                        <rect id="z_porte_arriere_droite" x="126" y="164" width="22" height="64"
                              class="damage-zone" data-zone="Porte arrière droite" onclick="toggleDamage(this)"/>

                        <!-- 10. Aile arrière gauche -->
                        <rect id="z_aile_arriere_gauche" x="52" y="228" width="22" height="40"
                              class="damage-zone" data-zone="Aile arrière gauche" onclick="toggleDamage(this)"/>

                        <!-- 11. Coffre -->
                        <rect id="z_coffre" x="74" y="228" width="52" height="40"
                              class="damage-zone" data-zone="Coffre" onclick="toggleDamage(this)"/>

                        <!-- 12. Aile arrière droite -->
                        <rect id="z_aile_arriere_droite" x="126" y="228" width="22" height="40"
                              class="damage-zone" data-zone="Aile arrière droite" onclick="toggleDamage(this)"/>

                        <!-- 13. Pare-choc arrière -->
                        <rect id="z_pc_arriere" x="52" y="268" width="96" height="38" rx="16"
                              class="damage-zone" data-zone="Pare-choc arrière" onclick="toggleDamage(this)"/>

                        <!-- ── Labels des zones (pointer-events désactivés) ── -->
                        <text x="100" y="41"  text-anchor="middle" font-size="6.5" fill="#1e40af" class="zone-label">Pare-choc avant</text>

                        <text x="63"  y="69"  text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(-90,63,69)">Aile AV.G</text>
                        <text x="100" y="75"  text-anchor="middle" font-size="7"   fill="#1e40af" class="zone-label">Capot</text>
                        <text x="137" y="69"  text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(90,137,69)">Aile AV.D</text>

                        <text x="63"  y="128" text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(-90,63,128)">Porte AV.G</text>
                        <text x="100" y="163" text-anchor="middle" font-size="7.5" fill="#1e40af" class="zone-label">Toit</text>
                        <text x="137" y="128" text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(90,137,128)">Porte AV.D</text>

                        <text x="63"  y="196" text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(-90,63,196)">Porte AR.G</text>
                        <text x="137" y="196" text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(90,137,196)">Porte AR.D</text>

                        <text x="63"  y="249" text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(-90,63,249)">Aile AR.G</text>
                        <text x="100" y="251" text-anchor="middle" font-size="7"   fill="#1e40af" class="zone-label">Coffre</text>
                        <text x="137" y="249" text-anchor="middle" font-size="5.5" fill="#1e40af" class="zone-label" transform="rotate(90,137,249)">Aile AR.D</text>

                        <text x="100" y="291" text-anchor="middle" font-size="6.5" fill="#1e40af" class="zone-label">Pare-choc arrière</text>

                        <!-- ── Boussole directionnelle ── -->
                        <text x="100" y="15"  text-anchor="middle" font-size="9" fill="#64748b" font-weight="bold">AVANT</text>
                        <text x="100" y="320" text-anchor="middle" font-size="9" fill="#64748b" font-weight="bold">ARRIÈRE</text>
                        <text x="8"   y="168" text-anchor="middle" font-size="8" fill="#64748b" transform="rotate(-90,8,168)">GAUCHE</text>
                        <text x="192" y="168" text-anchor="middle" font-size="8" fill="#64748b" transform="rotate(90,192,168)">DROIT</text>
                    </svg>
                </div>

                {{-- Légende --}}
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-slate-600">Zones endommagées :</p>
                    <div id="dommages_liste" class="space-y-1.5 min-w-36">
                        <p class="text-xs text-slate-400 italic" id="no_dommage_msg">Aucun dommage marqué</p>
                    </div>
                    <div class="mt-4 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <div class="w-4 h-4 rounded bg-red-400 opacity-70"></div>
                            <span>Zone endommagée</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <div class="w-4 h-4 rounded bg-gray-200 border border-gray-300"></div>
                            <span>Zone intacte</span>
                        </div>
                    </div>
                    <button type="button" onclick="resetDommages()"
                            class="mt-2 text-xs text-red-500 hover:text-red-700 underline">
                        Réinitialiser le schéma
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SECTION 4B — PHOTOS DU VÉHICULE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Photos du véhicule à la réception <span class="text-red-500">*</span></h3>
        <span class="text-xs text-slate-400">Obligatoire — max 10 photos, 8 Mo chacune</span>
    </div>
    <div class="p-6">
        <div id="photo-drop-zone"
             class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center transition-colors"
             ondragover="event.preventDefault(); this.classList.add('border-orange-400','bg-orange-50')"
             ondragleave="this.classList.remove('border-orange-400','bg-orange-50')"
             ondrop="handlePhotoDrop(event)">
            <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
            </svg>
            <p class="text-sm font-medium text-slate-600 mb-1">Glisser-déposer des photos, ou :</p>
            <div class="flex items-center justify-center gap-3 mt-3">
                <button type="button" onclick="document.getElementById('photos_vehicule_input').click()"
                        class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-orange-400 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                    </svg>
                    Importer des photos
                </button>
                <button type="button" onclick="ouvrirCapturePhoto('photo_camera_input', afficherApercusPhotos)"
                        class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                    </svg>
                    Prendre une photo
                </button>
            </div>
            <p class="text-xs text-slate-400 mt-3">JPG, PNG, WEBP — jusqu'à 10 photos</p>
            <input type="file" id="photos_vehicule_input" name="photos_vehicule[]" multiple
                   accept="image/jpeg,image/png,image/webp" class="hidden"
                   onchange="afficherApercusPhotos(this.files)">
            <input type="file" id="photo_camera_input" accept="image/*" capture="environment" class="hidden"
                   onchange="afficherApercusPhotos(this.files)">
        </div>

        {{-- Aperçus photos --}}
        <div id="photos-preview" class="mt-4 grid grid-cols-4 gap-3 hidden"></div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SECTION 5 — ÉQUIPEMENTS
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Équipements présents dans le véhicule</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-3 gap-3">
            @php
            $equipements_liste = [
                'roue_secours'   => 'Roue de secours',
                'cric'           => 'Cric',
                'cle_roue'       => 'Clé de roue',
                'cable_demarrage'=> 'Câble de démarrage',
                'triangle'       => 'Triangle de signalisation',
                'gilet'          => 'Gilet réfléchissant',
                'extincteur'     => 'Extincteur',
                'trousse_secours'=> 'Trousse de secours',
                'carnet_bord'    => 'Carnet de bord',
                'carte_grise'    => 'Carte grise',
                'autoradio'      => 'Autoradio / façade',
                'autre_equip'    => 'Autre',
            ];
            $oldEquip = old('equipements', []);
            @endphp
            @foreach($equipements_liste as $key => $label)
            <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 hover:border-orange-300 transition-colors">
                <input type="checkbox" name="equipements[]" value="{{ $key }}"
                       {{ in_array($key, $oldEquip) ? 'checked' : '' }}
                       class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                <span class="text-sm text-slate-700">{{ $label }}</span>
            </label>
            @endforeach
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Précisions sur les accessoires</label>
            <input type="text" name="liste_accessoires" value="{{ old('liste_accessoires') }}"
                   placeholder="Ex: autoradio marque Sony, câble USB, GPS..."
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
    </div>
</div>

{{-- Urgence par défaut (champ requis côté serveur) --}}
<input type="hidden" name="urgence" value="normal">

{{-- ══════════════════════════════════════════════════════
     SECTION 7 — NOTES & SIGNATURE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Notes & Validation</h3>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes internes (non visibles par le client)</label>
            <textarea name="notes_internes" rows="2"
                      class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"
                      placeholder="Instructions spéciales, remarques internes...">{{ old('notes_internes') }}</textarea>
        </div>

        <div class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl">
            <input type="checkbox" name="signature_client" value="1" id="signature_client"
                   {{ old('signature_client') ? 'checked' : '' }}
                   class="w-5 h-5 text-blue-500 border-gray-300 rounded focus:ring-blue-500">
            <label for="signature_client" class="text-sm text-blue-800 cursor-pointer">
                <span class="font-semibold">Signature du client obtenue</span> — Le client a été informé de l'état du véhicule à la réception et a signé la fiche physique.
            </label>
        </div>
    </div>
</div>

{{-- Boutons --}}
<div class="flex items-center gap-3 pb-6">
    <button type="submit"
            class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">
        Enregistrer la fiche de réception
    </button>
    <a href="{{ route('ordres-reparations.index') }}"
       class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
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
                <button type="button" onclick="fermerModalClient()" class="text-slate-400 hover:text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div id="modal_client_error" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700"></div>

                {{-- Type --}}
                <input type="hidden" id="mc_type_value" value="particulier">
                <div class="flex gap-3">
                    @foreach(['particulier' => 'Particulier', 'societe' => 'Société', 'assurance' => 'Assurance'] as $val => $lbl)
                    <button type="button" onclick="selectClientType('{{ $val }}')" data-mc-type="{{ $val }}"
                            class="mc-type-btn flex-1 border-2 rounded-xl px-3 py-2 text-center text-xs font-medium transition-all
                                   {{ $val === 'particulier' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-slate-600 hover:border-gray-300' }}">
                        {{ $lbl }}
                    </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div id="mc_nom_wrap">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nom <span class="text-red-500">*</span></label>
                        <input type="text" id="mc_nom" placeholder="Nom de famille"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div id="mc_prenom_wrap">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Prénom</label>
                        <input type="text" id="mc_prenom" placeholder="Prénom"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
                <div id="mc_rs_wrap" class="hidden">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Raison sociale <span class="text-red-500">*</span></label>
                    <input type="text" id="mc_raison_sociale" placeholder="Nom de l'entreprise"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Téléphone <span class="text-red-500">*</span></label>
                        <input type="tel" id="mc_telephone" placeholder="0555 000 000"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                        <input type="email" id="mc_email" placeholder="email@exemple.com"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Adresse</label>
                    <input type="text" id="mc_adresse" placeholder="Adresse complète"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
            </div>
            <div class="px-6 pb-5 flex gap-3">
                <button type="button" onclick="sauvegarderClient()"
                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                    Créer le client
                </button>
                <button type="button" onclick="fermerModalClient()"
                        class="px-5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors">
                    Annuler
                </button>
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
                <button type="button" onclick="fermerModalVehicule()" class="text-slate-400 hover:text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div id="modal_vehicule_error" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700"></div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Immatriculation <span class="text-red-500">*</span></label>
                        <input type="text" id="mv_immat" placeholder="123-A-456" style="text-transform:uppercase"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Kilométrage actuel</label>
                        <input type="number" id="mv_km" placeholder="0" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Marque <span class="text-red-500">*</span></label>
                        <input type="text" id="mv_marque" placeholder="Toyota, Renault..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Modèle <span class="text-red-500">*</span></label>
                        <input type="text" id="mv_modele" placeholder="Corolla, Clio..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Année</label>
                        <input type="number" id="mv_annee" placeholder="{{ date('Y') }}" min="1950" max="{{ date('Y') + 1 }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Date de mise en circulation <span class="text-red-500">*</span></label>
                        <input type="date" id="mv_date_mise_circulation" max="{{ date('Y-m-d') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Motorisation <span class="text-red-500">*</span></label>
                        <select id="mv_motorisation"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
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
                        <select id="mv_categorie"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">Autre / non précisé</option>
                            <option value="pick-up">Pick-up</option>
                            <option value="suv">SUV</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Sous garantie constructeur ? <span class="text-red-500">*</span></label>
                        <select id="mv_sous_garantie"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
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
                <button type="button" onclick="sauvegarderVehicule()"
                        class="flex-1 bg-slate-700 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                    Créer le véhicule
                </button>
                <button type="button" onclick="fermerModalVehicule()"
                        class="px-5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors">
                    Annuler
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══ MODAL : Capture webcam (PC — sur mobile/tablette, l'appareil photo natif s'ouvre directement) ══ --}}
<div id="modal_webcam" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70" onclick="fermerWebcam()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-bold text-slate-800">Prendre une photo</h3>
                <button type="button" onclick="fermerWebcam()" class="text-slate-400 hover:text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-4">
                <div id="webcam_error" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 mb-3"></div>
                <video id="webcam_video" autoplay playsinline class="w-full rounded-xl bg-black"></video>
                <canvas id="webcam_canvas" class="hidden"></canvas>
            </div>
            <div class="px-6 pb-5 flex gap-3">
                <button type="button" onclick="capturerPhotoWebcam()"
                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                    📷 Capturer
                </button>
                <button type="button" onclick="fermerWebcam()"
                        class="px-5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors">
                    Annuler
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';

// ── Info client ────────────────────────────────────────
function chargerInfoClient(clientId) {
    const opt = document.querySelector(`#client_id option[value="${clientId}"]`);
    document.getElementById('client_tel').value     = opt?.dataset.tel     || '';
    document.getElementById('client_adresse').value = opt?.dataset.adresse || '';
    document.getElementById('client_type').value    = opt?.dataset.type    || '';

    const sel = document.getElementById('vehicule_id');
    sel.innerHTML = '<option value="">Chargement...</option>';
    ['v_immat','v_marque','v_modele','v_vin'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('garantie_badge').classList.add('hidden');
    document.getElementById('garantie_sortie_badge').classList.add('hidden');

    const btnV = document.getElementById('btn_nouveau_vehicule');
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
                o.dataset.immat   = v.immatriculation;
                o.dataset.marque  = v.marque;
                o.dataset.modele  = v.modele;
                o.dataset.vin     = v.vin || '';
                o.dataset.km      = v.kilometrage || 0;
                o.dataset.garantie = v.eligible_garantie ? '1' : '0';
                o.dataset.garantieSortie = v.garantie_sortie ? '1' : '0';
                o.dataset.typeMoteurId = v.type_moteur_id || '';
                o.textContent = `${v.immatriculation} — ${v.marque} ${v.modele}`;
                sel.appendChild(o);
            });
            if (vehicules.length === 0) {
                const o = document.createElement('option');
                o.disabled = true;
                o.textContent = '— Aucun véhicule — cliquez "+ Nouveau" —';
                sel.appendChild(o);
            }
        });
}

// ── Info véhicule ──────────────────────────────────────
function chargerInfoVehicule(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('v_immat').value  = opt?.dataset.immat  || '';
    document.getElementById('v_marque').value = opt?.dataset.marque || '';
    document.getElementById('v_modele').value = opt?.dataset.modele || '';
    document.getElementById('v_vin').value    = opt?.dataset.vin    || '';
    document.getElementById('km_input').value = opt?.dataset.km     || '';
    const badge = document.getElementById('garantie_badge');
    if (opt?.dataset.garantie === '1') badge.classList.remove('hidden');
    else badge.classList.add('hidden');
    const badgeSortie = document.getElementById('garantie_sortie_badge');
    if (opt?.dataset.garantieSortie === '1') badgeSortie.classList.remove('hidden');
    else badgeSortie.classList.add('hidden');

    filtrerTypeMoteur(opt?.dataset.typeMoteurId || '');
}

// ── Filtre la liste des types de moteur selon la marque/modèle du véhicule reçu ──
// Comparaison souple (contient / est contenu) car marque et modèle sont du texte libre.
function filtrerTypeMoteur(typeMoteurIdMemorise) {
    const vSel = document.getElementById('vehicule_id');
    const vOpt = vSel?.options[vSel.selectedIndex];
    const marqueVehicule = (vOpt?.dataset.marque || '').toLowerCase().trim();
    const modeleVehicule = (vOpt?.dataset.modele || '').toLowerCase().trim();

    const moteurSel = document.getElementById('type_moteur_id');
    if (!moteurSel) return;
    const options = Array.from(moteurSel.options).filter(o => o.value !== '');

    let nbVisibles = 0;
    options.forEach(o => {
        const modeleType = (o.dataset.modele || '').toLowerCase().trim();
        const correspond = modeleVehicule && modeleType && (
            modeleVehicule.includes(modeleType) || modeleType.includes(modeleVehicule)
        );
        o.hidden = modeleVehicule !== '' && !correspond;
        if (!o.hidden) nbVisibles++;
    });

    const note = document.getElementById('type_moteur_note');
    if (modeleVehicule !== '' && nbVisibles === 0) {
        // Aucune correspondance : on ne perd rien, on réaffiche tout pour choisir à la main
        options.forEach(o => o.hidden = false);
        if (note) note.textContent = "Aucun barème catalogué ne correspond automatiquement à ce modèle — sélectionnez manuellement, ou faites ajouter le barème du constructeur pour ce modèle.";
        if (note) note.classList.add('text-amber-500');
    } else if (note) {
        note.textContent = 'Le système déterminera automatiquement les pièces à remplacer et les points à contrôler selon le barème constructeur et le kilométrage.';
        note.classList.remove('text-amber-500');
    }

    // Pré-remplit le type de moteur déjà mémorisé sur ce véhicule (modifiable),
    // sinon sélectionne automatiquement s'il n'y a qu'une seule correspondance.
    if (typeMoteurIdMemorise) {
        moteurSel.value = typeMoteurIdMemorise;
    } else if (nbVisibles === 1) {
        moteurSel.value = options.find(o => !o.hidden).value;
    } else {
        moteurSel.value = '';
    }
}

// ── Sélection type visite ──────────────────────────────
function selectType(val) {
    document.getElementById('type_value').value = val;
    document.querySelectorAll('.type-btn').forEach(btn => {
        const active = btn.dataset.type === val;
        btn.classList.toggle('border-orange-500', active);
        btn.classList.toggle('bg-orange-50',      active);
        btn.classList.toggle('text-orange-700',   active);
        btn.classList.toggle('border-gray-200',   !active);
        btn.classList.toggle('text-slate-600',    !active);
    });

    const blocMoteur = document.getElementById('bloc_type_moteur');
    if (blocMoteur) blocMoteur.classList.toggle('hidden', val !== 'entretien');
    if (val === 'entretien') {
        const vSel = document.getElementById('vehicule_id');
        const vOpt = vSel?.options[vSel.selectedIndex];
        filtrerTypeMoteur(vOpt?.dataset.typeMoteurId || '');
    }
}

// ── Sélection urgence ──────────────────────────────────
function selectUrgence(val) {
    document.getElementById('urgence_value').value = val;
    document.querySelectorAll('.urgence-btn').forEach(btn => {
        const active = btn.dataset.urgence === val;
        btn.classList.toggle('border-orange-500', active);
        btn.classList.toggle('bg-orange-50',      active);
        btn.classList.toggle('text-orange-700',   active);
        btn.classList.toggle('border-gray-200',   !active);
        btn.classList.toggle('text-slate-600',    !active);
    });
}

// ── Jauge carburant ────────────────────────────────────
const jaugeWidths = { 'vide': '5%', '1/4': '25%', '1/2': '50%', '3/4': '75%', 'plein': '100%' };
function selectCarburant(val) {
    document.getElementById('carburant_value').value = val;
    document.getElementById('jauge_bar').style.width = jaugeWidths[val] || '50%';
    document.querySelectorAll('.carburant-btn').forEach(btn => {
        const active = btn.dataset.carburant === val;
        btn.classList.toggle('border-orange-500', active);
        btn.classList.toggle('bg-orange-500',     active);
        btn.classList.toggle('text-white',        active);
        btn.classList.toggle('border-gray-300',   !active);
        btn.classList.toggle('text-gray-400',     !active);
    });
}

// ── Schéma dommages ────────────────────────────────────
let dommages = new Set();
try {
    const existing = JSON.parse(document.getElementById('dommages_data').value || '[]');
    if (Array.isArray(existing)) existing.forEach(d => {
        dommages.add(d);
        const el = document.querySelector('[data-zone="' + d + '"]');
        if (el) el.classList.add('damaged');
    });
} catch(e) {}

function toggleDamage(el) {
    const zone = el.dataset.zone;
    if (dommages.has(zone)) {
        dommages.delete(zone);
        el.classList.remove('damaged');
    } else {
        dommages.add(zone);
        el.classList.add('damaged');
    }
    syncDommages();
}

function syncDommages() {
    const arr = [...dommages];
    document.getElementById('dommages_data').value = JSON.stringify(arr);
    const liste = document.getElementById('dommages_liste');
    const msg   = document.getElementById('no_dommage_msg');
    if (arr.length === 0) {
        msg.style.display = 'block';
        liste.querySelectorAll('.dommage-item').forEach(el => el.remove());
    } else {
        msg.style.display = 'none';
        liste.querySelectorAll('.dommage-item').forEach(el => el.remove());
        arr.forEach(zone => {
            const div = document.createElement('div');
            div.className = 'dommage-item flex items-center gap-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-2 py-1';
            div.innerHTML = `<span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span> ${zone}`;
            liste.appendChild(div);
        });
    }
}

function resetDommages() {
    dommages.clear();
    document.querySelectorAll('.damage-zone').forEach(el => el.classList.remove('damaged'));
    syncDommages();
}

// ── Modal client ────────────────────────────────────────
function ouvrirModalClient() {
    document.getElementById('modal_client').classList.remove('hidden');
    document.getElementById('mc_nom').focus();
    document.getElementById('modal_client_error').classList.add('hidden');
}
function fermerModalClient() {
    document.getElementById('modal_client').classList.add('hidden');
}
function selectClientType(type) {
    document.getElementById('mc_type_value').value = type;
    document.querySelectorAll('.mc-type-btn').forEach(btn => {
        const active = btn.dataset.mcType === type;
        btn.classList.toggle('border-orange-500', active);
        btn.classList.toggle('bg-orange-50',      active);
        btn.classList.toggle('text-orange-700',   active);
        btn.classList.toggle('border-gray-200',   !active);
        btn.classList.toggle('text-slate-600',    !active);
    });
    const isSociete = type === 'societe' || type === 'assurance';
    document.getElementById('mc_nom_wrap').classList.toggle('hidden', isSociete);
    document.getElementById('mc_prenom_wrap').classList.toggle('hidden', isSociete);
    document.getElementById('mc_rs_wrap').classList.toggle('hidden', !isSociete);
}
async function sauvegarderClient() {
    const type  = document.getElementById('mc_type_value').value || 'particulier';
    const isSoc = type === 'societe' || type === 'assurance';
    const errBox = document.getElementById('modal_client_error');
    errBox.classList.add('hidden');

    const body = {
        type,
        nom:            isSoc ? (document.getElementById('mc_raison_sociale').value.trim() || ' ') : document.getElementById('mc_nom').value.trim(),
        prenom:         isSoc ? null : document.getElementById('mc_prenom').value.trim(),
        raison_sociale: isSoc ? document.getElementById('mc_raison_sociale').value.trim() : null,
        telephone:      document.getElementById('mc_telephone').value.trim(),
        email:          document.getElementById('mc_email').value.trim(),
        adresse:        document.getElementById('mc_adresse').value.trim(),
    };

    try {
        const res  = await fetch('/api/clients/rapide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            const msgs = data.errors
                ? Object.values(data.errors).flat().join('\n')
                : (data.message || 'Une erreur est survenue. Vérifiez les informations saisies.');
            errBox.style.whiteSpace = 'pre-line';
            errBox.textContent = msgs;
            errBox.classList.remove('hidden');
            return;
        }
        // Ajouter au select et sélectionner
        const sel = document.getElementById('client_id');
        const opt = document.createElement('option');
        opt.value = data.id;
        opt.dataset.tel     = data.telephone;
        opt.dataset.adresse = data.adresse;
        opt.dataset.type    = data.type_label || data.type || '';
        opt.textContent = data.nom_complet;
        sel.appendChild(opt);
        sel.value = data.id;
        chargerInfoClient(data.id);
        fermerModalClient();
        // Reset fields
        ['mc_nom','mc_prenom','mc_raison_sociale','mc_telephone','mc_email','mc_adresse'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    } catch(e) {
        errBox.textContent = 'Erreur réseau. Réessayez.';
        errBox.classList.remove('hidden');
    }
}

// ── Modal véhicule ──────────────────────────────────────
function ouvrirModalVehicule() {
    document.getElementById('modal_vehicule').classList.remove('hidden');
    document.getElementById('mv_immat').focus();
    document.getElementById('modal_vehicule_error').classList.add('hidden');
}
function fermerModalVehicule() {
    document.getElementById('modal_vehicule').classList.add('hidden');
}
async function sauvegarderVehicule() {
    const clientId = document.getElementById('client_id').value;
    const errBox   = document.getElementById('modal_vehicule_error');
    errBox.classList.add('hidden');

    const vin = document.getElementById('mv_vin').value.trim().toUpperCase();
    if (!/^[A-Z0-9]{17}$/.test(vin)) {
        errBox.textContent = 'Le numéro de châssis (VIN) est obligatoire et doit contenir exactement 17 caractères alphanumériques (ni plus, ni moins).';
        errBox.classList.remove('hidden');
        return;
    }

    const dateMiseCirculation = document.getElementById('mv_date_mise_circulation').value;
    if (!dateMiseCirculation) {
        errBox.textContent = 'La date de mise en circulation est obligatoire.';
        errBox.classList.remove('hidden');
        return;
    }

    const body = {
        client_id:       clientId,
        immatriculation: document.getElementById('mv_immat').value.trim().toUpperCase(),
        marque:          document.getElementById('mv_marque').value.trim(),
        modele:          document.getElementById('mv_modele').value.trim(),
        annee:           document.getElementById('mv_annee').value || null,
        motorisation:    document.getElementById('mv_motorisation').value,
        categorie:       document.getElementById('mv_categorie').value || null,
        sous_garantie:   document.getElementById('mv_sous_garantie').value,
        vin:             vin,
        date_mise_circulation: dateMiseCirculation,
        kilometrage:     document.getElementById('mv_km').value || 0,
    };

    try {
        const res  = await fetch('/api/vehicules/rapide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            const msgs = data.errors
                ? Object.values(data.errors).flat().join('\n')
                : (data.message || 'Une erreur est survenue. Vérifiez les informations saisies.');
            errBox.style.whiteSpace = 'pre-line';
            errBox.textContent = msgs;
            errBox.classList.remove('hidden');
            return;
        }
        // Ajouter au select véhicule et sélectionner
        const sel = document.getElementById('vehicule_id');
        const opt = document.createElement('option');
        opt.value            = data.id;
        opt.dataset.immat    = data.immatriculation;
        opt.dataset.marque   = data.marque;
        opt.dataset.modele   = data.modele;
        opt.dataset.vin      = data.vin || '';
        opt.dataset.km       = data.kilometrage || 0;
        opt.dataset.garantie = data.sous_garantie === '1' ? '1' : '0';
        opt.dataset.garantieSortie = '0';
        opt.textContent = `${data.immatriculation} — ${data.marque} ${data.modele}`;
        sel.appendChild(opt);
        sel.value = data.id;
        chargerInfoVehicule(sel);
        fermerModalVehicule();
        ['mv_immat','mv_marque','mv_modele','mv_annee','mv_vin','mv_km','mv_date_mise_circulation'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('mv_categorie').value = '';
        document.getElementById('mv_sous_garantie').value = '0';
    } catch(e) {
        errBox.textContent = 'Erreur réseau. Réessayez.';
        errBox.classList.remove('hidden');
    }
}

// Init véhicule si pré-sélectionné
document.addEventListener('DOMContentLoaded', () => {
    const v = document.getElementById('vehicule_id');
    if (v.value) chargerInfoVehicule(v);
    const c = document.getElementById('client_id');
    if (c.value) document.getElementById('btn_nouveau_vehicule').disabled = false;
});

// ── Photos véhicule ─────────────────────────────────────
let photosSelectionnees = new DataTransfer();

function validerPhotosReception() {
    if (photosSelectionnees.files.length === 0) {
        alert('Au moins une photo du véhicule est obligatoire à la réception.');
        document.getElementById('photo-drop-zone').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}

// ── Capture photo : appareil photo natif sur mobile/tablette (écran tactile),
//    webcam en direct sur PC (pas de "capture" natif via un simple input file) ──
let webcamStream = null;
let webcamCallback = null;

function ouvrirCapturePhoto(inputCameraId, callbackAjout) {
    if (window.matchMedia('(pointer: coarse)').matches) {
        document.getElementById(inputCameraId).click();
        return;
    }
    webcamCallback = callbackAjout;
    const errBox = document.getElementById('webcam_error');
    errBox.classList.add('hidden');
    document.getElementById('modal_webcam').classList.remove('hidden');
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            webcamStream = stream;
            document.getElementById('webcam_video').srcObject = stream;
        })
        .catch(() => {
            errBox.textContent = 'Impossible d\'accéder à la caméra — vérifiez les autorisations du navigateur, ou utilisez "Importer des photos".';
            errBox.classList.remove('hidden');
        });
}

function fermerWebcam() {
    document.getElementById('modal_webcam').classList.add('hidden');
    if (webcamStream) {
        webcamStream.getTracks().forEach(t => t.stop());
        webcamStream = null;
    }
}

function capturerPhotoWebcam() {
    const video = document.getElementById('webcam_video');
    if (!video.videoWidth) return;
    const canvas = document.getElementById('webcam_canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        const file = new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
        const dt = new DataTransfer();
        dt.items.add(file);
        if (webcamCallback) webcamCallback(dt.files);
        fermerWebcam();
    }, 'image/jpeg', 0.9);
}

function afficherApercusPhotos(files) {
    for (const file of files) {
        if (photosSelectionnees.files.length >= 10) break;
        photosSelectionnees.items.add(file);
    }
    document.getElementById('photos_vehicule_input').files = photosSelectionnees.files;
    renderApercus();
}

function supprimerApercu(index) {
    const dt = new DataTransfer();
    const files = photosSelectionnees.files;
    for (let i = 0; i < files.length; i++) {
        if (i !== index) dt.items.add(files[i]);
    }
    photosSelectionnees = dt;
    document.getElementById('photos_vehicule_input').files = photosSelectionnees.files;
    renderApercus();
}

function renderApercus() {
    const container = document.getElementById('photos-preview');
    const files = photosSelectionnees.files;
    container.innerHTML = '';
    if (files.length === 0) { container.classList.add('hidden'); return; }
    container.classList.remove('hidden');
    Array.from(files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'relative group';
            div.innerHTML = `
                <img src="${e.target.result}" class="w-full h-24 object-cover rounded-xl border border-gray-200">
                <button type="button" onclick="supprimerApercu(${i})"
                        class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs hidden group-hover:flex items-center justify-center font-bold leading-none">×</button>
                <p class="text-xs text-slate-400 truncate mt-1">${file.name}</p>
            `;
            container.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function handlePhotoDrop(event) {
    event.preventDefault();
    document.getElementById('photo-drop-zone').classList.remove('border-orange-400','bg-orange-50');
    afficherApercusPhotos(event.dataTransfer.files);
}
</script>
@endsection
