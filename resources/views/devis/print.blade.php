<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis {{ $devis->numero }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 210mm; background: #fff; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; }
        .page { width: 210mm; min-height: 297mm; padding: 10mm 13mm 8mm; }
        @media screen {
            html, body { width: 100%; background: #d1d5db; }
            .page { margin: 55px auto 40px; box-shadow: 0 4px 24px rgba(0,0,0,.18); }
        }

        /* ── En-tête ── */
        .header { display: flex; justify-content: space-between; align-items: flex-start;
                  border-bottom: 3px double #111; padding-bottom: 8px; margin-bottom: 10px; }
        .header-left  { display: flex; align-items: flex-start; gap: 10px; }
        .header-logo  { height: 56px; width: auto; flex-shrink: 0; }
        .company-name { font-size: 13.5pt; font-weight: 900; color: #000; line-height: 1.2; }
        .company-sub  { font-size: 8.5pt; font-style: italic; color: #333; margin-top: 2px; }
        .company-tag  { font-size: 7pt; color: #555; margin-top: 4px;
                        border-top: 1px solid #bbb; padding-top: 3px; }
        .header-date  { text-align: right; font-size: 10pt; font-weight: 700; white-space: nowrap; padding-top: 6px; }

        /* ── Titre ── */
        .doc-title { text-align: center; margin: 12px 0 14px; }
        .doc-title-inner {
            display: inline-block;
            font-size: 16pt; font-weight: 900;
            text-decoration: underline;
            text-underline-offset: 4px;
            letter-spacing: 1px;
        }

        /* ── Section client / véhicule ── */
        .cv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 12px; }
        .cv-block { font-size: 9.5pt; line-height: 1.7; }
        .cv-block .doit { font-weight: 900; text-decoration: underline; font-size: 10.5pt; }
        .cv-block .row  { display: flex; gap: 4px; }
        .cv-block .lbl  { font-weight: 700; white-space: nowrap; }

        /* ── Tableau ── */
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }

        .thead-row th {
            background: #e8a020;
            color: #000;
            font-weight: 900;
            font-size: 8.5pt;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #c8880a;
            text-align: center;
        }
        .thead-row th.left { text-align: left; }

        /* Sous-titre groupe (ex: Révision de 23 897km) */
        tr.group-header td {
            background: #f0f0f0;
            font-weight: 700;
            font-size: 8.5pt;
            padding: 4px 6px;
            border: 1px solid #ccc;
            font-style: italic;
        }
        /* Sous-section (Pièces détachées / Main d'Œuvre) */
        tr.section-header td {
            background: #fff;
            font-weight: 700;
            font-size: 9pt;
            padding: 3px 6px 2px;
            border: 1px solid #ccc;
            text-decoration: underline;
        }
        /* Ligne normale */
        tr.data-row td {
            padding: 4px 6px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        /* Sous-total pièces */
        tr.subtotal-row td {
            padding: 4px 6px;
            border: 1px solid #ccc;
            font-weight: 700;
            background: #fafafa;
        }
        /* Total général */
        tr.total-row td {
            padding: 5px 6px;
            border: 2px solid #111;
            font-weight: 900;
            font-size: 10pt;
            background: #e8a020;
        }

        .tc  { text-align: center; }
        .tr  { text-align: right; }
        .tl  { text-align: left; }
        .bold { font-weight: 700; }

        /* ── Titre de section dans tableau (visuellement hors grille) ── */
        tr.section-sep-row td {
            border-left: none !important;
            border-right: none !important;
            border-bottom: none !important;
            border-top: 6px solid #fff !important;
            padding: 0;
            background: #fff;
        }
        tr.section-sep-row .sep-inner {
            display: block;
            background: #f0f0f0;
            border-left: 4px solid #c07010;
            padding: 4px 10px;
            font-size: 9.5pt;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* ── Mentions bas de tableau ── */
        .mentions { font-size: 8pt; margin-top: 0; }
        .mentions td { border: 1px solid #ccc; padding: 4px 7px; }
        .mentions td.italic { font-style: italic; }

        /* ── Signatures ── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .sig-table td {
            width: 50%; border: 1px solid #aaa;
            padding: 8px 10px; min-height: 70px; height: 80px;
            vertical-align: top; font-size: 9.5pt; font-weight: 700; text-align: center;
        }

        /* ── Pied de page ── */
        .footer-bar {
            margin-top: 18px;
            border-top: 2px solid #aaa;
            padding-top: 5px;
            font-size: 7pt;
            color: #333;
            text-align: center;
            line-height: 1.6;
        }

        /* ── Boutons écran ── */
        .btn-bar { position: fixed; top: 14px; left: 50%; transform: translateX(-50%);
                   display: flex; gap: 10px; z-index: 99; }
        .print-btn { background: #c07010; color: #fff; border: none; padding: 8px 22px;
                     border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .back-btn  { background: #dc2626; color: #fff; border: none; padding: 8px 22px;
                     border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
        @media print { .btn-bar { display: none; } }
    </style>
</head>
<body>

<div class="btn-bar">
    @if(request('apercu'))
    <button onclick="window.close()" class="back-btn">← Fermer</button>
    @else
    <a href="{{ route('devis.show', $devis) }}" class="back-btn">← Retour</a>
    @endif
    <button onclick="window.print()" class="print-btn">🖨 Imprimer</button>
</div>

@php
    $or      = $devis->parent;
    $client  = $or->client;
    $vehicule = $or->vehicule;

    $lignesPce = $devis->lignes->where('type', 'piece')->values();
    $lignesMO  = $devis->lignes->where('type', 'main_oeuvre')->values();
    $lignesAut = $devis->lignes->whereNotIn('type', ['piece','main_oeuvre'])->values();

    $totalPieces  = $lignesPce->sum('total_ht') + $lignesAut->sum('total_ht');

    /* Remise : afficher la colonne uniquement si au moins une ligne a une remise > 0 */
    $hasRemise     = $devis->lignes->where('remise', '>', 0)->isNotEmpty();
    $totalBrut     = $devis->lignes->sum(fn($l) => round($l->quantite * $l->prix_unitaire, 2));
    $totalRemise   = round($totalBrut - $devis->montant_ht, 2);

    /* ── Montant en lettres (FDJ, sans centimes) ── */
    function nombreEnLettres(int $n): string {
        if ($n === 0) return 'ZÉRO';
        $u = ['','UN','DEUX','TROIS','QUATRE','CINQ','SIX','SEPT','HUIT','NEUF',
              'DIX','ONZE','DOUZE','TREIZE','QUATORZE','QUINZE','SEIZE','DIX-SEPT','DIX-HUIT','DIX-NEUF'];
        $d = ['','','VINGT','TRENTE','QUARANTE','CINQUANTE','SOIXANTE','SOIXANTE-DIX','QUATRE-VINGT','QUATRE-VINGT-DIX'];
        $centainesFn = function(int $c) use ($u, $d): string {
            $s = '';
            if ($c >= 100) { $h = intdiv($c, 100); $s = ($h > 1 ? $u[$h].' ' : '').'CENT'; $c %= 100; if ($c) $s .= ' '; else if ($h > 1) $s .= 'S'; }
            if ($c >= 20) { $dix = intdiv($c, 10); $un = $c % 10;
                if ($dix == 7 || $dix == 9) { $s .= $d[$dix]; $un += 10; if ($un) $s .= '-'.$u[$un]; }
                else { $s .= $d[$dix]; if ($dix == 8 && !$un) $s .= 'S'; if ($un) $s .= ($un == 1 && $dix != 8 ? ' ET ' : '-').$u[$un]; }
            } elseif ($c > 0) { $s .= $u[$c]; }
            return $s;
        };
        $result = '';
        if ($n >= 1000000) { $m = intdiv($n,1000000); $result .= ($m > 1 ? $centainesFn($m).' ' : '').'MILLION'.($m>1?'S':'').' '; $n %= 1000000; }
        if ($n >= 1000)    { $k = intdiv($n,1000);    $result .= ($k > 1 ? $centainesFn($k).' ' : '').'MILLE '; $n %= 1000; }
        if ($n > 0)        { $result .= $centainesFn($n); }
        return rtrim($result);
    }
    $montantLettres = nombreEnLettres((int) round($devis->montant_ttc));
@endphp

<div class="page">

    {{-- ── En-tête ── --}}
    <div class="header">
        <div class="header-left">
            <img src="{{ asset('logo.jpg') }}" alt="STCD" class="header-logo">
            <div>
                <div class="company-name">SOCIÉTÉ DE TRANSPORT EN COMMUN DE DJIBOUTI</div>
                <div class="company-sub">Le professionnalisme au quotidien !</div>
                <div class="company-tag">TRANSPORT PUBLIC - SCOLAIRE - TOURISTIQUE - PERSONNEL - LOCATION &amp; LEASING DE VÉHICULES - VENTES &amp; MAINTENANCE DES VÉHICULES</div>
            </div>
        </div>
        <div class="header-date">Djibouti, le {{ $devis->created_at->format('d/m/Y') }}</div>
    </div>

    {{-- ── Titre ── --}}
    <div class="doc-title">
        <span class="doc-title-inner">DEVIS N° {{ $devis->numero }}</span>
    </div>

    {{-- ── Client & Véhicule ── --}}
    <div class="cv-grid">
        <div class="cv-block">
            <div class="doit">DOIT : {{ strtoupper($client->nom_complet) }}</div>
            @if($client->nif)
            <div class="row"><span class="lbl">CODE NIF :</span> {{ $client->nif }}</div>
            @endif
            <div class="row"><span class="lbl">N° BON DE COMMANDE :</span></div>
            <div class="row"><span class="lbl">KM :</span> {{ number_format($or->kilometrage_entree, 0, ',', ' ') }} km</div>
            @if($vehicule->date_mise_circulation)
            <div class="row"><span class="lbl">Date de mise en circulation :</span> {{ $vehicule->date_mise_circulation->format('d/m/Y') }}</div>
            @endif
        </div>
        <div class="cv-block">
            <div class="row"><span class="lbl">N° de véhicule :</span> {{ $vehicule->immatriculation }}</div>
            <div class="row"><span class="lbl">Type de véhicule :</span> {{ $vehicule->modele ?? $vehicule->designation }}</div>
            <div class="row"><span class="lbl">Numéro de Téléphone :</span> {{ $client->telephone }}</div>
        </div>
    </div>

    {{-- ── Tableau unique (alignement garanti) ── --}}
    @php
        /* 8 colonnes avec remise, 7 sans */
        $nbCols = $hasRemise ? 8 : 7;
    @endphp
    <table style="table-layout:fixed;">
        <colgroup>
            <col style="width:70px;">
            <col style="width:80px;">
            <col>{{-- DÉSIGNATION : largeur auto --}}
            <col style="width:36px;">
            <col style="width:44px;">
            <col style="width:84px;">
            @if($hasRemise)<col style="width:52px;">@endif
            <col style="width:150px;">
        </colgroup>
        <thead>
            <tr class="thead-row">
                <th>DATE</th>
                <th>RÉFÉRENCE</th>
                <th class="left">DÉSIGNATION</th>
                <th class="tc">QTÉ</th>
                <th class="tc">UNITÉ</th>
                <th class="tr">Prix Unitaire</th>
                @if($hasRemise)<th class="tc">REMISE</th>@endif
                <th class="tr">MONTANT</th>
            </tr>
        </thead>
        <tbody>

            {{-- ─ Section Pièces détachées ─ --}}
            @if($lignesPce->isNotEmpty() || $lignesAut->isNotEmpty())
            <tr class="section-sep-row">
                <td colspan="{{ $nbCols }}"><span class="sep-inner">Pièces détachées</span></td>
            </tr>
            @foreach($lignesPce->merge($lignesAut) as $l)
            <tr class="data-row">
                <td class="tc"></td>
                <td style="font-family:monospace;font-size:8pt;color:#555;">{{ $l->reference ?: '' }}</td>
                <td>{{ $l->designation }}</td>
                <td class="tc bold">{{ (int) $l->quantite }}</td>
                <td class="tc">PCS</td>
                <td class="tr">{{ number_format($l->prix_unitaire, 0, ',', ' ') }}</td>
                @if($hasRemise)
                <td class="tc" @if($l->remise > 0) style="color:#c00;font-weight:700;" @else style="color:#999;" @endif>
                    {{ $l->remise > 0 ? number_format($l->remise, 0).' %' : '—' }}
                </td>
                @endif
                <td class="tr bold">{{ number_format($l->total_ht, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="{{ $nbCols - 1 }}" class="tr" style="padding-right:8px;white-space:nowrap;">Total Général des Pièces</td>
                <td class="tr bold">{{ number_format($totalPieces, 0, ',', ' ') }}</td>
            </tr>
            @endif

            {{-- ─ Section Main d'Œuvre ─ --}}
            @if($lignesMO->isNotEmpty())
            <tr class="section-sep-row">
                <td colspan="{{ $nbCols }}"><span class="sep-inner">Main d'Œuvre</span></td>
            </tr>
            @foreach($lignesMO as $l)
            <tr class="data-row">
                <td class="tc">{{ $devis->created_at->format('d/m/Y') }}</td>
                <td></td>
                <td>{{ $l->designation }}</td>
                <td class="tc">{{ $l->quantite > 0 ? number_format($l->quantite, 0, ',', ' ') : '' }}</td>
                <td class="tc">{{ $l->quantite > 0 ? 'H' : '' }}</td>
                <td class="tr">{{ $l->prix_unitaire > 0 ? number_format($l->prix_unitaire, 0, ',', ' ') : '' }}</td>
                @if($hasRemise)
                <td class="tc" @if($l->remise > 0) style="color:#c00;font-weight:700;" @else style="color:#999;" @endif>
                    {{ $l->remise > 0 ? number_format($l->remise, 0).' %' : '—' }}
                </td>
                @endif
                <td class="tr bold">{{ number_format($l->total_ht, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            @endif

            {{-- ─ Remise globale (uniquement si au moins une ligne a une remise) ─ --}}
            @if($hasRemise)
            <tr class="subtotal-row">
                <td colspan="{{ $nbCols - 1 }}" class="tr" style="padding-right:8px;color:#555;white-space:nowrap;">Sous-total HT brut</td>
                <td class="tr" style="white-space:nowrap;">{{ number_format($totalBrut, 0, ',', ' ') }}</td>
            </tr>
            <tr class="subtotal-row">
                <td colspan="{{ $nbCols - 1 }}" class="tr" style="padding-right:8px;color:#c00;font-weight:700;white-space:nowrap;">Remise totale</td>
                <td class="tr" style="color:#c00;font-weight:700;white-space:nowrap;">- {{ number_format($totalRemise, 0, ',', ' ') }}</td>
            </tr>
            @endif

            {{-- ─ Total HT net + TVA — toujours affichés (pas seulement s'il y a une remise),
                 sinon le saut de "pièces + main d'œuvre" au "TOTAL GÉNÉRAL" TTC est incompris ─ --}}
            <tr class="subtotal-row">
                <td colspan="{{ $nbCols - 1 }}" class="tr" style="padding-right:8px;white-space:nowrap;">Total HT net</td>
                <td class="tr bold" style="white-space:nowrap;">{{ number_format($devis->montant_ht, 0, ',', ' ') }}</td>
            </tr>
            @if($devis->taux_tva > 0)
            <tr class="subtotal-row">
                <td colspan="{{ $nbCols - 1 }}" class="tr" style="padding-right:8px;color:#555;white-space:nowrap;">TVA ({{ (int)$devis->taux_tva }} %)</td>
                <td class="tr" style="white-space:nowrap;">{{ number_format($devis->montant_tva, 0, ',', ' ') }}</td>
            </tr>
            @endif

            {{-- ─ Total général ─ --}}
            <tr class="total-row">
                <td colspan="{{ $nbCols - 1 }}" class="tr" style="white-space:nowrap;">TOTAL GÉNÉRAL</td>
                <td class="tr" style="white-space:nowrap;">{{ number_format($devis->montant_ttc, 0, ',', ' ') }} FDJ</td>
            </tr>

        </tbody>
    </table>

    {{-- ── Mentions ── --}}
    <table class="mentions">
        <tr>
            <td>Validité du devis : 7 jours à compter de sa date d'émission</td>
        </tr>
        <tr>
            <td>Modes de paiement acceptés : Comptant, Paiement en ligne <strong>(WAFI : 9452 / CAC : 10507)</strong></td>
        </tr>
        <tr>
            <td class="italic">
                Arrêter le présent devis à la somme de :
                <strong>{{ $montantLettres }} Francs Djiboutiens.</strong>
            </td>
        </tr>
    </table>

    {{-- ── Signatures ── --}}
    <table class="sig-table">
        <tr>
            <td>Accusée de Réception du Client</td>
            <td>Cachet de la Société</td>
        </tr>
        <tr>
            <td style="height:80px;vertical-align:bottom;font-weight:400;font-size:8pt;color:#999;padding-bottom:6px;">Signature</td>
            <td style="height:80px;"></td>
        </tr>
    </table>

    {{-- ── Pied de page ── --}}
    <div class="footer-bar">
        RÉPUBLIQUE DE DJIBOUTI - WAAFI RESIDENCES LA CORNICHE - 21 35 67 73 / 21 35 30 09 - BP : 1061<br>
        EMAIL : contact@stcd.dj &nbsp;—&nbsp; NUMÉRO DE COMPTE BANCAIRE : 213-10181226 SALAAM AFRICA BANK
    </div>

</div>

<script>
@if(!request('apercu'))
window.addEventListener('load', function () { setTimeout(window.print, 400); });
window.onafterprint = function () { window.close(); };
@endif
</script>
</body>
</html>
