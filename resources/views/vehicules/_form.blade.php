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

    {{-- Propriétaire --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Propriétaire</h3>
        @if($vehicule)
        {{-- En modification : le propriétaire ne se change plus ici (véhicule vendu à
             quelqu'un d'autre = évènement à part, avec confirmation et trace — cf. bouton
             "Changer de propriétaire" sur la fiche véhicule). L'historique reste toujours
             attaché au véhicule, pas au client. --}}
        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
            <div>
                <p class="text-sm font-medium text-slate-800">{{ $vehicule->client->nom_complet }}</p>
                <p class="text-xs text-slate-400 mt-0.5">Pour changer de propriétaire (véhicule vendu), utilisez le bouton dédié sur la fiche véhicule.</p>
            </div>
        </div>
        @else
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Client <span class="text-red-500">*</span></label>
            <select name="client_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white @error('client_id') border-red-400 @enderror">
                <option value="">— Sélectionner un client —</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ old('client_id', $clientSelectionne?->id) == $c->id ? 'selected' : '' }}>
                        {{ $c->nom_complet }} — {{ $c->telephone }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
    </div>

    {{-- Identification --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Identification</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Immatriculation <span class="text-red-500">*</span></label>
                <input type="text" name="immatriculation" value="{{ old('immatriculation', $vehicule?->immatriculation) }}"
                       placeholder="123 ABC 16" style="text-transform:uppercase"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-500 @error('immatriculation') border-red-400 @enderror">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">N° Châssis (VIN) <span class="text-red-500">*</span></label>
                <input type="text" name="vin" value="{{ old('vin', $vehicule?->vin) }}" required
                       minlength="17" maxlength="17" pattern="[A-Za-z0-9]{17}"
                       title="Le VIN doit contenir exactement 17 caractères alphanumériques (lettres et chiffres)."
                       placeholder="VF7XXXXXXXXXXXXXXX" style="text-transform:uppercase"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-500 @error('vin') border-red-400 @enderror">
                <p class="text-xs text-slate-400 mt-1">Exactement 17 caractères alphanumériques (ni plus, ni moins).</p>
            </div>
        </div>
    </div>

    {{-- Caractéristiques --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Caractéristiques du véhicule</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Marque <span class="text-red-500">*</span></label>
                <input type="text" name="marque" value="{{ old('marque', $vehicule?->marque) }}"
                       placeholder="Peugeot, Renault, Toyota..."
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('marque') border-red-400 @enderror">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Modèle <span class="text-red-500">*</span></label>
                <input type="text" name="modele" value="{{ old('modele', $vehicule?->modele) }}"
                       placeholder="208, Clio, Corolla..."
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('modele') border-red-400 @enderror">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Version / Finition</label>
                <input type="text" name="version" value="{{ old('version', $vehicule?->version) }}"
                       placeholder="1.6 HDi Confort"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Année</label>
                <input type="number" name="annee" value="{{ old('annee', $vehicule?->annee) }}"
                       min="1960" max="{{ date('Y') + 1 }}" placeholder="{{ date('Y') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Motorisation <span class="text-red-500">*</span></label>
                <select name="motorisation" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                    @foreach(['diesel' => 'Diesel', 'essence' => 'Essence', 'hybride' => 'Hybride', 'electrique' => 'Électrique', 'gpl' => 'GPL', 'autre' => 'Autre'] as $val => $label)
                        <option value="{{ $val }}" {{ old('motorisation', $vehicule?->motorisation ?? 'diesel') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Catégorie</label>
                <select id="input-categorie" name="categorie" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white">
                    @foreach(['' => 'Autre / non précisé', 'pick-up' => 'Pick-up', 'suv' => 'SUV'] as $val => $label)
                        <option value="{{ $val }}" {{ old('categorie', $vehicule?->categorie ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Détermine la limite d'âge pour l'éligibilité garantie (pick-up : 3 ans, SUV : 5 ans).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Couleur</label>
                <input type="text" name="couleur" value="{{ old('couleur', $vehicule?->couleur) }}"
                       placeholder="Blanc, Noir, Gris..."
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Cylindrée</label>
                <input type="text" name="cylindree" value="{{ old('cylindree', $vehicule?->cylindree) }}"
                       placeholder="1600cc"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Puissance fiscale (CV)</label>
                <input type="text" name="puissance_fiscale" value="{{ old('puissance_fiscale', $vehicule?->puissance_fiscale) }}"
                       placeholder="7 CV"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Kilométrage <span class="text-red-500">*</span></label>
                <input type="number" name="kilometrage" value="{{ old('kilometrage', $vehicule?->kilometrage ?? 0) }}"
                       min="0" placeholder="0"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('kilometrage') border-red-400 @enderror">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Date de mise en circulation <span class="text-red-500">*</span></label>
                <input type="date" id="input-date-mise-circulation" name="date_mise_circulation" value="{{ old('date_mise_circulation', $vehicule?->date_mise_circulation?->format('Y-m-d')) }}"
                       required max="{{ date('Y-m-d') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 @error('date_mise_circulation') border-red-400 @enderror">
            </div>
        </div>
    </div>

    {{-- Documents & Garantie --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Documents & Garantie</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Expiration assurance</label>
                <input type="date" name="date_expiration_assurance" value="{{ old('date_expiration_assurance', $vehicule?->date_expiration_assurance?->format('Y-m-d')) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Expiration vignette</label>
                <input type="date" name="date_expiration_vignette" value="{{ old('date_expiration_vignette', $vehicule?->date_expiration_vignette?->format('Y-m-d')) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            @php $garantieVerrouillee = $vehicule && ! $vehicule->sous_garantie; @endphp
            <div class="col-span-2">
                @if($garantieVerrouillee)
                <input type="hidden" name="sous_garantie" value="0">
                <div class="flex items-center gap-3 opacity-60">
                    <input type="checkbox" disabled class="w-4 h-4 text-orange-500 border-gray-300 rounded">
                    <span class="text-sm font-medium text-slate-700">Véhicule sous garantie constructeur</span>
                </div>
                <p class="text-xs text-red-500 mt-1">Ce véhicule a été enregistré sans garantie constructeur — ce choix est définitif et ne peut plus être réactivé.</p>
                @else
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="sous_garantie" value="1"
                           {{ old('sous_garantie', $vehicule?->sous_garantie) ? 'checked' : '' }}
                           class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"
                           onchange="document.getElementById('fin_garantie_block').classList.toggle('hidden', !this.checked)">
                    <span class="text-sm font-medium text-slate-700">Véhicule sous garantie constructeur</span>
                </label>
                @if($vehicule)
                <p class="text-xs text-slate-400 mt-1">Attention : si vous décochez et enregistrez, ce choix sera définitif.</p>
                @endif
                @endif
            </div>
            @if($vehicule?->garantie_sortie_le)
            <div class="col-span-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                <p class="text-sm text-red-700 font-medium">Ce véhicule a été marqué définitivement sorti de la garantie le {{ $vehicule->garantie_sortie_le->format('d/m/Y') }}.</p>
            </div>
            @endif
            <div id="fin_garantie_block" class="{{ old('sous_garantie', $vehicule?->sous_garantie) ? '' : 'hidden' }} col-span-2 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Fin de garantie</label>
                    <input type="date" id="input-fin-garantie" name="fin_garantie" value="{{ old('fin_garantie', $vehicule?->fin_garantie?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <p class="text-xs text-slate-400 mt-1">Calculée automatiquement depuis la date de mise en circulation et la catégorie — modifiable si besoin.</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Équipements couverts par la garantie
                        <span class="text-slate-400 font-normal text-xs ml-1">(varie selon le contrat)</span>
                    </label>
                    <textarea name="garantie_couverture" rows="2"
                              placeholder="Ex : Moteur, boîte de vitesses, climatisation, électronique embarquée..."
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none">{{ old('garantie_couverture', $vehicule?->garantie_couverture) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Notes</h3>
        <textarea name="notes" rows="3" placeholder="Observations particulières sur le véhicule..."
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none">{{ old('notes', $vehicule?->notes) }}</textarea>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors shadow-sm">
            {{ $vehicule ? 'Enregistrer les modifications' : 'Enregistrer le véhicule' }}
        </button>
        <a href="{{ $vehicule ? route('vehicules.show', $vehicule) : route('vehicules.index') }}"
           class="px-6 py-2.5 border border-gray-300 text-slate-600 font-medium rounded-xl hover:bg-gray-50 transition-colors">
            Annuler
        </a>
    </div>
</form>

<script>
(function() {
    const limitesAgeAns = @json(\App\Models\Vehicule::limitesAgeGarantieParCategorie());
    const inputDate = document.getElementById('input-date-mise-circulation');
    const selectCategorie = document.getElementById('input-categorie');
    const inputFinGarantie = document.getElementById('input-fin-garantie');
    if (!inputDate || !selectCategorie || !inputFinGarantie) return;

    function calculerFinGarantie() {
        const annees = limitesAgeAns[selectCategorie.value];
        if (!annees || !inputDate.value) return;
        // Ne pas écraser une date saisie/modifiée manuellement par l'utilisateur.
        if (inputFinGarantie.value && inputFinGarantie.dataset.autoFilled !== 'true') return;

        const [annee, mois, jour] = inputDate.value.split('-').map(Number);
        const dateFin = new Date(annee + annees, mois - 1, jour);
        const pad = (n) => String(n).padStart(2, '0');
        inputFinGarantie.value = `${dateFin.getFullYear()}-${pad(dateFin.getMonth() + 1)}-${pad(dateFin.getDate())}`;
        inputFinGarantie.dataset.autoFilled = 'true';
    }

    inputDate.addEventListener('change', calculerFinGarantie);
    selectCategorie.addEventListener('change', calculerFinGarantie);
    inputFinGarantie.addEventListener('input', function() {
        inputFinGarantie.dataset.autoFilled = 'false';
    });
})();
</script>
