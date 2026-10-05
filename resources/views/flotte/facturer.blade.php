@extends('layouts.app')
@section('title', 'Facturer — ' . $livraison->vehicule?->immatriculation)
@section('page-title', 'Facturer la livraison du bus ' . $livraison->vehicule?->immatriculation)
@section('page-subtitle', $livraison->client?->nom_complet . ' — livrée le ' . $livraison->date_livraison->format('d/m/Y'))

@section('header-actions')
<a href="{{ route('flotte.show', $livraison->import_flotte_id) }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Import {{ $livraison->import?->numero }}
</a>
@endsection

@section('content')
@php
    $anciennes = old('lignes', $lignes);
@endphp
<div class="max-w-5xl space-y-5">

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
    <ul class="space-y-1">
        @foreach($errors->all() as $e)
        <li class="text-sm text-red-700">• {{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-800 space-y-1">
    <p>Quantités des pièces = <strong>ce que le magasin a réellement sorti</strong> (bon de transfert{{ $livraison->bonCommande?->bonTransfert ? ' ' . $livraison->bonCommande->bonTransfert->numero : '' }}). Prix = ceux du fichier Excel, sinon le prix du magasin.</p>
    <p>Vérifiez et corrigez si besoin avant de créer la facture. Pas d'OR ni de réception pour cette facture.</p>
</div>

<form method="POST" action="{{ route('flotte.facturer', $livraison) }}" class="space-y-5">
    @csrf

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="table-lignes">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">Type</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">Référence</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">Désignation</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500">Qté</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500">P.U. HT (FDJ)</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500">Remise %</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500">Total HT</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($anciennes as $i => $l)
                    <tr class="ligne">
                        <td class="px-3 py-2">
                            <select name="lignes[{{ $i }}][type]" class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                                <option value="piece" @selected(($l['type'] ?? '') === 'piece')>Pièce</option>
                                <option value="main_oeuvre" @selected(($l['type'] ?? '') === 'main_oeuvre')>Main d'œuvre</option>
                            </select>
                        </td>
                        <td class="px-3 py-2"><input type="text" name="lignes[{{ $i }}][reference]" value="{{ $l['reference'] ?? '' }}" class="w-28 px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-mono"></td>
                        <td class="px-3 py-2"><input type="text" name="lignes[{{ $i }}][designation]" value="{{ $l['designation'] ?? '' }}" required class="w-full min-w-40 px-2 py-1.5 border border-gray-300 rounded-lg text-sm"></td>
                        <td class="px-3 py-2 text-right">
                            <input type="number" step="0.01" min="0.01" name="lignes[{{ $i }}][quantite]" value="{{ $l['quantite'] ?? '' }}" required class="calc w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-right">
                            @if(isset($l['quantite_demandee']) && (float) $l['quantite_demandee'] != (float) $l['quantite'])
                            <p class="text-[11px] text-amber-600 mt-0.5">demandé : {{ rtrim(rtrim(number_format($l['quantite_demandee'], 2, ',', ''), '0'), ',') }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            <input type="number" step="1" min="0" name="lignes[{{ $i }}][prix_unitaire]" value="{{ isset($l['prix_unitaire']) && $l['prix_unitaire'] !== null ? (int) round($l['prix_unitaire']) : '' }}" required
                                   class="calc w-28 px-2 py-1.5 border rounded-lg text-sm text-right {{ ($l['prix_unitaire'] ?? null) === null ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                        </td>
                        <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" max="100" name="lignes[{{ $i }}][remise]" value="{{ ($l['remise'] ?? 0) ?: '' }}" class="calc w-16 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-right"></td>
                        <td class="px-3 py-2 text-right font-semibold text-slate-800 total-ligne whitespace-nowrap">—</td>
                        <td class="px-3 py-2 text-right"><button type="button" onclick="retirerLigne(this)" class="text-red-500 hover:text-red-700 text-xs font-bold" title="Retirer la ligne">✕</button></td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50">
                        <td colspan="6" class="px-3 py-3 text-right font-bold text-slate-700">Total HT (avant TVA 10 % et arrondi à 5 FDJ)</td>
                        <td class="px-3 py-3 text-right font-black text-orange-500 whitespace-nowrap" id="total-ht">—</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="frais_timbre" value="0">
            <input type="checkbox" name="frais_timbre" value="1" @checked(old('frais_timbre')) class="rounded border-gray-300">
            Frais de timbre (1 000 FDJ)
        </label>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Échéance</label>
            <input type="date" name="date_echeance" value="{{ old('date_echeance') }}" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
            <input type="text" name="notes" value="{{ old('notes') }}" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
        </div>
    </div>

    <button type="submit" onclick="return confirm('Créer la facture du bus {{ $livraison->vehicule?->immatriculation }} ?')"
            class="bg-green-500 hover:bg-green-600 text-white font-black text-sm px-8 py-3 rounded-xl transition-colors">
        ✓ Créer la facture
    </button>
</form>

</div>

<script>
function recalculer() {
    let total = 0;
    document.querySelectorAll('#table-lignes tr.ligne').forEach(tr => {
        const v = sel => parseFloat(tr.querySelector(sel)?.value.replace(',', '.')) || 0;
        const ligne = v('[name$="[quantite]"]') * v('[name$="[prix_unitaire]"]') * (1 - v('[name$="[remise]"]') / 100);
        tr.querySelector('.total-ligne').textContent = Math.round(ligne).toLocaleString('fr-FR') + ' FDJ';
        total += ligne;
    });
    document.getElementById('total-ht').textContent = Math.round(total).toLocaleString('fr-FR') + ' FDJ';
}
function retirerLigne(bouton) {
    if (document.querySelectorAll('#table-lignes tr.ligne').length <= 1) {
        alert('La facture doit contenir au moins une ligne.');
        return;
    }
    bouton.closest('tr').remove();
    recalculer();
}
document.querySelectorAll('#table-lignes .calc').forEach(input => input.addEventListener('input', recalculer));
recalculer();
</script>
@endsection
