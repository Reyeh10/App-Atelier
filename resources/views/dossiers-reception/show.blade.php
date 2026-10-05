@extends('layouts.app')
@section('title', $dossier->numero)
@section('page-title', $dossier->numero)
@section('page-subtitle', $dossier->client->nom_complet . ' — ' . $dossier->vehicule->immatriculation)

@section('header-actions')
<div class="flex gap-2">
    @if($dossier->or_id)
    <a href="{{ route('ordres-reparations.show', $dossier->or_id) }}"
       class="flex items-center gap-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg px-3 py-2 transition-colors">
        Voir l'OR {{ $dossier->ordreReparation->numero }} →
    </a>
    @endif
    @if(auth()->user()->isAdmin())
    <a href="{{ route('dossiers-reception.corriger', $dossier) }}"
       class="flex items-center gap-2 text-sm bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg px-3 py-2 transition-colors">
        ✏️ Corriger
    </a>
    @endif
    @if(auth()->user()->hasPermission('supprimer_dossiers') && (! $dossier->or_id || auth()->user()->isAdmin()))
    <form method="POST" action="{{ route('dossiers-reception.destroy', $dossier) }}"
          onsubmit="return confirm('Supprimer le dossier {{ $dossier->numero }} ?{{ $dossier->or_id ? ' L’OR, ses devis et ses photos sont conservés.' : '' }} Cette action est irréversible.')">
        @csrf @method('DELETE')
        <button type="submit" class="flex items-center gap-2 text-sm bg-red-50 hover:bg-red-100 text-red-600 rounded-lg px-3 py-2 transition-colors">
            Supprimer
        </button>
    </form>
    @endif
    <a href="{{ route('dossiers-reception.index') }}"
       class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
        ← Dossiers
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
    // Une Panne doit être diagnostiquée (électrique/mécanique/garantie) avant de
    // pouvoir créer un devis — jamais choisi à l'accueil, seulement après réception.
    $enAttenteDiagnostic = $dossier->motif_visite === 'panne' && !$dossier->type_panne && !$dossier->or_id;
    // Véhicule encore éligible à la garantie (jamais sorti, sous garantie depuis la
    // création, dans la limite d'âge de sa catégorie) : le diagnostic revient alors
    // exclusivement à l'équipe garantie, jamais au chef de garage — cf.
    // Vehicule::estEligibleGarantie() et DossierReceptionController::diagnostiquer().
    $eligibleGarantie = $enAttenteDiagnostic && $dossier->vehicule->estEligibleGarantie();
    $peutDiagnostiquer = $eligibleGarantie
        ? auth()->user()->hasPermission('traiter_garanties')
        : auth()->user()->hasPermission('gerer_devis');
    $peutCreerDevis = in_array($dossier->statut, ['nouveau', 'diagnostic'])
        && auth()->user()->hasPermission('gerer_devis')
        && !$enAttenteDiagnostic;
@endphp

{{-- Statut --}}
<div class="bg-white rounded-2xl border-2 border-{{ $dossier->getStatutColor() }}-300 p-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-{{ $dossier->getStatutColor() }}-100 text-{{ $dossier->getStatutColor() }}-700">
                {{ $dossier->getStatutLabel() }}
            </span>
            <span class="text-slate-500 text-sm">{{ $dossier->getMotifVisiteLabel() }}</span>
            @if($dossier->type_panne)
            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                {{ match($dossier->type_panne) { 'electrique' => 'Électrique', 'mecanique' => 'Mécanique', 'garantie' => 'Garantie', default => $dossier->type_panne } }}
            </span>
            @endif
        </div>
        @if($peutCreerDevis)
        <a href="{{ route('dossiers-reception.devis.create', $dossier) }}"
           class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
            + Créer un devis
        </a>
        @endif
    </div>
    @if($dossier->reservation)
    <p class="text-xs text-slate-400 mt-3">Issu de la réservation {{ $dossier->reservation->numero }} du {{ $dossier->reservation->date_rdv->format('d/m/Y') }}</p>
    @endif
</div>

