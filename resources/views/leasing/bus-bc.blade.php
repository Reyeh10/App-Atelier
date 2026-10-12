@extends('layouts.app')
@section('title', 'Bon de commande bus')
@section('page-title', 'Bon de commande — ' . mb_strtolower($g['libelle']))
@section('page-subtitle', 'Pièces pour un bus réparé dans un autre garage : le BC part au magasin, pas de facture (récap mensuel)')

@section('header-actions')
<a href="{{ route($g['route'] . '.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← {{ $g['libelle'] }}
</a>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

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

{{-- Un bus, une ou plusieurs pièces --}}
<form method="POST" action="{{ route($g['route'] . '.bc.store') }}" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
    @csrf
    <h3 class="text-sm font-bold text-slate-700">Un bus</h3>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Bus <span class="text-red-500">*</span></label>
        <select name="bus_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">— Choisir le bus —</option>
            @foreach($bus as $b)
            <option value="{{ $b->id }}" @selected(old('bus_id', $busId) == $b->id)>{{ $b->immatriculation }}{{ trim(($b->fiche?->marque ?? '') . ' ' . ($b->fiche?->modele ?? '')) ? ' — ' . trim($b->fiche->marque . ' ' . $b->fiche->modele) : '' }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Pièces <span class="text-red-500">*</span></label>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Désignation</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Référence (facultative)</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Quantité</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody id="lignes-bc" class="divide-y divide-gray-100">
                @foreach(old('lignes', [['designation' => '', 'reference' => '', 'quantite' => 1]]) as $i => $l)
                <tr>
                    <td class="px-3 py-2"><input type="text" name="lignes[{{ $i }}][designation]" value="{{ $l['designation'] ?? '' }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></td>
                    <td class="px-3 py-2"><input type="text" name="lignes[{{ $i }}][reference]" value="{{ $l['reference'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></td>
                    <td class="px-3 py-2 w-32"><input type="number" name="lignes[{{ $i }}][quantite]" value="{{ $l['quantite'] ?? 1 }}" min="0.01" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></td>
                    <td class="px-3 py-2 text-right"><button type="button" onclick="retirerLigneBc(this)" class="text-xs text-red-500 hover:text-red-700 font-semibold">Retirer</button></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <button type="button" onclick="ajouterLigneBc()" class="mt-2 text-sm text-orange-500 hover:text-orange-600 font-semibold">+ Ajouter une pièce</button>
    </div>

    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-6 rounded-xl text-sm transition-colors">
        Créer le bon de commande et l'envoyer au magasin
    </button>
</form>

{{-- Plusieurs bus : import Excel avec aperçu --}}
<form method="POST" action="{{ route($g['route'] . '.bc.apercu') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-3">
    @csrf
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-sm font-bold text-slate-700">Plusieurs bus : importer un fichier Excel</h3>
        <a href="{{ route($g['route'] . '.bc.modele') }}" class="text-xs text-orange-500 hover:underline font-semibold">⬇ Modèle Excel (Immatriculation, Désignation, Référence, Quantité)</a>
    </div>
    <p class="text-sm text-slate-600"><strong>Une ligne = une pièce</strong>, avec l'immatriculation du bus répétée sur chaque ligne. Un bon de commande est créé <strong>par bus</strong>.</p>
    <div class="flex gap-3 items-end flex-wrap">
        <input type="file" name="fichier" required accept=".xlsx,.xls,.csv"
               class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded-xl transition-colors">
            Vérifier le fichier →
        </button>
    </div>
    <p class="text-xs text-slate-400">Rien n'est enregistré à cette étape : un aperçu par bus s'affiche d'abord, avec les erreurs éventuelles (bus inconnu, quantité manquante…).</p>
</form>

</div>

<script>
function ajouterLigneBc() {
    const corps = document.getElementById('lignes-bc');
    const i = Date.now();
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="px-3 py-2"><input type="text" name="lignes[${i}][designation]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></td>
        <td class="px-3 py-2"><input type="text" name="lignes[${i}][reference]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></td>
        <td class="px-3 py-2 w-32"><input type="number" name="lignes[${i}][quantite]" value="1" min="0.01" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></td>
        <td class="px-3 py-2 text-right"><button type="button" onclick="retirerLigneBc(this)" class="text-xs text-red-500 hover:text-red-700 font-semibold">Retirer</button></td>`;
    corps.appendChild(tr);
    tr.querySelector('input').focus();
}
function retirerLigneBc(bouton) {
    const corps = document.getElementById('lignes-bc');
    if (corps.children.length > 1) bouton.closest('tr').remove();
}
</script>
@endsection
