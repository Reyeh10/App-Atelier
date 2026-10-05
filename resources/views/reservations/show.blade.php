@extends('layouts.app')
@section('title', $reservation->numero)
@section('page-title', $reservation->numero)
@section('page-subtitle', $reservation->client->nom_complet . ' — ' . $reservation->vehicule->immatriculation)

@section('header-actions')
@if(auth()->user()->isAdmin())
<div class="flex gap-2">
<a href="{{ route('reservations.corriger', $reservation) }}"
   class="flex items-center gap-2 text-sm bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg px-3 py-2 transition-colors">
    ✏️ Corriger
</a>
<form method="POST" action="{{ route('reservations.supprimer', $reservation) }}"
      onsubmit="return confirm('Supprimer définitivement cette réservation{{ $reservation->devis && $reservation->devis->estEnAvance() ? ' et son devis en avance' : '' }} ?')">
    @csrf @method('DELETE')
    <button type="submit" class="flex items-center gap-2 text-sm bg-red-500 hover:bg-red-600 text-white rounded-lg px-3 py-2 transition-colors">🗑 Supprimer</button>
</form>
@endif
<a href="{{ route('reservations.index') }}" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 border border-gray-300 rounded-lg px-3 py-2 transition-colors">
    ← Réservations
</a>
@if(auth()->user()->isAdmin())
</div>
@endif
@endsection

@section('content')
<div class="max-w-2xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-2xl border-2 border-{{ $reservation->getStatutColor() }}-300 p-6">
    <div class="flex items-center justify-between mb-4">
        <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-{{ $reservation->getStatutColor() }}-100 text-{{ $reservation->getStatutColor() }}-700">
            {{ $reservation->getStatutLabel() }}
        </span>
        @if($reservation->statut === 'planifie' && auth()->user()->hasPermission('gerer_reservations'))
        <div class="flex gap-2">
            <form method="POST" action="{{ route('reservations.honorer', $reservation) }}">
                @csrf @method('PATCH')
                <button type="submit" class="text-xs bg-teal-600 hover:bg-teal-700 text-white font-bold px-3 py-2 rounded-lg transition-colors">Client arrivé</button>
            </form>
            <form method="POST" action="{{ route('reservations.no-show', $reservation) }}">
                @csrf @method('PATCH')
                <button type="submit" class="text-xs border border-orange-300 text-orange-600 hover:bg-orange-50 font-medium px-3 py-2 rounded-lg transition-colors">Absent</button>
            </form>
            <form method="POST" action="{{ route('reservations.annuler', $reservation) }}" onsubmit="return confirm('Annuler cette réservation ?')">
                @csrf @method('PATCH')
                <button type="submit" class="text-xs border border-red-300 text-red-600 hover:bg-red-50 font-medium px-3 py-2 rounded-lg transition-colors">Annuler</button>
            </form>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <p class="text-xs text-slate-400 mb-1">Client</p>
            <p class="font-medium text-slate-800">{{ $reservation->client->nom_complet }}</p>
            <p class="text-xs text-slate-400">{{ $reservation->client->telephone }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Véhicule</p>
            <p class="font-mono font-bold text-slate-800">{{ $reservation->vehicule->immatriculation }}</p>
            <p class="text-xs text-slate-400">{{ $reservation->vehicule->designation }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Service</p>
            <p class="text-slate-700">{{ $reservation->getCanalServiceLabel() }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-1">Date / Heure du RDV</p>
            <p class="text-slate-700">{{ $reservation->date_rdv->format('d/m/Y') }} @if($reservation->heure_rdv) à {{ substr($reservation->heure_rdv, 0, 5) }}@endif</p>
        </div>
        @if($reservation->tache)
        <div>
            <p class="text-xs text-slate-400 mb-1">Tâche prévue</p>
            <p class="text-slate-700">{{ $reservation->tache }}</p>
        </div>
        @endif
        @if($reservation->duree_estimee)
        <div>
            <p class="text-xs text-slate-400 mb-1">Durée estimée</p>
            <p class="text-slate-700">{{ $reservation->duree_estimee }} h @if($reservation->heure_fin)(jusqu'à {{ $reservation->heure_fin }})@endif</p>
        </div>
        @endif
        <div>
            <p class="text-xs text-slate-400 mb-1">Pris par</p>
            <p class="text-slate-700">{{ $reservation->conseiller->name }}</p>
        </div>
        @if($reservation->notes)
        <div class="col-span-2">
            <p class="text-xs text-slate-400 mb-1">Notes</p>
            <p class="text-slate-700">{{ $reservation->notes }}</p>
        </div>
        @endif
    </div>

    {{-- Devis en avance : le client connaît le prix avant son rendez-vous --}}
    <div class="mt-4 border-t border-gray-100 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Devis en avance</p>
        @if($reservation->devis)
        <div class="flex items-center justify-between gap-3 bg-gray-50 rounded-xl p-3">
            <div>
                <a href="{{ route('devis.show', $reservation->devis) }}" class="font-mono font-bold text-orange-500 hover:underline text-sm">{{ $reservation->devis->numero }}</a>
                <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-{{ $reservation->devis->getStatutColor() }}-100 text-{{ $reservation->devis->getStatutColor() }}-700">{{ $reservation->devis->getStatutLabel() }}</span>
                @if($reservation->devis->entretien_km_seuil)
                <p class="text-xs text-slate-500 mt-1">Palier {{ number_format($reservation->devis->entretien_km_seuil, 0, ',', ' ') }} km — km prévu {{ number_format($reservation->devis->kilometrage_prevu, 0, ',', ' ') }}</p>
                @endif
            </div>
            <p class="text-base font-bold text-slate-900">{{ number_format($reservation->devis->montant_ttc, 0, ',', ' ') }} FDJ TTC</p>
        </div>
        @elseif($reservation->statut === 'planifie' && auth()->user()->hasPermission('gerer_devis'))
        <a href="{{ route('devis-avance.create', ['reservation_id' => $reservation->id]) }}"
           class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
            + Faire le devis en avance
        </a>
        <p class="text-xs text-slate-400 mt-1">Le client connaîtra le prix avant de venir ; le jour J, la réception reprendra ce devis.</p>
        @else
        <p class="text-sm text-slate-400">Aucun devis en avance.</p>
        @endif
    </div>

    @if($reservation->dossier)
    <div class="mt-4 border-t border-gray-100 pt-4">
        <a href="{{ route('dossiers-reception.show', $reservation->dossier) }}" class="text-sm text-orange-500 hover:underline font-medium">
            Voir le dossier de réception {{ $reservation->dossier->numero }} →
        </a>
    </div>
    @endif
</div>

</div>
@endsection
