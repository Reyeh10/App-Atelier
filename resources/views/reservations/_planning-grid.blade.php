{{--
    Grille de planning réutilisable : $planning (cf. ReservationService::planningJour)
    et $date (chaîne AAAA-MM-JJ du jour affiché — requis pour les créneaux cliquables).
    Chaque colonne porte un attribut data-colonne="{clé}" (sur les <th>, <td> et les
    lignes "sans heure") pour permettre un filtrage côté JS à une seule colonne
    (cf. reception/choix-motif.blade.php) sans devoir re-render la grille.
    Les cases libres sont cliquables (si gerer_reservations) pour réserver
    directement ce créneau précis.
--}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 w-20">Heure</th>
                    @foreach($planning['colonnes'] as $cle => $nom)
                    <th data-colonne="{{ $cle }}" class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500">{{ $nom }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($planning['rows'] as $rowIndex => $label)
                <tr class="border-b border-gray-100">
                    <td class="px-3 py-2 text-xs font-mono text-slate-400 align-top">{{ $label }}</td>
                    @foreach($planning['colonnes'] as $cle => $nom)
                        @php $cell = $planning['grid'][$rowIndex][$cle] ?? null; @endphp
                        @if($cell === 'skip')
                            {{-- couvert par un rowspan de la ligne au-dessus --}}
                        @elseif(is_array($cell))
                            <td data-colonne="{{ $cle }}" rowspan="{{ $cell['rowspan'] }}" class="px-2 py-1.5 align-top">
                                @if(count($cell['items']) > 2)
                                    @php
                                        $premier = $cell['items'][0];
                                        $itemsJson = collect($cell['items'])->map(fn ($item) => [
                                            'debut'  => $item['debut'],
                                            'fin'    => $item['fin'],
                                            'client' => $item['reservation']->client->nom_complet,
                                            'immat'  => $item['reservation']->vehicule->immatriculation,
                                            'url'    => route('reservations.show', $item['reservation']),
                                            'honore' => $item['reservation']->statut === 'honore',
                                        ]);
                                    @endphp
                                    <button type="button"
                                            data-titre="{{ $nom }} — {{ $premier['debut'] }} à {{ $premier['fin'] }}"
                                            data-items="{{ json_encode($itemsJson) }}"
                                            onclick="ouvrirModalCreneau(this)"
                                            class="w-full flex items-center justify-between gap-2 rounded-lg border-l-4 border-blue-400 bg-blue-50 hover:bg-blue-100 px-3 py-2 transition-colors text-left">
                                        <span class="text-xs font-bold text-slate-800">{{ $premier['debut'] }} – {{ $premier['fin'] }}</span>
                                        <span class="text-xs font-semibold text-blue-700 bg-blue-100 rounded-full px-2 py-0.5 flex-shrink-0">{{ count($cell['items']) }} véhicules</span>
                                    </button>
                                @else
                                    <div class="space-y-1.5">
                                        @foreach($cell['items'] as $item)
                                        @php $r = $item['reservation']; @endphp
                                        <a href="{{ route('reservations.show', $r) }}"
                                           class="block rounded-lg border-l-4 px-3 py-2 transition-colors
                                                  {{ $r->statut === 'honore' ? 'bg-green-50 border-green-400 hover:bg-green-100' : 'bg-blue-50 border-blue-400 hover:bg-blue-100' }}">
                                            <p class="text-xs font-bold text-slate-800">{{ $item['debut'] }} – {{ $item['fin'] }}</p>
                                            <p class="text-xs font-medium text-slate-700 truncate">{{ $r->client->nom_complet }}</p>
                                            <p class="text-xs text-slate-500 font-mono">{{ $r->vehicule->immatriculation }}</p>
                                        </a>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        @elseif(auth()->user()->hasPermission('gerer_reservations') && $cle !== '__autre__' && ! \Carbon\Carbon::parse($date . ' ' . $label)->isPast())
                            @php
                                $estEntretien = $cle === 'entretien_periodique';
                                $service = \App\Services\ReservationService::servicesAutre()[$cle] ?? null;
                                $paramsCreneau = [
                                    'date' => $date,
                                    'heure_rdv' => $label,
                                    'canal_service' => $estEntretien ? 'entretien_periodique' : 'autre',
                                ];
                                if (! $estEntretien) {
                                    $paramsCreneau['service_cle'] = $cle;
                                    if ($service) {
                                        $paramsCreneau['tache'] = $service['label'];
                                        $paramsCreneau['duree_estimee'] = \App\Services\ReservationService::dureeDefautHeures($cle);
                                    }
                                }
                            @endphp
                            <td data-colonne="{{ $cle }}" class="p-0">
                                <a href="{{ route('reservations.create', $paramsCreneau) }}"
                                   class="group flex items-center justify-center h-9 hover:bg-teal-50 transition-colors"
                                   title="Réserver ce créneau — {{ $nom }} à {{ $label }}">
                                    <span class="text-teal-300 group-hover:text-teal-600 text-base font-bold opacity-0 group-hover:opacity-100 transition-opacity">+</span>
                                </a>
                            </td>
                        @else
                            <td data-colonne="{{ $cle }}" class="px-2 py-1.5 {{ \Carbon\Carbon::parse($date . ' ' . $label)->isPast() ? 'bg-gray-50' : '' }}"></td>
                        @endif
                    @endforeach
                </tr>
                @empty
                <tr><td colspan="{{ count($planning['colonnes']) + 1 }}" class="px-6 py-10 text-center text-slate-400">Atelier fermé ce jour.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($planning['sansHeure']->isNotEmpty())
<div class="mt-5 bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Réservations sans heure précisée</h3>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($planning['sansHeure'] as $r)
        <a href="{{ route('reservations.show', $r) }}"
           data-colonne="{{ $r->canal_service === 'entretien_periodique' ? 'entretien_periodique' : $r->service_cle }}"
           class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors">
            <div>
                <p class="text-sm font-medium text-slate-700">{{ $r->client->nom_complet }} — {{ $r->vehicule->immatriculation }}</p>
                <p class="text-xs text-slate-400">{{ $r->numero }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $r->getStatutColor() }}-100 text-{{ $r->getStatutColor() }}-700">{{ $r->getStatutLabel() }}</span>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- ── Modale : liste des véhicules d'un créneau à forte affluence ────── --}}
