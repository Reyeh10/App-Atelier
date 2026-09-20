@extends('layouts.app')
@section('title', $devis->numero)
@section('page-title', $devis->numero)
@section('page-subtitle', $devis->parent->client->nom_complet . ' — ' . $devis->parent->vehicule->immatriculation)

@section('header-actions')
<div class="flex gap-2">
    @if(in_array($devis->statut, ['brouillon','envoye']) && auth()->user()->hasPermission('gerer_devis'))
    <a href="{{ route('devis.edit', $devis) }}"
       class="flex items-center gap-2 text-sm bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg px-3 py-2 transition-colors">
        ✏️ Modifier
    </a>
    <form method="POST" action="{{ route('devis.destroy', $devis) }}"
          onsubmit="return confirm('Supprimer ce devis ? Cette action est irréversible.')">
        @csrf @method('DELETE')
        <button type="submit" class="flex items-center gap-2 text-sm bg-red-500 hover:bg-red-600 text-white rounded-lg px-3 py-2 transition-colors">
            🗑 Supprimer
        </button>
    </form>
    @endif
    <a href="{{ route('devis.imprimer', $devis) }}?apercu=1" target="_blank"
       class="flex items-center gap-2 text-sm border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        👁 Aperçu
    </a>
    <a href="{{ route('devis.imprimer', $devis) }}" target="_blank"
       class="flex items-center gap-2 text-sm bg-slate-700 hover:bg-slate-800 text-white rounded-lg px-3 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Imprimer
    </a>
    @if($devis->bonCommande && auth()->user()->peutVoirBonsCommande())
    <a href="{{ route('bons-commande.show', $devis->bonCommande) }}"
       class="flex items-center gap-2 text-sm bg-teal-600 hover:bg-teal-700 text-white rounded-lg px-3 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        BC {{ $devis->bonCommande->numero }}
    </a>
    @endif
    @if($devis->or_id)
    <a href="{{ route('ordres-reparations.show', $devis->or_id) }}"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← OR {{ $devis->parent->numero }}
    </a>
    @else
    <a href="{{ route('dossiers-reception.show', $devis->dossier_id) }}"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Dossier {{ $devis->parent->numero }}
    </a>
    @endif
</div>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

@php $attendFournisseur = $devis->attendReponseFournisseur(); @endphp

