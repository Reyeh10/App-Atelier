@extends('layouts.app')
@section('title', $vehicule->immatriculation)
@section('page-title', $vehicule->immatriculation)
@section('page-subtitle', $vehicule->designation . ' — ' . $vehicule->client->nom_complet)

@section('header-actions')
<div class="flex gap-2">
    @if(auth()->user()->hasPermission('creer_dossiers'))
    <a href="{{ route('reception.index', ['vehicule_id' => $vehicule->id, 'client_id' => $vehicule->client_id]) }}"
       class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Nouvelle Réception
    </a>
    @endif
    @if(auth()->user()->hasPermission('gerer_vehicules'))
    <a href="{{ route('vehicules.edit', $vehicule) }}"
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

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700 mb-5">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 mb-5">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-3 gap-5">

    {{-- Infos véhicule --}}
    <div class="col-span-1 space-y-5">

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 1m0-7h8m0 0V6a1 1 0 00-1-1H9M5 16H3"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xl font-bold text-slate-900 font-mono">{{ $vehicule->immatriculation }}</p>
                    <p class="text-sm text-slate-500">{{ $vehicule->designation }}</p>
                </div>
            </div>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Propriétaire</span>
                    <span class="flex items-center gap-2">
                        <span class="text-slate-800 font-medium">{{ $vehicule->client->nom_complet }}</span>
                        @if(auth()->user()->hasPermission('gerer_vehicules'))
                        <button type="button" onclick="ouvrirModalTransfert()" class="text-xs text-orange-500 hover:underline font-medium">Changer</button>
                        @endif
                    </span>
                </div>
                @foreach([
                    ['label' => 'Motorisation',   'value' => $vehicule->getMotorisationLabel()],
                    ['label' => 'Couleur',        'value' => $vehicule->couleur ?? '—'],
                    ['label' => 'Cylindrée',      'value' => $vehicule->cylindree ?? '—'],
                    ['label' => 'Puissance',      'value' => $vehicule->puissance_fiscale ? $vehicule->puissance_fiscale . ' CV' : '—'],
                    ['label' => 'Kilométrage',    'value' => number_format($vehicule->kilometrage) . ' km'],
                    ['label' => 'N° Châssis',     'value' => $vehicule->vin ?? '—'],
                ] as $item)
                <div class="flex justify-between">
                    <span class="text-slate-500">{{ $item['label'] }}</span>
                    <span class="text-slate-800 font-medium text-right">{{ $item['value'] }}</span>
                </div>
                @endforeach
            </dl>
        </div>

        {{-- Documents --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Documents</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Assurance</span>
                    @if($vehicule->date_expiration_assurance)
                        <span class="font-medium {{ $vehicule->isAssuranceExpiree() ? 'text-red-600' : 'text-green-600' }}">
                            {{ $vehicule->date_expiration_assurance->format('d/m/Y') }}
                            {{ $vehicule->isAssuranceExpiree() ? '⚠ Expirée' : '' }}
                        </span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Vignette</span>
                    @if($vehicule->date_expiration_vignette)
                        <span class="font-medium {{ $vehicule->isVignetteExpiree() ? 'text-red-600' : 'text-green-600' }}">
                            {{ $vehicule->date_expiration_vignette->format('d/m/Y') }}
                            {{ $vehicule->isVignetteExpiree() ? '⚠ Expirée' : '' }}
                        </span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Catégorie</span>
                    <span class="font-medium text-slate-700">
                        {{ match($vehicule->categorie) { 'pick-up' => 'Pick-up', 'suv' => 'SUV', default => 'Autre / non précisé' } }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Garantie</span>
                    @if($vehicule->sous_garantie)
                        <span class="text-green-600 font-medium">
                            Oui {{ $vehicule->fin_garantie ? '— jusqu\'au ' . $vehicule->fin_garantie->format('d/m/Y') : '' }}
                        </span>
                    @else
                        <span class="text-slate-400">Non</span>
                    @endif
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Éligible garantie panne</span>
                    @if($vehicule->estEligibleGarantie())
                        <span class="text-green-600 font-medium">Oui</span>
                    @elseif($vehicule->getMotifSortieGarantieLabel())
                        <span class="text-red-600 font-medium text-right">Non — {{ $vehicule->getMotifSortieGarantieLabel() }}</span>
                    @else
                        <span class="text-slate-400">Non</span>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- Historique OR --}}
    <div class="col-span-2">
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-800">Historique des interventions (<span id="or-count">{{ $vehicule->ordresReparations->count() }}</span>)</h3>
                @if(auth()->user()->hasPermission('creer_dossiers'))
                <a href="{{ route('reception.index', ['vehicule_id' => $vehicule->id, 'client_id' => $vehicule->client_id]) }}"
                   class="text-sm text-orange-500 hover:text-orange-600 font-medium">+ Nouvelle Réception</a>
                @endif
            </div>

            @if($vehicule->ordresReparations->isNotEmpty())
            {{-- Barre de recherche OR + filtre par date d'entrée --}}
            <div class="flex gap-2 mb-3">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <input id="or-search" type="text" placeholder="Rechercher par numéro OR ou motif…"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-orange-400 bg-gray-50"
                       oninput="filtrerOR()">
            </div>
            <input id="or-date" type="date" title="Date d'entrée à l'atelier" onchange="filtrerOR()"
                   class="w-44 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-orange-400 bg-gray-50">
            <button type="button" id="or-date-effacer" onclick="document.getElementById('or-date').value=''; filtrerOR()"
                    class="hidden text-xs text-slate-400 hover:text-slate-600 px-1">✕</button>
            </div>
            @endif

            @if($vehicule->ordresReparations->isEmpty())
            <div class="text-center py-12">
                <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877"/>
                    </svg>
                </div>
                <p class="text-slate-500 text-sm">Aucune intervention pour ce véhicule.</p>
            </div>
            @else
            {{-- Liste avec son propre défilement quand l'historique devient long --}}
            <div id="or-list" class="space-y-2 overflow-y-auto" style="max-height: 440px; padding-right: 4px;">
                @foreach($vehicule->ordresReparations as $or)
                <a href="{{ route('ordres-reparations.show', $or) }}"
                   class="or-item flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50 transition-colors"
                   data-numero="{{ strtolower($or->numero) }}"
                   data-motif="{{ strtolower($or->motif_entree) }}"
                   data-date="{{ $or->date_entree->format('Y-m-d') }}">
                    <div class="w-2 h-2 rounded-full bg-{{ $or->getStatutColor() }}-500 flex-shrink-0"></div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-sm font-bold text-slate-900">{{ $or->numero }}</span>
                            <span class="text-xs bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700 px-2 py-0.5 rounded-full">{{ $or->getStatutLabel() }}</span>
                            @if($or->type !== 'normal')
                            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ $or->getTypeLabel() }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500">{{ $or->date_entree->format('d/m/Y') }} — {{ Str::limit($or->motif_entree, 60) }}</p>
                        @if($or->client_id !== $vehicule->client_id)
                        <p class="text-xs text-amber-600 mt-0.5">Propriétaire à l'époque : {{ $or->client?->nom_complet ?? 'client supprimé' }}</p>
                        @endif
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-xs text-slate-400">{{ number_format($or->kilometrage_entree) }} km</p>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                @endforeach

                <p id="or-empty-msg" class="hidden text-center text-sm text-slate-400 py-6">Aucun OR ne correspond à cette recherche ou à cette date.</p>
            </div>
            @endif
        </div>
    </div>

</div>

@if(auth()->user()->hasPermission('voir_factures'))
{{-- ══ Pièces et main-d'œuvre facturées ═══════════════════════════ --}}
@include('partials.lignes-facturees', [
    'urlFiltre'   => route('vehicules.show', $vehicule),
    'sousTitre'   => 'Lignes des factures de ce véhicule (les factures annulées par avoir ne sont pas comptées).',
    'messageVide' => "Aucune pièce ni main-d'œuvre facturée pour ce véhicule.",
])
@endif

@if(auth()->user()->hasPermission('gerer_vehicules'))
{{-- ══ MODAL : Changer de propriétaire ═══════════════════════════ --}}
<div id="modal_transfert" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="fermerModalTransfert()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-bold text-slate-800">Changer de propriétaire</h3>
                <button type="button" onclick="fermerModalTransfert()" class="text-slate-400 hover:text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('vehicules.transferer', $vehicule) }}">
                @csrf @method('PATCH')
                <div class="p-6 space-y-4">
                    <p class="text-sm text-slate-500">
                        Véhicule vendu à quelqu'un d'autre — le propriétaire actuel est
                        <strong class="text-slate-700">{{ $vehicule->client->nom_complet }}</strong>.
                        L'historique des interventions déjà effectuées reste inchangé et consultable ci-dessous.
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nouveau propriétaire <span class="text-red-500">*</span></label>
                        <select name="nouveau_client_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">— Sélectionner un client —</option>
                            @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->nom_complet }} — {{ $c->telephone }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="px-6 pb-5 flex gap-3">
                    <button type="submit" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                        Confirmer le transfert
                    </button>
                    <button type="button" onclick="fermerModalTransfert()" class="px-5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
function ouvrirModalTransfert() { document.getElementById('modal_transfert')?.classList.remove('hidden'); }
function fermerModalTransfert() { document.getElementById('modal_transfert')?.classList.add('hidden'); }

function filtrerOR() {
    const q    = (document.getElementById('or-search')?.value || '').toLowerCase().trim();
    const date = document.getElementById('or-date')?.value || '';
    document.getElementById('or-date-effacer')?.classList.toggle('hidden', !date);
    const items = document.querySelectorAll('.or-item');
    let visible = 0;
    items.forEach(function(el) {
        const match = (!q || el.dataset.numero.includes(q) || el.dataset.motif.includes(q))
            && (!date || el.dataset.date === date);
        el.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('or-count').textContent = visible;
    const emptyMsg = document.getElementById('or-empty-msg');
    if (emptyMsg) emptyMsg.classList.toggle('hidden', visible > 0);
}
</script>
@endpush