<div id="modal-creneau-reservations" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="fermerModalCreneau()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm mx-4 max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 id="modal-creneau-titre" class="font-bold text-slate-800 pr-3"></h3>
            <button type="button" onclick="fermerModalCreneau()" class="text-slate-400 hover:text-slate-700 flex-shrink-0">×</button>
        </div>
        <div id="modal-creneau-body" class="space-y-1.5"></div>
    </div>
</div>

@push('scripts')
<script>
function ouvrirModalCreneau(btn) {
    const items = JSON.parse(btn.dataset.items);
    document.getElementById('modal-creneau-titre').textContent = btn.dataset.titre;

    const body = document.getElementById('modal-creneau-body');
    body.innerHTML = '';
    items.forEach(function (it) {
        const a = document.createElement('a');
        a.href = it.url;
        a.className = 'block rounded-lg border-l-4 px-3 py-2 transition-colors '
            + (it.honore ? 'bg-green-50 border-green-400 hover:bg-green-100' : 'bg-blue-50 border-blue-400 hover:bg-blue-100');

        const pHeure = document.createElement('p');
        pHeure.className = 'text-xs font-bold text-slate-800';
        pHeure.textContent = it.debut + ' – ' + it.fin;

        const pClient = document.createElement('p');
        pClient.className = 'text-xs font-medium text-slate-700';
        pClient.textContent = it.client;

        const pImmat = document.createElement('p');
        pImmat.className = 'text-xs text-slate-500 font-mono';
        pImmat.textContent = it.immat;

        a.append(pHeure, pClient, pImmat);
        body.appendChild(a);
    });

    document.getElementById('modal-creneau-reservations').classList.remove('hidden');
}

function fermerModalCreneau() {
    document.getElementById('modal-creneau-reservations').classList.add('hidden');
}
</script>
@endpush
