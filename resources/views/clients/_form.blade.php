<form method="POST" action="{{ $action }}" class="space-y-6 max-w-3xl">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
        <ul class="space-y-1">
            @foreach($errors->all() as $e)
            <li class="text-sm text-red-700 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>{{ $e }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Type de client --}}
    @php
        $allTypes = [
            'particulier' => ['label' => 'Particulier', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'color' => 'blue'],
            'societe'     => ['label' => 'Société',     'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'purple'],
            'assurance'   => ['label' => 'Assurance',   'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => 'green'],
        ];
        $typesAutorises = $typesAutorises ?? array_keys($allTypes);
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Type de client</h3>
        <div class="grid grid-cols-3 gap-3">
            @foreach($allTypes as $val => $opt)
            @php $autorise = in_array($val, $typesAutorises); @endphp
            <label class="{{ $autorise ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' }}">
                <input type="radio" name="type" value="{{ $val }}"
                       {{ old('type', $client?->type ?? $typesAutorises[0] ?? 'particulier') === $val ? 'checked' : '' }}
                       {{ ! $autorise ? 'disabled' : '' }}
                       class="sr-only peer" onchange="toggleTypeFields()">
                <div class="border-2 rounded-xl p-4 text-center transition-all peer-checked:border-{{ $opt['color'] }}-500 peer-checked:bg-{{ $opt['color'] }}-50 border-gray-200 {{ $autorise ? 'hover:border-gray-300' : '' }}">
                    <div class="w-10 h-10 mx-auto mb-2 bg-{{ $opt['color'] }}-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-{{ $opt['color'] }}-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $opt['icon'] }}"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-slate-700">{{ $opt['label'] }}</p>
                    @if(! $autorise)<p class="text-xs text-red-400 mt-0.5">Non autorisé</p>@endif
                </div>
            </label>
            @endforeach
        </div>
    </div>

    {{-- Informations principales --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Informations</h3>
        <div class="grid grid-cols-2 gap-4">

            {{-- Nom complet (particulier & assurance) --}}
            <div class="field-particulier field-assurance col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Nom complet <span class="text-red-500">*</span>
                </label>
                <input type="text" name="nom"
                       value="{{ old('nom', $client?->type === 'particulier' ? $client->nom_complet : $client?->nom) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('nom') border-red-400 @enderror"
                       placeholder="Ex : Ibrahim Moumine">
                <input type="hidden" name="prenom" value="">
            </div>

            {{-- Champs société/assurance --}}
            <div class="field-societe col-span-2 hidden">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Raison sociale <span class="text-red-500">*</span>
                </label>
                <input type="text" name="raison_sociale" value="{{ old('raison_sociale', $client?->raison_sociale) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <div class="field-societe hidden">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">RC (Registre commercial)</label>
                <input type="text" name="rc" value="{{ old('rc', $client?->rc) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <div class="field-societe hidden">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">NIF</label>
                <input type="text" name="nif" value="{{ old('nif', $client?->nif) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <div class="field-societe field-assurance hidden col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nom du contact / Responsable</label>
                <input type="text" name="contact_nom" value="{{ old('contact_nom', $client?->contact_nom) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            {{-- Contact commun --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Téléphone <span class="text-red-500">*</span>
                </label>
                <input type="text" name="telephone" value="{{ old('telephone', $client?->telephone) }}"
                       placeholder="0550 000 000"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('telephone') border-red-400 @enderror">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Téléphone 2</label>
                <input type="text" name="telephone2" value="{{ old('telephone2', $client?->telephone2) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <div class="col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input type="email" name="email" value="{{ old('email', $client?->email) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
        </div>
    </div>

    {{-- Adresse --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Adresse</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Adresse</label>
                <input type="text" name="adresse" value="{{ old('adresse', $client?->adresse) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Ville</label>
                <input type="text" name="ville" value="{{ old('ville', $client?->ville) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Wilaya</label>
                <input type="text" name="wilaya" value="{{ old('wilaya', $client?->wilaya) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
        </div>
    </div>

    {{-- Compte crédit (admin uniquement) --}}
    @if(auth()->user()->isAdmin())
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            Compte crédit client
        </h3>
        <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="compte_actif" value="0">
                <input type="checkbox" name="compte_actif" value="1" id="compte_actif"
                       {{ old('compte_actif', $client?->compte_actif) ? 'checked' : '' }}
                       onchange="togglePlafond()"
                       class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <div>
                    <p class="text-sm font-medium text-slate-700">Activer le compte crédit</p>
                    <p class="text-xs text-slate-400">Le client peut être facturé sur compte jusqu'au plafond défini</p>
                </div>
            </label>
            <div id="plafond_wrap" class="{{ old('compte_actif', $client?->compte_actif) ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Plafond du compte (FDJ) <span class="text-red-500">*</span>
                </label>
                <input type="number" name="plafond_compte" id="plafond_compte"
                       value="{{ old('plafond_compte', $client?->plafond_compte !== null ? (int) round($client->plafond_compte) : null) }}"
                       min="1" step="1" placeholder="Ex : 500000"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @if($client?->compte_actif)
                <p class="text-xs text-slate-500 mt-1">
                    Solde utilisé actuel :
                    <span class="font-semibold {{ $client->solde_compte >= $client->plafond_compte ? 'text-red-600' : 'text-slate-700' }}">
                        {{ number_format($client->solde_compte, 0, ',', ' ') }} FDJ
                    </span>
                    / {{ number_format($client->plafond_compte, 0, ',', ' ') }} FDJ
                </p>
                @endif
            </div>
        </div>
    </div>
    <script>
    function togglePlafond() {
        const checked = document.getElementById('compte_actif').checked;
        document.getElementById('plafond_wrap').classList.toggle('hidden', !checked);
    }
    </script>
    @endif

    {{-- Notes --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Notes internes</h3>
        <textarea name="notes" rows="3"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"
                  placeholder="Observations, préférences client...">{{ old('notes', $client?->notes) }}</textarea>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3">
        <button type="submit"
                class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors shadow-sm">
            {{ $client ? 'Enregistrer les modifications' : 'Créer le client' }}
        </button>
        <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}"
           class="px-6 py-2.5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors">
            Annuler
        </a>
    </div>
</form>

<script>
function toggleTypeFields() {
    const type = document.querySelector('input[name="type"]:checked')?.value || 'particulier';
    document.querySelectorAll('.field-particulier').forEach(el => el.classList.toggle('hidden', type !== 'particulier'));
    document.querySelectorAll('.field-societe').forEach(el => el.classList.toggle('hidden', type !== 'societe'));
    document.querySelectorAll('.field-assurance').forEach(el => {
        if (type === 'assurance') el.classList.remove('hidden');
        else if (!el.classList.contains('field-particulier')) el.classList.add('hidden');
    });
}
document.addEventListener('DOMContentLoaded', toggleTypeFields);
</script>
