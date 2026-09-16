@extends('layouts.app')

@section('title', 'Paramètres de l\'atelier')
@section('page-title', 'Paramètres de l\'atelier')
@section('page-subtitle', 'Horaires, pauses et Service Rapide')

@php
$nomsJours = [
    0 => 'Dimanche',
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
];
@endphp

@section('content')
<div class="max-w-7xl space-y-6">

    {{-- ── Sous-menu de la page ────────────────────────────────────── --}}
    <div class="inline-flex items-center gap-1 bg-gray-100 dark:bg-slate-900 rounded-xl p-1">
        <button type="button" onclick="afficherOnglet('horaires')" id="onglet-btn-horaires" class="onglet-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Horaires d'ouverture
        </button>
        <button type="button" onclick="afficherOnglet('pauses')" id="onglet-btn-pauses" class="onglet-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Pauses
        </button>
        <button type="button" onclick="afficherOnglet('service-rapide')" id="onglet-btn-service-rapide" class="onglet-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Service Rapide
        </button>
        <button type="button" onclick="afficherOnglet('garantie')" id="onglet-btn-garantie" class="onglet-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Garantie constructeur
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════
         ONGLET 1 — HORAIRES D'OUVERTURE
    ═══════════════════════════════════════════════════════ --}}
    <div id="onglet-horaires" class="onglet-panel">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Horaires par jour</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Définissez les heures d'ouverture pour chaque jour de la semaine</p>
            </div>
        </div>

        <form method="POST" action="{{ route('parametres.update') }}">
            @csrf
            @method('PATCH')

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-900">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider w-36">Jour</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider w-28">Travaillé</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Heure début</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Heure fin</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Durée</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach($ordreJours as $jourNum)
                        @php $h = $horaires[$jourNum] ?? null; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-750 transition-colors" id="row-jour-{{ $jourNum }}">
                            {{-- Nom du jour --}}
                            <td class="px-6 py-4">
                                <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">
                                    {{ $nomsJours[$jourNum] }}
                                </span>
                                <input type="hidden" name="jours[{{ $jourNum }}][placeholder]" value="1">
                            </td>

                            {{-- Toggle travaillé --}}
                            <td class="px-4 py-4 text-center">
                                @php $estActif = $h && $h->actif; @endphp
                                <input type="checkbox"
                                       id="chk-{{ $jourNum }}"
                                       name="jours[{{ $jourNum }}][actif]"
                                       value="1"
                                       {{ $estActif ? 'checked' : '' }}
                                       class="hidden">
                                <button type="button"
                                        id="toggle-{{ $jourNum }}"
                                        onclick="basculerJour({{ $jourNum }})"
                                        style="position:relative; display:inline-flex; align-items:center;
                                               width:44px; height:24px; border-radius:9999px; border:none; cursor:pointer;
                                               transition: background-color 0.25s ease;
                                               background-color: {{ $estActif ? '#3b82f6' : '#9ca3af' }};">
                                    <span id="knob-{{ $jourNum }}"
                                          style="position:absolute; top:2px; left:2px;
                                                 width:20px; height:20px; border-radius:9999px;
                                                 background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.3);
                                                 transition: transform 0.25s ease;
                                                 transform: {{ $estActif ? 'translateX(20px)' : 'translateX(0)' }};">
                                    </span>
                                </button>
                            </td>

                            {{-- Heure début --}}
                            <td class="px-4 py-4">
                                <input type="time"
                                       name="jours[{{ $jourNum }}][heure_debut]"
                                       value="{{ old('jours.'.$jourNum.'.heure_debut', $h ? substr($h->heure_debut ?? '', 0, 5) : '') }}"
                                       id="debut-{{ $jourNum }}"
                                       {{ $h && !$h->actif ? 'disabled' : '' }}
                                       onchange="calculerDuree({{ $jourNum }})"
                                       class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm w-32
                                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                                              focus:outline-none focus:ring-2 focus:ring-blue-500
                                              disabled:opacity-40 disabled:cursor-not-allowed">
                            </td>

                            {{-- Heure fin --}}
                            <td class="px-4 py-4">
                                <input type="time"
                                       name="jours[{{ $jourNum }}][heure_fin]"
                                       value="{{ old('jours.'.$jourNum.'.heure_fin', $h ? substr($h->heure_fin ?? '', 0, 5) : '') }}"
                                       id="fin-{{ $jourNum }}"
                                       {{ $h && !$h->actif ? 'disabled' : '' }}
                                       onchange="calculerDuree({{ $jourNum }})"
                                       class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm w-32
                                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                                              focus:outline-none focus:ring-2 focus:ring-blue-500
                                              disabled:opacity-40 disabled:cursor-not-allowed">
                            </td>

                            {{-- Durée calculée --}}
                            <td class="px-4 py-4">
                                @if($h && $h->actif && $h->heure_debut && $h->heure_fin)
                                @php
                                    [$h1, $m1] = explode(':', $h->heure_debut);
                                    [$h2, $m2] = explode(':', $h->heure_fin);
                                    $dur = ($h2 * 60 + $m2) - ($h1 * 60 + (int)$m1);
                                @endphp
                                <span id="duree-{{ $jourNum }}" class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                    {{ floor($dur/60) }}h{{ $dur%60 > 0 ? ($dur%60).'min' : '' }}
                                </span>
                                @else
                                <span id="duree-{{ $jourNum }}" class="text-sm text-slate-400">
                                    {{ $h && !$h->actif ? 'Fermé' : '—' }}
                                </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex justify-end">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Enregistrer les horaires
                </button>
            </div>
        </form>
    </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         ONGLET 2 — PAUSES
    ═══════════════════════════════════════════════════════ --}}
    <div id="onglet-pauses" class="onglet-panel hidden space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-100 dark:bg-orange-900 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Pauses par jour</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Ces périodes sont soustraites du calcul de durée nette pour chaque jour</p>
            </div>
        </div>

        {{-- Liste des pauses groupées par jour --}}
        <div class="divide-y divide-gray-100 dark:divide-slate-700">
            @foreach($ordreJours as $jourNum)
            @php
                $horaire    = $horaires[$jourNum] ?? null;
                $pausesJour = $pauses[$jourNum] ?? collect();
            @endphp

            <div class="px-6 py-4">
                {{-- En-tête du jour --}}
                <div class="flex items-center gap-3 mb-3">
                    <span class="font-semibold text-sm text-slate-800 dark:text-slate-100 w-24">
                        {{ $nomsJours[$jourNum] }}
                    </span>
                    @if($horaire && $horaire->actif)
                    <span class="text-xs text-slate-400 dark:text-slate-500">
                        {{ substr($horaire->heure_debut, 0, 5) }} → {{ substr($horaire->heure_fin, 0, 5) }}
                    </span>
                    @else
                    <span class="text-xs text-slate-300 dark:text-slate-600 italic">Jour fermé</span>
                    @endif
                </div>

                {{-- Pauses de ce jour --}}
                @if($pausesJour->isNotEmpty())
                <div class="space-y-2 mb-3 ml-4">
                    @foreach($pausesJour as $pause)
                    <div class="flex items-center gap-3 bg-gray-50 dark:bg-slate-900 rounded-xl px-4 py-2.5">
                        <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $pause->actif ? 'bg-green-500' : 'bg-gray-300 dark:bg-slate-600' }}"></div>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-100 {{ $pause->actif ? '' : 'opacity-50' }}">
                                {{ $pause->nom }}
                            </span>
                            <span class="text-xs text-slate-400 ml-2 {{ $pause->actif ? '' : 'opacity-50' }}">
                                {{ substr($pause->heure_debut, 0, 5) }} → {{ substr($pause->heure_fin, 0, 5) }}
                                @php
                                    [$ph1, $pm1] = explode(':', $pause->heure_debut);
                                    [$ph2, $pm2] = explode(':', $pause->heure_fin);
                                    $pdur = ($ph2 * 60 + $pm2) - ($ph1 * 60 + (int)$pm1);
                                @endphp
                                ({{ $pdur >= 60 ? floor($pdur/60).'h'.($pdur%60 > 0 ? ($pdur%60).'min' : '') : $pdur.'min' }})
                            </span>
                        </div>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0
                            {{ $pause->actif
                                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-400' }}">
                            {{ $pause->actif ? 'Active' : 'Inactive' }}
                        </span>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <form method="POST" action="{{ route('parametres.pauses.toggle', $pause) }}">
                                @csrf @method('PATCH')
                                <button type="submit" title="{{ $pause->actif ? 'Désactiver' : 'Activer' }}"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    @if($pause->actif)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    @endif
                                </button>
                            </form>
                            <form method="POST" action="{{ route('parametres.pauses.destroy', $pause) }}"
                                  onsubmit="return confirm('Supprimer cette pause ?')">
                                @csrf @method('DELETE')
                                <button type="submit" title="Supprimer"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-slate-400 dark:text-slate-600 ml-4 mb-3 italic">Aucune pause configurée pour ce jour.</p>
                @endif

                {{-- Bouton pour ajouter une pause sur ce jour --}}
                @if($horaire && $horaire->actif)
                <div class="ml-4">
                    <button type="button"
                            onclick="ouvrirFormPause({{ $jourNum }}, '{{ $nomsJours[$jourNum] }}')"
                            class="flex items-center gap-1.5 text-xs font-medium text-orange-600 dark:text-orange-400
                                   hover:text-orange-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajouter une pause
                    </button>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Explication ─────────────────────────────────────────────── --}}
    <div class="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-2xl p-5">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-300">
                <p class="font-semibold mb-1">Comment fonctionne le calcul ?</p>
                <p class="leading-relaxed">
                    Quand un mécanicien démarre et termine des travaux, le système détecte le jour de la semaine
                    et soustrait automatiquement les pauses configurées pour ce jour.
                </p>
                <p class="mt-2 leading-relaxed">
                    <strong>Exemple :</strong> travaux un lundi, lancés à 11h00, terminés à 18h00.
                    Pause déjeuner configurée ce jour : 12h00 → 15h00 →
                    durée nette = <strong>4h</strong> (au lieu de 7h brutes).
                </p>
            </div>
        </div>
    </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         ONGLET 3 — SERVICE RAPIDE
    ═══════════════════════════════════════════════════════ --}}
    <div id="onglet-service-rapide" class="onglet-panel hidden">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

    {{-- ── Capacité Service Rapide ───────────────────────────────── --}}
    <div class="lg:col-span-1 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-teal-100 dark:bg-teal-900 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Capacité</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Véhicules pris en charge EN MÊME TEMPS (postes disponibles) — utilisé pour le planning des réservations et la réception "Sans RDV"</p>
            </div>
        </div>

        <form method="POST" action="{{ route('parametres.capacite.update') }}" class="px-6 py-4 space-y-3">
            @csrf @method('PATCH')
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Postes simultanés</label>
                <input type="number" name="capacite_service_rapide_simultanee" min="1"
                       value="{{ old('capacite_service_rapide_simultanee', $parametreAtelier->capacite_service_rapide_simultanee) }}"
                       placeholder="Illimité"
                       class="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl text-sm
                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                              focus:outline-none focus:ring-2 focus:ring-teal-500">
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Laisser vide = pas de limite.</p>
            </div>
            <button type="submit" class="w-full px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Enregistrer
            </button>
        </form>
    </div>

    {{-- ── Tarif Entretien périodique ────────────────────────────── --}}
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-green-100 dark:bg-green-900 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3v-3m-3 3v-3m9-7H3a2 2 0 00-2 2v9a2 2 0 002 2h18a2 2 0 002-2v-9a2 2 0 00-2-2z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Tarif — Entretien périodique</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Prix fixe en FDJ — le devis se génère et s'accepte automatiquement à la réception avec ce tarif, sans validation manuelle</p>
            </div>
        </div>

        <form method="POST" action="{{ route('parametres.tarifs.update') }}">
            @csrf @method('PATCH')
            <div class="p-6">
                @php $tarifs = $parametreAtelier->tarifs_service_rapide ?? []; @endphp
                <div class="flex items-center justify-between gap-3 bg-gray-50 dark:bg-slate-900 rounded-xl px-4 py-2.5 max-w-sm">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Entretien périodique</span>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <input type="number" name="tarifs[entretien_periodique]" min="0" step="1"
                               value="{{ old('tarifs.entretien_periodique', $tarifs['entretien_periodique'] ?? '') }}"
                               placeholder="0"
                               class="w-24 px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-right
                                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <span class="text-xs text-slate-400 w-8">FDJ</span>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    </div>

    {{-- ── Catalogue Services Rapides ("Autre") ──────────────────── --}}
    <div class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-teal-100 dark:bg-teal-900 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Catalogue Services Rapides ("Autre")</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Ajoutez, modifiez ou retirez un service — chacun a son propre tarif et sa propre capacité (postes simultanés), indépendante des autres services</p>
            </div>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-slate-700">
            @forelse($servicesRapides as $service)
            <div class="px-6 py-4 flex items-center gap-3 flex-wrap {{ $service->actif ? '' : 'opacity-50' }}">
                <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $service->actif ? 'bg-green-500' : 'bg-gray-300 dark:bg-slate-600' }}"></span>

                <form method="POST" action="{{ route('parametres.services-rapides.update', $service) }}" class="flex items-center gap-3 flex-wrap flex-1">
                    @csrf @method('PATCH')
                    <input type="text" name="nom" value="{{ old('nom', $service->nom) }}" placeholder="Nom du service"
                           class="w-48 px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium
                                  bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">

                    <div class="flex items-center gap-1.5">
                        <input type="number" name="duree_min" min="1" value="{{ old('duree_min', $service->duree_min) }}"
                               class="w-16 px-2 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-right
                                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <span class="text-xs text-slate-400">–</span>
                        <input type="number" name="duree_max" min="1" value="{{ old('duree_max', $service->duree_max) }}"
                               class="w-16 px-2 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-right
                                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <span class="text-xs text-slate-400">min</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <input type="number" name="tarif" min="0" step="1" value="{{ old('tarif', $service->tarif) }}"
                               class="w-24 px-2 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-right
                                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <span class="text-xs text-slate-400">FDJ</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <input type="number" name="capacite_simultanee" min="1" placeholder="Illimité"
                               value="{{ old('capacite_simultanee', $service->capacite_simultanee) }}"
                               class="w-20 px-2 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-right
                                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <span class="text-xs text-slate-400">postes simult.</span>
                    </div>

                    <button type="submit" class="text-xs bg-teal-50 hover:bg-teal-100 text-teal-600 font-medium px-3 py-1.5 rounded-lg transition-colors">
                        Enregistrer
                    </button>
                </form>

                <div class="flex items-center gap-1 ml-auto flex-shrink-0">
                    <form method="POST" action="{{ route('parametres.services-rapides.toggle', $service) }}">
                        @csrf @method('PATCH')
                        <button type="submit" title="{{ $service->actif ? 'Désactiver' : 'Activer' }}"
                                class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors
                                       {{ $service->actif ? 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300' : 'bg-green-50 text-green-600 hover:bg-green-100' }}">
                            {{ $service->actif ? 'Désactiver' : 'Activer' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('parametres.services-rapides.destroy', $service) }}"
                          onsubmit="return confirm('Supprimer le service « {{ $service->nom }} » ?')">
                        @csrf @method('DELETE')
                        <button type="submit" title="Supprimer"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <p class="px-6 py-8 text-sm text-slate-400 text-center italic">Aucun service dans le catalogue — ajoutez-en un ci-dessous.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('parametres.services-rapides.store') }}" class="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex items-end gap-3 flex-wrap">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Nom du service</label>
                <input type="text" name="nom" value="{{ old('nom') }}" placeholder="Ex : Lavage moteur"
                       class="w-48 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm
                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Durée min–max (min)</label>
                <div class="flex items-center gap-1.5">
                    <input type="number" name="duree_min" min="1" value="{{ old('duree_min') }}" placeholder="15"
                           class="w-16 px-2 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm text-right
                                  bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <span class="text-xs text-slate-400">–</span>
                    <input type="number" name="duree_max" min="1" value="{{ old('duree_max') }}" placeholder="30"
                           class="w-16 px-2 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm text-right
                                  bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Tarif</label>
                <input type="number" name="tarif" value="{{ old('tarif') }}" min="0" step="1" placeholder="0"
                       class="w-24 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm text-right
                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Postes simultanés</label>
                <input type="number" name="capacite_simultanee" value="{{ old('capacite_simultanee') }}" min="1" placeholder="Illimité"
                       class="w-24 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm text-right
                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Ajouter le service
            </button>
        </form>
        @if($errors->any())
        <div class="mx-6 mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            @foreach($errors->all() as $e)
            <p class="text-sm text-red-700">{{ $e }}</p>
            @endforeach
        </div>
        @endif
    </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         ONGLET 4 — GARANTIE CONSTRUCTEUR
    ═══════════════════════════════════════════════════════ --}}
    <div id="onglet-garantie" class="onglet-panel hidden">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.75h-.152c-3.196 0-6.1-1.248-8.25-3.286z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Comptes garantie par marque</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Quand une panne est reconnue couverte par la garantie constructeur, la facture est adressée à ce compte (ex : GWM) au lieu du client, jusqu'à son plafond</p>
            </div>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-slate-700">
            @forelse($marquesGarantie as $marque)
            <div class="px-6 py-4 flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-2 min-w-[160px]">
                    <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $marque->actif ? 'bg-green-500' : 'bg-gray-300 dark:bg-slate-600' }}"></span>
                    <span class="font-semibold text-sm text-slate-800 dark:text-slate-100 {{ $marque->actif ? '' : 'opacity-50' }}">{{ $marque->nom }}</span>
                </div>

                <div class="text-xs text-slate-500 dark:text-slate-400 min-w-[180px]">
                    Utilisé : <span class="font-medium text-slate-700 dark:text-slate-200">{{ number_format($marque->solde_utilise, 0, ',', ' ') }} FDJ</span>
                    / Disponible : <span class="font-medium {{ $marque->disponible > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ number_format($marque->disponible, 0, ',', ' ') }} FDJ</span>
                </div>

                <form method="POST" action="{{ route('parametres.marques-garantie.update', $marque) }}" class="flex items-center gap-2">
                    @csrf @method('PATCH')
                    <input type="number" name="plafond_credit" min="0" step="1" value="{{ old('plafond_credit', $marque->plafond_credit) }}"
                           class="w-32 px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-right
                                  bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <span class="text-xs text-slate-400">FDJ</span>
                    <button type="submit" class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-medium px-3 py-1.5 rounded-lg transition-colors">
                        Enregistrer
                    </button>
                </form>

                <div class="flex items-center gap-1 ml-auto flex-shrink-0">
                    <form method="POST" action="{{ route('parametres.marques-garantie.toggle', $marque) }}">
                        @csrf @method('PATCH')
                        <button type="submit" title="{{ $marque->actif ? 'Désactiver' : 'Activer' }}"
                                class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors
                                       {{ $marque->actif ? 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300' : 'bg-green-50 text-green-600 hover:bg-green-100' }}">
                            {{ $marque->actif ? 'Désactiver' : 'Activer' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('parametres.marques-garantie.destroy', $marque) }}"
                          onsubmit="return confirm('Supprimer le compte garantie « {{ $marque->nom }} » ?')">
                        @csrf @method('DELETE')
                        <button type="submit" title="Supprimer"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <p class="px-6 py-8 text-sm text-slate-400 text-center italic">Aucun compte garantie configuré — ajoutez-en un ci-dessous (ex : GWM).</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('parametres.marques-garantie.store') }}" class="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex items-end gap-3 flex-wrap">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Marque</label>
                <input type="text" name="nom" value="{{ old('nom') }}" placeholder="Ex : GWM"
                       class="w-40 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm
                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Plafond de crédit</label>
                <input type="number" name="plafond_credit" value="{{ old('plafond_credit') }}" min="0" step="1" placeholder="0"
                       class="w-32 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm
                              bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Ajouter le compte
            </button>
        </form>
        @if($errors->any())
        <div class="mx-6 mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            @foreach($errors->all() as $e)
            <p class="text-sm text-red-700">{{ $e }}</p>
            @endforeach
        </div>
        @endif
    </div>

    <div class="mt-6 bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-2xl p-5">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-300">
                <p class="font-semibold mb-1">Comment ça fonctionne ?</p>
                <p class="leading-relaxed">
                    Le nom saisi ici doit correspondre exactement à la marque du véhicule (ex : "GWM"). Quand une
                    demande de garantie est <strong>approuvée</strong> par l'équipe garantie, la facture finale est
                    automatiquement adressée à ce compte au lieu du client, sans attendre le caissier. Si la garantie
                    est <strong>refusée</strong>, l'OR redevient normal et un devis peut être établi au client.
                </p>
            </div>
        </div>
    </div>
    </div>

