{{--
    Pièces et main-d'œuvre facturées — section commune à la fiche véhicule et à
    la fiche client (lignes des factures, filtres par dates / type, totaux de ce
    qui est affiché, n° de facture et d'OR).

    Paramètres :
      $lignesFacturees  Collection de LigneFacture (avec facture.ordreReparation.vehicule et facture.vehicule)
      $filtresLignes    ['date_debut', 'date_fin', 'type', 'vehicule_id' (fiche client)]
      $urlFiltre        URL de la page (sans ancre)
      $sousTitre        Texte sous le titre
      $messageVide      Message quand il n'y a aucune ligne (sans filtre)
      $vehiculesFiltre  (fiche client) véhicules proposés dans le filtre — affiche
                        aussi la colonne « Véhicule »
--}}
@php
    $vehiculesFiltre = $vehiculesFiltre ?? null;
    $parVehicule     = $vehiculesFiltre !== null;
    $totalHtLignes   = $lignesFacturees->sum('total_ht');
    $totalPiecesHt   = $lignesFacturees->where('type', 'piece')->sum('total_ht');
    $totalMoHt       = $lignesFacturees->where('type', 'main_oeuvre')->sum('total_ht');
    $qtePieces       = $lignesFacturees->where('type', 'piece')->sum('quantite');
    $heuresMo        = $lignesFacturees->where('type', 'main_oeuvre')->sum('quantite');
    $facturesLignes  = $lignesFacturees->pluck('facture')->unique('id')->values();
    $nbFactures      = $facturesLignes->count();
    $filtreActif     = $filtresLignes['date_debut'] || $filtresLignes['date_fin'] || $filtresLignes['type'] || ($filtresLignes['vehicule_id'] ?? null);
    $fmtQte          = fn ($q) => rtrim(rtrim(number_format($q, 2, ',', ' '), '0'), ',');
@endphp
<div id="pieces" class="bg-white rounded-2xl border border-gray-200 mt-5 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="font-semibold text-slate-800">Pièces et main-d'œuvre facturées</h3>
            <p class="text-xs text-slate-500">{{ $sousTitre }}</p>
        </div>
        <form method="GET" action="{{ $urlFiltre }}#pieces" class="flex flex-wrap items-end gap-2">
            @if($parVehicule)
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Véhicule</label>
                <select name="vehicule_id" onchange="this.form.submit()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">Tous les véhicules ({{ $vehiculesFiltre->count() }})</option>
                    @foreach($vehiculesFiltre as $vf)
                    <option value="{{ $vf->id }}" {{ (int) ($filtresLignes['vehicule_id'] ?? 0) === $vf->id ? 'selected' : '' }}>{{ $vf->immatriculation }} — {{ $vf->marque }} {{ $vf->modele }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Du</label>
                <input type="date" name="date_debut" value="{{ $filtresLignes['date_debut'] }}"
                       class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Au</label>
                <input type="date" name="date_fin" value="{{ $filtresLignes['date_fin'] }}"
                       class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Type</label>
                <select name="type" onchange="this.form.submit()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">Tout</option>
                    <option value="piece" {{ $filtresLignes['type'] === 'piece' ? 'selected' : '' }}>Pièces</option>
                    <option value="main_oeuvre" {{ $filtresLignes['type'] === 'main_oeuvre' ? 'selected' : '' }}>Main-d'œuvre</option>
                </select>
            </div>
            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-1.5 rounded-lg transition-colors">Filtrer</button>
            @if($filtreActif)
            <a href="{{ $urlFiltre }}#pieces" class="text-xs text-slate-400 hover:text-slate-600 pb-2">✕ Effacer</a>
            @endif
        </form>
    </div>

    {{-- Totaux de ce qui est affiché --}}
    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex flex-wrap gap-6 text-sm">
        <div><span class="text-slate-500">Lignes :</span> <span class="font-bold text-slate-800">{{ $lignesFacturees->count() }}</span> <span class="text-xs text-slate-400">({{ $nbFactures }} facture{{ $nbFactures > 1 ? 's' : '' }})</span></div>
        @if($filtresLignes['type'] !== 'main_oeuvre')
        <div><span class="text-slate-500">Pièces :</span> <span class="font-bold text-slate-800">{{ number_format($totalPiecesHt, 0, ',', ' ') }} FDJ</span> <span class="text-xs text-slate-400">({{ $fmtQte($qtePieces) }} unité{{ $qtePieces > 1 ? 's' : '' }})</span></div>
        @endif
        @if($filtresLignes['type'] !== 'piece')
        <div><span class="text-slate-500">Main-d'œuvre :</span> <span class="font-bold text-slate-800">{{ number_format($totalMoHt, 0, ',', ' ') }} FDJ</span> <span class="text-xs text-slate-400">({{ $fmtQte($heuresMo) }} h)</span></div>
        @endif
        <div class="ml-auto"><span class="text-slate-500">Total HT :</span> <span class="font-black text-orange-500">{{ number_format($totalHtLignes, 0, ',', ' ') }} FDJ</span></div>
        @if($nbFactures > 0)
        <div class="w-full">
            <span class="text-slate-500">N° de facture{{ $nbFactures > 1 ? 's' : '' }} :</span>
            @foreach($facturesLignes as $fl)
            <a href="{{ route('factures.show', $fl) }}" class="font-mono font-bold text-orange-500 hover:underline">{{ $fl->numero }}</a>@if(! $loop->last)<span class="text-slate-400">, </span>@endif
            @endforeach
        </div>
        @endif
    </div>

    @if($lignesFacturees->isEmpty())
    <p class="text-center text-sm text-slate-400 py-10">{{ $filtreActif ? 'Aucune ligne facturée pour ces filtres.' : $messageVide }}</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">N° facture</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">N° OR</th>
                    @if($parVehicule)
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Véhicule</th>
                    @endif
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Référence</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">Désignation</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">Qté</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">P.U. HT</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">Remise</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500">Total HT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($lignesFacturees as $ligne)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap">{{ $ligne->facture->date_emission->format('d/m/Y') }}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap">
                        <a href="{{ route('factures.show', $ligne->facture) }}" class="font-mono text-sm font-bold text-orange-500 hover:underline">{{ $ligne->facture->numero }}</a>
                    </td>
                    <td class="px-4 py-2.5 whitespace-nowrap">
                        @if($ligne->facture->ordreReparation)
                        <a href="{{ route('ordres-reparations.show', $ligne->facture->ordreReparation) }}" class="font-mono text-xs text-orange-500 hover:underline">{{ $ligne->facture->ordreReparation->numero }}</a>
                        @else
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700" title="Pièces livrées sans passage à l'atelier (import flotte)">Flotte</span>
                        @endif
                    </td>
                    @if($parVehicule)
                    @php $vehiculeLigne = $ligne->facture->vehiculeConcerne(); @endphp
                    <td class="px-4 py-2.5 whitespace-nowrap">
                        @if($vehiculeLigne)
                        <a href="{{ route('vehicules.show', $vehiculeLigne) }}" class="font-mono text-xs font-bold text-slate-700 hover:underline">{{ $vehiculeLigne->immatriculation }}</a>
                        @else
                        <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    @endif
                    <td class="px-4 py-2.5">
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $ligne->type === 'piece' ? 'bg-orange-100 text-orange-700' : ($ligne->type === 'main_oeuvre' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">{{ $ligne->getTypeLabel() }}</span>
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $ligne->reference ?: '—' }}</td>
                    <td class="px-4 py-2.5 text-slate-700">{{ $ligne->designation }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-600 whitespace-nowrap">{{ $fmtQte($ligne->quantite) }}@if($ligne->type === 'main_oeuvre') <span class="text-xs text-blue-500">h</span>@endif</td>
                    <td class="px-4 py-2.5 text-right text-slate-600 whitespace-nowrap">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-500">{{ $ligne->remise > 0 ? $fmtQte($ligne->remise) . ' %' : '—' }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-slate-800 whitespace-nowrap">{{ number_format($ligne->total_ht, 0, ',', ' ') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 bg-gray-50">
                    <td colspan="{{ $parVehicule ? 10 : 9 }}" class="px-4 py-3 text-right font-bold text-slate-700">Total HT ({{ $lignesFacturees->count() }} ligne{{ $lignesFacturees->count() > 1 ? 's' : '' }})</td>
                    <td class="px-4 py-3 text-right font-black text-orange-500 whitespace-nowrap">{{ number_format($totalHtLignes, 0, ',', ' ') }} FDJ</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>
