@extends('layouts.app')
@section('title', $bonCommande->numero)
@section('page-title', $bonCommande->numero)
@section('page-subtitle', ($bonCommande->vehicule?->immatriculation ?? '—') . ' — ' . ($bonCommande->client?->nom_complet ?? '—'))

@section('header-actions')
<div class="flex gap-2">
    <a href="{{ route('bons-commande.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Retour
    </a>
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

@php
    $valide = $bonCommande->estValideParFournisseur();
    $raisonReception = $bonCommande->raisonReceptionImpossible();
@endphp

{{-- Statut + actions --}}
<div class="bg-white rounded-2xl border-2 border-{{ $bonCommande->getStatutColor() }}-300 p-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-{{ $bonCommande->getStatutColor() }}-100 text-{{ $bonCommande->getStatutColor() }}-700">
                {{ $bonCommande->getStatutLabel() }}
            </span>
            <span class="font-mono font-bold text-slate-700">{{ $bonCommande->numero }}</span>
            <span class="text-slate-400 text-sm">Devis {{ $bonCommande->devis?->numero ?? '—' }}</span>
        </div>
        @if(auth()->user()->peutGererBonsCommande() && ! in_array($bonCommande->statut, ['recu', 'annule'], true))
        <div class="flex gap-2">
            @if(! $raisonReception)
            <form method="POST" action="{{ route('bons-commande.recevoir', $bonCommande) }}">
                @csrf @method('PATCH')
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
                    ✓ Tout reçu
                </button>
            </form>
            @else
            <button type="button" disabled title="{{ $raisonReception }}"
                    class="bg-gray-100 text-gray-400 text-sm font-bold px-4 py-2 rounded-xl cursor-not-allowed">
                ✓ Tout reçu
            </button>
            @endif
        </div>
        @endif
    </div>

    {{-- Réponse fournisseur --}}
    <div class="mt-4 pt-4 border-t border-gray-100">
        @if($bonCommande->fournisseur_repondu_at && $valide)
            <p class="text-xs text-slate-500">
                📡 Fournisseur (stcd-magasin) — disponibilité validée le {{ $bonCommande->fournisseur_repondu_at->format('d/m/Y à H:i') }}
            </p>
        @elseif($bonCommande->fournisseur_repondu_at)
            <p class="text-xs text-amber-600">
                📡 Réponse partielle du fournisseur reçue le {{ $bonCommande->fournisseur_repondu_at->format('d/m/Y à H:i') }} — certaines pièces restent à identifier côté stcd-magasin.
            </p>
        @else
            <p class="text-xs text-slate-400">📡 En attente de la réponse du fournisseur (stcd-magasin)...</p>
        @endif
    </div>
</div>

