@extends('layouts.app')
@section('title', 'Devis')
@section('page-title', 'Devis')
@section('page-subtitle', 'Tous les devis créés')

@section('header-actions')
@if(auth()->user()->hasPermission('gerer_devis'))
<a href="{{ route('devis-avance.create') }}"
   class="flex items-center gap-2 text-sm bg-orange-500 hover:bg-orange-600 text-white rounded-lg px-4 py-2 transition-colors font-medium">
    + Devis sans réception
</a>
@endif
@endsection

@section('content')
<div class="space-y-4">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Numéro</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Véhicule</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">OR / Dossier</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">TTC</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Statut</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($devis as $d)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ $d->numero }}</td>
                    <td class="px-5 py-3 text-slate-700">{{ $d->parent->client->nom_complet }}</td>
                    <td class="px-5 py-3 text-slate-500 font-mono text-xs">{{ $d->parent->vehicule->immatriculation }}</td>
                    <td class="px-5 py-3">
                        <a href="{{ $d->getParentUrl() }}"
                           class="font-mono text-orange-500 hover:underline text-xs">
                            {{ $d->estEnAvance() ? ($d->reservation_id ? 'Réservation ' . $d->parent->numero : 'Devis libre') : $d->parent->numero }}
                        </a>
                    </td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ number_format($d->montant_ttc, 0, ',', ' ') }} FDJ</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold
                            @if($d->statut === 'accepte') bg-green-100 text-green-700
                            @elseif($d->statut === 'envoye') bg-blue-100 text-blue-700
                            @elseif($d->statut === 'refuse') bg-red-100 text-red-700
                            @else bg-gray-100 text-gray-600 @endif">
                            {{ $d->getStatutLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('devis.show', $d) }}"
                           class="text-xs text-orange-500 hover:underline font-medium">Voir</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucun devis.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($devis->hasPages())
    <div class="px-5 py-3 border-t border-gray-200">{{ $devis->links() }}</div>
    @endif
</div>

</div>
@endsection
