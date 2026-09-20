@extends('layouts.app')
@section('title', 'Créer une facture')
@section('page-title', 'Créer une facture')
@section('page-subtitle', $or->numero . ' — ' . $or->client->nom_complet)

@section('header-actions')
<a href="{{ route('ordres-reparations.show', $or) }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour OR
</a>
@endsection

@php
    $estGarantieApprouvee = $or->type === 'garantie' && $or->statut_garantie === 'approuve';
    $marqueGarantie = $estGarantieApprouvee ? \App\Models\MarqueGarantie::pourMarque($or->vehicule->marque) : null;
@endphp

@section('content')
<form method="POST" action="{{ route('factures.store', $or) }}" id="form-facture">
@csrf
<div class="max-w-5xl space-y-5">

@if($estGarantieApprouvee)
<div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-800">
    @if($marqueGarantie)
    Panne couverte par la garantie constructeur — cette facture sera automatiquement adressée au compte
    <strong>{{ $marqueGarantie->nom }}</strong> ({{ number_format($marqueGarantie->disponible, 0, ',', ' ') }} FDJ disponibles),
    pas au client.
    @else
    <strong>⚠ Attention :</strong> panne couverte par la garantie constructeur, mais aucun compte garantie n'est configuré pour la marque « {{ $or->vehicule->marque }} ».
    <a href="{{ route('parametres.index') }}?onglet=garantie" class="underline font-medium">Ajoutez-le dans Réglages atelier</a> avant de créer cette facture.
    @endif
</div>
@endif