</div>

{{-- ── Modale ajout de pause ─────────────────────────────────── --}}
<div id="modal-pause" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="fermerFormPause()"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-sm mx-4">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4">
            Ajouter une pause —
            <span id="modal-jour-label" class="text-orange-500"></span>
        </h3>

        <form method="POST" action="{{ route('parametres.pauses.store') }}">
            @csrf
            <input type="hidden" name="jour" id="modal-jour-value">

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Nom de la pause</label>
                    <input type="text" name="nom" placeholder="Ex : Pause déjeuner"
                           class="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl text-sm
                                  bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                                  focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Heure début</label>
                        <input type="time" name="heure_debut"
                               class="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl text-sm
                                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                                      focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Heure fin</label>
                        <input type="time" name="heure_fin"
                               class="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl text-sm
                                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                                      focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
            </div>

            <div class="mt-5 flex gap-3">
                <button type="button" onclick="fermerFormPause()"
                        class="flex-1 py-2.5 border border-gray-300 dark:border-slate-600 text-slate-600 dark:text-slate-300
                               text-sm font-medium rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    Annuler
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function afficherOnglet(nom) {
    document.querySelectorAll('.onglet-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('onglet-' + nom).classList.remove('hidden');

    document.querySelectorAll('.onglet-btn').forEach(btn => {
        const actif = btn.id === 'onglet-btn-' + nom;
        btn.classList.toggle('bg-white', actif);
        btn.classList.toggle('dark:bg-slate-800', actif);
        btn.classList.toggle('text-slate-800', actif);
        btn.classList.toggle('dark:text-slate-100', actif);
        btn.classList.toggle('shadow-sm', actif);
        btn.classList.toggle('text-slate-500', !actif);
        btn.classList.toggle('dark:text-slate-400', !actif);
    });

    if (history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.set('onglet', nom);
        history.replaceState(null, '', url);
    }
}

function basculerJour(jour) {
    const chk   = document.getElementById('chk-' + jour);
    const btn   = document.getElementById('toggle-' + jour);
    const knob  = document.getElementById('knob-' + jour);
    const actif = !chk.checked;

    chk.checked = actif;
    btn.style.backgroundColor = actif ? '#3b82f6' : '#9ca3af';
    knob.style.transform      = actif ? 'translateX(20px)' : 'translateX(0)';

    toggleJour(jour, actif);
}

function toggleJour(jour, actif) {
    const debut = document.getElementById('debut-' + jour);
    const fin   = document.getElementById('fin-' + jour);
    const duree = document.getElementById('duree-' + jour);
    debut.disabled = !actif;
    fin.disabled   = !actif;
    if (!actif) {
        duree.textContent = 'Fermé';
        duree.className   = 'text-sm text-slate-400';
    } else {
        calculerDuree(jour);
    }
}

function calculerDuree(jour) {
    const debut = document.getElementById('debut-' + jour).value;
    const fin   = document.getElementById('fin-' + jour).value;
    const span  = document.getElementById('duree-' + jour);
    if (!debut || !fin) { span.textContent = '—'; return; }
    const [h1, m1] = debut.split(':').map(Number);
    const [h2, m2] = fin.split(':').map(Number);
    const diff = (h2 * 60 + m2) - (h1 * 60 + m1);
    if (diff <= 0) { span.textContent = '—'; return; }
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    span.textContent  = h + 'h' + (m > 0 ? m + 'min' : '');
    span.className    = 'text-sm font-medium text-blue-600 dark:text-blue-400';
}

function ouvrirFormPause(jourNum, nomJour) {
    document.getElementById('modal-jour-value').value = jourNum;
    document.getElementById('modal-jour-label').textContent = nomJour;
    document.getElementById('modal-pause').classList.remove('hidden');
}

function fermerFormPause() {
    document.getElementById('modal-pause').classList.add('hidden');
}

// Ouvre l'onglet demandé en query string (ex: après "Enregistrer les tarifs"),
// sinon le premier onglet par défaut.
afficherOnglet(new URLSearchParams(window.location.search).get('onglet') || 'horaires');
</script>
@endpush
@endsection
