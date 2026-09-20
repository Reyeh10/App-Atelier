@extends('layouts.app')
@section('title', $or->numero)
@section('page-title', $or->numero)
@section('page-subtitle', $or->client->nom_complet . ' — ' . $or->vehicule->immatriculation)

@section('header-actions')
<div class="flex gap-2">
    {{-- Tant que l'OR est de type garantie, il appartient exclusivement à
         l'équipe garantie (approbation/refus via le module Garantie plus bas) —
         le chef de garage ne peut pas en changer le statut, comme il ne peut
         pas non plus l'affecter à un technicien (cf. bloc Affectation). Si la
         garantie est refusée, le type redevient "normal" et ce contrôle
         redevient disponible normalement. --}}
    @if(auth()->user()->canManageWorkshop() && $or->type !== 'garantie')
    <form method="POST" action="{{ route('ordres-reparations.statut', $or) }}" class="flex items-center gap-2">
        @csrf @method('PATCH')
        <select name="statut" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            @foreach(['ouvert'=>'Ouvert','diagnostic'=>'Diagnostic','devis_envoye'=>'Devis envoyé','devis_accepte'=>'Devis accepté','en_cours'=>'En cours','controle_qualite'=>'Contrôle qualité','lavage'=>'Lavage','pret'=>'Prêt','facture'=>'Facturé','livre'=>'Livré','annule'=>'Annulé'] as $val => $label)
            <option value="{{ $val }}" {{ $or->statut === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    @elseif($or->type === 'garantie')
    <span class="flex items-center gap-2 text-sm text-slate-400 border border-gray-200 rounded-lg px-3 py-2 bg-gray-50">
        {{ $or->getStatutLabel() }} — géré par l'équipe garantie
    </span>
    @endif
    <a href="{{ route('ordres-reparations.imprimer', $or) }}?apercu=1" target="_blank"
       class="flex items-center gap-2 text-sm border border-gray-300 text-slate-600 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        👁 Fiche réception
    </a>
    <a href="{{ route('ordres-reparations.imprimer', $or) }}" target="_blank"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        🖨 Fiche réception
    </a>
    @if($or->isAffecte())
    <a href="{{ route('ordres-reparations.feuille-travail', $or) }}?apercu=1" target="_blank"
       class="flex items-center gap-2 text-sm border border-gray-300 text-slate-600 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        👁 Feuille travail
    </a>
    <a href="{{ route('ordres-reparations.feuille-travail', $or) }}" target="_blank"
       class="flex items-center gap-2 text-sm bg-slate-700 hover:bg-slate-800 text-white rounded-lg px-3 py-2 transition-colors">
        📋 Feuille travail
    </a>
    @endif
    @if(($or->statut === 'facture' && $or->facture?->peutEtreRestitue() || ($or->service_gratuit && $or->statut === 'pret')) && auth()->user()->hasPermission('restituer_vehicule'))
    <a href="{{ route('ordres-reparations.restitution', $or) }}"
       class="flex items-center gap-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg px-3 py-2 transition-colors font-bold">
        ✓ Restituer le véhicule
    </a>
    @endif
    @if($or->statut === 'livre')
    <a href="{{ route('ordres-reparations.imprimer-restitution', $or) }}?apercu=1" target="_blank"
       class="flex items-center gap-2 text-sm border border-gray-300 text-slate-600 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
        👁 Fiche restitution
    </a>
    <a href="{{ route('ordres-reparations.imprimer-restitution', $or) }}" target="_blank"
       class="flex items-center gap-2 text-sm bg-green-100 hover:bg-green-200 text-green-800 border border-green-300 rounded-lg px-3 py-2 transition-colors">
        🖨 Fiche restitution
    </a>
    @endif
    @if($or->facture)
    <a href="{{ route('factures.show', $or->facture) }}"
       class="flex items-center gap-2 text-sm bg-green-500 hover:bg-green-600 text-white rounded-lg px-3 py-2 transition-colors">
        🧾 {{ $or->facture->statut === 'payee' ? 'Facture payée' : 'Encaisser' }}
    </a>
    @endif
    <a href="{{ route('ordres-reparations.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Retour
    </a>
</div>
@endsection

@section('content')

@if(session('success'))
<div class="mb-4 bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
    <ul class="space-y-1">
        @foreach($errors->all() as $e)
        <li class="text-sm text-red-700 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>{{ $e }}
        </li>
        @endforeach
    </ul>
</div>
@endif

{{-- ═══ BANNIÈRE ENTRETIEN EN RETARD ════
     Réservée au chef de garage et au réceptionniste (pas le caissier) — reste
     affichée en permanence sur la fiche OR tant que le dépassement est réel
     (contrairement à l'alerte équivalente du tableau de bord, limitée à 1h). --}}
@php $retardEntretien = \App\Services\EntretienService::calculerRetard($or); @endphp
@if($retardEntretien['enRetard'] && auth()->user()->peutVoirAlerteEntretien())
<div class="mb-4 bg-red-50 border border-red-300 rounded-2xl p-4 flex items-center gap-4">
    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
    </div>
    <div class="flex-1">
        <p class="text-sm font-bold text-red-700">
            Entretien en retard
            @if($retardEntretien['enRetardKm']){{ ' de ' . number_format($retardEntretien['depassementKm']) . ' km' }}@endif
            @if($retardEntretien['enRetardKm'] && $retardEntretien['enRetardMois']){{ ' et' }}@endif
            @if($retardEntretien['enRetardMois']){{ ' de ' . $retardEntretien['depassementMois'] . ' mois' }}@endif
        </p>
        <p class="text-xs text-red-600 mt-0.5">Le véhicule a dépassé le palier de {{ number_format($or->entretien_km_seuil) }} km{{ $retardEntretien['moisSeuil'] ? ' / ' . $retardEntretien['moisSeuil'] . ' mois' : '' }} ({{ $or->vehicule->typeMoteur?->libelle }}) — vérifiez si un palier intermédiaire a été sauté avant de valider le devis.</p>
    </div>
</div>
@endif

{{-- ═══ BANNIÈRE RÉCEPTIONNISTE — EN ATTENTE RÈGLEMENT ════ --}}
@if($or->statut === 'facture' && $or->facture && !$or->facture->peutEtreRestitue() && auth()->user()->hasPermission('restituer_vehicule'))
<div class="mb-4 bg-amber-50 border border-amber-300 rounded-2xl p-4 flex items-center gap-4">
    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
    </div>
    <div class="flex-1">
        <p class="text-sm font-bold text-amber-800">En attente d'encaissement</p>
        <p class="text-xs text-amber-700 mt-0.5">La facture {{ $or->facture->numero }} ({{ number_format($or->facture->totalGeneral(), 0, ',', ' ') }} FDJ) n'a pas encore été réglée. Le bouton "Restituer" sera disponible une fois le paiement confirmé ou un crédit accordé par le caissier.</p>
    </div>
</div>
@endif

{{-- ═══ BANNIÈRE SERVICE GRATUIT — PRÊT SANS FACTURE ════════ --}}
@if($or->service_gratuit && $or->statut === 'pret' && auth()->user()->hasPermission('restituer_vehicule'))
<div class="mb-4 bg-teal-500 rounded-2xl p-5 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 text-white">
        <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="font-bold text-lg">Service gratuit terminé — Prêt à restituer</p>
            <p class="text-teal-100 text-sm">{{ $or->vehicule->immatriculation }} — {{ $or->client->nom_complet }} — pas de facturation nécessaire</p>
        </div>
    </div>
    <a href="{{ route('ordres-reparations.restitution', $or) }}"
       class="flex-shrink-0 bg-white text-teal-700 hover:bg-teal-50 font-black text-base px-6 py-3 rounded-xl transition-colors shadow-sm whitespace-nowrap">
        ✓ Restituer le véhicule
    </a>
</div>
@endif

{{-- ═══ BANNIÈRE CAISSIER ══════════════════════════════════ --}}
@if(auth()->user()->hasPermission('creer_factures') && ! $or->service_gratuit)
    {{-- La création de la facture se fait uniquement depuis Factures → À facturer. --}}
    @if($or->facture && $or->facture->statut === 'emise')
    <div class="mb-4 bg-blue-500 rounded-2xl p-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 text-white">
            <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <div>
                <p class="font-bold text-lg">Facture émise — En attente de paiement</p>
                <p class="text-blue-100 text-sm">{{ $or->facture->numero }} — {{ number_format($or->facture->totalGeneral(), 0, ',', ' ') }} FDJ</p>
            </div>
        </div>
        <a href="{{ route('factures.show', $or->facture) }}"
           class="flex-shrink-0 bg-white text-blue-700 hover:bg-blue-50 font-black text-base px-6 py-3 rounded-xl transition-colors shadow-sm whitespace-nowrap">
            💰 Encaisser
        </a>
    </div>
    @elseif($or->facture && $or->facture->statut === 'payee')
    <div class="mb-4 bg-slate-100 border border-slate-200 rounded-2xl p-4 flex items-center gap-3 text-slate-600">
        <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-sm font-medium">Facture {{ $or->facture->numero }} — Payée le {{ $or->facture->date_paiement?->format('d/m/Y') }}</span>
        <a href="{{ route('factures.show', $or->facture) }}" class="ml-auto text-xs text-orange-500 hover:underline font-medium">Voir la facture</a>
    </div>
    @endif
@endif

<div class="grid grid-cols-3 gap-5">

{{-- ═══ COLONNE PRINCIPALE ════════════════════════════════ --}}
<div class="col-span-2 space-y-5">

    {{-- En-tête + pipeline --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <div class="flex items-center gap-3 mb-2 flex-wrap">
                    <h2 class="text-2xl font-bold text-slate-900 font-mono">{{ $or->numero }}</h2>
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-{{ $or->getStatutColor() }}-100 text-{{ $or->getStatutColor() }}-700">
                        {{ $or->getStatutLabel() }}
                    </span>
                    @if($or->type !== 'normal')
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-slate-100 text-slate-600">{{ $or->getTypeLabel() }}</span>
                    @endif
                    @if($or->service)
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-700">{{ $or->getServiceLabel() }}</span>
                    @endif
                    @if($or->urgence !== 'normal')
                    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $or->urgence === 'tres_urgent' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' }}">
                        {{ $or->getUrgenceLabel() }}
                    </span>
                    @endif
                </div>
                <p class="text-slate-500 text-sm">Créé le {{ $or->created_at->format('d/m/Y à H:i') }} par {{ $or->conseiller?->name ?? '—' }}</p>
            </div>
        </div>

        {{-- Pipeline — uniquement les étapes qui se passent RÉELLEMENT pour cet OR.
             "Diagnostic" et "Devis accepté" ont désormais lieu AVANT que l'OR n'existe (au
             niveau du dossier de réception) : le statut du devis reste visible plus bas,
             dans la carte "Devis", plutôt que de dupliquer ces étapes ici.
             Un Service Rapide gratuit (tarif 0 — cf. service_gratuit) ne passe jamais par
             le contrôle qualité, le lavage ni la facturation : le schéma s'adapte pour ne
             montrer que le parcours réellement suivi (Ouvert → En cours → Prêt → Livré). --}}
        @php
            if ($or->service_gratuit) {
                $etapes = ['ouvert', 'en_cours', 'pret', 'livre'];
                $labels = ['Ouvert', 'En cours', 'Prêt', 'Livré'];
            } else {
                $etapes = ['ouvert', 'en_cours', 'controle_qualite', 'lavage', 'pret', 'facture', 'livre'];
                $labels = ['Ouvert', 'En cours', 'Contrôle', 'Lavage', 'Prêt', 'Facturé', 'Livré'];
            }
            // ouvert/diagnostic/devis_envoye/devis_accepte sont tous "avant le lancement des
            // travaux" — on les regroupe sur l'étape "Ouvert" plutôt que de les distinguer.
            $statutsAvantTravaux = ['ouvert', 'diagnostic', 'devis_envoye', 'devis_accepte'];
            $courant = in_array($or->statut, $statutsAvantTravaux) ? 0 : array_search($or->statut, $etapes);
        @endphp
        <div class="flex items-center overflow-x-auto pb-2">
            @foreach($etapes as $i => $etape)
            <div class="flex items-center">
                @if($i > 0)<div class="w-6 h-0.5 {{ $courant !== false && $i <= $courant ? 'bg-orange-400' : 'bg-gray-200' }} flex-shrink-0"></div>@endif
                <div class="flex flex-col items-center flex-shrink-0">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                        {{ $courant !== false && $i < $courant ? 'bg-orange-500 text-white' : ($courant !== false && $i === $courant ? 'bg-orange-500 text-white ring-4 ring-orange-100' : 'bg-gray-200 text-gray-400') }}">
                        {{ $courant !== false && $i < $courant ? '✓' : ($i + 1) }}
                    </div>
                    <p class="text-xs mt-1 text-slate-500 whitespace-nowrap" style="font-size:9px">{{ $labels[$i] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Devis --}}
    <div class="bg-white rounded-2xl border-2 {{ $or->devis ? 'border-' . $or->devis->getStatutColor() . '-300' : 'border-gray-200' }} p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Devis
                @if($or->allDevis->count() > 1)
                <span class="text-xs text-slate-400 font-normal">({{ $or->allDevis->count() }})</span>
                @endif
            </h3>
            @if(!$or->devis && $or->type !== 'garantie' && auth()->user()->hasPermission('gerer_devis'))
            <a href="{{ route('devis.create', $or) }}"
               class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors">
                + Créer un devis
            </a>
            @elseif($or->devis && $or->devis->statut === 'accepte' && $or->type !== 'garantie' && auth()->user()->hasPermission('gerer_devis'))
            <a href="{{ route('devis.create', $or) }}"
               class="bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors">
                + Devis complémentaire
            </a>
            @endif
        </div>

        @if($or->allDevis->isEmpty())
        @if($or->type === 'garantie')
        <p class="text-sm text-slate-400 bg-gray-50 rounded-xl p-3">Panne en cours d'instruction par l'équipe garantie — pas de devis client tant que la garantie n'est pas refusée (voir module Garantie ci-dessous).</p>
        @else
        <p class="text-sm text-slate-400 bg-gray-50 rounded-xl p-3">Aucun devis créé — créez un devis après le diagnostic.</p>
        @endif
        @else

        {{-- Devis précédents (tous sauf le dernier) --}}
        @foreach($or->allDevis as $dv)
        @php $isLast = $loop->last; @endphp
        <div class="{{ !$isLast ? 'mb-3 pb-3 border-b border-gray-100' : '' }}">
            <div class="flex items-center justify-between bg-gray-50 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-500">{{ $dv->numero }}</span>
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-{{ $dv->getStatutColor() }}-100 text-{{ $dv->getStatutColor() }}-700">
                        {{ $dv->getStatutLabel() }}
                    </span>
                    <span class="text-sm text-slate-500">{{ $dv->lignes->count() }} lignes</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-base font-bold text-slate-900">{{ number_format($dv->montant_ttc, 0, ',', ' ') }} FDJ TTC</p>
                        <p class="text-xs text-slate-400">HT : {{ number_format($dv->montant_ht, 0, ',', ' ') }} FDJ</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('devis.imprimer', $dv) }}?apercu=1" target="_blank"
                           class="text-xs text-slate-500 hover:text-slate-800 border border-gray-200 rounded-lg px-2 py-1 transition-colors" title="Aperçu">
                            👁
                        </a>
                        <a href="{{ route('devis.imprimer', $dv) }}" target="_blank"
                           class="text-xs text-slate-500 hover:text-slate-800 border border-gray-200 rounded-lg px-2 py-1 transition-colors" title="Imprimer">
                            🖨
                        </a>
                        <a href="{{ route('devis.show', $dv) }}" class="text-xs text-orange-500 hover:underline font-medium px-2">
                            Voir →
                        </a>
                    </div>
                </div>
            </div>

            {{-- Actions sur le dernier devis uniquement --}}
            @if($isLast && in_array($dv->statut, ['brouillon','envoye']) && auth()->user()->peutValiderDevis())
            @php $dvAttendFournisseur = $dv->attendReponseFournisseur(); @endphp
            @if($dvAttendFournisseur)
            <p class="mt-3 text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2">
                ⏳ En attente de la confirmation du fournisseur pour une ou plusieurs pièces.
            </p>
            @endif
            <div class="flex gap-2 mt-3">
                @if($dvAttendFournisseur)
                <button type="button" disabled title="En attente de la confirmation du fournisseur pour toutes les pièces"
                        class="flex-1 bg-gray-200 text-gray-400 text-xs font-bold py-2 rounded-xl cursor-not-allowed">📤 Envoyé au client</button>
                <button type="button" disabled title="En attente de la confirmation du fournisseur pour toutes les pièces"
                        class="flex-1 bg-gray-200 text-gray-400 text-xs font-bold py-2 rounded-xl cursor-not-allowed">✓ Client a accepté</button>
                <button type="button" disabled title="En attente de la confirmation du fournisseur pour toutes les pièces"
                        class="flex-1 bg-gray-200 text-gray-400 text-xs font-bold py-2 rounded-xl cursor-not-allowed">✗ Refusé</button>
                @else
                <form method="POST" action="{{ route('devis.envoyer', $dv) }}" class="flex-1">
                    @csrf @method('PATCH')
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2 rounded-xl transition-colors">📤 Envoyé au client</button>
                </form>
                <form method="POST" action="{{ route('devis.accepter', $dv) }}" class="flex-1">
                    @csrf @method('PATCH')
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-2 rounded-xl transition-colors">✓ Client a accepté</button>
                </form>
                <form method="POST" action="{{ route('devis.refuser', $dv) }}" class="flex-1">
                    @csrf @method('PATCH')
                    <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-2 rounded-xl transition-colors" onclick="return confirm('Confirmer le refus ?')">✗ Refusé</button>
                </form>
                @endif
            </div>
            @if(! $dvAttendFournisseur)
            <form method="POST" action="{{ route('devis.upload-signature', $dv) }}" enctype="multipart/form-data" class="flex gap-2 mt-2">
                @csrf @method('PATCH')
                <input type="file" name="fichier_signe" accept=".pdf,.jpg,.jpeg,.png"
                       class="flex-1 text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600">
                <button type="submit" class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-3 py-1.5 rounded-xl">Upload devis signé</button>
            </form>
            @endif
            @endif
        </div>
        @endforeach

        @endif
    </div>

    {{-- Bon de commande pièces --}}
    @if($or->devis && $or->devis->bonCommande && auth()->user()->peutVoirBonsCommande())
    @php $bc = $or->devis->bonCommande; @endphp
    <div class="bg-white rounded-2xl border-2 border-{{ $bc->getStatutColor() }}-300 p-5">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-teal-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bon de commande pièces</p>
                    <p class="font-mono font-bold text-slate-800">{{ $bc->numero }}</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-{{ $bc->getStatutColor() }}-100 text-{{ $bc->getStatutColor() }}-700">
                    {{ $bc->getStatutLabel() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('bons-commande.show', $bc) }}"
                   class="text-xs bg-teal-500 hover:bg-teal-600 text-white font-bold px-3 py-1.5 rounded-lg transition-colors">
                    Voir →
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Pointage technicien --}}
    @if($or->isAffecte())
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Pointage & Performance — {{ $or->technicien?->name ?? '—' }}
        </h3>

        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-slate-400 mb-1">Durée estimée</p>
                @if($or->duree_estimee)
                <p class="font-bold text-slate-800">{{ $or->formatDuree($or->duree_estimee) }}</p>
                @else
                <p class="text-slate-400 text-sm">—</p>
                @endif
            </div>
            <div class="bg-{{ $or->heure_debut_travaux ? 'blue' : 'gray' }}-50 rounded-xl p-3 text-center">
                <p class="text-xs text-{{ $or->heure_debut_travaux ? 'blue' : 'slate' }}-400 mb-1">Heure début</p>
                <p class="font-bold text-{{ $or->heure_debut_travaux ? 'blue' : 'slate' }}-{{ $or->heure_debut_travaux ? '800' : '400' }}">
                    {{ $or->heure_debut_travaux ? $or->heure_debut_travaux->format('H:i') : '—' }}
                </p>
                @if($or->heure_debut_travaux)
                <p class="text-xs text-blue-400">{{ $or->heure_debut_travaux->format('d/m/Y') }}</p>
                @endif
            </div>
            <div class="bg-{{ $or->heure_fin_travaux ? 'green' : 'gray' }}-50 rounded-xl p-3 text-center">
                <p class="text-xs text-{{ $or->heure_fin_travaux ? 'green' : 'slate' }}-400 mb-1">Heure fin</p>
                <p class="font-bold text-{{ $or->heure_fin_travaux ? 'green' : 'slate' }}-{{ $or->heure_fin_travaux ? '800' : '400' }}">
                    {{ $or->heure_fin_travaux ? $or->heure_fin_travaux->format('H:i') : '—' }}
                </p>
                @if($or->heure_fin_travaux)
                <p class="text-xs text-green-400">Écoulé : {{ $or->formatDuree($or->getDureeReelleHeures()) }}</p>
                @endif
            </div>
        </div>

        {{-- Durée nette (hors pauses) --}}
        @if($or->heure_debut_travaux && $or->heure_fin_travaux)
        @php
            $dureeNette  = $or->getDureeNetteHeures();
            $dureeBreute = $or->getDureeReelleHeures();
            $pausesDeduit = round($dureeBreute - $dureeNette, 2);
        @endphp
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-indigo-50 rounded-xl p-3 text-center">
                <p class="text-xs text-indigo-400 mb-1">Durée nette (hors pauses)</p>
                <p class="font-bold text-indigo-800 text-lg">{{ $or->formatDuree($dureeNette) }}</p>
                @if($pausesDeduit > 0)
                <p class="text-xs text-indigo-400">{{ $or->formatDuree($pausesDeduit) }} de pause déduits</p>
                @else
                <p class="text-xs text-indigo-300">Aucune pause sur cette plage</p>
                @endif
            </div>
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-slate-400 mb-1">Temps total écoulé</p>
                <p class="font-bold text-slate-600 text-lg">{{ $or->formatDuree($dureeBreute) }}</p>
                <p class="text-xs text-slate-400">Pauses incluses</p>
            </div>
        </div>
        @endif

        {{-- Performance basée sur la durée nette --}}
        @if($or->getPerformance() !== null)
        @php $perf = $or->getPerformance(); @endphp
        <div class="bg-{{ $perf >= 100 ? 'green' : ($perf >= 80 ? 'yellow' : 'red') }}-50 border border-{{ $perf >= 100 ? 'green' : ($perf >= 80 ? 'yellow' : 'red') }}-200 rounded-xl p-4 mb-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-{{ $perf >= 100 ? 'green' : ($perf >= 80 ? 'yellow' : 'red') }}-800">
                    Performance : {{ $perf }}%
                    @if($perf >= 100) — Excellent (en avance)
                    @elseif($perf >= 80) — Bon
                    @else — À améliorer (dépassement)
                    @endif
                </p>
                <span class="text-2xl font-black text-{{ $perf >= 100 ? 'green' : ($perf >= 80 ? 'yellow' : 'red') }}-600">{{ $perf }}%</span>
            </div>
            <p class="text-xs text-{{ $perf >= 100 ? 'green' : ($perf >= 80 ? 'yellow' : 'red') }}-600 mt-1 mb-2">Calculé sur la durée nette (pauses exclues)</p>
            <div class="mt-2 h-2 bg-{{ $perf >= 100 ? 'green' : ($perf >= 80 ? 'yellow' : 'red') }}-200 rounded-full">
                <div class="h-full bg-{{ $perf >= 100 ? 'green' : ($perf >= 80 ? 'yellow' : 'red') }}-500 rounded-full" style="width: {{ min(100, $perf) }}%"></div>
            </div>
        </div>
        @endif

        {{-- Boutons pointage — le technicien n'a pas de compte, c'est le chef qui pointe pour lui --}}
        @if(auth()->user()->canManageWorkshop())
        <div class="flex gap-3 flex-wrap">
            @if(!$or->heure_debut_travaux)
            <form method="POST" action="{{ route('ordres-reparations.demarrer', $or) }}" class="flex-1">
                @csrf @method('PATCH')
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                    ▶ Démarrer les travaux
                </button>
            </form>
            @endif
            @if($or->heure_debut_travaux && !$or->heure_fin_travaux)
            <form method="POST" action="{{ route('ordres-reparations.terminer', $or) }}" class="flex-1 flex gap-2">
                @csrf @method('PATCH')
                @if(!$or->duree_estimee)
                <input type="number" name="duree_estimee" placeholder="Durée estimée (h)" min="0.5" step="0.5"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                @endif
                <button type="submit" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                    ⏹ Terminer les travaux
                </button>
            </form>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- Contrôle qualité + Lavage --}}
    @if(in_array($or->statut, ['controle_qualite','lavage']) && auth()->user()->canManageWorkshop())
    <div class="bg-white rounded-2xl border-2 border-{{ $or->statut === 'lavage' ? 'blue' : 'pink' }}-300 p-6">
        @if($or->statut === 'controle_qualite')
        <h3 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-pink-500 inline-block"></span> Contrôle qualité
        </h3>
        <p class="text-sm text-slate-500 mb-4">Vérifier les travaux effectués avant de passer au lavage.</p>
        <form method="POST" action="{{ route('ordres-reparations.valider-qualite', $or) }}">
            @csrf @method('PATCH')
            <button type="submit" class="w-full bg-pink-500 hover:bg-pink-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                ✓ Contrôle qualité validé → Lavage
            </button>
        </form>
        @else
        <h3 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> Lavage en cours
        </h3>
        <p class="text-sm text-slate-500 mb-4">Le véhicule est au lavage. Une fois terminé, marquer comme prêt pour contacter le client.</p>
        <form method="POST" action="{{ route('ordres-reparations.terminer-lavage', $or) }}">
            @csrf @method('PATCH')
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors">
                ✓ Lavage terminé → Prêt pour livraison
            </button>
        </form>
        @endif
    </div>
    @endif

    {{-- Motif & Réception --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Motif & Réception</h3>
        <div class="grid grid-cols-3 gap-4 mb-4 text-sm">
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-slate-400 mb-1">Date d'entrée</p>
                <p class="font-semibold text-slate-800">{{ $or->date_entree->format('d/m/Y') }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-slate-400 mb-1">Kilométrage</p>
                <p class="font-semibold text-slate-800">{{ number_format($or->kilometrage_entree) }} km</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-slate-400 mb-1">Carburant</p>
                <p class="font-semibold text-slate-800">{{ $or->niveau_carburant }}</p>
            </div>
            @if($or->date_sortie_prevue)
            <div class="bg-blue-50 rounded-xl p-3">
                <p class="text-xs text-blue-400 mb-1">Sortie prévue</p>
                <p class="font-semibold text-blue-800">{{ $or->date_sortie_prevue->format('d/m/Y') }}</p>
            </div>
            @endif
        </div>
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Motif de la visite</p>
        <p class="text-slate-700 bg-gray-50 rounded-xl p-3">{{ $or->motif_entree }}</p>
        @if($or->etat_exterieur)
        <div class="mt-3">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">État extérieur</p>
            <p class="text-slate-600 bg-yellow-50 border border-yellow-100 rounded-xl p-3 text-sm">{{ $or->etat_exterieur }}</p>
        </div>
        @endif

        {{-- Photos du véhicule --}}
        @if($or->photosOr->count() > 0 || auth()->user()->hasPermission('gerer_ordres'))
        <div class="mt-4 border-t border-gray-100 pt-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Photos du véhicule</p>
                @if($or->photosOr->count() > 0)
                <span class="text-xs text-slate-400">{{ $or->photosOr->count() }} photo(s)</span>
                @endif
            </div>

            @if($or->photosOr->count() > 0)
            <div class="grid grid-cols-3 gap-2 mb-3">
                @foreach($or->photosOr as $photo)
                <div class="relative group">
                    <a href="{{ $photo->url() }}" target="_blank">
                        <img src="{{ $photo->url() }}" alt="Photo véhicule"
                             class="w-full h-24 object-cover rounded-xl border border-gray-200 hover:opacity-90 transition-opacity">
                    </a>
                    @if(auth()->user()->hasPermission('gerer_ordres'))
                    <form method="POST" action="{{ route('ordres-reparations.photos.supprimer', [$or, $photo]) }}"
                          class="absolute top-1 right-1 hidden group-hover:block"
                          onsubmit="return confirm('Supprimer cette photo ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs font-bold flex items-center justify-center">×</button>
                    </form>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <p class="text-xs text-slate-400 italic mb-3">Aucune photo enregistrée.</p>
            @endif

            @if(auth()->user()->hasPermission('gerer_ordres'))
            <form method="POST" action="{{ route('ordres-reparations.photos.upload', $or) }}" enctype="multipart/form-data" class="flex gap-2 items-center">
                @csrf
                <input type="file" name="photos_vehicule[]" multiple accept="image/jpeg,image/png,image/webp" required
                       class="flex-1 text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                <button type="submit" class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors whitespace-nowrap">
                    Ajouter photos
                </button>
            </form>
            <p class="text-xs text-slate-400 mt-1">JPG, PNG, WEBP — max 8 Mo par photo</p>
            @endif
        </div>
        @endif

        {{-- Fiche signée --}}
        <div class="mt-4 border-t border-gray-100 pt-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Fiche de réception signée</p>
            @if($or->fiche_signee)
            <div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-xl px-4 py-3 mb-3">
                <div class="flex items-center gap-2 text-sm text-green-700">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Fiche signée enregistrée
                </div>
                <a href="{{ asset('storage/' . $or->fiche_signee) }}" target="_blank"
                   class="text-xs font-bold text-green-700 hover:text-green-900 underline">
                    Voir / Télécharger
                </a>
            </div>
            @endif
            @if(auth()->user()->hasPermission('creer_dossiers'))
            <form method="POST" action="{{ route('ordres-reparations.upload-fiche', $or) }}" enctype="multipart/form-data" class="flex gap-2 items-center">
                @csrf @method('PATCH')
                <input type="file" name="fiche_signee" accept=".pdf,.jpg,.jpeg,.png" required
                       class="flex-1 text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                <button type="submit" class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors">
                    {{ $or->fiche_signee ? 'Remplacer' : 'Uploader' }}
                </button>
            </form>
            <p class="text-xs text-slate-400 mt-1">PDF, JPG ou PNG — max 10 Mo</p>
            @endif
        </div>
    </div>

    {{-- Fiche de restitution signée — visible une fois le véhicule livré --}}
    @if($or->statut === 'livre' && auth()->user()->hasPermission('restituer_vehicule'))
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-3">Fiche de restitution signée</h3>
        @if($or->fiche_signee_restitution)
        <div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-xl px-4 py-3 mb-3">
            <div class="flex items-center gap-2 text-sm text-green-700">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Fiche signée enregistrée
            </div>
            <a href="{{ asset('storage/' . $or->fiche_signee_restitution) }}" target="_blank"
               class="text-xs font-bold text-green-700 hover:text-green-900 underline">
                Voir / Télécharger
            </a>
        </div>
        @endif
        <form method="POST" action="{{ route('ordres-reparations.upload-fiche-restitution', $or) }}" enctype="multipart/form-data" class="flex gap-2 items-center">
            @csrf @method('PATCH')
            <input type="file" name="fiche_signee_restitution" accept=".pdf,.jpg,.jpeg,.png" required
                   class="flex-1 text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
            <button type="submit" class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors">
                {{ $or->fiche_signee_restitution ? 'Remplacer' : 'Uploader' }}
            </button>
        </form>
        <p class="text-xs text-slate-400 mt-1">PDF, JPG ou PNG — max 10 Mo</p>
    </div>
    @endif

    {{-- Garantie — reste visible même après un refus (l'OR redevient "normal" mais l'historique doit rester consultable) --}}
    @if($or->type === 'garantie' || $or->statut_garantie === 'refuse')
    <div class="bg-white rounded-2xl border-2 {{ $or->statut_garantie === 'approuve' ? 'border-green-300' : ($or->statut_garantie === 'refuse' ? 'border-red-300' : 'border-yellow-300') }} p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Module Garantie</h3>
        @if($or->statut_garantie === 'refuse')
        <p class="text-xs text-slate-400 mb-3">Cette demande de garantie a été refusée — l'OR suit maintenant le parcours normal (devis → travaux → facturation client).</p>
        @endif
        @if($or->statut_garantie === 'en_attente')
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
            <p class="text-sm text-yellow-800 font-medium">⏳ En attente de validation</p>
        </div>
        @if(auth()->user()->hasPermission('traiter_garanties'))
        <form method="POST" action="{{ route('ordres-reparations.garantie', $or) }}" class="space-y-3">
            @csrf @method('PATCH')
            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="document.getElementById('motif_approbation').classList.toggle('hidden')" class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-xl text-sm">✓ Approuver</button>
                <button type="button" onclick="document.getElementById('motif_refus').classList.toggle('hidden')" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-xl text-sm">✗ Refuser</button>
            </div>
            <div id="motif_approbation" class="hidden space-y-2">
                <textarea name="motif_approbation_garantie" rows="2" required placeholder="Motif de l'approbation..." class="w-full px-4 py-2 border border-green-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
                <button type="submit" name="statut_garantie" value="approuve" class="w-full bg-green-500 hover:bg-green-600 text-white font-medium py-2 rounded-xl text-sm">Confirmer l'approbation</button>
            </div>
            <div id="motif_refus" class="hidden space-y-2">
                <textarea name="motif_refus_garantie" rows="2" required placeholder="Motif du refus..." class="w-full px-4 py-2 border border-red-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
                <label class="flex items-start gap-2 cursor-pointer bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                    <input type="checkbox" name="sortie_garantie" value="1" class="w-4 h-4 mt-0.5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                    <span class="text-xs text-red-700">Ce véhicule est <strong>définitivement</strong> sorti de la garantie constructeur — il ne sera plus jamais proposé au circuit garantie, décision irréversible.</span>
                </label>
                <button type="submit" name="statut_garantie" value="refuse" class="w-full bg-red-500 hover:bg-red-600 text-white font-medium py-2 rounded-xl text-sm">Confirmer le refus</button>
            </div>
        </form>
        @endif
        @elseif($or->statut_garantie === 'approuve')
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-sm text-green-800 font-medium">✓ Garantie approuvée</p>
            @if($or->motif_approbation_garantie)<p class="text-xs text-green-600 mt-1">{{ $or->motif_approbation_garantie }}</p>@endif
        </div>
        @elseif($or->statut_garantie === 'refuse')
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-sm text-red-800 font-medium">✗ Garantie refusée</p>
            @if($or->motif_refus_garantie)<p class="text-xs text-red-600 mt-1">{{ $or->motif_refus_garantie }}</p>@endif
        </div>
        @endif
    </div>
    @endif

    @if($or->notes_internes)
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-3">Notes internes</h3>
        <p class="text-slate-600 text-sm bg-gray-50 rounded-xl p-3">{{ $or->notes_internes }}</p>
    </div>
    @endif

</div>

{{-- ═══ COLONNE DROITE ═══════════════════════════════════ --}}
<div class="col-span-1 space-y-5">

    {{-- Client --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Client</h3>
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm flex-shrink-0">
                {{ strtoupper(substr($or->client->nom, 0, 1)) }}
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ $or->client->nom_complet }}</p>
                <p class="text-xs text-slate-500">{{ $or->client->getTypeLabel() }}</p>
            </div>
        </div>
        <div class="space-y-1.5 text-sm">
            <p class="text-slate-600">📞 {{ $or->client->telephone }}</p>
            @if($or->client->email)<p class="text-slate-600">✉ {{ $or->client->email }}</p>@endif
        </div>
        <a href="{{ route('clients.show', $or->client) }}" class="block mt-3 text-xs text-orange-500 hover:underline">Voir la fiche client →</a>
    </div>

    {{-- Véhicule --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Véhicule</h3>
        <p class="text-lg font-bold text-slate-900 font-mono mb-1">{{ $or->vehicule->immatriculation }}</p>
        <p class="text-sm text-slate-600 mb-3">{{ $or->vehicule->designation }}</p>
        <div class="space-y-1.5 text-xs text-slate-500">
            <p>Motorisation : {{ $or->vehicule->getMotorisationLabel() }}</p>
            <p>Km entrée : {{ number_format($or->kilometrage_entree) }} km</p>
            @if($or->vehicule->sous_garantie)<p class="text-green-600 font-medium">✓ Sous garantie constructeur</p>@endif
        </div>
        <a href="{{ route('vehicules.show', $or->vehicule) }}" class="block mt-3 text-xs text-orange-500 hover:underline">Voir la fiche véhicule →</a>
    </div>

    {{-- Affectation technicien — pas d'affectation tant que l'OR est de type garantie : la prise
         en charge est gérée par l'équipe garantie. Si la garantie est refusée, l'OR redevient
         "normal" (cf. changerStatutGarantie) et l'affectation redevient possible normalement. --}}
    @if(auth()->user()->canManageWorkshop() && $or->type !== 'garantie')
    @php
        $bcBloquant = $or->bonsCommande()->whereIn('statut', ['en_attente', 'commande'])->first();
    @endphp
    <div class="bg-white rounded-2xl border-2 {{ $bcBloquant ? 'border-yellow-300' : ($or->isAffecte() ? 'border-green-300' : 'border-orange-300') }} p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Affectation mécanicien</h3>

        @if($or->isAffecte())
        <div class="space-y-2 mb-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-orange-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    {{ strtoupper(substr($or->technicien?->name ?? '?', 0, 1)) }}
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ $or->technicien?->name ?? '—' }}</p>
                    <p class="text-xs text-slate-500">{{ $or->getServiceLabel() }}</p>
                </div>
            </div>
            @if($or->duree_estimee)<p class="text-xs text-slate-400">Durée estimée : {{ $or->formatDuree($or->duree_estimee) }}</p>@endif
            @if($or->date_affectation)<p class="text-xs text-slate-400">Affecté le {{ $or->date_affectation->format('d/m/Y à H:i') }}</p>@endif
        </div>
        @endif

        @if($bcBloquant)
        {{-- BC pièces pas encore reçu — bloquer l'affectation --}}
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-xs text-yellow-800">
            <p class="font-bold mb-1">⏳ En attente des pièces</p>
            <p>Le bon de commande <span class="font-mono font-bold">{{ $bcBloquant->numero }}</span> n'est pas encore reçu au garage.</p>
            <p class="mt-1">Le chef de garage (ou l'admin) doit marquer <strong>"Tout reçu"</strong> avant de pouvoir affecter un technicien.</p>
        </div>
        @else
        <form method="POST" action="{{ route('ordres-reparations.affecter', $or) }}" class="space-y-2 mt-2">
            @csrf @method('PATCH')
            <select name="technicien_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="">Choisir un technicien</option>
                @foreach(\App\Models\Technicien::where('actif', true)->orderBy('nom')->get() as $tech)
                <option value="{{ $tech->id }}" {{ $or->technicien_id == $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>
                @endforeach
            </select>
            <select name="service" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="">Choisir un service</option>
                @foreach(['rapide'=>'Service Rapide','mecanique'=>'Mécanique','electricite'=>'Électricité','carrosserie'=>'Carrosserie','peinture'=>'Peinture'] as $val => $label)
                <option value="{{ $val }}" {{ $or->service === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @php
                $dureeReservation = $or->dossier?->reservation?->duree_estimee;
                $dureeDevisSomme  = $or->allDevis->flatMap->lignes->where('type', 'main_oeuvre')->sum('quantite');
                $dureeSuggeree    = $or->duree_estimee ?? $dureeReservation ?? ($dureeDevisSomme > 0 ? $dureeDevisSomme : null);
            @endphp
            <div class="flex gap-2">
                <input type="number" name="duree_estimee" value="{{ $dureeSuggeree }}" placeholder="Durée estimée (h)" min="0.25" step="0.25"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            @if(!$or->duree_estimee && $dureeReservation)
            <p class="text-xs text-teal-600">Reprise de la durée réservée ({{ $dureeReservation }} h) — modifiable.</p>
            @elseif(!$or->duree_estimee && !$dureeReservation && $dureeDevisSomme > 0)
            <p class="text-xs text-teal-600">Somme des mains d'œuvre du devis ({{ $dureeDevisSomme }} h) — modifiable.</p>
            @endif
            <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 rounded-xl text-sm transition-colors">
                {{ $or->isAffecte() ? 'Réaffecter' : 'Affecter' }}
            </button>
        </form>
        @endif
    </div>
    @elseif($or->technicien && $or->type !== 'garantie')
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Technicien assigné</h3>
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-orange-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                {{ strtoupper(substr($or->technicien->name, 0, 1)) }}
            </div>
            <div>
                <p class="text-sm font-medium text-slate-900">{{ $or->technicien->name }}</p>
                <p class="text-xs text-slate-500">{{ $or->getServiceLabel() }}</p>
            </div>
        </div>
    </div>
    @endif

</div>
</div>
@endsection
