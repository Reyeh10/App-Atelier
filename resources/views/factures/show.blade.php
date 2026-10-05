@extends('layouts.app')
@section('title', $facture->numero)
@section('page-title', $facture->numero)
@section('page-subtitle', $facture->payeur_nom)

@section('header-actions')
<div class="flex gap-2">
    @if($facture->ordreReparation)
    <a href="{{ route('ordres-reparations.show', $facture->ordreReparation) }}"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← OR {{ $facture->ordreReparation->numero }}
    </a>
    @elseif($facture->livraisonFlotte)
    <a href="{{ route('flotte.show', $facture->livraisonFlotte->import_flotte_id) }}"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Import flotte {{ $facture->livraisonFlotte->import?->numero }}
    </a>
    @endif
    <a href="{{ route('factures.imprimer', $facture) }}?apercu=1" target="_blank"
       class="flex items-center gap-2 text-sm border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        👁 Aperçu
    </a>
    <a href="{{ route('factures.imprimer', $facture) }}" target="_blank"
       class="flex items-center gap-2 text-sm bg-orange-500 hover:bg-orange-600 text-white rounded-lg px-3 py-2 transition-colors">
        🖨 Imprimer
    </a>
</div>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

{{-- Avoirs : facture annulée, ou facture émise en remplacement d'une facture annulée --}}
@php
    $avoirsLies = collect([$facture->avoir, $facture->avoirOrigine])->filter();
    $modesRemboursement = ['especes' => 'Espèces', 'cheque' => 'Chèque', 'waafi' => 'Waafi', 'virement' => 'Virement', 'deduit' => 'Déduit de la nouvelle facture'];
@endphp
@if($facture->statut === 'annulee' && $facture->avoir)
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
    <p class="font-bold">Facture annulée par l'avoir {{ $facture->avoir->numero }} du {{ $facture->avoir->date_emission->format('d/m/Y') }}</p>
    <p class="mt-0.5">Motif : {{ $facture->avoir->motif }}</p>
    <div class="flex flex-wrap gap-3 mt-2">
        <a href="{{ route('avoirs.imprimer', $facture->avoir) }}" target="_blank" class="font-semibold underline">🖨 Imprimer l'avoir</a>
        @if($facture->avoir->factureRemplacement)
        <a href="{{ route('factures.show', $facture->avoir->factureRemplacement) }}" class="font-semibold underline">→ Facture de remplacement {{ $facture->avoir->factureRemplacement->numero }}</a>
        @endif
    </div>
</div>
@endif
@if($facture->avoirOrigine)
<div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-700">
    Cette facture remplace la facture
    <a href="{{ route('factures.show', $facture->avoirOrigine->facture) }}" class="font-semibold underline">{{ $facture->avoirOrigine->facture->numero }}</a>,
    annulée par l'avoir <a href="{{ route('avoirs.imprimer', $facture->avoirOrigine) }}" target="_blank" class="font-semibold underline">{{ $facture->avoirOrigine->numero }}</a>
    — motif : {{ $facture->avoirOrigine->motif }}
</div>
@endif

{{-- Montant déjà encaissé à rendre au client (annulation, ou trop-perçu après correction) --}}
@foreach($avoirsLies as $av)
    @if($av->montant_a_rembourser > 0)
    <div class="bg-white rounded-2xl border border-amber-200 p-4">
        @if($av->resteARembourser())
        <p class="text-sm font-bold text-amber-700">À rembourser au client : {{ number_format($av->montant_a_rembourser, 0, ',', ' ') }} FDJ <span class="font-normal">(avoir {{ $av->numero }})</span></p>
        @if(auth()->user()->hasPermission('encaisser_factures'))
        <form method="POST" action="{{ route('avoirs.rembourser', $av) }}" class="flex flex-wrap gap-3 items-end mt-3">
            @csrf @method('PATCH')
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Mode</label>
                <select name="mode_remboursement" required class="px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                    @foreach($modesRemboursement as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                <input type="date" name="rembourse_le" required value="{{ now()->format('Y-m-d') }}" class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">Enregistrer le remboursement</button>
        </form>
        @endif
        @else
        <p class="text-sm text-green-600 font-medium">✓ {{ number_format($av->montant_a_rembourser, 0, ',', ' ') }} FDJ remboursés le {{ $av->rembourse_le->format('d/m/Y') }} — {{ $av->getModeRemboursementLabel() }} (avoir {{ $av->numero }})</p>
        @endif
    </div>
    @endif
@endforeach

{{-- Facture flotte : pièces livrées sans passage à l'atelier (ni OR, ni réception) --}}
@if(! $facture->ordreReparation && $facture->livraisonFlotte)
@php $livraison = $facture->livraisonFlotte; @endphp
<div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-800 flex flex-wrap gap-6 gap-y-1">
    <span class="font-bold">Livraison flotte</span>
    <span>Véhicule :
        @if($facture->vehicule)
        <a href="{{ route('vehicules.show', $facture->vehicule) }}" class="font-mono font-bold hover:underline">{{ $facture->vehicule->immatriculation }}</a>
        @else — @endif
    </span>
    <span>Livrée le {{ $livraison->date_livraison->format('d/m/Y') }}</span>
    @if($livraison->bonCommande)
    <span>BC : <a href="{{ route('bons-commande.show', $livraison->bonCommande) }}" class="font-mono font-bold hover:underline">{{ $livraison->bonCommande->numero }}</a></span>
    @if($livraison->bonCommande->bonTransfert)
    <span>BT magasin : <span class="font-mono font-bold">{{ $livraison->bonCommande->bonTransfert->numero }}</span></span>
    @endif
    @endif
</div>
@endif

{{-- Statut + paiement --}}
<div class="bg-white rounded-2xl border-2 border-{{ $facture->getStatutColor() }}-300 p-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-{{ $facture->getStatutColor() }}-100 text-{{ $facture->getStatutColor() }}-700">
                {{ $facture->getStatutLabel() }}
            </span>
            <span class="text-slate-500 text-sm">
                {{ $facture->getModePaiementLabel() }}
                @if($facture->mode_paiement === 'bon_commande' && $facture->numero_bon_commande_client)
                    — BC n° {{ $facture->numero_bon_commande_client }}
                    @if($facture->bon_commande_client_url)
                    <a href="{{ $facture->bon_commande_client_url }}" target="_blank" class="text-orange-500 hover:underline">(voir le scan)</a>
                    @endif
                @endif
            </span>
            <span class="text-slate-500 text-sm">Émise le {{ $facture->date_emission->format('d/m/Y') }}</span>
            @if($facture->date_echeance)
            <span class="text-orange-600 text-sm font-medium">Échéance : {{ $facture->date_echeance->format('d/m/Y') }}</span>
            @endif
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold text-orange-500">{{ number_format($facture->montant_ttc, 0, ',', ' ') }} FDJ</p>
            @if($facture->statut === 'annulee')
            <p class="text-sm text-red-600 font-medium">Annulée — n'est plus due</p>
            @elseif($facture->getMontantRestant() > 0)
            <p class="text-sm text-red-500 font-medium">Reste à payer : {{ number_format($facture->getMontantRestant(), 0, ',', ' ') }} FDJ</p>
            @else
            <p class="text-sm text-green-600 font-medium">✓ Entièrement payée</p>
            @endif
        </div>
    </div>

    {{-- Crédit accordé ou compte crédit actif --}}
    @if($facture->credit_accorde)
    <div class="mt-3 bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                @if($facture->marque_garantie_id)
                <span class="text-sm font-semibold text-indigo-800">Facturée au compte garantie constructeur {{ $facture->marqueGarantie->nom }}</span>
                <span class="text-xs text-indigo-600 ml-1">— le client ne paie rien</span>
                @else
                <span class="text-sm font-semibold text-indigo-800">Facture sur le compte client</span>
                <span class="text-xs text-indigo-600 ml-1">— accordé le {{ $facture->credit_accorde_at?->format('d/m/Y à H:i') }}</span>
                @endif
            </div>
        </div>
        @if(auth()->user()->hasPermission('gerer_compte_credit'))
        <form method="POST" action="{{ route('factures.revoquer-credit', $facture) }}" onsubmit="return confirm('Révoquer le crédit ?')">
            @csrf @method('PATCH')
            <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium underline">Révoquer</button>
        </form>
        @endif
    </div>
    @elseif($facture->client->compte_actif && $facture->statut === 'emise')
    <div class="mt-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 flex items-center gap-2">
        <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <p class="text-xs text-slate-500">Ce client a un compte crédit actif — paiement cash ou sur compte selon le choix ci-dessous.</p>
    </div>
    @endif

    {{-- Paiement encaissé --}}
    @if($facture->statut === 'emise' && $facture->getMontantRestant() > 0 && !$facture->credit_accorde && auth()->user()->hasPermission('encaisser_factures'))
    <div class="mt-4 border-t border-gray-100 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Paiement encaissé</p>
        <form method="POST" action="{{ route('factures.payer', $facture) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf @method('PATCH')
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-2">Mode de paiement</label>
                <input type="hidden" name="mode_paiement" id="pay_mode_val" value="">
                <div class="flex flex-wrap gap-2">
                    @foreach(['especes' => 'Espèces', 'cheque' => 'Chèque', 'waafi' => 'Waafi', 'cac' => 'CAC', 'carte' => 'Carte', 'virement' => 'Virement', 'bon_commande' => 'Bon de commande'] as $val => $label)
                    <button type="button" onclick="selectPayMode('{{ $val }}')" data-paymode="{{ $val }}"
                            class="pay-mode-btn border-2 rounded-xl px-4 py-1.5 text-xs font-bold transition-all border-gray-200 text-slate-600 hover:border-gray-300">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>
            <div id="pay_bon_commande_champs" class="hidden flex gap-3 items-end flex-wrap bg-gray-50 rounded-xl p-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Numéro du bon de commande <span class="text-red-500">*</span></label>
                    <input type="text" name="numero_bon_commande_client"
                           class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500 w-48">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Scan / photo du bon (optionnel)</label>
                    <input type="file" name="bon_commande_scan" accept=".jpg,.jpeg,.png,.pdf"
                           class="text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-gray-200 file:text-slate-700 hover:file:bg-gray-300">
                    <p class="text-xs text-slate-400 mt-1">JPG, PNG ou PDF — 10 Mo max.</p>
                </div>
            </div>
            <div class="flex gap-3 items-end flex-wrap">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Montant reçu maintenant (FDJ)</label>
                    <input type="number" name="montant_paye"
                           value="{{ (int) round($facture->getMontantRestant()) }}" min="1" max="{{ (int) round($facture->getMontantRestant()) }}" step="1"
                           class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500 w-48 font-semibold">
                    @if($facture->montant_paye > 0)
                    <p class="text-xs text-slate-500 mt-1">Déjà payé : {{ number_format($facture->montant_paye, 0, ',', ' ') }} FDJ — le versement s'ajoute.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Date de paiement</label>
                    <input type="date" name="date_paiement" value="{{ now()->format('Y-m-d') }}"
                           class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <button type="submit"
                        class="bg-green-500 hover:bg-green-600 text-white font-black text-sm px-8 py-2 rounded-xl transition-colors">
                    ✓ Enregistrer le paiement — reste {{ number_format($facture->getMontantRestant(), 0, ',', ' ') }} FDJ
                </button>
            </div>
        </form>
        <script>
        function selectPayMode(val) {
            document.getElementById('pay_mode_val').value = val;
            document.querySelectorAll('.pay-mode-btn').forEach(btn => {
                const active = btn.dataset.paymode === val;
                btn.classList.toggle('border-green-500', active);
                btn.classList.toggle('bg-green-50',     active);
                btn.classList.toggle('text-green-700',  active);
                btn.classList.toggle('border-gray-200', !active);
                btn.classList.toggle('text-slate-600',  !active);
            });
            document.getElementById('pay_bon_commande_champs').classList.toggle('hidden', val !== 'bon_commande');
        }
        </script>
    </div>

    {{-- Bouton crédit : uniquement si le client a un compte crédit autorisé --}}
    @if($facture->client->compte_actif && !$facture->credit_accorde && (auth()->user()->hasPermission('gerer_compte_credit')))
    <div class="mt-3 border-t border-gray-100 pt-3">
        <p class="text-xs text-slate-400 mb-2">Si le client ne peut pas payer maintenant :</p>
        <form method="POST" action="{{ route('factures.credit', $facture) }}" onsubmit="return confirm('Accorder un crédit ? Le véhicule pourra être restitué sans paiement immédiat.')">
            @csrf @method('PATCH')
            <button type="submit" class="flex items-center gap-2 text-sm border-2 border-indigo-400 text-indigo-600 hover:bg-indigo-50 font-bold px-4 py-2 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Mettre sur le compte client — Autoriser la restitution
            </button>
        </form>
    </div>
    @endif
    @endif
</div>

{{-- Lignes --}}
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
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Qté</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">P.U. HT</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Remise</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Total HT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($facture->lignes as $ligne)
                <tr>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">{{ $ligne->getTypeLabel() }}</span></td>
                    <td class="px-5 py-3 text-slate-700">{{ $ligne->designation }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">
                        {{ $ligne->type === 'main_oeuvre' ? rtrim(rtrim(number_format($ligne->quantite, 2, ',', ''), '0'), ',') : rtrim(rtrim(number_format($ligne->quantite, 2, ',', ' '), '0'), ',') }}
                        @if($ligne->type === 'main_oeuvre')<span class="text-xs text-blue-500">h</span>@endif
                    </td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} FDJ</td>
                    <td class="px-5 py-3 text-right text-slate-500">{{ $ligne->remise > 0 ? $ligne->remise . '%' : '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ number_format($ligne->total_ht, 0, ',', ' ') }} FDJ</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 flex justify-end">
        <div class="space-y-2 min-w-64">
            <div class="flex justify-between text-sm"><span class="text-slate-500">Total HT</span><span class="font-semibold">{{ number_format($facture->montant_ht, 0, ',', ' ') }} FDJ</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">TVA ({{ $facture->taux_tva }}%)</span><span class="font-semibold">{{ number_format($facture->montant_tva, 0, ',', ' ') }} FDJ</span></div>
            <div class="flex justify-between text-base font-bold border-t border-gray-300 pt-2">
                <span>Total TTC</span><span class="text-orange-500 text-lg">{{ number_format($facture->montant_ttc, 0, ',', ' ') }} FDJ</span>
            </div>
        </div>
    </div>
</div>

{{-- Annuler / corriger : administrateur uniquement, toujours par avoir --}}
@if($facture->peutEtreAnnuleePar(auth()->user()))
<div class="bg-white rounded-2xl border border-red-200 p-6">
    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Annuler ou corriger cette facture</h3>
    @if($facture->ordreReparation)
    <p class="text-xs text-slate-500 mt-1 mb-4">Une facture émise ne se supprime pas : un <strong>avoir</strong> du même montant est émis automatiquement pour l'annuler. « Corriger » émet l'avoir puis une nouvelle facture avec les lignes modifiées.</p>
    @else
    <p class="text-xs text-slate-500 mt-1 mb-4">Une facture émise ne se supprime pas : un <strong>avoir</strong> du même montant est émis automatiquement pour l'annuler. Pour corriger une facture flotte, annulez-la : la livraison redevient « À facturer » et vous pouvez la refacturer avec les bons prix et quantités.</p>
    @endif
    <div class="flex flex-wrap gap-4 items-end">
        @if($facture->ordreReparation)
        <a href="{{ route('factures.corriger', $facture) }}"
           class="flex items-center gap-2 text-sm bg-indigo-500 hover:bg-indigo-600 text-white font-bold rounded-xl px-4 py-2 transition-colors">
            ✏️ Corriger (avoir + nouvelle facture)
        </a>
        @endif
        <form method="POST" action="{{ route('factures.annuler', $facture) }}" class="flex flex-wrap gap-3 items-end flex-1"
              onsubmit="return confirm('Annuler la facture {{ $facture->numero }} ? Un avoir de {{ number_format($facture->totalGeneral(), 0, ',', ' ') }} FDJ sera émis et {{ $facture->ordreReparation ? 'le véhicule' : 'la livraison' }} reviendra dans « À facturer ».')">
            @csrf @method('PATCH')
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1">Motif de l’annulation <span class="text-red-500">*</span></label>
                <input type="text" name="motif" required maxlength="1000" value="{{ old('motif') }}" placeholder="Ex : facture émise au mauvais client"
                       class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
                Annuler par avoir
            </button>
        </form>
    </div>
</div>
@endif

</div>
@endsection
