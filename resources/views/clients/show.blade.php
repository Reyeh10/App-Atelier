@extends('layouts.app')
@section('title', $client->nom_complet)
@section('page-title', $client->nom_complet)
@section('page-subtitle', $client->getTypeLabel() . ' — Fiche client')

@section('header-actions')
<div class="flex gap-2">
    @if(auth()->user()->hasPermission('encaisser_factures') && $client->compte_actif)
    @php $nbFacturesEmises = \App\Models\Facture::where('client_id',$client->id)->where('statut','emise')->whereNull('encaissement_global_id')->count(); @endphp
    @if($nbFacturesEmises > 0)
    <a href="{{ route('encaissements-globaux.create', ['client_id' => $client->id]) }}"
       class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        Encaisser ({{ $nbFacturesEmises }})
    </a>
    @endif
    @endif
    @if(auth()->user()->hasPermission('gerer_vehicules'))
    <a href="{{ route('vehicules.create', ['client_id' => $client->id]) }}"
       class="flex items-center gap-2 border border-gray-300 text-slate-700 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Ajouter véhicule
    </a>
    @endif
    @if(auth()->user()->hasPermission('creer_dossiers'))
    <a href="{{ route('reception.index', ['client_id' => $client->id]) }}"
       class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Nouvelle Réception
    </a>
    @endif
    @if(auth()->user()->hasPermission('gerer_clients') && (!in_array($client->type, ['societe','assurance']) || auth()->user()->peutGererClientSociete()))
    <a href="{{ route('clients.edit', $client) }}"
       class="flex items-center gap-2 border border-gray-300 text-slate-700 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        Modifier
    </a>
    @endif
</div>
@endsection