{{-- Client + paiement --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <div class="grid grid-cols-2 gap-6">
        <div>
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ $estGarantieApprouvee ? 'Propriétaire du véhicule' : 'Client facturé' }}</h3>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="font-bold text-slate-900">{{ $or->client->nom_complet }}</p>
                <p class="text-sm text-slate-500">{{ $or->client->telephone }}</p>
                @if($or->client->email)<p class="text-sm text-slate-500">{{ $or->client->email }}</p>@endif
                <p class="text-xs text-slate-400 mt-1">Type : <span class="font-medium">{{ $or->client->getTypeLabel() }}</span></p>
            </div>
        </div>
        <div class="space-y-3">
            @if(!$estGarantieApprouvee && $or->client->compte_actif)
                @if($or->client->plafond_compte && !$or->client->peutFacturerSurCompte())
                <div class="bg-red-50 border border-red-300 rounded-xl p-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <div>
                        <p class="text-xs font-bold text-red-800">Plafond de compte atteint — facturation bloquée</p>
                        <p class="text-xs text-red-700">{{ number_format($or->client->solde_compte, 0, ',', ' ') }} FDJ utilisés sur {{ number_format($or->client->plafond_compte, 0, ',', ' ') }} FDJ autorisés. Encaissez les factures en attente avant de continuer.</p>
                    </div>
                </div>
                @else
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <div>
                        <p class="text-xs font-bold text-indigo-800">Compte crédit actif</p>
                        <p class="text-xs text-indigo-600">La facture sera créée non payée. Le caissier pourra encaisser cash ou mettre sur le compte selon le choix du client.</p>
                    </div>
                </div>
                @endif
            @else
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/>
                </svg>
                <p class="text-xs text-yellow-800">La facture sera créée en mode <strong>non payée</strong>. Le caissier enregistrera le paiement ensuite.</p>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Lignes --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Détail des prestations</h3>
        <button type="button" onclick="ajouterLigne()"
                class="flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Ajouter une ligne
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="table-lignes">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 w-32">Type</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">Désignation</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 w-28">Référence <span class="text-slate-300 font-normal">(pièce)</span></th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 w-20">Unité</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-24">Qté / Heures</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-28">P.U. HT (FDJ)</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-20">Remise %</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-28">Total HT</th>
                    <th class="px-3 py-3 w-8"></th>
                </tr>
            </thead>
            <tbody id="lignes-body">
                @php
                $uniteOptions = ['PCS' => 'PCS', 'Litre' => 'Litre', 'H' => 'H (heure)', 'Fft' => 'Forfait', 'Kg' => 'Kg', 'U' => 'Unité', 'Lot' => 'Lot'];
                $uniteDefaut  = ['main_oeuvre' => 'H', 'piece' => 'PCS', 'forfait' => 'Fft', 'autre' => 'U'];
                $i = 0;
                $totalLignes = $or->allDevis->sum(fn($d) => $d->lignes->count());
                @endphp

                @if($totalLignes > 0)
                    @foreach($or->allDevis as $devis)
                        @if($devis->lignes->count())
                        {{-- Séparateur de devis --}}
                        <tr class="bg-slate-50">
                            <td colspan="9" class="px-4 py-2 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                {{ $devis->numero }}
                                <span class="ml-2 px-2 py-0.5 rounded-full bg-{{ $devis->getStatutColor() }}-100 text-{{ $devis->getStatutColor() }}-700 font-medium normal-case">{{ $devis->getStatutLabel() }}</span>
                                <span class="ml-1 text-slate-400 font-normal">— {{ $devis->lignes->count() }} ligne(s)</span>
                            </td>
                        </tr>
                        @foreach($devis->lignes as $ligne)
                        @php $defUnite = $uniteDefaut[$ligne->type] ?? 'U'; @endphp
                        <tr class="ligne-row border-b border-gray-100 {{ $ligne->type === 'main_oeuvre' ? 'bg-blue-50/30' : ($ligne->type === 'piece' ? 'bg-orange-50/30' : '') }}" data-index="{{ $i }}">
                            <td class="px-3 py-3">
                                <select name="lignes[{{ $i }}][type]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500">
                                    @foreach(['main_oeuvre' => "Main d'œuvre", 'piece' => 'Pièce', 'forfait' => 'Forfait', 'autre' => 'Autre'] as $v => $l)
                                    <option value="{{ $v }}" {{ $ligne->type === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-3">
                                <input type="text" name="lignes[{{ $i }}][designation]" value="{{ $ligne->designation }}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-orange-500" required>
                            </td>
                            <td class="px-3 py-3">
                                <input type="text" name="lignes[{{ $i }}][reference]" value="{{ $ligne->reference }}"
                                       {{ $ligne->type !== 'piece' ? 'disabled' : '' }}
                                       class="w-full border rounded-lg px-2 py-1.5 text-xs font-mono {{ $ligne->type === 'piece' ? 'border-gray-300 bg-white focus:outline-none focus:ring-1 focus:ring-orange-500' : 'border-gray-100 bg-gray-50 text-slate-400 cursor-not-allowed' }}"
                                       placeholder="{{ $ligne->type === 'piece' ? 'Réf.' : '—' }}">
                            </td>
                            <td class="px-3 py-3">
                                <select name="lignes[{{ $i }}][unite]" class="w-full border border-gray-300 rounded-lg px-1 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500">
                                    @foreach($uniteOptions as $v => $l)
                                    <option value="{{ $v }}" {{ ($ligne->unite ?? $defUnite) === $v ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-3">
                                <input type="number" name="lignes[{{ $i }}][quantite]" value="{{ $ligne->quantite }}" min="0.01" step="0.01"
                                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-qty" oninput="calculerLigne(this)">
                            </td>
                            <td class="px-3 py-3">
                                <input type="number" name="lignes[{{ $i }}][prix_unitaire]" value="{{ $ligne->prix_unitaire }}" min="0" step="0.01"
                                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-pu" oninput="calculerLigne(this)">
                            </td>
                            <td class="px-3 py-3">
                                <input type="number" name="lignes[{{ $i }}][remise]" value="{{ $ligne->remise }}" min="0" max="100" step="0.01"
                                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-remise" oninput="calculerLigne(this)">
                            </td>
                            <td class="px-3 py-3 text-right">
                                <span class="font-semibold text-slate-800 ligne-total text-xs">{{ number_format($ligne->total_ht, 0, ',', ' ') }}</span>
                                <input type="hidden" name="lignes[{{ $i }}][total_ht]" class="ligne-total-input" value="{{ $ligne->total_ht }}">
                            </td>
                            <td class="px-3 py-3 text-center">
                                <button type="button" onclick="supprimerLigne(this)" class="text-red-400 hover:text-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </td>
                        </tr>
                        @php $i++; @endphp
                        @endforeach
                        @endif
                    @endforeach
                @else
                <tr class="ligne-row border-b border-gray-100" data-index="0">
                    <td class="px-3 py-3">
                        <select name="lignes[0][type]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500">
                            <option value="main_oeuvre" selected>Main d'œuvre</option>
                            <option value="piece">Pièce</option><option value="forfait">Forfait</option><option value="autre">Autre</option>
                        </select>
                    </td>
                    <td class="px-3 py-3"><input type="text" name="lignes[0][designation]" value="{{ $or->motif_entree }}" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-orange-500" required></td>
                    <td class="px-3 py-3"><input type="text" name="lignes[0][reference]" disabled placeholder="—" class="w-full border border-gray-100 bg-gray-50 rounded-lg px-2 py-1.5 text-xs text-slate-400 cursor-not-allowed"></td>
                    <td class="px-3 py-3">
                        <select name="lignes[0][unite]" class="w-full border border-gray-300 rounded-lg px-1 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500">
                            <option value="H" selected>H</option><option value="PCS">PCS</option><option value="Litre">Litre</option><option value="Fft">Fft</option><option value="Kg">Kg</option><option value="U">U</option><option value="Lot">Lot</option>
                        </select>
                    </td>
                    <td class="px-3 py-3"><input type="number" name="lignes[0][quantite]" value="1" min="0.01" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-qty" oninput="calculerLigne(this)"></td>
                    <td class="px-3 py-3"><input type="number" name="lignes[0][prix_unitaire]" value="0" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-pu" oninput="calculerLigne(this)"></td>
                    <td class="px-3 py-3"><input type="number" name="lignes[0][remise]" value="0" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-remise" oninput="calculerLigne(this)"></td>
                    <td class="px-3 py-3 text-right"><span class="font-semibold text-slate-800 ligne-total text-xs">0,00</span><input type="hidden" name="lignes[0][total_ht]" class="ligne-total-input" value="0"></td>
                    <td class="px-3 py-3"></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-6 py-4 bg-gray-50">
        <div class="flex items-end justify-between">
            {{-- Frais de timbre optionnels : ajoutés seulement si la caissière coche la case (1 000 FDJ fixes) --}}
            <label class="flex items-center gap-3 cursor-pointer select-none bg-white border border-gray-300 hover:border-orange-400 rounded-xl px-4 py-2.5 transition-colors">
                <input type="checkbox" name="frais_timbre" id="frais_timbre" value="1"
                       class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"
                       onchange="recalculerTotaux()">
                <span class="text-sm font-semibold text-slate-700">Ajouter les frais de timbre <span class="text-slate-400 font-normal">(1 000 FDJ)</span></span>
            </label>
            <div class="space-y-2 min-w-64">
                <div class="flex justify-between text-sm"><span class="text-slate-500">Total HT</span><span class="font-semibold" id="total-ht">0,00 FDJ</span></div>
                <div class="flex justify-between text-sm"><span class="text-slate-500">TVA (10%)</span><span class="font-semibold" id="total-tva">0,00 FDJ</span></div>
                <div class="flex justify-between text-sm"><span class="text-slate-500">Frais de timbre</span><span class="font-semibold" id="total-timbre">0,00 FDJ</span></div>
                <div class="flex justify-between text-base font-bold border-t border-gray-300 pt-2"><span>Total général</span><span class="text-orange-500" id="total-ttc">0,00 FDJ</span></div>
            </div>
        </div>
    </div>
</div>

<div>
    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Notes sur la facture</label>
    <textarea name="notes" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none bg-white" placeholder="Conditions de paiement, remarques..."></textarea>
</div>

<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">
        Émettre la facture
    </button>
    <a href="{{ route('ordres-reparations.show', $or) }}" class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
        Annuler
    </a>
</div>

</div>
</form>

<script>
let ligneIndex = {{ max(1, $or->allDevis->sum(fn($d) => $d->lignes->count())) }};

function selectMode(val) {
    document.getElementById('mode_paiement_value').value = val;
    document.querySelectorAll('.mode-btn').forEach(btn => {
        const active = btn.dataset.mode === val;
        btn.classList.toggle('border-orange-500', active);
        btn.classList.toggle('bg-orange-50',      active);
        btn.classList.toggle('text-orange-700',   active);
        btn.classList.toggle('border-gray-200',   !active);
        btn.classList.toggle('text-slate-600',    !active);
    });
    document.getElementById('echeance_wrap').classList.toggle('hidden', val !== 'compte');
}

function ajouterLigne() {
    const i = ligneIndex++;
    const row = document.createElement('tr');
    row.className = 'ligne-row border-b border-gray-100';
    row.innerHTML = `
        <td class="px-3 py-3"><select name="lignes[${i}][type]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500"><option value="main_oeuvre">Main d'œuvre</option><option value="piece">Pièce</option><option value="forfait">Forfait</option><option value="autre">Autre</option></select></td>
        <td class="px-3 py-3"><input type="text" name="lignes[${i}][designation]" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-orange-500" required placeholder="Description..."></td>
        <td class="px-3 py-3"><input type="text" name="lignes[${i}][reference]" placeholder="Réf." class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-orange-500"></td>
        <td class="px-3 py-3"><select name="lignes[${i}][unite]" class="w-full border border-gray-300 rounded-lg px-1 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500"><option value="PCS" selected>PCS</option><option value="Litre">Litre</option><option value="H">H</option><option value="Fft">Fft</option><option value="Kg">Kg</option><option value="U">U</option><option value="Lot">Lot</option></select></td>
        <td class="px-3 py-3"><input type="number" name="lignes[${i}][quantite]" value="1" min="0.01" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-qty" oninput="calculerLigne(this)"></td>
        <td class="px-3 py-3"><input type="number" name="lignes[${i}][prix_unitaire]" value="0" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-pu" oninput="calculerLigne(this)"></td>
        <td class="px-3 py-3"><input type="number" name="lignes[${i}][remise]" value="0" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-remise" oninput="calculerLigne(this)"></td>
        <td class="px-3 py-3 text-right"><span class="font-semibold text-slate-800 ligne-total text-xs">0,00</span><input type="hidden" name="lignes[${i}][total_ht]" class="ligne-total-input" value="0"></td>
        <td class="px-3 py-3 text-center"><button type="button" onclick="supprimerLigne(this)" class="text-red-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></td>`;
    document.getElementById('lignes-body').appendChild(row);
}

function supprimerLigne(btn) {
    if (document.querySelectorAll('.ligne-row').length <= 1) return;
    btn.closest('tr').remove();
    recalculerTotaux();
}

function calculerLigne(input) {
    const row = input.closest('tr');
    const total = (parseFloat(row.querySelector('.ligne-qty').value)||0) * (parseFloat(row.querySelector('.ligne-pu').value)||0) * (1 - (parseFloat(row.querySelector('.ligne-remise').value)||0)/100);
    row.querySelector('.ligne-total').textContent = formatFDJ(total);
    row.querySelector('.ligne-total-input').value = total.toFixed(2);
    recalculerTotaux();
}

function recalculerTotaux() {
    // Taux fixe imposé par la direction — non modifiable par le formulaire.
    let ht = 0;
    document.querySelectorAll('.ligne-total-input').forEach(i => { ht += parseFloat(i.value)||0; });
    const tva    = ht * 0.10;
    const timbre = document.getElementById('frais_timbre').checked ? 1000 : 0;
    const total  = ht + tva + timbre;
    document.getElementById('total-ht').textContent     = formatFDJ(ht) + ' FDJ';
    document.getElementById('total-tva').textContent    = formatFDJ(tva) + ' FDJ';
    document.getElementById('total-timbre').textContent = formatFDJ(timbre) + ' FDJ';
    document.getElementById('total-ttc').textContent    = formatFDJ(total) + ' FDJ';
}

function formatFDJ(n) { return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ','); }

document.addEventListener('DOMContentLoaded', recalculerTotaux);
</script>
@endsection
