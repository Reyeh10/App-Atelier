{{--
    Accident : qui paie ? Le client (propriétaire du véhicule) ou une assurance.
    Le véhicule reste toujours au client — seule la facture va à l'assurance.
    Paramètres : $modele (dossier ou OR), $action (route du formulaire), $modifiable (bool)
--}}
@php
    $assurances = \App\Models\Client::where('type', 'assurance')->orderBy('raison_sociale')->orderBy('nom')->get();
    $parAssurance = (bool) $modele->assurance_id;
@endphp
<div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="font-semibold text-slate-800 mb-3">Qui paie ?</h3>

    <p class="text-sm text-slate-700">
        @if($parAssurance)
            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-700">Assurance</span>
            <span class="font-medium">{{ $modele->assurance?->nom_complet }}</span>
            @if($modele->numero_sinistre)<span class="text-slate-500"> — sinistre n° {{ $modele->numero_sinistre }}</span>@endif
        @else
            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Client</span>
            <span class="font-medium">{{ $modele->client?->nom_complet }}</span>
        @endif
    </p>
    <p class="text-xs text-slate-400 mt-1">Le véhicule reste au nom de {{ $modele->client?->nom_complet }} : seule la facture va à l'assurance.</p>

    @if($modifiable && auth()->user()->hasPermission('creer_dossiers'))
    <form method="POST" action="{{ $action }}" class="mt-4 border-t border-gray-100 pt-4 space-y-3">
        @csrf @method('PATCH')
        <div class="flex gap-4 text-sm">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="payeur" value="client" {{ $parAssurance ? '' : 'checked' }} onchange="this.form.querySelector('.bloc-assurance').classList.add('hidden')"> Le client
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="payeur" value="assurance" {{ $parAssurance ? 'checked' : '' }} onchange="this.form.querySelector('.bloc-assurance').classList.remove('hidden')"> Une assurance
            </label>
        </div>
        <div class="bloc-assurance grid grid-cols-2 gap-3 {{ $parAssurance ? '' : 'hidden' }}">
            <select name="assurance_id" class="px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="">— Choisir l'assurance —</option>
                @foreach($assurances as $a)
                <option value="{{ $a->id }}" {{ (int) $modele->assurance_id === $a->id ? 'selected' : '' }}>{{ $a->nom_complet }}</option>
                @endforeach
            </select>
            <input type="text" name="numero_sinistre" value="{{ $modele->numero_sinistre }}" maxlength="100" placeholder="N° de sinistre (facultatif)"
                   class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            @if($assurances->isEmpty())
            <p class="col-span-2 text-xs text-amber-600">Aucune assurance enregistrée : créez-la d'abord dans Clients (type « Assurance »).</p>
            @endif
        </div>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
            Enregistrer
        </button>
    </form>
    @elseif(! $modifiable)
    <p class="text-xs text-slate-400 mt-2">La facture est faite : le payeur ne peut plus changer (corriger la facture par un avoir si besoin).</p>
    @endif
</div>