{{-- Info véhicule + OR/dossier --}}
<div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Véhicule</p>
        <p class="font-mono font-bold text-slate-900 text-lg">{{ $bonCommande->vehicule?->immatriculation ?? '—' }}</p>
        <p class="text-sm text-slate-500">{{ $bonCommande->vehicule?->designation }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Client</p>
        <p class="font-semibold text-slate-900">{{ $bonCommande->client?->nom_complet ?? '—' }}</p>
        <p class="text-sm text-slate-500">{{ $bonCommande->client?->telephone }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        @if($bonCommande->ordreReparation)
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Ordre de Réparation</p>
        <p class="font-mono font-bold text-slate-900">{{ $bonCommande->ordreReparation->numero }}</p>
        @elseif($bonCommande->livraisonFlotte)
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Livraison flotte</p>
        <a href="{{ route('flotte.show', $bonCommande->livraisonFlotte->import_flotte_id) }}" class="font-mono font-bold text-orange-500 hover:underline">{{ $bonCommande->livraisonFlotte->import?->numero }}</a>
        <p class="text-xs text-indigo-600 mt-0.5">Pièces livrées le {{ $bonCommande->livraisonFlotte->date_livraison->format('d/m/Y') }} — sans OR</p>
        @else
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Dossier de réception</p>
        <p class="font-mono font-bold text-slate-900">{{ $bonCommande->dossier?->numero ?? '—' }}</p>
        <p class="text-xs text-amber-600 mt-0.5">Devis pas encore accepté — pas d'OR pour l'instant</p>
        @endif
        <p class="text-sm text-slate-500">Créé le {{ $bonCommande->created_at->format('d/m/Y') }}</p>
    </div>
</div>

{{-- Liste des pièces --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Pièces commandées</h3>
        <p class="text-xs text-slate-400 mt-0.5">{{ $bonCommande->lignes->count() }} pièce(s)</p>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Désignation</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 font-mono">Référence</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500">Qté</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500">Disponibilité fournisseur</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500">Reçu au garage</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($bonCommande->lignes as $ligne)
            <tr class="{{ $ligne->recu ? 'bg-green-50' : '' }}">
                <td class="px-5 py-3 font-medium text-slate-800 {{ $ligne->recu ? 'line-through text-slate-400' : '' }}">
                    {{ $ligne->designation }}
                </td>
                <td class="px-5 py-3 font-mono text-sm text-orange-600 font-bold">{{ $ligne->reference ?: '—' }}</td>
                <td class="px-5 py-3 text-center font-bold text-slate-700">{{ rtrim(rtrim(number_format($ligne->quantite, 2, ',', ' '), '0'), ',') }}</td>
                <td class="px-5 py-3 text-center">
                    @if(is_null($ligne->disponible))
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">En attente</span>
                    @elseif($ligne->disponible)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Disponible{{ $ligne->quantite_disponible !== null ? ' ('.rtrim(rtrim(number_format($ligne->quantite_disponible, 2, ',', ' '), '0'), ',').')' : '' }}</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Indisponible</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-center">
                    @php $raisonLigne = $ligne->recu ? null : $bonCommande->raisonReceptionImpossible($ligne); @endphp
                    @if(auth()->user()->peutGererBonsCommande() && ! $raisonLigne)
                    <form method="POST" action="{{ route('bons-commande.ligne-recu', [$bonCommande, $ligne->id]) }}">
                        @csrf @method('PATCH')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-colors
                                    {{ $ligne->recu ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-slate-500 hover:bg-gray-200' }}">
                            {{ $ligne->recu ? '✓ Reçu' : '○ En attente' }}
                        </button>
                    </form>
                    @elseif(auth()->user()->peutGererBonsCommande())
                        <span class="text-xs text-slate-300" title="{{ $raisonLigne }}">○ {{ is_null($ligne->disponible) ? 'En attente (fournisseur)' : ($ligne->disponible ? 'En attente du BT' : 'Indisponible') }}</span>
                    @else
                        <span class="text-xs text-slate-400">{{ $ligne->recu ? '✓ Reçu' : '○ En attente' }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Bon de transfert du magasin (un seul par bon de commande) --}}
@php $bt = $bonCommande->bonTransfert; @endphp
<div id="bon-transfert" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Bon de transfert (BT)</h3>
            <p class="text-xs text-slate-400 mt-0.5">Établi par le magasin pour livrer les pièces de ce bon de commande.</p>
        </div>
        @if($bt)
        <a href="{{ route('bons-commande.bon-transfert', $bonCommande) }}" target="_blank"
           class="text-sm bg-orange-500 hover:bg-orange-600 text-white font-bold px-4 py-2 rounded-xl transition-colors">
            📄 Ouvrir le BT
        </a>
        @endif
    </div>
    <div class="p-6 space-y-4">
        @if($bt)
        <div class="flex flex-wrap gap-6 text-sm">
            <div><span class="text-slate-500">N° BT :</span> <span class="font-mono font-bold text-slate-800">{{ $bt->numero }}</span></div>
            <div><span class="text-slate-500">Date :</span> <span class="font-semibold text-slate-800">{{ $bt->date_transfert?->format('d/m/Y') ?? '—' }}</span></div>
            @if($bt->depot)<div><span class="text-slate-500">Dépôt :</span> <span class="font-semibold text-slate-800">{{ $bt->depot }}</span></div>@endif
            <div><span class="text-slate-500">Origine :</span> <span class="font-semibold text-slate-800">{{ $bt->getSourceLabel() }}</span></div>
            @if($bt->fichier_nom_original)<div><span class="text-slate-500">Fichier :</span> <a href="{{ $bt->fichier_url }}" target="_blank" class="text-orange-500 hover:underline">{{ $bt->fichier_nom_original }}</a></div>@endif
        </div>
        @if(! empty($bt->lignes))
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-2 text-left text-xs font-semibold text-slate-500">Référence</th>
                    <th class="py-2 text-left text-xs font-semibold text-slate-500">Désignation</th>
                    <th class="py-2 text-right text-xs font-semibold text-slate-500">Qté</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($bt->lignes as $l)
                <tr>
                    <td class="py-2 font-mono text-xs text-slate-600">{{ $l['reference'] ?? '—' }}</td>
                    <td class="py-2 text-slate-700">{{ $l['designation'] ?? '—' }}</td>
                    <td class="py-2 text-right text-slate-600">{{ isset($l['quantite']) ? rtrim(rtrim(number_format((float) $l['quantite'], 2, ',', ' '), '0'), ',') : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        @else
        <p class="text-sm text-slate-400">Pas encore de bon de transfert. Il arrive automatiquement dès que le magasin le crée ; sinon, joignez-le ci-dessous.</p>
        @endif

        @if(auth()->user()->peutGererBonsCommande())
        <form method="POST" action="{{ route('bons-commande.bon-transfert.enregistrer', $bonCommande) }}" enctype="multipart/form-data"
              class="flex flex-wrap items-end gap-3 pt-4 border-t border-gray-100">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">N° du BT <span class="text-red-500">*</span></label>
                <input type="text" name="numero" required maxlength="50" value="{{ old('numero', $bt?->numero) }}"
                       class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 w-40">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                <input type="date" name="date_transfert" value="{{ old('date_transfert', $bt?->date_transfert?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                       class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Scan / photo du BT @if(! $bt?->fichier_chemin)<span class="text-red-500">*</span>@endif</label>
                <input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png" {{ $bt?->fichier_chemin ? '' : 'required' }}
                       class="text-sm text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
            </div>
            <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
                {{ $bt ? 'Remplacer le BT' : 'Joindre le BT' }}
            </button>
        </form>
        @endif
    </div>
</div>

{{-- Correction administrateur — même après « Tout reçu » --}}
@if(auth()->user()->isAdmin())
<div class="bg-white rounded-2xl border-2 border-indigo-200 overflow-hidden">
    <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-200 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h3 class="text-sm font-bold text-indigo-700 uppercase tracking-wider">Correction administrateur</h3>
            <p class="text-xs text-indigo-600 mt-0.5">Les changements sont reportés sur le devis (sauf si l'OR est déjà facturé) et enregistrés dans le journal.</p>
        </div>
        <div class="flex gap-2">
            @if($bonCommande->statut === 'recu')
            <form method="POST" action="{{ route('bons-commande.rouvrir', $bonCommande) }}"
                  onsubmit="return confirm('Rouvrir ce bon de commande ? Toutes les pièces repasseront « en attente ».')">
                @csrf @method('PATCH')
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">↺ Rouvrir</button>
            </form>
            @endif
            <form method="POST" action="{{ route('bons-commande.supprimer', $bonCommande) }}"
                  onsubmit="return confirm('Supprimer définitivement ce bon de commande ?')">
                @csrf @method('DELETE')
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">🗑 Supprimer</button>
            </form>
        </div>
    </div>
    <form method="POST" action="{{ route('bons-commande.corriger', $bonCommande) }}">
        @csrf @method('PUT')
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Désignation</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Référence</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Qté</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Disponibilité</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Prix unit. (FDJ)</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Note</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500">Reçu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($bonCommande->lignes as $ligne)
                <tr>
                    <td class="px-3 py-2"><input type="text" name="lignes[{{ $ligne->id }}][designation]" value="{{ $ligne->designation }}" required class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white"></td>
                    <td class="px-3 py-2"><input type="text" name="lignes[{{ $ligne->id }}][reference]" value="{{ $ligne->reference }}" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white font-mono"></td>
                    <td class="px-3 py-2 w-20"><input type="number" name="lignes[{{ $ligne->id }}][quantite]" value="{{ rtrim(rtrim(number_format($ligne->quantite, 2, '.', ''), '0'), '.') }}" min="0.01" step="0.01" required class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white"></td>
                    <td class="px-3 py-2 w-36">
                        <select name="lignes[{{ $ligne->id }}][disponible]" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                            <option value="" {{ is_null($ligne->disponible) ? 'selected' : '' }}>En attente</option>
                            <option value="1" {{ $ligne->disponible === true ? 'selected' : '' }}>Disponible</option>
                            <option value="0" {{ $ligne->disponible === false ? 'selected' : '' }}>Indisponible</option>
                        </select>
                    </td>
                    <td class="px-3 py-2 w-32"><input type="number" name="lignes[{{ $ligne->id }}][prix_unitaire]" value="{{ $ligne->prix_unitaire !== null ? (int) round($ligne->prix_unitaire) : '' }}" min="0" step="1" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white"></td>
                    <td class="px-3 py-2"><input type="text" name="lignes[{{ $ligne->id }}][note]" value="{{ $ligne->note }}" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white"></td>
                    <td class="px-3 py-2 text-center"><input type="checkbox" name="lignes[{{ $ligne->id }}][recu]" value="1" {{ $ligne->recu ? 'checked' : '' }} class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
            <button type="submit" class="bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-bold px-6 py-2.5 rounded-xl transition-colors">Enregistrer la correction</button>
        </div>
    </form>
</div>
@endif

</div>
@endsection
