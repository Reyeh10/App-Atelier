@extends('layouts.app')
@section('title', 'Dossiers de Réception')
@section('page-title', 'Dossiers de Réception')
@section('page-subtitle', $dossiers->total() . ' dossier(s)')

@section('header-actions')
@if(auth()->user()->hasPermission('creer_dossiers'))
<a href="{{ route('reception.index') }}"
   class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    Nouvelle Réception
</a>
@endif
@endsection

@section('content')

<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="recherche" value="{{ request('recherche') }}" placeholder="N° dossier, client, téléphone, immatriculation, OR, sinistre..."
           class="flex-1 min-w-0 max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
    <button type="submit" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">Rechercher</button>
    <select name="statut" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
        <option value="">Tous les statuts</option>
        @foreach(['nouveau' => 'Nouveau', 'diagnostic' => 'Diagnostic', 'devis_en_cours' => 'Devis en cours', 'transforme_en_or' => 'Transformé en OR', 'en_attente_client' => 'En attente du client', 'annule' => 'Annulé'] as $val => $label)
            <option value="{{ $val }}" {{ request('statut') === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @if(request()->anyFilled(['statut', 'recherche']))
    <a href="{{ route('dossiers-reception.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-slate-600 hover:bg-gray-50 transition-colors">Réinitialiser</a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    @if($dossiers->isEmpty())
    <div class="flex flex-col items-center justify-center py-20">
        <p class="text-slate-600 font-medium">Aucun dossier de réception</p>
    </div>
    @else
    <table class="w-full">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <th class="text-left px-6 py-3">N° Dossier</th>
                <th class="text-left px-6 py-3">Client / Véhicule</th>
                <th class="text-left px-6 py-3">Motif</th>
                <th class="text-left px-6 py-3">Date</th>
                <th class="text-left px-6 py-3">Statut</th>
                <th class="text-right px-6 py-3">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($dossiers as $d)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                    <a href="{{ route('dossiers-reception.show', $d) }}" class="text-sm font-bold text-slate-900 font-mono hover:text-orange-600 transition-colors">
                        {{ $d->numero }}
                    </a>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm font-medium text-slate-800">{{ $d->client->nom_complet }}</p>
                    <p class="text-xs text-slate-400 font-mono">{{ $d->vehicule->immatriculation }} — {{ $d->vehicule->marque }}</p>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-slate-600">{{ $d->getMotifVisiteLabel() }}</p>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-slate-700">{{ $d->date_entree->format('d/m/Y') }}</p>
                    <p class="text-xs text-slate-400">{{ $d->heure_entree ?? '—' }}</p>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $d->getStatutColor() }}-100 text-{{ $d->getStatutColor() }}-700">
                        {{ $d->getStatutLabel() }}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-3">
                        @if(auth()->user()->hasPermission('supprimer_dossiers') && ! $d->or_id)
                        <form method="POST" action="{{ route('dossiers-reception.destroy', $d) }}"
                              onsubmit="return confirm('Supprimer le dossier {{ $d->numero }} ? Cette action est irréversible.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-red-500 hover:text-red-600 font-medium">
                                Supprimer
                            </button>
                        </form>
                        @endif
                        <a href="{{ route('dossiers-reception.show', $d) }}" class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                            Ouvrir →
                        </a>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="px-6 py-4 border-t border-gray-100">{{ $dossiers->links() }}</div>
    @endif
</div>
@endsection
