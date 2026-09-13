@extends('layouts.app')
@section('title', 'Modifier ' . $devis->numero)
@section('page-title', 'Modifier le devis')
@section('page-subtitle', $devis->numero . ' — ' . $devis->parent->client->nom_complet)

@section('header-actions')
<a href="{{ route('devis.show', $devis) }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Retour devis
</a>
@endsection

@section('content')
<form method="POST" action="{{ route('devis.update', $devis) }}" id="form-devis">
@csrf @method('PUT')

<div id="operations-dropdown" class="hidden fixed z-50 bg-white border border-gray-200 rounded-xl shadow-lg overflow-y-auto" style="max-height: 280px;"></div>

<div class="max-w-5xl space-y-5">

{{-- En-tête --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-orange-50 rounded-xl p-4">
            <p class="text-xs text-orange-400 mb-1">Ordre de réparation</p>
            <p class="font-mono font-bold text-slate-900">{{ $devis->parent->numero }}</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
            <p class="text-xs text-slate-400 mb-1">Client</p>
            <p class="font-semibold text-slate-800 text-sm">{{ $devis->parent->client->nom_complet }}</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
            <p class="text-xs text-slate-400 mb-1">Véhicule</p>
            <p class="font-mono font-bold text-slate-700">{{ $devis->parent->vehicule->immatriculation }}</p>
            <p class="text-xs text-slate-500">{{ $devis->parent->vehicule->designation }}</p>
        </div>
    </div>
</div>

{{-- Options --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Taux TVA (%)</label>
            <input type="number" name="taux_tva" value="{{ $devis->taux_tva }}" min="0" max="100" step="0.01"
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Notes / Conditions</label>
            <input type="text" name="notes" value="{{ $devis->notes }}" placeholder="Validité du devis, remarques..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
    </div>
</div>

{{-- Lignes --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Détail des prestations</h3>
            <p class="text-xs text-slate-400 mt-0.5">Main d'œuvre → nombre d'heures &nbsp;|&nbsp; Pièce → quantité + référence &nbsp;|&nbsp; le prix des pièces est fourni automatiquement par le magasin</p>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="ajouterLigne('main_oeuvre')"
                    class="flex items-center gap-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors">
                + Main d'œuvre
            </button>
            <button type="button" onclick="ajouterLigne('piece')"
                    class="flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors">
                + Pièce
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="table-lignes">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 w-36">Type</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">Désignation</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 w-32">Référence <span class="text-slate-300 font-normal">(pièce)</span></th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-24">Qté / H</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-28">P.U. HT (FDJ)</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-20">Remise %</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 w-28">Total HT</th>
                    <th class="px-3 py-3 w-8"></th>
                </tr>
            </thead>
            <tbody id="lignes-body">
                @foreach($devis->lignes as $i => $ligne)
                @php $isPiece = $ligne->type === 'piece'; @endphp
                <tr class="ligne-row border-b border-gray-100" data-index="{{ $i }}">
                    <td class="px-3 py-3">
                        <select name="lignes[{{ $i }}][type]" onchange="typeChanged(this)"
                                class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500">
                            <option value="main_oeuvre" {{ $ligne->type === 'main_oeuvre' ? 'selected' : '' }}>Main d'œuvre</option>
                            <option value="piece" {{ $ligne->type === 'piece' ? 'selected' : '' }}>Pièce</option>
                        </select>
                    </td>
                    <td class="px-3 py-3">
                        <input type="text" name="lignes[{{ $i }}][designation]" value="{{ $ligne->designation }}" autocomplete="off"
                               class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-designation" required
                               oninput="operationInputChanged(this)" onfocus="ouvrirAutocompleteOperation(this)">
                        @if($isPiece && $ligne->disponible === true)
                        <p class="text-[11px] text-green-600 mt-1">✓ Confirmé par le fournisseur</p>
                        @elseif($isPiece && $ligne->disponible === false)
                        <p class="text-[11px] text-red-600 mt-1">⚠ Indisponible{{ $ligne->note_fournisseur ? ' — ' . $ligne->note_fournisseur : '' }}</p>
                        @elseif($isPiece && is_null($ligne->disponible))
                        <p class="text-[11px] text-slate-400 mt-1">En attente du fournisseur…</p>
                        @endif
                    </td>
                    <td class="px-3 py-3">
                        <input type="text" name="lignes[{{ $i }}][reference]" value="{{ $ligne->reference }}"
                               {{ !$isPiece ? 'disabled' : '' }}
                               class="w-full border rounded-lg px-2 py-1.5 text-xs ligne-ref {{ $isPiece ? 'border-gray-300 bg-white focus:outline-none focus:ring-1 focus:ring-orange-500' : 'border-gray-100 bg-gray-50 text-slate-400 cursor-not-allowed' }}"
                               placeholder="{{ $isPiece ? 'Ex: OE-12345' : '—' }}">
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-1">
                            <input type="number" name="lignes[{{ $i }}][quantite]" value="{{ $ligne->quantite }}" min="0.01" step="0.01"
                                   placeholder="{{ !$isPiece ? 'Heures' : 'Qté' }}"
                                   class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-qty" oninput="calculerLigne(this)">
                            <span class="ligne-unite text-xs font-bold w-4 text-center {{ !$isPiece ? 'text-blue-500' : 'text-orange-500' }}">{{ !$isPiece ? 'h' : 'u' }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <input type="number" name="lignes[{{ $i }}][prix_unitaire]" value="{{ $ligne->prix_unitaire }}" min="0" step="0.01"
                               @if($isPiece) readonly title="Prix fourni automatiquement par le magasin" @endif
                               class="w-full border rounded-lg px-2 py-1.5 text-xs text-right ligne-pu {{ $isPiece ? 'border-gray-100 bg-gray-50 text-slate-400 cursor-not-allowed' : 'border-gray-300 focus:outline-none focus:ring-1 focus:ring-orange-500' }}" oninput="calculerLigne(this)">
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
                        <button type="button" onclick="supprimerLigne(this)" class="text-red-400 hover:text-red-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totaux --}}
    <div class="border-t border-gray-200 px-6 py-4 bg-gray-50">
        <div class="flex justify-end">
            <div class="space-y-2 min-w-64">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Total HT</span>
                    <span class="font-semibold text-slate-800" id="total-ht">0,00 FDJ</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">TVA (<span id="taux-tva-display">{{ $devis->taux_tva }}</span>%)</span>
                    <span class="font-semibold text-slate-800" id="total-tva">0,00 FDJ</span>
                </div>
                <div class="flex justify-between text-base font-bold border-t border-gray-300 pt-2">
                    <span class="text-slate-800">Total TTC</span>
                    <span class="text-orange-500" id="total-ttc">0,00 FDJ</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Actions --}}
<div class="flex items-center gap-3 pb-6">
    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-sm text-sm">
        Enregistrer les modifications
    </button>
    <a href="{{ route('devis.show', $devis) }}" class="px-6 py-3 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm">
        Annuler
    </a>
</div>

</div>
</form>

<script>
let ligneIndex = {{ $devis->lignes->count() }};

// Référentiel des opérations de maintenance — cf. config/operations_maintenance.php
const OPERATIONS_LISTE  = @json($operationsMaintenance);
const OPERATIONS_DUREES = @json($dureesOperations);
let inputActifAutocomplete = null;

function operationInputChanged(input) {
    filtrerAutocompleteOperation(input);
    suggererDureeOperation(input);
}

function suggererDureeOperation(input) {
    const row = input.closest('tr');
    const type = row.querySelector('select[name$="[type]"]').value;
    if (type !== 'main_oeuvre') return;

    const duree = OPERATIONS_DUREES[input.value];
    if (duree === undefined) return;

    const qtyInput = row.querySelector('.ligne-qty');
    qtyInput.value = duree;
    calculerLigne(qtyInput);
}

function ouvrirAutocompleteOperation(input) {
    inputActifAutocomplete = input;
    filtrerAutocompleteOperation(input);
}

function filtrerAutocompleteOperation(input) {
    inputActifAutocomplete = input;
    const requete = input.value.trim().toLowerCase();
    const dropdown = document.getElementById('operations-dropdown');

    const resultats = requete
        ? OPERATIONS_LISTE.filter(op => op.designation.toLowerCase().includes(requete))
        : OPERATIONS_LISTE;

    if (resultats.length === 0) {
        dropdown.innerHTML = `<div class="px-3 py-4 text-sm text-slate-400 text-center">Aucune opération trouvée — la désignation libre reste utilisable.</div>`;
        dropdown.classList.remove('hidden');
        positionnerDropdown(input, dropdown);
        return;
    }

    // Regroupement par catégorie, avec en-tête collante pour naviguer plus facilement dans les 85 opérations
    const parCategorie = {};
    resultats.forEach(op => {
        (parCategorie[op.categorie] ??= []).push(op);
    });

    dropdown.innerHTML = Object.entries(parCategorie).map(([categorie, ops]) => `
        <div class="sticky top-0 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-gray-100">${categorie}</div>
        ${ops.map(op => `
        <div class="px-3 py-2 hover:bg-orange-50 cursor-pointer border-b border-gray-100"
             onmousedown="choisirOperationAutocomplete('${op.designation.replace(/'/g, "\\'")}')">
            <p class="text-sm font-medium text-slate-800">${op.designation}</p>
            <p class="text-xs text-slate-400">${op.temps_min}-${op.temps_max} min</p>
        </div>`).join('')}
    `).join('');

    dropdown.classList.remove('hidden');
    positionnerDropdown(input, dropdown);
}

function positionnerDropdown(input, dropdown) {
    const rect = input.getBoundingClientRect();
    dropdown.style.top   = (rect.bottom + 4) + 'px';
    dropdown.style.left  = rect.left + 'px';
    dropdown.style.width = Math.max(rect.width, 280) + 'px';
    dropdown.style.maxHeight = Math.min(360, window.innerHeight - rect.bottom - 16) + 'px';
}

function choisirOperationAutocomplete(designation) {
    if (!inputActifAutocomplete) return;
    inputActifAutocomplete.value = designation;
    suggererDureeOperation(inputActifAutocomplete);
    document.getElementById('operations-dropdown').classList.add('hidden');
}

document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('operations-dropdown');
    if (e.target !== inputActifAutocomplete && !dropdown.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

function ajouterLigne(type) {
    const i = ligneIndex++;
    const isPiece = type === 'piece';
    const isMO    = type === 'main_oeuvre';
    const row = document.createElement('tr');
    row.className = 'ligne-row border-b border-gray-100';
    row.dataset.index = i;
    row.innerHTML = `
        <td class="px-3 py-3">
            <select name="lignes[${i}][type]" onchange="typeChanged(this)"
                    class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-500">
                <option value="main_oeuvre" ${type === 'main_oeuvre' ? 'selected' : ''}>Main d'œuvre</option>
                <option value="piece" ${type === 'piece' ? 'selected' : ''}>Pièce</option>
            </select>
        </td>
        <td class="px-3 py-3">
            <input type="text" name="lignes[${i}][designation]" autocomplete="off"
                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-designation"
                   required placeholder="${isPiece ? 'Ex: Filtre à huile, Courroie...' : 'Description de la prestation...'}"
                   oninput="operationInputChanged(this)" onfocus="ouvrirAutocompleteOperation(this)">
        </td>
        <td class="px-3 py-3">
            <input type="text" name="lignes[${i}][reference]"
                   ${isPiece ? '' : 'disabled'}
                   class="w-full border rounded-lg px-2 py-1.5 text-xs ligne-ref ${isPiece ? 'border-gray-300 bg-white focus:outline-none focus:ring-1 focus:ring-orange-500' : 'border-gray-100 bg-gray-50 text-slate-400 cursor-not-allowed'}"
                   placeholder="${isPiece ? 'Ex: OE-12345' : '—'}">
        </td>
        <td class="px-3 py-3">
            <div class="flex items-center gap-1">
                <input type="number" name="lignes[${i}][quantite]" value="1" min="0.01" step="0.01"
                       placeholder="${isMO ? 'Heures' : 'Qté'}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-qty"
                       oninput="calculerLigne(this)">
                <span class="ligne-unite text-xs font-bold w-4 text-center ${isMO ? 'text-blue-500' : 'text-orange-500'}">${isMO ? 'h' : 'u'}</span>
            </div>
        </td>
        <td class="px-3 py-3">
            <input type="number" name="lignes[${i}][prix_unitaire]" value="0" min="0" step="0.01"
                   ${isPiece ? 'readonly title="Prix fourni automatiquement par le magasin"' : ''}
                   class="w-full border rounded-lg px-2 py-1.5 text-xs text-right ligne-pu ${isPiece ? 'border-gray-100 bg-gray-50 text-slate-400 cursor-not-allowed' : 'border-gray-300 focus:outline-none focus:ring-1 focus:ring-orange-500'}"
                   oninput="calculerLigne(this)">
        </td>
        <td class="px-3 py-3">
            <input type="number" name="lignes[${i}][remise]" value="0" min="0" max="100" step="0.01"
                   class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-remise"
                   oninput="calculerLigne(this)">
        </td>
        <td class="px-3 py-3 text-right">
            <span class="font-semibold text-slate-800 ligne-total text-xs">0,00</span>
            <input type="hidden" name="lignes[${i}][total_ht]" class="ligne-total-input" value="0">
        </td>
        <td class="px-3 py-3 text-center">
            <button type="button" onclick="supprimerLigne(this)" class="text-red-400 hover:text-red-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </td>`;
    document.getElementById('lignes-body').appendChild(row);
    recalculerTotaux();
}

function typeChanged(select) {
    const row       = select.closest('tr');
    const refInput  = row.querySelector('.ligne-ref');
    const qtyInput  = row.querySelector('.ligne-qty');
    const puInput   = row.querySelector('.ligne-pu');
    const uniteSpan = row.querySelector('.ligne-unite');
    const type      = select.value;

    if (type === 'piece') {
        refInput.disabled    = false;
        refInput.placeholder = 'Ex: OE-12345';
        refInput.className   = 'w-full border border-gray-300 bg-white rounded-lg px-2 py-1.5 text-xs ligne-ref focus:outline-none focus:ring-1 focus:ring-orange-500';
        qtyInput.placeholder = 'Qté';
        uniteSpan.textContent = 'u';
        uniteSpan.className   = 'ligne-unite text-xs font-bold text-orange-500 w-4 text-center';
        // Le prix des pièces vient du magasin : champ verrouillé, remis à 0.
        puInput.readOnly     = true;
        puInput.title        = 'Prix fourni automatiquement par le magasin';
        puInput.value        = 0;
        puInput.className     = 'w-full border border-gray-100 bg-gray-50 rounded-lg px-2 py-1.5 text-xs text-right text-slate-400 cursor-not-allowed ligne-pu';
        calculerLigne(puInput);
    } else {
        refInput.disabled    = true;
        refInput.value       = '';
        refInput.placeholder = '—';
        refInput.className   = 'w-full border border-gray-100 bg-gray-50 rounded-lg px-2 py-1.5 text-xs text-slate-400 ligne-ref cursor-not-allowed';
        qtyInput.placeholder = 'Heures';
        uniteSpan.textContent = 'h';
        uniteSpan.className   = 'ligne-unite text-xs font-bold text-blue-500 w-4 text-center';
        puInput.readOnly     = false;
        puInput.title        = '';
        puInput.className     = 'w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500 ligne-pu';
    }

    const designationInput = row.querySelector('.ligne-designation');
    if (designationInput) suggererDureeOperation(designationInput);
}

function supprimerLigne(btn) {
    const rows = document.querySelectorAll('.ligne-row');
    if (rows.length <= 1) return;
    btn.closest('tr').remove();
    recalculerTotaux();
}

function calculerLigne(input) {
    const row    = input.closest('tr');
    const qty    = parseFloat(row.querySelector('.ligne-qty').value) || 0;
    const pu     = parseFloat(row.querySelector('.ligne-pu').value) || 0;
    const remise = parseFloat(row.querySelector('.ligne-remise').value) || 0;
    const total  = qty * pu * (1 - remise / 100);
    row.querySelector('.ligne-total').textContent  = formatFDJ(total);
    row.querySelector('.ligne-total-input').value  = total.toFixed(2);
    recalculerTotaux();
}

function recalculerTotaux() {
    let ht = 0;
    document.querySelectorAll('.ligne-total-input').forEach(i => { ht += parseFloat(i.value) || 0; });
    const tva        = parseFloat(document.querySelector('[name="taux_tva"]').value) || 10;
    const montantTva = ht * tva / 100;
    document.getElementById('total-ht').textContent  = formatFDJ(ht) + ' FDJ';
    document.getElementById('total-tva').textContent = formatFDJ(montantTva) + ' FDJ';
    document.getElementById('total-ttc').textContent = formatFDJ(ht + montantTva) + ' FDJ';
    document.getElementById('taux-tva-display').textContent = tva;
}

function formatFDJ(n) {
    return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ',');
}

document.querySelector('[name="taux_tva"]').addEventListener('input', recalculerTotaux);
recalculerTotaux();
</script>
@endsection
