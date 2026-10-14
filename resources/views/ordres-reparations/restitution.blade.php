@extends('layouts.app')
@section('title', 'Restitution — ' . $or->numero)
@section('page-title', 'Fiche de Restitution')
@section('page-subtitle', $or->numero . ' — ' . $or->client->nom_complet . ' / ' . $or->vehicule->immatriculation)

@section('header-actions')
<a href="{{ route('ordres-reparations.show', $or) }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour à l'OR
</a>
@endsection

@section('content')

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

<form method="POST" action="{{ route('ordres-reparations.restituer', $or) }}" enctype="multipart/form-data" onsubmit="return validerPhotosSortie()">
@csrf

<div class="max-w-5xl space-y-5">

{{-- ══════════════════════════════════════════════════════
     EN-TÊTE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="bg-slate-800 px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <span class="text-white font-bold text-sm tracking-wide uppercase">Fiche de Restitution — STCD Motors</span>
        </div>
        <span class="text-slate-400 text-xs font-mono">{{ $or->numero }}</span>
    </div>
    <div class="px-6 py-4 grid grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Date de restitution <span class="text-red-500">*</span></label>
            <input type="date" name="date_sortie_reelle" value="{{ old('date_sortie_reelle', date('Y-m-d')) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 @error('date_sortie_reelle') border-red-400 @enderror">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Réceptionniste</label>
            <input type="text" value="{{ auth()->user()->name }}" disabled
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-slate-500">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Client</label>
            <input type="text" value="{{ $or->client->nom_complet }}" disabled
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-slate-700 font-medium">
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     COMPARATIF ENTRÉE / SORTIE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">État du véhicule — Comparatif</h3>
    </div>
    <div class="overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-700 text-white">
                    <th class="px-4 py-2.5 text-left text-xs font-semibold tracking-wider w-36">ÉTAT</th>
                    <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">KILOMÉTRAGE</th>
                    <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">PROPRETÉ INTERNE</th>
                    <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">PROPRETÉ EXTERNE</th>
                    <th class="px-4 py-2.5 text-center text-xs font-semibold tracking-wider">CARBURANT</th>
                </tr>
            </thead>
            <tbody>
                {{-- Ligne Entrée (lecture seule, référence) --}}
                <tr class="border-b border-gray-200 bg-blue-50">
                    <td class="px-4 py-3 font-semibold text-blue-700 text-xs uppercase tracking-wide">À L'ENTRÉE</td>
                    <td class="px-4 py-3 text-center font-bold text-slate-700">{{ number_format($or->kilometrage_entree) }} km</td>
                    <td class="px-4 py-3 text-center text-slate-600">{{ ucfirst($or->proprete_interne) }}</td>
                    <td class="px-4 py-3 text-center text-slate-600">{{ ucfirst($or->proprete_externe) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-slate-700 font-medium">{{ $or->niveau_carburant }}</span>
                        <div class="w-20 mx-auto bg-gray-200 rounded-full h-2 mt-1">
                            <div class="bg-blue-400 h-2 rounded-full" style="width: {{ ['vide'=>'5%','1/4'=>'25%','1/2'=>'50%','3/4'=>'75%','plein'=>'100%'][$or->niveau_carburant] ?? '50%' }}"></div>
                        </div>
                    </td>
                </tr>
                {{-- Ligne Sortie (à remplir) --}}
                <tr class="border-b border-gray-200">
                    <td class="px-4 py-3 font-semibold text-green-700 text-xs uppercase tracking-wide">À LA SORTIE <span class="text-red-500">*</span></td>
                    <td class="px-4 py-3 text-center">
                        <input type="number" name="kilometrage_sortie" value="{{ old('kilometrage_sortie', $or->kilometrage_entree) }}"
                               min="{{ $or->kilometrage_entree }}" placeholder="0"
                               class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-green-500 @error('kilometrage_sortie') border-red-400 @enderror">
                        <span class="text-xs text-slate-400 ml-1">km</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <select name="proprete_interne_sortie" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 bg-white @error('proprete_interne_sortie') border-red-400 @enderror">
                            @foreach(['bon' => 'Bon', 'acceptable' => 'Acceptable', 'mauvais' => 'Mauvais'] as $v => $l)
                                <option value="{{ $v }}" {{ old('proprete_interne_sortie', $or->proprete_interne) === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <select name="proprete_externe_sortie" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 bg-white @error('proprete_externe_sortie') border-red-400 @enderror">
                            @foreach(['bon' => 'Bon', 'acceptable' => 'Acceptable', 'mauvais' => 'Mauvais'] as $v => $l)
                                <option value="{{ $v }}" {{ old('proprete_externe_sortie', $or->proprete_externe) === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <input type="hidden" name="niveau_carburant_sortie" id="carburant_sortie_value" value="{{ old('niveau_carburant_sortie', $or->niveau_carburant) }}">
                        <div class="flex flex-col items-center gap-1">
                            <div class="flex items-center gap-1">
                                @php $selectedCarburant = old('niveau_carburant_sortie', $or->niveau_carburant); @endphp
                                @foreach(['vide' => 'V', '1/4' => '¼', '1/2' => '½', '3/4' => '¾', 'plein' => 'F'] as $val => $label)
                                <button type="button" onclick="selectCarburantSortie('{{ $val }}')" data-carburant="{{ $val }}"
                                        class="carburant-sortie-btn w-8 h-8 border-2 rounded flex items-center justify-center text-xs font-bold transition-all
                                               {{ $selectedCarburant === $val ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 text-gray-400 hover:border-green-300' }}">
                                    {{ $label }}
                                </button>
                                @endforeach
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                <div id="jauge_sortie_bar" class="bg-green-500 h-2 rounded-full transition-all" style="width: {{ ['vide'=>'5%','1/4'=>'25%','1/2'=>'50%','3/4'=>'75%','plein'=>'100%'][$selectedCarburant] ?? '50%' }}"></div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     VÉRIFICATION DES ÉQUIPEMENTS
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Vérification des équipements à la restitution</h3>
        <span class="text-xs text-slate-400">Cocher uniquement ce qui est présent dans le véhicule</span>
    </div>
    <div class="p-6">
        @php
        $equipements_liste = [
            'roue_secours'    => 'Roue de secours',
            'cric'            => 'Cric',
            'cle_roue'        => 'Clé de roue',
            'cable_demarrage' => 'Câble de démarrage',
            'triangle'        => 'Triangle de signalisation',
            'gilet'           => 'Gilet réfléchissant',
            'extincteur'      => 'Extincteur',
            'trousse_secours' => 'Trousse de secours',
            'carnet_bord'     => 'Carnet de bord',
            'carte_grise'     => 'Carte grise',
            'autoradio'       => 'Autoradio / façade',
            'autre_equip'     => 'Autre',
        ];
        $equip_entree = $or->equipements ?? [];
        $equip_sortie_old = old('equipements_sortie', $equip_entree);
        @endphp
        <div class="grid grid-cols-3 gap-3">
            @foreach($equipements_liste as $key => $label)
            @php
                $wasPresent = in_array($key, $equip_entree);
                $isChecked  = in_array($key, (array) $equip_sortie_old);
            @endphp
            <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-colors
                          {{ $wasPresent ? 'border-blue-200 bg-blue-50 hover:border-green-300' : 'border-gray-200 bg-gray-50 hover:border-gray-300' }}">
                <input type="checkbox" name="equipements_sortie[]" value="{{ $key }}"
                       {{ $isChecked ? 'checked' : '' }}
                       class="w-4 h-4 text-green-500 border-gray-300 rounded focus:ring-green-500">
                <div class="flex-1 min-w-0">
                    <span class="text-sm text-slate-700">{{ $label }}</span>
                    @if($wasPresent)
                    <span class="block text-xs text-blue-500 font-medium">✓ Présent à l'entrée</span>
                    @else
                    <span class="block text-xs text-slate-400 italic">— Absent à l'entrée</span>
                    @endif
                </div>
            </label>
            @endforeach
        </div>

        @if($or->liste_accessoires)
        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
            <p class="text-xs font-semibold text-amber-700 mb-1">Accessoires notés à l'entrée :</p>
            <p class="text-sm text-amber-800">{{ $or->liste_accessoires }}</p>
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SCHÉMA DES DOMMAGES — comparatif entrée / sortie
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Schéma des dommages — Cliquer sur les nouvelles zones endommagées</h3>
        <p class="text-xs text-slate-400 mt-0.5">Les zones en orange étaient déjà signalées à l'entrée (référence, non modifiable ici).</p>
    </div>
    <div class="p-4">
        @php
            $zonesLabels = ['Pare-choc avant','Aile avant gauche','Capot','Aile avant droite','Porte avant gauche','Toit','Porte avant droite','Porte arrière gauche','Porte arrière droite','Aile arrière gauche','Coffre','Aile arrière droite','Pare-choc arrière'];
            $entreeHorsSchema = array_diff($or->dommages_carrosserie ?? [], $zonesLabels);
        @endphp
        @if(!empty($entreeHorsSchema))
        <div class="mb-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
            <p class="text-xs font-semibold text-amber-700 mb-1.5">Autres dommages notés à l'entrée (texte libre, hors schéma) :</p>
            <div class="flex flex-wrap gap-2">
                @foreach($entreeHorsSchema as $zone)
                <span class="inline-flex items-center gap-1.5 bg-white border border-amber-300 text-amber-800 text-xs px-2.5 py-1 rounded-full">{{ $zone }}</span>
                @endforeach
            </div>
        </div>
        @endif
        <input type="hidden" name="dommages_carrosserie_sortie" id="dommages_sortie_data" value="{{ old('dommages_carrosserie_sortie', '[]') }}">
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex gap-8 justify-center items-center">

            {{-- Vue de dessus --}}
            <div class="relative">
                <style>
                    .damage-zone { fill: transparent; cursor: pointer; transition: fill 0.12s; }
                    .damage-zone:hover { fill: rgba(251,146,60,0.25); }
                    .damage-zone.damaged { fill: rgba(239,68,68,0.50) !important; stroke: rgba(185,28,28,0.6); stroke-width: 1px; }
                    .damage-zone.damaged:hover { fill: rgba(185,28,28,0.62) !important; }
                    .damage-zone.damaged-entree { fill: rgba(245,158,11,0.35); stroke: rgba(180,83,9,0.6); stroke-width: 1px; }
                    .damage-zone.damaged-entree:hover { fill: rgba(245,158,11,0.45); }
                    .zone-label { pointer-events: none; user-select: none; }
                </style>
                <p class="text-xs text-center text-slate-400 mb-2 font-medium">Vue de dessus — cliquer sur une zone</p>
                <svg width="200" height="330" viewBox="0 0 200 330" class="car-schema">
                    <rect x="52" y="24" width="96" height="282" rx="22" fill="#dbeafe" stroke="#93c5fd" stroke-width="2"/>
                    <rect x="64" y="90" width="72" height="138" rx="7" fill="#bfdbfe" stroke="#93c5fd" stroke-width="1.2"/>
                    <rect x="62" y="64" width="76" height="28" rx="4" fill="#e0f2fe" stroke="#7dd3fc" stroke-width="1.2"/>
                    <rect x="62" y="226" width="76" height="22" rx="4" fill="#e0f2fe" stroke="#7dd3fc" stroke-width="1.2"/>
                    <rect x="24" y="34"  width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>
                    <rect x="150" y="34" width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>
                    <rect x="24" y="240"  width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>
                    <rect x="150" y="240" width="26" height="44" rx="6" fill="#334155" stroke="#1e293b" stroke-width="1"/>

                    <line x1="52"  y1="52"  x2="148" y2="52"  stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                    <line x1="52"  y1="92"  x2="148" y2="92"  stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                    <line x1="52"  y1="164" x2="148" y2="164" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                    <line x1="52"  y1="228" x2="148" y2="228" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                    <line x1="52"  y1="268" x2="148" y2="268" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                    <line x1="74"  y1="52"  x2="74"  y2="268" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>
                    <line x1="126" y1="52"  x2="126" y2="268" stroke="#60a5fa" stroke-width="1" stroke-dasharray="4,3"/>

                    @php $entreeDommages = $or->dommages_carrosserie ?? []; @endphp
                    @php
                        $zones = [
                            'z_pc_avant'            => ['x'=>52,'y'=>24,'w'=>96,'h'=>28,'rx'=>16, 'label'=>'Pare-choc avant'],
                            'z_aile_avant_gauche'   => ['x'=>52,'y'=>52,'w'=>22,'h'=>40, 'label'=>'Aile avant gauche'],
                            'z_capot'               => ['x'=>74,'y'=>52,'w'=>52,'h'=>40, 'label'=>'Capot'],
                            'z_aile_avant_droite'   => ['x'=>126,'y'=>52,'w'=>22,'h'=>40, 'label'=>'Aile avant droite'],
                            'z_porte_avant_gauche'  => ['x'=>52,'y'=>92,'w'=>22,'h'=>72, 'label'=>'Porte avant gauche'],
                            'z_toit'                => ['x'=>74,'y'=>92,'w'=>52,'h'=>136, 'label'=>'Toit'],
                            'z_porte_avant_droite'  => ['x'=>126,'y'=>92,'w'=>22,'h'=>72, 'label'=>'Porte avant droite'],
                            'z_porte_arriere_gauche'=> ['x'=>52,'y'=>164,'w'=>22,'h'=>64, 'label'=>'Porte arrière gauche'],
                            'z_porte_arriere_droite'=> ['x'=>126,'y'=>164,'w'=>22,'h'=>64, 'label'=>'Porte arrière droite'],
                            'z_aile_arriere_gauche' => ['x'=>52,'y'=>228,'w'=>22,'h'=>40, 'label'=>'Aile arrière gauche'],
                            'z_coffre'              => ['x'=>74,'y'=>228,'w'=>52,'h'=>40, 'label'=>'Coffre'],
                            'z_aile_arriere_droite' => ['x'=>126,'y'=>228,'w'=>22,'h'=>40, 'label'=>'Aile arrière droite'],
                            'z_pc_arriere'          => ['x'=>52,'y'=>268,'w'=>96,'h'=>38,'rx'=>16, 'label'=>'Pare-choc arrière'],
                        ];
                    @endphp
                    @foreach($zones as $id => $z)
                    <rect id="{{ $id }}" x="{{ $z['x'] }}" y="{{ $z['y'] }}" width="{{ $z['w'] }}" height="{{ $z['h'] }}" {{ isset($z['rx']) ? 'rx="'.$z['rx'].'"' : '' }}
                          class="damage-zone {{ in_array($z['label'], $entreeDommages) ? 'damaged-entree' : '' }}"
                          data-zone="{{ $z['label'] }}" data-entree="{{ in_array($z['label'], $entreeDommages) ? '1' : '0' }}"
                          onclick="toggleDamageSortie(this)"/>
                    @endforeach

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

                    <text x="100" y="15"  text-anchor="middle" font-size="9" fill="#64748b" font-weight="bold">AVANT</text>
                    <text x="100" y="320" text-anchor="middle" font-size="9" fill="#64748b" font-weight="bold">ARRIÈRE</text>
                    <text x="8"   y="168" text-anchor="middle" font-size="8" fill="#64748b" transform="rotate(-90,8,168)">GAUCHE</text>
                    <text x="192" y="168" text-anchor="middle" font-size="8" fill="#64748b" transform="rotate(90,192,168)">DROIT</text>
                </svg>
            </div>

            {{-- Légende --}}
            <div class="space-y-3">
                <p class="text-xs font-semibold text-slate-600">Zones endommagées :</p>
                <div id="dommages_sortie_liste" class="space-y-1.5 min-w-40">
                    <p class="text-xs text-slate-400 italic" id="no_dommage_sortie_msg">Aucune nouvelle zone marquée</p>
                </div>
                <div class="mt-4 space-y-1.5">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <div class="w-4 h-4 rounded bg-red-400 opacity-70"></div>
                        <span>Nouveau dommage (sortie)</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <div class="w-4 h-4 rounded bg-amber-400 opacity-70"></div>
                        <span>Déjà signalé à l'entrée</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <div class="w-4 h-4 rounded bg-gray-200 border border-gray-300"></div>
                        <span>Zone intacte</span>
                    </div>
                </div>
                <button type="button" onclick="resetDommagesSortie()" class="mt-2 text-xs text-red-500 hover:text-red-700 underline">
                    Réinitialiser
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     PHOTOS — comparatif entrée / sortie
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Photos du véhicule <span class="text-red-500">*</span></h3>
        <span class="text-xs text-slate-400">Obligatoire à la restitution — max 10 photos, 8 Mo chacune</span>
    </div>
    <div class="p-6 space-y-5">
        @php $photosEntree = $or->photosOr->where('moment', 'entree'); @endphp
        @if($photosEntree->isNotEmpty())
        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Photos prises à la réception (référence)</p>
            <div class="grid grid-cols-4 gap-3">
                @foreach($photosEntree as $photo)
                <a href="{{ $photo->url() }}" target="_blank">
                    <img src="{{ $photo->url() }}" class="w-full h-24 object-cover rounded-xl border border-gray-200">
                </a>
                @endforeach
            </div>
        </div>
        @endif

        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Nouvelles photos à la restitution</p>
            <div id="photo-drop-zone"
                 class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center transition-colors"
                 ondragover="event.preventDefault(); this.classList.add('border-green-400','bg-green-50')"
                 ondragleave="this.classList.remove('border-green-400','bg-green-50')"
                 ondrop="handlePhotoDropSortie(event)">
                <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                </svg>
                <p class="text-sm font-medium text-slate-600 mb-1">Glisser-déposer des photos, ou :</p>
                <div class="flex items-center justify-center gap-3 mt-3">
                    <button type="button" onclick="document.getElementById('photos_sortie_input').click()"
                            class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-green-400 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        Importer des photos
                    </button>
                    <button type="button" onclick="ouvrirCapturePhoto('photo_camera_sortie_input', afficherApercusPhotosSortie)"
                            class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                        </svg>
                        Prendre une photo
                    </button>
                </div>
                <p class="text-xs text-slate-400 mt-3">JPG, PNG, WEBP — jusqu'à 10 photos</p>
                <input type="file" id="photos_sortie_input" name="photos_vehicule_sortie[]" multiple
                       accept="image/jpeg,image/png,image/webp" class="hidden"
                       onchange="afficherApercusPhotosSortie(this.files)">
                <input type="file" id="photo_camera_sortie_input" accept="image/*" capture="environment" class="hidden"
                       onchange="afficherApercusPhotosSortie(this.files)">
            </div>
            <div id="photos-sortie-preview" class="mt-4 grid grid-cols-4 gap-3 hidden"></div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     NOTES & SIGNATURE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Notes & Validation restitution</h3>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Observations à la restitution (optionnel)</label>
            <textarea name="notes_restitution" rows="2"
                      class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"
                      placeholder="Ex: véhicule nettoyé, nouvelle rayure sur aile avant constatée...">{{ old('notes_restitution') }}</textarea>
        </div>

        @include('partials.signature-pad', [
            'nom'   => 'signature_restitution_image',
            'titre' => 'Signature du client à la restitution',
            'texte' => "Je confirme avoir vérifié l'état du véhicule et l'avoir récupéré.",
        ])
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     RÉCAPITULATIF RÉPARATION
═══════════════════════════════════════════════════════ --}}
<div class="bg-slate-800 rounded-2xl p-5">
    <h3 class="text-white font-bold text-sm mb-4 uppercase tracking-wider">Récapitulatif de l'intervention</h3>
    <div class="grid grid-cols-4 gap-3">
        <div class="bg-slate-700 rounded-xl p-3 text-center">
            <p class="text-slate-400 text-xs mb-1">OR</p>
            <p class="text-white font-mono font-bold text-sm">{{ $or->numero }}</p>
        </div>
        <div class="bg-slate-700 rounded-xl p-3 text-center">
            <p class="text-slate-400 text-xs mb-1">Date entrée</p>
            <p class="text-white font-bold text-sm">{{ $or->date_entree->format('d/m/Y') }}</p>
        </div>
        <div class="bg-slate-700 rounded-xl p-3 text-center">
            <p class="text-slate-400 text-xs mb-1">Statut</p>
            <p class="text-orange-400 font-bold text-sm">{{ $or->getStatutLabel() }}</p>
        </div>
        @if($or->facture)
        <div class="bg-{{ $or->facture->statut === 'payee' ? 'green' : 'yellow' }}-900 rounded-xl p-3 text-center">
            <p class="text-slate-300 text-xs mb-1">Facture</p>
            <p class="text-white font-bold text-sm">{{ $or->facture->statut === 'payee' ? '✓ Payée' : '⚠ Non payée' }}</p>
        </div>
        @else
        <div class="bg-slate-700 rounded-xl p-3 text-center">
            <p class="text-slate-400 text-xs mb-1">Facture</p>
            <p class="text-slate-300 text-sm">—</p>
        </div>
        @endif
    </div>

    @if($or->facture && $or->facture->statut !== 'payee')
    <div class="mt-3 bg-yellow-900 border border-yellow-700 rounded-xl px-4 py-3">
        <p class="text-yellow-200 text-sm font-medium">⚠ Attention : la facture {{ $or->facture->numero }} n'est pas encore payée. Confirmez que le règlement a été effectué avant de restituer.</p>
    </div>
    @endif
</div>

{{-- Boutons --}}
<div class="flex items-center gap-3 pb-6">
    <button type="submit"
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Confirmer la restitution — Marquer comme Livré
    </button>
    <a href="{{ route('ordres-reparations.show', $or) }}"
       class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
        Annuler
    </a>
</div>

</div>
</form>

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
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
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
const jaugeSortieWidths = { 'vide': '5%', '1/4': '25%', '1/2': '50%', '3/4': '75%', 'plein': '100%' };

function selectCarburantSortie(val) {
    document.getElementById('carburant_sortie_value').value = val;
    document.getElementById('jauge_sortie_bar').style.width = jaugeSortieWidths[val] || '50%';
    document.querySelectorAll('.carburant-sortie-btn').forEach(btn => {
        const active = btn.dataset.carburant === val;
        btn.classList.toggle('border-green-500', active);
        btn.classList.toggle('bg-green-500',     active);
        btn.classList.toggle('text-white',        active);
        btn.classList.toggle('border-gray-300',   !active);
        btn.classList.toggle('text-gray-400',     !active);
    });
}

// ── Schéma dommages (sortie) ────────────────────────────
let dommagesSortie = new Set();
try {
    const existing = JSON.parse(document.getElementById('dommages_sortie_data').value || '[]');
    if (Array.isArray(existing)) existing.forEach(d => {
        dommagesSortie.add(d);
        const el = document.querySelector('[data-zone="' + d + '"]');
        if (el) el.classList.add('damaged');
    });
} catch(e) {}

function toggleDamageSortie(el) {
    const zone = el.dataset.zone;
    if (dommagesSortie.has(zone)) {
        dommagesSortie.delete(zone);
        el.classList.remove('damaged');
    } else {
        dommagesSortie.add(zone);
        el.classList.add('damaged');
    }
    syncDommagesSortie();
}

function syncDommagesSortie() {
    const arr = [...dommagesSortie];
    document.getElementById('dommages_sortie_data').value = JSON.stringify(arr);
    const liste = document.getElementById('dommages_sortie_liste');
    const msg   = document.getElementById('no_dommage_sortie_msg');
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

function resetDommagesSortie() {
    dommagesSortie.clear();
    document.querySelectorAll('.damage-zone').forEach(el => el.classList.remove('damaged'));
    syncDommagesSortie();
}

// ── Photos (sortie) ─────────────────────────────────────
let photosSortieSelectionnees = new DataTransfer();

function validerPhotosSortie() {
    if (photosSortieSelectionnees.files.length === 0) {
        alert('Au moins une photo du véhicule est obligatoire à la restitution.');
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

function afficherApercusPhotosSortie(files) {
    for (const file of files) {
        if (photosSortieSelectionnees.files.length >= 10) break;
        photosSortieSelectionnees.items.add(file);
    }
    document.getElementById('photos_sortie_input').files = photosSortieSelectionnees.files;
    renderApercusSortie();
}

function supprimerApercuSortie(index) {
    const dt = new DataTransfer();
    const files = photosSortieSelectionnees.files;
    for (let i = 0; i < files.length; i++) {
        if (i !== index) dt.items.add(files[i]);
    }
    photosSortieSelectionnees = dt;
    document.getElementById('photos_sortie_input').files = photosSortieSelectionnees.files;
    renderApercusSortie();
}

function renderApercusSortie() {
    const container = document.getElementById('photos-sortie-preview');
    const files = photosSortieSelectionnees.files;
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
                <button type="button" onclick="supprimerApercuSortie(${i})"
                        class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs hidden group-hover:flex items-center justify-center font-bold leading-none">×</button>
                <p class="text-xs text-slate-400 truncate mt-1">${file.name}</p>
            `;
            container.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function handlePhotoDropSortie(event) {
    event.preventDefault();
    document.getElementById('photo-drop-zone').classList.remove('border-green-400','bg-green-50');
    afficherApercusPhotosSortie(event.dataTransfer.files);
}
</script>
@endsection
