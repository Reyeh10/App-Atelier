<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Feuille de Travail — {{ $or->numero }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 210mm; background: #fff; }
        body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #111; }

        /* ── Tenue sur une seule page ──
           Le contenu (nombre de lignes main d'œuvre / pièces / contrôles...) varie
           selon l'OR — impossible à garantir en CSS statique. .page-outer représente
           la feuille A4 imprimable exacte (210×297mm, hauteur fixe, débordement masqué) ;
           .page est le contenu réel, réduit dynamiquement en JS (cf. ajusterPourUnePage())
           s'il dépasse cette hauteur, pour toujours tenir sur une seule feuille. */
        .page-outer { width: 210mm; height: 297mm; overflow: hidden; background: #fff; }
        .page { width: 210mm; padding: 8mm 11mm 6mm; }
        @media screen {
            html, body { width: 100%; background: #d1d5db; }
            .page-outer { margin: 55px auto 40px; box-shadow: 0 4px 24px rgba(0,0,0,.18); }
        }

        /* ── En-tête ── */
        .header { display: flex; justify-content: space-between; align-items: flex-start;
                  border-bottom: 2.5px solid #5B3FAF; padding-bottom: 6px; margin-bottom: 7px; }
        .header img { height: 44px; width: auto; }
        .header p  { font-size: 7.5pt; color: #666; margin-top: 3px; }
        .or-number { font-size: 16pt; font-weight: 900; font-family: 'Courier New', monospace; }
        .or-date   { font-size: 7.5pt; color: #555; margin-top: 1px; }

        .doc-title { text-align: center; font-size: 11.5pt; font-weight: 800;
                     letter-spacing: 2px; text-transform: uppercase;
                     border: 2px solid #111; padding: 4px; margin-bottom: 7px; }

        /* ── Grilles infos ── */
        .g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 5px; }
        .g3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; margin-bottom: 5px; }
        .g4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 5px; margin-bottom: 5px; }

        .ib { border: 1px solid #ccc; border-radius: 3px; padding: 3px 6px; }
        .ib label { font-size: 7pt; color: #888; display: block; margin-bottom: 1px; }
        .ib span  { font-size: 9.5pt; font-weight: 700; }
        .ib.hi    { background: #f3f0ff; border-color: #5B3FAF; }

        /* ── Section titre ── */
        .st { font-size: 8pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
              color: #5B3FAF; border-bottom: 1.5px solid #5B3FAF;
              padding-bottom: 2px; margin-bottom: 4px; margin-top: 6px; }

        /* ── Tableaux ── */
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        th { background: #f0eeff; padding: 4px 6px; text-align: left; font-weight: 700;
             border: 1px solid #bbb; font-size: 8pt; }
        td { padding: 3px 6px; border: 1px solid #ccc; height: 19px; vertical-align: middle; }
        .tc  { text-align: center; }
        .num { width: 24px; text-align: center; background: #f9fafb; font-weight: 700; color: #aaa; font-size: 8pt; }
        .cb  { text-align: center; font-size: 12pt; color: #555; }
        .ref { font-family: 'Courier New', monospace; font-size: 8.5pt; color: #c2410c; font-weight: 700; }

        /* ── Service badge ── */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 8.5pt;
                 font-weight: 700; background: #f3f0ff; color: #5B3FAF; border: 1.5px solid #5B3FAF; }

        /* ── Chronométrage ── */
        .chrono-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; }
        .c-box { border: 1.5px solid #ccc; border-radius: 3px; padding: 4px 6px; }
        .c-box label { font-size: 7pt; color: #666; display: block; margin-bottom: 3px; }
        .c-box .c-line { height: 22px; border-bottom: 1.5px solid #999; }

        /* ── Observations ── */
        .obs-lines { border: 1px solid #ccc; border-radius: 3px; padding: 3px 6px; }
        .obs-line  { border-bottom: 1px solid #eee; height: 17px; }

        /* ── Signatures ── */
        .sig-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .sig-box { border: 1px solid #ccc; border-radius: 3px; padding: 6px 7px; }
        .sig-box label { font-size: 7pt; color: #888; display: block; margin-bottom: 2px; }
        .sig-box .sig-name { font-weight: 700; font-size: 9pt; }
        .sig-box .sig-line { border-bottom: 1.5px solid #999; height: 32px; margin-top: 4px; }
        .sig-box .sig-hint { font-size: 7pt; color: #bbb; margin-top: 2px; }

        /* ── Bas de page ── */
        .footer { margin-top: 8px; border-top: 1px solid #ddd; padding-top: 4px;
                  display: flex; justify-content: space-between; font-size: 7pt; color: #aaa; }

        /* ── Motif ── */
        .motif-box { border: 1px solid #ccc; border-radius: 3px; padding: 4px 7px;
                     font-size: 9.5pt; line-height: 1.4; min-height: 24px; }

        /* ── Deux colonnes côte à côte ── */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 6px; }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .page-outer { height: 297mm; }
            .page { padding: 6mm 9mm 5mm; }
        }

        /* Boutons écran */
        .btn-bar { position: fixed; top: 14px; left: 50%; transform: translateX(-50%);
                   display: flex; gap: 10px; z-index: 99; }
        .print-btn { background: #5B3FAF; color: #fff; border: none; padding: 8px 22px;
                     border-radius: 7px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .back-btn  { background: #dc2626; color: #fff; border: none; padding: 8px 22px;
                     border-radius: 7px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
        @media print { .btn-bar { display: none; } }
    </style>
</head>
<body>

<div class="btn-bar no-print">
    @if(request('apercu'))
    <button onclick="window.close()" class="back-btn">← Fermer</button>
    @else
    <a href="{{ route('ordres-reparations.show', $or) }}" class="back-btn">← Retour</a>
    @endif
    <button onclick="window.print()" class="print-btn">🖨 Imprimer</button>
</div>

<div class="page-outer" id="page-outer">
<div class="page" id="page-content">

    {{-- ── En-tête ── --}}
    <div class="header">
        <div>
            <img src="{{ asset('logo.jpg') }}" alt="STCD Motors">
            <p>Service Après-Vente — Atelier</p>
        </div>
        <div style="text-align:right;">
            <div class="or-number">{{ $or->numero }}</div>
            <div class="or-date">Créé le {{ $or->created_at->format('d/m/Y à H:i') }}</div>
            @if($or->date_affectation)
            <div class="or-date">Affecté le {{ $or->date_affectation->format('d/m/Y à H:i') }}</div>
            @endif
        </div>
    </div>

    <div class="doc-title">Feuille de Travail Mécanicien</div>

    {{-- ── Infos OR ── --}}
    <div class="g4" style="margin-bottom:4px;">
        <div class="ib hi">
            <label>Service</label>
            <span class="badge">{{ $or->getServiceLabel() }}</span>
        </div>
        <div class="ib">
            <label>Date d'entrée</label>
            <span>{{ $or->date_entree->format('d/m/Y') }}@if($or->heure_entree) à {{ substr($or->heure_entree,0,5) }}@endif</span>
        </div>
        <div class="ib">
            <label>Urgence</label>
            <span style="{{ $or->urgence === 'tres_urgent' ? 'color:#dc2626;font-weight:700;' : ($or->urgence === 'urgent' ? 'color:#ea580c;font-weight:700;' : '') }}">
                {{ $or->getUrgenceLabel() }}
            </span>
        </div>
        <div class="ib">
            <label>Type OR</label>
            <span>{{ $or->getTypeLabel() }}</span>
        </div>
    </div>
    <div class="g4">
        <div class="ib">
            <label>Kilométrage entrée</label>
            <span>{{ number_format($or->kilometrage_entree) }} km</span>
        </div>
        <div class="ib">
            <label>Carburant</label>
            <span>{{ strtoupper($or->niveau_carburant) }}</span>
        </div>
        <div class="ib">
            <label>Client</label>
            <span>{{ $or->client->nom_complet }}</span>
        </div>
        <div class="ib">
            <label>Immatriculation</label>
            <span style="font-family:'Courier New',monospace;font-size:11pt;">{{ $or->vehicule->immatriculation }}</span>
        </div>
    </div>

    {{-- Véhicule --}}
    <div class="g2" style="margin-bottom:5px;">
        <div class="ib">
            <label>Véhicule</label>
            <span>{{ $or->vehicule->marque }} {{ $or->vehicule->modele }} @if($or->vehicule->annee)({{ $or->vehicule->annee }})@endif</span>
        </div>
        <div class="ib">
            <label>Motif de l'intervention</label>
            <span>{{ $or->motif_entree }}</span>
        </div>
    </div>

    @php
        $tousLesDevis = $or->allDevis;
        $toutesLignes = $tousLesDevis->flatMap(fn($d) => $d->lignes);
        $lignesMO     = $toutesLignes->where('type', 'main_oeuvre')->values();
        $lignesPce    = $toutesLignes->where('type', 'piece')->values();
        $lignesAutr   = $toutesLignes->whereNotIn('type', ['main_oeuvre','piece'])->values();

        // Lignes vides : aucune si des données existent, 2 seulement si pas de devis
        $videsMO  = $lignesMO->count() > 0 ? 0 : 2;
    @endphp

    {{-- ── 1. Travaux / Main d'œuvre ── --}}
    <div class="st">
        Travaux à effectuer — Main d'œuvre
        @if($tousLesDevis->isNotEmpty())
        <span style="font-size:7pt;color:#5B3FAF;font-weight:400;text-transform:none;letter-spacing:0;margin-left:6px;">
            ({{ $tousLesDevis->pluck('numero')->implode(' + ') }})
        </span>
        @endif
    </div>
    <table>
        <thead>
            <tr>
                <th class="num">#</th>
                <th>Désignation de l'opération</th>
                <th style="width:76px;text-align:center;">H. prévues</th>
                <th style="width:44px;text-align:center;">Fait ✓</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lignesMO as $i => $l)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td style="font-weight:600;">{{ $l->designation }}</td>
                <td class="tc" style="font-family:'Courier New',monospace;font-weight:700;color:#1d4ed8;">
                    {{ number_format($l->quantite,1,'h','') }}h
                </td>
                <td class="cb">☐</td>
            </tr>
            @endforeach
            @foreach($lignesAutr as $j => $l)
            <tr>
                <td class="num" style="color:#bbb;">{{ $lignesMO->count() + $j + 1 }}</td>
                <td>{{ $l->designation }} <span style="font-size:7.5pt;color:#999;">({{ $l->getTypeLabel() }})</span></td>
                <td class="tc" style="color:#999;">—</td>
                <td class="cb">☐</td>
            </tr>
            @endforeach
            @for($k = 0; $k < $videsMO; $k++)
            <tr>
                <td class="num" style="color:#ddd;">{{ $lignesMO->count() + $lignesAutr->count() + $k + 1 }}</td>
                <td></td><td></td>
                <td class="cb" style="color:#ddd;">☐</td>
            </tr>
            @endfor
        </tbody>
    </table>

    {{-- ── 2. Pièces à changer + 3. Contrôle + 4. Nettoyage ── --}}

    {{-- Pièces à changer (pleine largeur) — masqué si aucune pièce --}}
    @if($lignesPce->isNotEmpty())
    <div class="st">Pièces à changer</div>
    <table>
        <thead>
            <tr>
                <th class="num">#</th>
                <th>Désignation de la pièce</th>
                <th style="width:100px;">Référence</th>
                <th style="width:42px;text-align:center;">Qté</th>
                <th style="width:44px;text-align:center;">Posé ✓</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lignesPce as $i => $l)
            <tr style="background:{{ $i % 2 ? '#fafafa' : '#fff' }};">
                <td class="num">{{ $i + 1 }}</td>
                <td style="font-weight:600;">{{ $l->designation }}</td>
                <td class="ref">{{ $l->reference ?: '—' }}</td>
                <td class="tc" style="font-weight:700;">{{ (int) $l->quantite }}</td>
                <td class="cb">☐</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Contrôle + Nettoyage côte à côte — uniquement pour un entretien périodique
         dont le barème constructeur a résolu des points d'inspection/nettoyage --}}
    @php
        $controles  = $tachesEntretien?->get('inspecter', collect()) ?? collect();
        $nettoyages = $tachesEntretien?->get('nettoyer', collect()) ?? collect();
    @endphp
    @if($controles->isNotEmpty() || $nettoyages->isNotEmpty())
    <div class="two-col">

        {{-- ── 3. Points à contrôler ── --}}
        @if($controles->isNotEmpty())
        <div>
            <div class="st" style="margin-top:5px;">
                Points à contrôler
                <span style="font-size:6.5pt;color:#5B3FAF;font-weight:400;text-transform:none;">(barème {{ $or->vehicule->typeMoteur->libelle }} — {{ number_format($or->entretien_km_seuil) }} km)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Point de contrôle</th>
                        <th style="width:38px;text-align:center;">OK ✓</th>
                        <th style="width:38px;text-align:center;">NOK ✗</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($controles as $t)
                    <tr>
                        <td style="font-size:8pt;">{{ $t->designation }}</td>
                        <td class="cb" style="font-size:11pt;">☐</td>
                        <td class="cb" style="font-size:11pt;">☐</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- ── 4. Points à nettoyer ── --}}
        @if($nettoyages->isNotEmpty())
        <div>
            <div class="st" style="margin-top:5px;">
                Points à nettoyer
                <span style="font-size:6.5pt;color:#5B3FAF;font-weight:400;text-transform:none;">(barème {{ $or->vehicule->typeMoteur->libelle }} — {{ number_format($or->entretien_km_seuil) }} km)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Zone à nettoyer</th>
                        <th style="width:44px;text-align:center;">Fait ✓</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($nettoyages as $t)
                    <tr>
                        <td style="font-size:8pt;">{{ $t->designation }}</td>
                        <td class="cb" style="font-size:11pt;">☐</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
    @endif

    {{-- ── 5. Points à lubrifier (barème constructeur uniquement) ── --}}
    @if($tachesEntretien && $tachesEntretien->has('lubrifier'))
    <div class="st" style="margin-top:5px;">Points à lubrifier</div>
    <table>
        <thead>
            <tr>
                <th>Point de lubrification</th>
                <th style="width:44px;text-align:center;">Fait ✓</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tachesEntretien->get('lubrifier') as $t)
            <tr>
                <td style="font-size:8pt;">{{ $t->designation }}</td>
                <td class="cb" style="font-size:11pt;">☐</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ── Chronométrage ── --}}
    <div class="st" style="margin-top:6px;">Chronométrage</div>
    <div class="chrono-grid">
        <div class="c-box"><label>Heure de début</label><div class="c-line"></div></div>
        <div class="c-box"><label>Heure de fin</label><div class="c-line"></div></div>
        <div class="c-box"><label>Durée réelle (H)</label><div class="c-line"></div></div>
        <div class="c-box"><label>Durée standard (H)</label><div class="c-line"></div></div>
    </div>

    {{-- ── Observations ── --}}
    <div class="st" style="margin-top:6px;">Observations / Remarques du technicien</div>
    <div class="obs-lines">
        @for($i = 0; $i < 3; $i++)<div class="obs-line"></div>@endfor
    </div>

    {{-- ── Signatures ── --}}
    <div class="st" style="margin-top:6px;">Validation</div>
    <div class="sig-grid">
        <div class="sig-box">
            <label>Technicien assigné</label>
            <div class="sig-name">{{ $or->technicien?->name ?? '—' }}</div>
            <div class="sig-line"></div>
            <div class="sig-hint">Signature</div>
        </div>
        <div class="sig-box">
            <label>Chef atelier</label>
            <div class="sig-name">{{ $or->chef?->name ?? '—' }}</div>
            <div class="sig-line"></div>
            <div class="sig-hint">Visa</div>
        </div>
        <div class="sig-box">
            <label>Contrôle qualité</label>
            <div class="sig-name">&nbsp;</div>
            <div class="sig-line"></div>
            <div class="sig-hint">Visa</div>
        </div>
    </div>

    <div class="footer">
        <span>STCD Motors — Feuille de Travail {{ $or->numero }}</span>
        <span>Imprimé le {{ now()->format('d/m/Y à H:i') }}</span>
    </div>

</div>
</div>

<script>
// Réduit (jamais n'agrandit) le contenu pour qu'il tienne toujours sur une seule
// page A4, quel que soit le nombre de lignes de l'OR (main d'œuvre, pièces,
// contrôles/nettoyage/lubrification). L'origine "top center" fait rétrécir le
// contenu symétriquement autour de son centre horizontal, ce qui le garde centré
// sans avoir à recalculer de décalage.
function ajusterPourUnePage() {
    var outer   = document.getElementById('page-outer');
    var content = document.getElementById('page-content');
    if (!outer || !content) return;

    content.style.transform = 'none';
    var hauteurMax = outer.clientHeight;
    var hauteurContenu = content.scrollHeight;

    if (hauteurContenu > hauteurMax) {
        var echelle = hauteurMax / hauteurContenu;
        content.style.transformOrigin = 'top center';
        content.style.transform = 'scale(' + echelle + ')';
    }
}

window.addEventListener('load', function () {
    ajusterPourUnePage();
@if(!request('apercu'))
    setTimeout(window.print, 400);
@endif
});
window.addEventListener('beforeprint', ajusterPourUnePage);
@if(!request('apercu'))
window.onafterprint = function () { window.close(); };
@endif
</script>
</body>
</html>