{{-- Diagnostic (Panne uniquement, avant de connaître le type précis) --}}
@if($enAttenteDiagnostic && $peutDiagnostiquer)
<div class="bg-white rounded-2xl border-2 border-yellow-300 p-6 space-y-4">
    <div>
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Diagnostic</h3>
        @if($eligibleGarantie)
        <p class="text-xs text-slate-400 mt-1">Ce véhicule est éligible à la garantie constructeur — le dossier doit être pris en charge par l'équipe garantie.</p>
        @else
        <p class="text-xs text-slate-400 mt-1">Après examen du véhicule, quel type de panne est-ce ?</p>
        @endif
    </div>
    <form method="POST" action="{{ route('dossiers-reception.diagnostiquer', $dossier) }}" class="grid {{ $eligibleGarantie ? 'grid-cols-1' : 'grid-cols-2' }} gap-3">
        @csrf @method('PATCH')
        @if($eligibleGarantie)
        <button type="submit" name="type_panne" value="garantie"
                class="border-2 border-yellow-400 bg-yellow-50 hover:bg-yellow-100 rounded-xl px-4 py-3 text-center text-sm font-medium text-slate-700 transition-colors">
            Envoyer à l'équipe garantie
        </button>
        @else
        <button type="submit" name="type_panne" value="electrique"
                class="border-2 border-gray-200 hover:border-yellow-400 rounded-xl px-4 py-3 text-center text-sm font-medium text-slate-700 transition-colors">
            Électrique
        </button>
        <button type="submit" name="type_panne" value="mecanique"
                class="border-2 border-gray-200 hover:border-yellow-400 rounded-xl px-4 py-3 text-center text-sm font-medium text-slate-700 transition-colors">
            Mécanique
        </button>
        @endif
    </form>
</div>
@elseif($enAttenteDiagnostic && $eligibleGarantie)
<div class="bg-white rounded-2xl border-2 border-yellow-300 p-6">
    <p class="text-sm text-slate-500">⏳ Ce véhicule est éligible à la garantie constructeur — en attente du diagnostic par l'équipe garantie.</p>
</div>
@endif

{{-- Infos réception --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Réception</h3>
    </div>
    <div class="p-6 grid grid-cols-3 gap-4 text-sm">
        <div>
            <p class="text-xs text-slate-400 mb-1">Client</p>
            <p class="font-medium text-slate-800">{{ $dossier->client->nom_complet }}</p>
            <p class="text-xs text-slate-400">{{ $dossier->client->telephone }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Véhicule</p>
            <p class="font-mono font-bold text-slate-800">{{ $dossier->vehicule->immatriculation }}</p>
            <p class="text-xs text-slate-400">{{ $dossier->vehicule->designation }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Date d'entrée</p>
            <p class="text-slate-700">{{ $dossier->date_entree->format('d/m/Y') }} {{ $dossier->heure_entree }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Kilométrage</p>
            <p class="text-slate-700">{{ number_format($dossier->kilometrage_entree, 0, ',', ' ') }} km</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Réceptionniste</p>
            <p class="text-slate-700">{{ $dossier->conseiller->name }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Urgence</p>
            <p class="text-slate-700">{{ $dossier->getUrgenceLabel() ?? 'Normal' }}</p>
        </div>
        <div class="col-span-3">
            <p class="text-xs text-slate-400 mb-1">Motif</p>
            <p class="text-slate-700">{{ $dossier->motif_entree }}</p>
        </div>
    </div>

    {{-- Fiche signée --}}
    @if(auth()->user()->hasPermission('creer_dossiers'))
    <div class="px-6 pb-6 border-t border-gray-100 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Fiche de réception signée</p>
        @if($dossier->fiche_signee)
        <div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-xl px-4 py-3 mb-3">
            <div class="flex items-center gap-2 text-sm text-green-700">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Fiche signée enregistrée
            </div>
            <a href="{{ asset('storage/' . $dossier->fiche_signee) }}" target="_blank"
               class="text-xs font-bold text-green-700 hover:text-green-900 underline">
                Voir / Télécharger
            </a>
        </div>
        @endif
        <form method="POST" action="{{ route('dossiers-reception.upload-fiche', $dossier) }}" enctype="multipart/form-data" class="flex gap-2 items-center">
            @csrf @method('PATCH')
            <input type="file" name="fiche_signee" accept=".pdf,.jpg,.jpeg,.png" required
                   class="flex-1 text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
            <button type="submit" class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors">
                {{ $dossier->fiche_signee ? 'Remplacer' : 'Uploader' }}
            </button>
        </form>
        <p class="text-xs text-slate-400 mt-1">PDF, JPG ou PNG — max 10 Mo</p>
    </div>
    @endif
</div>

{{-- Devis --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Devis</h3>
    </div>
    <div class="divide-y divide-gray-100">
        @forelse($dossier->devis as $d)
        <a href="{{ route('devis.show', $d) }}" class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors">
            <div>
                <p class="text-sm font-mono font-semibold text-slate-800">{{ $d->numero }}</p>
                <p class="text-xs text-slate-400">{{ number_format($d->montant_ttc, 0, ',', ' ') }} FDJ TTC</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $d->getStatutColor() }}-100 text-{{ $d->getStatutColor() }}-700">
                {{ $d->getStatutLabel() }}
            </span>
        </a>
        @empty
        <p class="px-6 py-8 text-center text-sm text-slate-400">Aucun devis pour ce dossier.</p>
        @endforelse
    </div>
</div>

</div>
@endsection
