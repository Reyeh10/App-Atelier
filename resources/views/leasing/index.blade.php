@extends('layouts.app')
@section('title', $p['libelle'])
@section('page-title', $p['libelle'])
@section('page-subtitle', $p['sous_titre'])

@section('content')
@php $service = $categorie === 'service'; @endphp
<div class="max-w-5xl space-y-5">

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

{{-- Importer la liste (crée les véhicules dans l'atelier), avec aperçu --}}
<form method="POST" action="{{ route($p['route'] . '.parc.apercu') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
    @csrf
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-sm font-bold text-slate-700">Importer une liste Excel (crée les véhicules)</h3>
        <a href="{{ route($p['route'] . '.parc.modele') }}" class="text-xs text-orange-500 hover:underline font-semibold">⬇ Modèle Excel (Immatriculation, Marque, Modèle, N° de châssis, Date de mise en circulation{{ $service ? ', Affecté à' : '' }})</a>
    </div>
    <div class="grid grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Client (fiche des véhicules)</label>
            <select name="client_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(old('client_id', $clientParDefaut) == $c->id)>{{ $c->nom_complet }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">{{ $service ? 'Affecté à (si vide dans le fichier)' : $p['champ'] }}</label>
            <input type="text" name="proprietaire" maxlength="100" value="{{ old('proprietaire', $p['defaut']) }}" list="affectations"
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fichier Excel ou CSV</label>
            <input type="file" name="fichier" required accept=".xlsx,.xls,.csv"
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded-xl transition-colors">
            Vérifier le fichier →
        </button>
    </div>
    <p class="text-xs text-slate-400">Rien n'est enregistré à cette étape : un aperçu s'affiche d'abord, avec les erreurs éventuelles. Les véhicules absents de l'atelier sont créés au nom du client choisi.</p>
</form>

{{-- Ajouter des véhicules --}}
<form method="POST" action="{{ route($p['route'] . '.store') }}" class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
    @csrf
    <h3 class="text-sm font-bold text-slate-700">Ou coller seulement des immatriculations</h3>
    <p class="text-xs text-slate-400 -mt-2">Les véhicules seront créés à leur premier passage à la réception.</p>
    <div class="grid grid-cols-3 gap-4">
        <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Immatriculation(s)</label>
            <textarea name="immatriculations" rows="3" required placeholder="Ex : 45D81 — ou collez une colonne de votre Excel (une immatriculation par ligne)"
                      class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">{{ old('immatriculations') }}</textarea>
            <p class="text-xs text-slate-400 mt-1">Les numéros d'ordre collés avec la liste et les véhicules déjà présents sont ignorés. Un véhicule n'est que dans une seule liste.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">{{ $p['champ'] }}</label>
            <input type="text" name="proprietaire" required maxlength="100" value="{{ old('proprietaire', $p['defaut']) }}" list="affectations"
                   placeholder="{{ $service ? 'Ex : Service technique, Directeur général…' : '' }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            @if($service)
            <datalist id="affectations">
                @foreach($affectations as $a)<option value="{{ $a }}">@endforeach
            </datalist>
            @endif
            <button type="submit" class="mt-3 w-full bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded-xl transition-colors">
                Ajouter à la liste
            </button>
        </div>
    </div>
</form>

{{-- Liste --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-4 flex-wrap">
        <h3 class="text-sm font-bold text-slate-700">{{ $total }} véhicule(s) — {{ mb_strtolower($p['libelle']) }}</h3>
        <form method="GET" action="{{ route($p['route'] . '.index') }}" class="flex gap-2 items-center">
            <input type="text" name="recherche" value="{{ request('recherche') }}" placeholder="{{ $service ? 'Immatriculation ou affectation' : 'Rechercher une immatriculation' }}"
                   class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            @if(request('recherche'))
            <a href="{{ route($p['route'] . '.index') }}" class="text-xs text-slate-400 hover:text-slate-600">✕ Effacer</a>
            @endif
        </form>
    </div>
    @if($vehicules->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">Aucun véhicule{{ request('recherche') ? ' trouvé' : ' dans la liste pour le moment' }}.</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">N°</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Immatriculation</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">{{ $service ? 'Affecté à' : 'Propriétaire' }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Ajouté le</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($vehicules as $i => $v)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-slate-400">{{ $i + 1 }}</td>
                    <td class="px-5 py-2.5 font-mono font-bold text-slate-800">{{ $v->immatriculation }}</td>
                    <td class="px-5 py-2.5 text-slate-700">
                        @if($service)
                        {{-- Changer l'affectation (service, directeur, responsable) --}}
                        <form method="POST" action="{{ route('vehicules-service.affectation', $v) }}" class="flex gap-2 items-center">
                            @csrf @method('PATCH')
                            <input type="text" name="proprietaire" value="{{ $v->proprietaire }}" required maxlength="100" list="affectations"
                                   class="px-2 py-1 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <button type="submit" class="text-xs text-orange-500 hover:underline font-semibold">Changer</button>
                        </form>
                        @else
                        {{ $v->proprietaire }}
                        @endif
                    </td>
                    <td class="px-5 py-2.5 text-slate-500">{{ $v->created_at?->format('d/m/Y') }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route($p['route'] . '.destroy', $v) }}" onsubmit="return confirm('Retirer {{ $v->immatriculation }} de la liste ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-semibold">Retirer</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

</div>
@endsection
