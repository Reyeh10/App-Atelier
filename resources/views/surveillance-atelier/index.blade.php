@extends('layouts.app')
@section('title', 'Surveillance atelier')
@section('page-title', 'Surveillance atelier')
@section('page-subtitle', 'Véhicules présents à l\'atelier')

@section('header-actions')
<span class="text-xs text-slate-400">Actualisé à {{ now()->format('H:i') }} — mise à jour automatique chaque minute</span>
@endsection

@php
    $Surveillance = \App\Http\Controllers\SurveillanceAtelierController::class;

    $etatsFeuille = [
        'a_affecter' => ['label' => 'À affecter', 'classe' => 'bg-gray-100 text-gray-600'],
        'affectee'   => ['label' => 'Affectée',   'classe' => 'bg-blue-100 text-blue-700'],
        'en_cours'   => ['label' => 'En cours',   'classe' => 'bg-purple-100 text-purple-700'],
        'terminee'   => ['label' => 'Terminée',   'classe' => 'bg-green-100 text-green-700'],
    ];
    $couleursEtape = ['reception' => 'blue', 'devis' => 'orange', 'reparation' => 'purple', 'controle' => 'pink', 'sortie' => 'teal'];
    $hexEtape      = ['reception' => '#2563eb', 'devis' => '#ea580c', 'reparation' => '#9333ea', 'controle' => '#db2777', 'sortie' => '#0d9488'];

    // Badge de couleur : rose et cyan n'existent pas dans le CSS compilé → couleurs en style direct
    $badge = function (string $couleur): string {
        $base = 'px-2 py-0.5 rounded-full text-xs font-bold';
        return match ($couleur) {
            'pink'  => 'class="' . $base . '" style="background:#fce7f3;color:#be185d"',
            'cyan'  => 'class="' . $base . '" style="background:#cffafe;color:#0e7490"',
            default => 'class="' . $base . ' bg-' . $couleur . '-100 text-' . $couleur . '-700"',
        };
    };
@endphp

@section('content')
<div class="space-y-4">

{{-- Chiffres clés --}}
<div class="grid grid-cols-5 gap-3">
    @foreach([
        ['À l\'atelier',        $stats['total'],     'text-slate-800'],
        ['En réparation',       $stats['en_cours'],  'text-purple-600'],
        ['En attente de pièces', $stats['pieces'],   'text-amber-600'],
        ['Prêts / à restituer', $stats['prets'],     'text-teal-600'],
        ['En retard',           $stats['en_retard'], 'text-red-600'],
    ] as [$label, $valeur, $couleur])
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3">
        <p class="text-2xl font-black {{ $couleur }}">{{ $valeur }}</p>
        <p class="text-xs text-slate-500">{{ $label }}</p>
    </div>
    @endforeach
</div>

{{-- Filtres (côté navigateur, sans recharger) --}}
<div class="flex flex-wrap gap-2">
    <button type="button" onclick="choisirEtape('')" data-etape-btn=""
            class="etape-btn px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all border-orange-500 bg-orange-50 text-orange-700">
        Toutes les étapes ({{ $stats['total'] }})
    </button>
    @foreach($etapes as $cle => $etape)
    <button type="button" onclick="choisirEtape('{{ $cle }}')" data-etape-btn="{{ $cle }}"
            class="etape-btn px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all border-gray-200 text-slate-600 hover:border-gray-300 bg-white">
        <span style="color:{{ $hexEtape[$cle] }}">●</span> {{ $etape['label'] }} ({{ $parEtape[$cle]->count() + $dossiersParEtape[$cle]->count() }})
    </button>
    @endforeach
</div>

<div class="bg-white rounded-2xl border border-gray-200 p-3 flex flex-wrap gap-3 items-center">
    <input type="text" id="filtre-recherche" oninput="filtrer()" placeholder="Rechercher : immatriculation, client, n° OR..."
           class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
    <select id="filtre-technicien" onchange="filtrer()"
            class="px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-500">
        <option value="">Tous les techniciens</option>
        <option value="aucun">Non affecté</option>
        @foreach($techniciens as $t)
        <option value="{{ $t->id }}">{{ $t->name }}</option>
        @endforeach
    </select>
    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
        <input type="checkbox" id="filtre-retard" onchange="filtrer()" class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
        En retard seulement
    </label>
</div>