@section('content')
<div class="grid grid-cols-3 gap-5">

    {{-- Colonne gauche — Infos client --}}
    <div class="col-span-1 space-y-5">

        {{-- Carte infos --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-14 h-14 rounded-2xl bg-{{ $client->getTypeBadgeColor() }}-100 flex items-center justify-center text-{{ $client->getTypeBadgeColor() }}-600 font-bold text-xl">
                    {{ strtoupper(substr($client->nom, 0, 1)) }}
                </div>
                <div>
                    <p class="font-bold text-slate-900">{{ $client->nom_complet }}</p>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $client->getTypeBadgeColor() }}-100 text-{{ $client->getTypeBadgeColor() }}-700">
                        {{ $client->getTypeLabel() }}
                    </span>
                </div>
            </div>

            <dl class="space-y-3 text-sm">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <span class="text-slate-700">{{ $client->telephone }}</span>
                </div>
                @if($client->telephone2)
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <span class="text-slate-500">{{ $client->telephone2 }}</span>
                </div>
                @endif
                @if($client->email)
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-slate-700">{{ $client->email }}</span>
                </div>
                @endif
                @if($client->adresse || $client->ville)
                <div class="flex items-start gap-3">
                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="text-slate-700">{{ implode(', ', array_filter([$client->adresse, $client->ville, $client->wilaya])) }}</span>
                </div>
                @endif
                @if($client->rc)
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="text-slate-700">RC : {{ $client->rc }}</span>
                </div>
                @endif
            </dl>

            @if($client->notes)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-medium text-slate-500 mb-1">Notes</p>
                <p class="text-sm text-slate-600">{{ $client->notes }}</p>
            </div>
            @endif

            @if($client->compte_actif)
            @php
                $solde      = $client->solde_compte;
                $plafond    = (float) $client->plafond_compte;
                $pourcentage = $plafond > 0 ? min(100, round($solde / $plafond * 100)) : 0;
                $depasse    = $solde >= $plafond;
            @endphp
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-semibold text-indigo-700 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Compte crédit
                    </p>
                    @if($depasse)
                    <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full">Plafond atteint</span>
                    @else
                    <span class="text-xs text-slate-500">{{ $pourcentage }}% utilisé</span>
                    @endif
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                    <div class="h-2 rounded-full {{ $depasse ? 'bg-red-500' : ($pourcentage > 75 ? 'bg-orange-400' : 'bg-indigo-500') }}"
                         style="width: {{ $pourcentage }}%"></div>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Utilisé : <strong class="{{ $depasse ? 'text-red-600' : 'text-slate-700' }}">{{ number_format($solde, 0, ',', ' ') }} FDJ</strong></span>
                    <span class="text-slate-500">Plafond : <strong>{{ number_format($plafond, 0, ',', ' ') }} FDJ</strong></span>
                </div>
            </div>
            @endif
        </div>

        {{-- Statistiques --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Statistiques</h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ $client->vehicules->count() }}</p>
                    <p class="text-xs text-blue-500">Véhicule(s)</p>
                </div>
                <div class="bg-orange-50 rounded-xl p-3 text-center">
                    <p class="text-2xl font-bold text-orange-600">{{ $client->ordresReparations->count() }}</p>
                    <p class="text-xs text-orange-500">OR total</p>
                </div>
            </div>
        </div>

        {{-- Véhicules --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-800">Véhicules</h3>
                @if(auth()->user()->hasPermission('gerer_vehicules'))
                <a href="{{ route('vehicules.create', ['client_id' => $client->id]) }}"
                   class="text-sm text-orange-500 hover:text-orange-600 font-medium">+ Ajouter</a>
                @endif
            </div>

            @if($client->vehicules->isEmpty())
            <p class="text-sm text-slate-400 text-center py-6">Aucun véhicule enregistré.</p>
            @else
            <div class="space-y-3">
                @foreach($client->vehicules as $v)
                <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100 hover:border-orange-200 transition-colors">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 1m0-7h8m0 0V6a1 1 0 00-1-1H9M5 16H3"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ $v->immatriculation }}</p>
                        <p class="text-xs text-slate-500">{{ $v->designation }} — {{ $v->getMotorisationLabel() }} — {{ number_format($v->kilometrage) }} km</p>
                    </div>
                    @if($v->sous_garantie)
                    <span class="text-xs bg-green-100 text-green-700 font-medium px-2 py-0.5 rounded-full">Garantie</span>
                    @endif
                    @if($v->isAssuranceExpiree())
                    <span class="text-xs bg-red-100 text-red-600 font-medium px-2 py-0.5 rounded-full">Assurance expirée</span>
                    @endif
                    <a href="{{ route('vehicules.show', $v) }}" class="text-slate-400 hover:text-orange-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>

    {{-- Colonne droite — Historique des interventions --}}
    <div class="col-span-2 space-y-5">

        {{-- Historique OR --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-800">Historique des interventions ({{ $client->ordresReparations->count() }})</h3>
                @if(auth()->user()->hasPermission('creer_dossiers'))
                <a href="{{ route('reception.index', ['client_id' => $client->id]) }}"
                   class="text-sm text-orange-500 hover:text-orange-600 font-medium">+ Nouvelle Réception</a>
                @endif
            </div>

            @if($client->ordresReparations->isEmpty())
            <p class="text-sm text-slate-400 text-center py-6">Aucune intervention enregistrée.</p>
            @else
            {{-- Liste avec son propre défilement quand l'historique devient long --}}
            <div class="space-y-2 overflow-y-auto" style="max-height: 560px; padding-right: 4px;">
                @foreach($client->ordresReparations as $or)
                <a href="{{ route('ordres-reparations.show', $or) }}"
                   class="flex items-center gap-4 p-3 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50 transition-colors">
                    <div class="flex-shrink-0 w-2 h-2 rounded-full bg-{{ $or->getStatutColor() }}-500 mt-1"></div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $or->numero }}</p>
                            <span class="text-xs bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700 px-2 py-0.5 rounded-full">
                                {{ $or->getStatutLabel() }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $or->vehicule?->immatriculation }} — {{ $or->date_entree->format('d/m/Y') }} — {{ Str::limit($or->motif_entree, 50) }}</p>
                    </div>
                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                @endforeach
            </div>
            @endif
        </div>

    </div>
</div>

@if(auth()->user()->hasPermission('voir_factures'))
{{-- ══ Pièces et main-d'œuvre facturées (tous ses véhicules) ═══════════ --}}
@include('partials.lignes-facturees', [
    'urlFiltre'   => route('clients.show', $client),
    'sousTitre'   => 'Lignes des factures de ce client, tous véhicules confondus (les factures annulées par avoir ne sont pas comptées).',
    'messageVide' => "Aucune pièce ni main-d'œuvre facturée pour ce client.",
])
@endif
@endsection