{{-- Statut + workflow --}}
<div class="bg-white rounded-2xl border-2 border-{{ $devis->getStatutColor() }}-300 p-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-{{ $devis->getStatutColor() }}-100 text-{{ $devis->getStatutColor() }}-700">
                {{ $devis->getStatutLabel() }}
            </span>
            <span class="text-slate-500 text-sm">{{ $devis->numero }}</span>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->peutValiderDevis())
            @if($devis->statut === 'brouillon')
            @if($attendFournisseur)
            <button type="button" disabled title="En attente de la confirmation du fournisseur pour toutes les pièces"
                    class="bg-gray-200 text-gray-400 text-sm font-bold px-4 py-2 rounded-xl cursor-not-allowed">
                📤 Marquer envoyé au client
            </button>
            @else
            <form method="POST" action="{{ route('devis.envoyer', $devis) }}">
                @csrf @method('PATCH')
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
                    📤 Marquer envoyé au client
                </button>
            </form>
            @endif
            @endif
            @if(in_array($devis->statut, ['brouillon','envoye']))
            @if($attendFournisseur)
            <button type="button" disabled title="En attente de la confirmation du fournisseur pour toutes les pièces"
                    class="bg-gray-200 text-gray-400 text-sm font-bold px-4 py-2 rounded-xl cursor-not-allowed">
                ✓ Marquer accepté
            </button>
            <button type="button" disabled title="En attente de la confirmation du fournisseur pour toutes les pièces"
                    class="bg-gray-200 text-gray-400 text-sm font-bold px-4 py-2 rounded-xl cursor-not-allowed">
                ✗ Refusé
            </button>
            @else
            <form method="POST" action="{{ route('devis.accepter', $devis) }}">
                @csrf @method('PATCH')
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
                    ✓ Marquer accepté
                </button>
            </form>
            <form method="POST" action="{{ route('devis.refuser', $devis) }}">
                @csrf @method('PATCH')
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors"
                        onclick="return confirm('Confirmer le refus du devis ?')">
                    ✗ Refusé
                </button>
            </form>
            @endif
            @endif
            @endif
        </div>
    </div>

    @if(in_array($devis->statut, ['brouillon','envoye']) && ! auth()->user()->peutValiderDevis())
    <p class="mt-3 text-xs text-slate-400 bg-gray-50 border border-gray-100 rounded-xl px-4 py-2">
        En attente de validation par une personne autorisée (chef d'atelier, administrateur, ou droit "Valider les devis").
    </p>
    @endif

    @if(in_array($devis->statut, ['brouillon','envoye']) && $attendFournisseur)
    <p class="mt-3 text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-xl px-4 py-2">
        ⏳ En attente de la confirmation du fournisseur pour une ou plusieurs pièces — la validation du devis n'est pas possible tant que le magasin n'a pas répondu.
    </p>
    @endif

    {{-- Upload devis signé — mêmes droits que la validation (gerer_devis ou valider_devis) --}}
    @if(in_array($devis->statut, ['envoye','brouillon']) && auth()->user()->peutValiderDevis() && ! $attendFournisseur)
    <div class="mt-4 border-t border-gray-100 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Uploader le devis signé par le client</p>
        <form method="POST" action="{{ route('devis.upload-signature', $devis) }}" enctype="multipart/form-data" class="flex gap-3 items-center">
            @csrf @method('PATCH')
            <input type="file" name="fichier_signe" accept=".pdf,.jpg,.jpeg,.png" required
                   class="flex-1 text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded-xl transition-colors flex-shrink-0">
                Valider & Uploader
            </button>
        </form>
        <p class="text-xs text-slate-400 mt-1">PDF, JPG ou PNG — max 5 Mo — cela marquera le devis comme accepté automatiquement</p>
    </div>
    @endif

    @if($devis->fichier_signe)
    <div class="mt-3 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Devis signé disponible —
        <a href="{{ asset('storage/' . $devis->fichier_signe) }}" target="_blank" class="underline">Télécharger</a>
    </div>
    @endif
</div>

{{-- Lignes devis --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Détail des prestations</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Type</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Désignation</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Référence</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Qté / Heures</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">P.U. HT</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Remise</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Total HT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($devis->lignes as $ligne)
                <tr class="{{ $ligne->type === 'main_oeuvre' ? 'bg-blue-50/30' : ($ligne->type === 'piece' ? 'bg-orange-50/30' : '') }}">
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded text-xs font-medium
                            {{ $ligne->type === 'main_oeuvre' ? 'bg-blue-100 text-blue-700' : ($ligne->type === 'piece' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ $ligne->getTypeLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-slate-700 font-medium">
                        {{ $ligne->designation }}
                        @if($ligne->type === 'piece' && $ligne->disponible === true)
                        <span class="block text-xs text-green-600 font-normal mt-0.5">✓ Confirmé par le fournisseur</span>
                        @elseif($ligne->type === 'piece' && $ligne->disponible === false)
                        <span class="block text-xs text-red-600 font-normal mt-0.5">⚠ Indisponible chez le fournisseur{{ $ligne->note_fournisseur ? ' — ' . $ligne->note_fournisseur : '' }}</span>
                        @elseif($ligne->type === 'piece' && is_null($ligne->disponible))
                        <span class="block text-xs text-slate-400 font-normal mt-0.5">En attente de réponse du fournisseur…</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-500 font-mono text-xs">
                        {{ $ligne->reference ?: '—' }}
                    </td>
                    <td class="px-5 py-3 text-right text-slate-600">
                        {{ number_format($ligne->quantite, 0, ',', ' ') }}
                        @if($ligne->type === 'main_oeuvre')
                            <span class="text-xs text-blue-500">h</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} FDJ</td>
                    <td class="px-5 py-3 text-right text-slate-500">{{ $ligne->remise > 0 ? $ligne->remise . '%' : '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ number_format($ligne->total_ht, 0, ',', ' ') }} FDJ</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totaux --}}
    <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 flex justify-end">
        <div class="space-y-2 min-w-64">
            <div class="flex justify-between text-sm"><span class="text-slate-500">Total HT</span><span class="font-semibold">{{ number_format($devis->montant_ht, 0, ',', ' ') }} FDJ</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">TVA ({{ (int) $devis->taux_tva }}%)</span><span class="font-semibold">{{ number_format($devis->montant_tva, 0, ',', ' ') }} FDJ</span></div>
            <div class="flex justify-between text-base font-bold border-t border-gray-300 pt-2">
                <span class="text-slate-800">Total TTC</span>
                <span class="text-orange-500 text-lg">{{ number_format($devis->montant_ttc, 0, ',', ' ') }} FDJ</span>
            </div>
        </div>
    </div>
</div>

@if($devis->notes)
<div class="bg-white rounded-2xl border border-gray-200 p-5">
    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Notes / Conditions</p>
    <p class="text-sm text-slate-600">{{ $devis->notes }}</p>
</div>
@endif

</div>
@endsection