{{-- Un bloc par véhicule, dans l'ordre des étapes puis de la date de réception --}}
@if($stats['total'] === 0)
<div class="bg-white rounded-2xl border border-gray-200 px-6 py-12 text-center text-slate-400">Aucun véhicule à l'atelier.</div>
@endif
<div class="grid grid-cols-4 gap-3 pb-4">
    @foreach($etapes as $cle => $etape)
            @foreach($parEtape[$cle] as $or)
            @php
                $feuilles  = $Surveillance::feuilles($or);
                $retard    = $Surveillance::estEnRetard($or);
                $jours     = (int) $or->date_entree->diffInDays(now());
                $techIds   = collect([$or->technicien_id])->merge($or->devisComplementaires()->pluck('technicien_id'))->filter()->unique()->values();
                $techNoms  = collect($feuilles)->pluck('technicien')->filter()->unique()->values();
                $pieces    = collect($feuilles)->contains('pieces', true);
                $enCours   = collect($feuilles)->firstWhere('etat', 'en_cours');
            @endphp
            <button type="button" onclick="ouvrirApercu({{ $or->id }})"
                    class="carte-vehicule min-w-0 text-left bg-white rounded-xl border border-gray-200 hover:border-orange-400 hover:shadow-md transition-all p-4"
                    style="border-top:4px solid {{ $hexEtape[$cle] }}"
                    data-etape="{{ $cle }}"
                    data-recherche="{{ strtolower($or->vehicule->immatriculation . ' ' . $or->client->nom_complet . ' ' . $or->numero . ' ' . $or->vehicule->marque . ' ' . $or->vehicule->modele) }}"
                    data-techniciens="{{ $techIds->isEmpty() ? 'aucun' : $techIds->implode(',') }}"
                    data-retard="{{ $retard ? 1 : 0 }}">
                <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color:{{ $hexEtape[$cle] }}">{{ $etape['label'] }}</p>
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono font-bold text-slate-900 text-base">{{ $or->vehicule->immatriculation }}</span>
                    @if($or->urgence !== 'normal')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">{{ $or->getUrgenceLabel() }}</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 truncate">{{ $or->vehicule->marque }} {{ $or->vehicule->modele }} — {{ $or->client->nom_complet }}</p>
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    <span {!! $badge($or->getStatutColor()) !!}>{{ $or->getStatutLabel() }}</span>
                    @if($pieces)<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">📦 Pièces</span>@endif
                    @if($retard)<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">En retard</span>@endif
                </div>
                <p class="text-xs text-slate-600 mt-2 truncate">👷 {{ $techNoms->isEmpty() ? 'Non affecté' : $techNoms->implode(', ') }}</p>
                <div class="flex items-center justify-between mt-1 text-xs text-slate-400">
                    <span>Reçu le {{ $or->date_entree->format('d/m') }} · {{ $jours }} j</span>
                    @if($enCours)
                    <span class="font-mono font-bold text-purple-600" data-depuis="{{ $enCours['debut']->toIso8601String() }}" data-estime="{{ $enCours['estimee'] ? (int) round($enCours['estimee'] * 3600) : '' }}">--:--:--</span>
                    @endif
                </div>
            </button>

            {{-- Aperçu affiché dans le panneau latéral --}}
            <template id="apercu-{{ $or->id }}">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xl font-black text-slate-900">{{ $or->vehicule->immatriculation }}</p>
                            <p class="text-sm text-slate-600">{{ $or->vehicule->marque }} {{ $or->vehicule->modele }}</p>
                            <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $or->numero }}</p>
                        </div>
                        <button type="button" onclick="fermerApercu()" class="text-slate-400 hover:text-slate-700 text-xl leading-none">✕</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <span {!! $badge($or->getStatutColor()) !!}>{{ $or->getStatutLabel() }}</span>
                        @if($or->type !== 'normal')<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">{{ $or->getTypeLabel() }}</span>@endif
                        @if($or->urgence !== 'normal')<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">{{ $or->getUrgenceLabel() }}</span>@endif
                        @if($retard)<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">En retard</span>@endif
                    </div>
                </div>

                <div class="px-6 py-4 space-y-5">
                    {{-- Réception --}}
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Réception</h4>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-slate-500">Reçu le</dt>
                            <dd class="font-semibold text-slate-800">{{ $or->date_entree->format('d/m/Y') }}{{ $or->heure_entree ? ' à ' . substr($or->heure_entree, 0, 5) : '' }}</dd>
                            <dt class="text-slate-500">À l'atelier depuis</dt>
                            <dd class="font-semibold {{ $retard ? 'text-red-600' : 'text-slate-800' }}">{{ $jours }} jour{{ $jours > 1 ? 's' : '' }}</dd>
                            <dt class="text-slate-500">Sortie prévue</dt>
                            <dd class="text-slate-800">{{ $or->date_sortie_prevue?->format('d/m/Y') ?? '—' }}</dd>
                            <dt class="text-slate-500">Conseiller</dt>
                            <dd class="text-slate-800">{{ $or->conseiller?->name ?? '—' }}</dd>
                            <dt class="text-slate-500">Kilométrage</dt>
                            <dd class="text-slate-800">{{ number_format($or->kilometrage_entree ?? 0, 0, ',', ' ') }} km</dd>
                            <dt class="text-slate-500">Carburant</dt>
                            <dd class="text-slate-800">{{ $or->niveau_carburant ?? '—' }}</dd>
                        </dl>
                        @if($or->motif_entree)
                        <p class="text-sm text-slate-700 bg-gray-50 rounded-xl px-3 py-2 mt-2">{{ $or->motif_entree }}</p>
                        @endif
                    </div>

                    {{-- Client --}}
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Client</h4>
                        <p class="text-sm font-semibold text-slate-800">{{ $or->client->nom_complet }}</p>
                        <p class="text-sm text-slate-500">{{ $or->client->telephone }}</p>
                    </div>

                    {{-- Feuilles de travail --}}
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Travaux</h4>
                        @forelse($feuilles as $f)
                        <div class="border border-gray-200 rounded-xl px-3 py-2 mb-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-800">Feuille {{ $f['numero'] }}{{ $f['devis'] ? ' — ' . $f['devis'] : '' }}</span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $etatsFeuille[$f['etat']]['classe'] }}">{{ $etatsFeuille[$f['etat']]['label'] }}</span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1">👷 {{ $f['technicien'] ?? 'Non affecté' }} · {{ $f['service'] }}</p>
                            @if($f['etat'] === 'en_cours')
                            <p class="text-xs text-slate-500 mt-1">
                                Démarrée à {{ $f['debut']->format('H:i') }} — temps écoulé
                                <span class="font-mono font-bold text-purple-600" data-depuis="{{ $f['debut']->toIso8601String() }}" data-estime="{{ $f['estimee'] ? (int) round($f['estimee'] * 3600) : '' }}">--:--:--</span>
                                @if($f['estimee'])<span class="text-slate-400">/ estimé {{ $or->formatDuree((float) $f['estimee']) }}</span>@endif
                            </p>
                            @elseif($f['etat'] === 'terminee')
                            <p class="text-xs text-slate-500 mt-1">{{ $f['debut']->format('d/m H:i') }} → {{ $f['fin']->format('d/m H:i') }}</p>
                            @endif
                            @if($f['pieces'])
                            <p class="text-xs font-semibold text-amber-700 mt-1">📦 En attente de pièces</p>
                            @endif
                        </div>
                        @empty
                        <p class="text-sm text-slate-400">Pas encore de devis accepté ni de technicien affecté.</p>
                        @endforelse
                    </div>

                    {{-- Devis --}}
                    @if($or->allDevis->isNotEmpty())
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Devis</h4>
                        @foreach($or->allDevis as $d)
                        <div class="flex items-center justify-between gap-2 text-sm py-1">
                            <span class="font-mono text-slate-700">{{ $d->numero }}</span>
                            <span {!! $badge($d->getStatutColor()) !!}>{{ $d->getStatutLabel() }}</span>
                            <span class="font-semibold text-slate-800">{{ number_format($d->montant_ttc, 0, ',', ' ') }} FDJ</span>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Facture --}}
                    @if($or->facture)
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Facture</h4>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-mono text-slate-700">{{ $or->facture->numero }}</span>
                            <span {!! $badge($or->facture->getStatutColor()) !!}>{{ $or->facture->getStatutLabel() }}</span>
                            <span class="font-semibold text-slate-800">{{ number_format($or->facture->totalGeneral(), 0, ',', ' ') }} FDJ</span>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    <a href="{{ route('ordres-reparations.show', $or) }}"
                       class="block text-center bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl transition-colors text-sm">
                        Ouvrir l'OR {{ $or->numero }} →
                    </a>
                </div>
            </template>
            @endforeach

            {{-- Dossiers de réception encore sans OR : véhicule présent, devis pas encore accepté --}}
            @foreach($dossiersParEtape[$cle] as $dossier)
            @php
                $retardDossier = $Surveillance::dossierEnRetard($dossier);
                $joursDossier  = $dossier->date_entree ? (int) $dossier->date_entree->diffInDays(now()) : 0;
                $devisDossier  = $dossier->devis->where('statut', '!=', 'refuse')->last();
            @endphp
            <button type="button" onclick="ouvrirApercu('dr-{{ $dossier->id }}')"
                    class="carte-vehicule min-w-0 text-left bg-white rounded-xl border border-gray-200 hover:border-orange-400 hover:shadow-md transition-all p-4"
                    style="border-top:4px solid {{ $hexEtape[$cle] }}"
                    data-etape="{{ $cle }}"
                    data-recherche="{{ strtolower($dossier->vehicule->immatriculation . ' ' . $dossier->client->nom_complet . ' ' . $dossier->numero . ' ' . $dossier->vehicule->marque . ' ' . $dossier->vehicule->modele) }}"
                    data-techniciens="aucun"
                    data-retard="{{ $retardDossier ? 1 : 0 }}">
                <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color:{{ $hexEtape[$cle] }}">{{ $etape['label'] }}</p>
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono font-bold text-slate-900 text-base">{{ $dossier->vehicule->immatriculation }}</span>
                    @if($dossier->urgence && $dossier->urgence !== 'normal')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">{{ $dossier->getUrgenceLabel() }}</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 truncate">{{ $dossier->vehicule->marque }} {{ $dossier->vehicule->modele }} — {{ $dossier->client->nom_complet }}</p>
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    <span {!! $badge($dossier->getStatutColor()) !!}>{{ $dossier->getStatutLabel() }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Dossier — pas encore d'OR</span>
                    @if($retardDossier)<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">En retard</span>@endif
                </div>
                <p class="text-xs text-slate-600 mt-2 truncate">{{ $dossier->getMotifVisiteLabel() }}{{ $devisDossier ? ' · devis ' . $devisDossier->numero : '' }}</p>
                <div class="flex items-center justify-between mt-1 text-xs text-slate-400">
                    <span>Reçu le {{ $dossier->date_entree?->format('d/m') }} · {{ $joursDossier }} j</span>
                </div>
            </button>

            <template id="apercu-dr-{{ $dossier->id }}">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xl font-black text-slate-900">{{ $dossier->vehicule->immatriculation }}</p>
                            <p class="text-sm text-slate-600">{{ $dossier->vehicule->marque }} {{ $dossier->vehicule->modele }}</p>
                            <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $dossier->numero }}</p>
                        </div>
                        <button type="button" onclick="fermerApercu()" class="text-slate-400 hover:text-slate-700 text-xl leading-none">✕</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <span {!! $badge($dossier->getStatutColor()) !!}>{{ $dossier->getStatutLabel() }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600">{{ $dossier->getMotifVisiteLabel() }}</span>
                        @if($retardDossier)<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">En retard</span>@endif
                    </div>
                </div>

                <div class="px-6 py-4 space-y-5">
                    <p class="text-sm text-slate-600 bg-gray-50 rounded-xl px-3 py-2">Véhicule réceptionné : l'OR sera créé quand le devis sera accepté.</p>

                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Réception</h4>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-slate-500">Reçu le</dt>
                            <dd class="font-semibold text-slate-800">{{ $dossier->date_entree?->format('d/m/Y') }}{{ $dossier->heure_entree ? ' à ' . substr($dossier->heure_entree, 0, 5) : '' }}</dd>
                            <dt class="text-slate-500">À l'atelier depuis</dt>
                            <dd class="font-semibold {{ $retardDossier ? 'text-red-600' : 'text-slate-800' }}">{{ $joursDossier }} jour{{ $joursDossier > 1 ? 's' : '' }}</dd>
                            <dt class="text-slate-500">Conseiller</dt>
                            <dd class="text-slate-800">{{ $dossier->conseiller?->name ?? '—' }}</dd>
                            <dt class="text-slate-500">Kilométrage</dt>
                            <dd class="text-slate-800">{{ number_format($dossier->kilometrage_entree ?? 0, 0, ',', ' ') }} km</dd>
                            <dt class="text-slate-500">Carburant</dt>
                            <dd class="text-slate-800">{{ $dossier->niveau_carburant ?? '—' }}</dd>
                        </dl>
                        @if($dossier->motif_entree)
                        <p class="text-sm text-slate-700 bg-gray-50 rounded-xl px-3 py-2 mt-2">{{ $dossier->motif_entree }}</p>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Client</h4>
                        <p class="text-sm font-semibold text-slate-800">{{ $dossier->client->nom_complet }}</p>
                        <p class="text-sm text-slate-500">{{ $dossier->client->telephone }}</p>
                    </div>

                    @if($dossier->devis->isNotEmpty())
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Devis</h4>
                        @foreach($dossier->devis as $d)
                        <div class="flex items-center justify-between gap-2 text-sm py-1">
                            <span class="font-mono text-slate-700">{{ $d->numero }}</span>
                            <span {!! $badge($d->getStatutColor()) !!}>{{ $d->getStatutLabel() }}</span>
                            <span class="font-semibold text-slate-800">{{ number_format($d->montant_ttc, 0, ',', ' ') }} FDJ</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    <a href="{{ route('dossiers-reception.show', $dossier) }}"
                       class="block text-center bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl transition-colors text-sm">
                        Ouvrir le dossier {{ $dossier->numero }} →
                    </a>
                </div>
            </template>
            @endforeach
    @endforeach
</div>
<div id="aucun-resultat" class="hidden bg-white rounded-2xl border border-gray-200 px-6 py-12 text-center text-slate-400">Aucun véhicule pour ce filtre.</div>

</div>

{{-- Panneau latéral d'aperçu --}}
<div id="apercu-fond" onclick="fermerApercu()" class="hidden fixed inset-0 bg-black/40 z-40"></div>
<div id="apercu-panneau" class="hidden fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 overflow-y-auto"></div>

<script>
function ouvrirApercu(id) {
    const panneau = document.getElementById('apercu-panneau');
    panneau.innerHTML = '';
    panneau.appendChild(document.getElementById('apercu-' + id).content.cloneNode(true));
    panneau.classList.remove('hidden');
    document.getElementById('apercu-fond').classList.remove('hidden');
    majChronos();
}

function fermerApercu() {
    document.getElementById('apercu-panneau').classList.add('hidden');
    document.getElementById('apercu-fond').classList.add('hidden');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerApercu(); });

let etapeChoisie = '';

function choisirEtape(cle) {
    etapeChoisie = cle;
    document.querySelectorAll('.etape-btn').forEach(btn => {
        const actif = btn.dataset.etapeBtn === cle;
        btn.classList.toggle('border-orange-500', actif);
        btn.classList.toggle('bg-orange-50', actif);
        btn.classList.toggle('text-orange-700', actif);
        btn.classList.toggle('border-gray-200', !actif);
        btn.classList.toggle('text-slate-600', !actif);
        btn.classList.toggle('bg-white', !actif);
    });
    filtrer();
}

function filtrer() {
    const texte  = document.getElementById('filtre-recherche').value.trim().toLowerCase();
    const tech   = document.getElementById('filtre-technicien').value;
    const retard = document.getElementById('filtre-retard').checked;
    document.querySelectorAll('.carte-vehicule').forEach(carte => {
        const visible = (!etapeChoisie || carte.dataset.etape === etapeChoisie)
            && (!texte || carte.dataset.recherche.includes(texte))
            && (!tech || carte.dataset.techniciens.split(',').includes(tech))
            && (!retard || carte.dataset.retard === '1');
        carte.classList.toggle('hidden', !visible);
    });
    const cartes   = document.querySelectorAll('.carte-vehicule').length;
    const visibles = document.querySelectorAll('.carte-vehicule:not(.hidden)').length;
    document.getElementById('aucun-resultat').classList.toggle('hidden', cartes === 0 || visibles > 0);
}

// Temps écoulé des feuilles en cours (rouge une fois la durée estimée dépassée)
function majChronos() {
    const format = s => [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(n => String(n).padStart(2, '0')).join(':');
    document.querySelectorAll('[data-depuis]').forEach(el => {
        const ecoule = Math.max(0, Math.floor((Date.now() - Date.parse(el.dataset.depuis)) / 1000));
        el.textContent = format(ecoule);
        if (el.dataset.estime && ecoule > parseInt(el.dataset.estime, 10)) {
            el.classList.remove('text-purple-600');
            el.classList.add('text-red-600');
        }
    });
}
majChronos();
setInterval(majChronos, 1000);

// Actualisation automatique chaque minute — sauf si un aperçu est ouvert ou un filtre saisi
setInterval(() => {
    const apercuOuvert = !document.getElementById('apercu-panneau').classList.contains('hidden');
    const filtreActif  = etapeChoisie || document.getElementById('filtre-recherche').value || document.getElementById('filtre-technicien').value || document.getElementById('filtre-retard').checked;
    if (!apercuOuvert && !filtreActif) location.reload();
}, 60000);
</script>
@endsection
