@extends('layouts.app')
@section('title', 'Nouvel encaissement groupé')
@section('page-title', 'Encaissement groupé')
@section('page-subtitle', $client ? $client->nom_complet : ($marqueGarantie ? $marqueGarantie->nom : 'Sélectionner un payeur'))

@section('header-actions')
<a href="{{ route('encaissements-globaux.index') }}"
   class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour
</a>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
    <ul class="space-y-1">
        @foreach($errors->all() as $e)
        <li class="text-sm text-red-700">• {{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Étape 1 : choisir le payeur (client ou marque garantie) si pas encore sélectionné --}}
@if(!$client && !$marqueGarantie)
<div class="flex flex-col lg:flex-row gap-5">

    <div class="flex-1 bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-1">Client à compte crédit</h3>
        <p class="text-xs text-slate-400 mb-4">Seuls les clients avec un compte crédit actif peuvent faire l'objet d'un encaissement groupé.</p>

        @if($clients->isEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
            Aucun client n'a de compte crédit actif.
        </div>
        @else
        <form method="GET" action="{{ route('encaissements-globaux.create') }}" class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Client avec compte crédit</label>
                <select name="client_id" required
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                    <option value="">— Choisir un client —</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->nom_complet }}
                        @php $nb = \App\Models\Facture::where('client_id',$c->id)->whereNull('marque_garantie_id')->where('statut','emise')->whereNull('encaissement_global_id')->count(); @endphp
                        @if($nb) — {{ $nb }} facture{{ $nb>1?'s':'' }} en attente @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-6 py-2.5 rounded-xl transition-colors">
                Continuer →
            </button>
        </form>
        @endif
    </div>

    <div class="flex-1 bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-1">Marque garantie constructeur</h3>
        <p class="text-xs text-slate-400 mb-4">Les factures des pannes couvertes par la garantie, elles aussi soumises à un plafond de crédit.</p>

        @if($marques->isEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
            Aucune marque garantie active. Configurez-en une dans Réglages atelier → Garantie constructeur.
        </div>
        @else
        <form method="GET" action="{{ route('encaissements-globaux.create') }}" class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Marque garantie</label>
                <select name="marque_garantie_id" required
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                    <option value="">— Choisir une marque —</option>
                    @foreach($marques as $m)
                    <option value="{{ $m->id }}">{{ $m->nom }}
                        @php $nb = \App\Models\Facture::where('marque_garantie_id',$m->id)->where('statut','emise')->whereNull('encaissement_global_id')->count(); @endphp
                        @if($nb) — {{ $nb }} facture{{ $nb>1?'s':'' }} en attente @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-6 py-2.5 rounded-xl transition-colors">
                Continuer →
            </button>
        </form>
        @endif
    </div>

</div>

@else

{{-- Étape 2 : sélectionner les factures --}}
<form method="POST" action="{{ route('encaissements-globaux.store') }}" id="form-eg">
@csrf
@if($client)
<input type="hidden" name="client_id" value="{{ $client->id }}">
@else
<input type="hidden" name="marque_garantie_id" value="{{ $marqueGarantie->id }}">
@endif

<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            @if($client)
            <h3 class="font-bold text-slate-800">{{ $client->nom_complet }}</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $client->getTypeLabel() }} — {{ $client->telephone }}</p>
            @else
            <h3 class="font-bold text-slate-800">{{ $marqueGarantie->nom }} <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 rounded px-1.5 py-0.5">Garantie constructeur</span></h3>
            <p class="text-xs text-slate-500 mt-0.5">Plafond {{ number_format($marqueGarantie->plafond_credit, 0, ',', ' ') }} FDJ — disponible {{ number_format($marqueGarantie->disponible, 0, ',', ' ') }} FDJ</p>
            @endif
        </div>
        <a href="{{ route('encaissements-globaux.create') }}"
           class="text-xs text-orange-500 hover:underline">Changer de {{ $client ? 'client' : 'marque' }}</a>
    </div>

    {{-- Mode de paiement --}}
    <div class="border-t border-gray-100 pt-4">
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Mode de paiement</label>
        <div class="flex flex-wrap gap-2">
            @php
            $modes = $client
                ? ['especes' => 'Espèces', 'cheque' => 'Chèque', 'carte' => 'Carte', 'virement' => 'Virement', 'compte' => 'Compte société']
                : ['virement' => 'Virement', 'cheque' => 'Chèque', 'especes' => 'Espèces'];
            $defaultMode = $client
                ? (in_array($client->type, ['societe','assurance']) ? 'compte' : 'especes')
                : 'virement';
            @endphp
            <input type="hidden" name="mode_paiement" id="mode_val" value="{{ $defaultMode }}">
            @foreach($modes as $val => $label)
            <button type="button" onclick="selectMode('{{ $val }}')" data-mode="{{ $val }}"
                    class="mode-btn border-2 rounded-xl px-4 py-1.5 text-xs font-medium transition-all
                           {{ $defaultMode === $val ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-slate-600 hover:border-gray-300' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>
</div>

{{-- Factures disponibles --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Factures émises — non regroupées</h3>
        <button type="button" onclick="toutSelectionner()"
                class="text-xs text-orange-500 hover:underline font-medium">Tout sélectionner</button>
    </div>

    @if($facturesDisponibles->isEmpty())
    <div class="px-6 py-10 text-center text-slate-400 text-sm">
        {{ $client ? 'Ce client' : 'Cette marque' }} n'a aucune facture émise en attente de paiement.
    </div>
    @else
    <div class="divide-y divide-gray-100">
        @foreach($facturesDisponibles as $facture)
        <label class="flex items-center gap-4 px-6 py-4 hover:bg-orange-50/40 cursor-pointer transition-colors facture-row">
            <input type="checkbox" name="facture_ids[]" value="{{ $facture->id }}"
                   onchange="recalculerTotal()"
                   data-montant="{{ $facture->totalGeneral() }}"
                   class="w-5 h-5 rounded border-gray-300 text-orange-500 focus:ring-orange-500 facture-check">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-slate-800 text-sm">{{ $facture->numero }}</span>
                    <span class="text-xs text-slate-500">— OR {{ $facture->ordreReparation->numero }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Émise le {{ $facture->date_emission->format('d/m/Y') }}</p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="font-bold text-slate-800 text-sm">{{ number_format($facture->totalGeneral(), 0, ',', ' ') }} FDJ</p>
            </div>
        </label>
        @endforeach
    </div>

    {{-- Total récapitulatif --}}
    <div class="border-t-2 border-orange-200 bg-orange-50 px-6 py-4 flex items-center justify-between">
        <div class="text-sm text-slate-600">
            <span id="nb-selectionnes">0</span> facture(s) sélectionnée(s)
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-500">Total à encaisser</p>
            <p class="text-2xl font-black text-orange-500" id="total-affiche">0,00 FDJ</p>
        </div>
    </div>
    @endif
</div>

@if($facturesDisponibles->isNotEmpty())
<div>
    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Notes (optionnel)</label>
    <textarea name="notes" rows="2"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none bg-white"
              placeholder="Remarques sur cet encaissement..."></textarea>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit"
            class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm"
            id="btn-submit" disabled>
        Créer l'encaissement groupé
    </button>
    <a href="{{ route('encaissements-globaux.index') }}"
       class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
        Annuler
    </a>
</div>
@endif

</form>
@endif

</div>

<script>
function selectMode(val) {
    document.getElementById('mode_val').value = val;
    document.querySelectorAll('.mode-btn').forEach(btn => {
        const active = btn.dataset.mode === val;
        btn.classList.toggle('border-orange-500', active);
        btn.classList.toggle('bg-orange-50',      active);
        btn.classList.toggle('text-orange-700',   active);
        btn.classList.toggle('border-gray-200',   !active);
        btn.classList.toggle('text-slate-600',    !active);
    });
}

function recalculerTotal() {
    let total = 0, nb = 0;
    document.querySelectorAll('.facture-check:checked').forEach(cb => {
        total += parseFloat(cb.dataset.montant) || 0;
        nb++;
    });
    document.getElementById('total-affiche').textContent = formatFDJ(total) + ' FDJ';
    document.getElementById('nb-selectionnes').textContent = nb;
    document.getElementById('btn-submit').disabled = nb === 0;
}

function toutSelectionner() {
    const checks = document.querySelectorAll('.facture-check');
    const tousCoches = Array.from(checks).every(c => c.checked);
    checks.forEach(c => c.checked = !tousCoches);
    recalculerTotal();
}

function formatFDJ(n) {
    return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ',');
}

document.addEventListener('DOMContentLoaded', recalculerTotal);
</script>
@endsection
