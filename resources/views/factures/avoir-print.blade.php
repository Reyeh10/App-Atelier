<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Avoir {{ $avoir->numero }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { width: 210mm; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; background: #fff; }
        .page { width: 210mm; min-height: 297mm; padding: 10mm 14mm 12mm; }
        @media screen {
            html, body { width: 100%; background: #d1d5db; }
            .page { margin: 55px auto 40px; box-shadow: 0 4px 24px rgba(0,0,0,.18); }
        }

        /* ── En-tête ── */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
        .company-logo { height: 56px; width: auto; }
        .company-name { font-size: 11pt; font-weight: 900; text-transform: uppercase; color: #111; margin-top: 4px; }
        .company-sub  { font-size: 7.5pt; color: #faf1f1; font-style: italic; }
        .company-addr { font-size: 7.5pt; color: #f7f7f7; margin-top: 2px; }

        .doc-meta { text-align: right; }
        .doc-city  { font-size: 9pt; color: #333; margin-bottom: 4px; }
        .doc-title { font-size: 18pt; font-weight: 900; letter-spacing: 1px; color: #111; }
        .doc-num   { font-size: 13pt; font-weight: 900; color: #111; }

        hr.thick { border: none; border-top: 2px solid #111; margin: 6px 0; }
        hr.thin  { border: none; border-top: 1px solid #ccc; margin: 4px 0; }

        /* ── Bloc client ── */
        .client-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 8px; }
        .client-left, .client-right { font-size: 9pt; }
        .client-row  { display: flex; margin-bottom: 2px; }
        .client-lbl  { font-weight: 700; min-width: 130px; flex-shrink: 0; }
        .client-val  { flex: 1; }

        /* ── Tableau ── */
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 0; }
        thead tr { background: #f0f0f0; }
        th { padding: 5px 6px; text-align: left; font-size: 8.5pt; font-weight: 700; border: 1px solid #ccc; }
        th.c, td.c { text-align: center; }
        th.r, td.r { text-align: right; }
        td { padding: 4px 6px; border: 1px solid #ddd; vertical-align: middle; }

        tr.section-sep-row td { border-left:none!important; border-right:none!important; border-bottom:none!important; border-top:6px solid #fff!important; padding:0; background:#fff; }
        tr.section-sep-row .sep-inner { display:block; background:#f0f0f0; border-left:4px solid #333; padding:4px 10px; font-size:9.5pt; font-weight:700; letter-spacing:0.5px; }
        .section-hdr { background: #e8e8e8; font-weight: 700; font-size: 8.5pt; padding: 3px 6px; border: 1px solid #ccc; text-transform: uppercase; letter-spacing: 0.5px; }
        .subtotal-row td { background: #f8f8f8; font-weight: 700; border-top: 1.5px solid #999; }

        /* ── Totaux bas ── */
        .totaux-zone { margin-top: 4px; display: flex; justify-content: flex-end; }
        .totaux-box  { min-width: 240px; border: 1px solid #ccc; }
        .totaux-row  { display: flex; justify-content: space-between; padding: 3px 8px; font-size: 9.5pt; border-bottom: 1px solid #ddd; }
        .totaux-row:last-child { border-bottom: none; }
        .totaux-row.timbre { font-weight: 700; }
        .totaux-row.total-final { background: #111; color: #fff; font-weight: 900; font-size: 11pt; padding: 5px 8px; }

        /* ── Lettres ── */
        .montant-lettres { margin-top: 6px; border: 1px solid #ccc; padding: 5px 8px; font-size: 8.5pt; font-style: italic; }
        .montant-lettres strong { font-style: normal; }

        /* ── Référence facture annulée ── */
        .avoir-ref { border: 1.5px solid #c00; padding: 5px 8px; margin: 6px 0 8px; font-size: 9pt; line-height: 1.5; }

        /* ── Signatures ── */
        .sig-zone { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
        .sig-box  { border: 1px solid #ccc; padding: 6px 10px; min-height: 60px; }
        .sig-label { font-size: 8pt; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }

        /* ── Pied ── */
        .footer { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7pt; color: #666; text-align: center; }

        /* ── Boutons ── */
        .btn-bar { position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
                   display: flex; gap: 10px; z-index: 99; }
        .print-btn { background: #111; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .back-btn  { background: #dc2626; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
        @media print { .btn-bar { display: none; } }
    </style>
</head>
<body>
<div class="btn-bar">
    @if(request('apercu'))
    <button onclick="window.close()" class="back-btn">← Fermer</button>
    @else
    <a href="{{ route('factures.show', $avoir->facture) }}" class="back-btn">← Retour</a>
    @endif
    <button onclick="window.print()" class="print-btn">🖨 Imprimer</button>
</div>

<div class="page">

    {{-- ── En-tête ── --}}
    <div class="header">
        <div>
            <img src="{{ asset('logo.jpg') }}" alt="STCD" class="company-logo">
            <div class="company-name">Société de Transport en Commun de Djibouti</div>
            <div class="company-sub">Le professionnalisme en question</div>
        </div>
        <div class="doc-meta">
            <div class="doc-city">Djibouti, le {{ $avoir->date_emission->format('d/m/Y') }}</div>
            <div class="doc-title">AVOIR N°</div>
            <div class="doc-num">{{ $avoir->numero }}</div>
        </div>
    </div>

    <hr class="thick">

    {{-- ── Bloc client / véhicule ── --}}
    @php
        // Avoir d'une facture flotte (livraison de pièces sans OR) : véhicule et BC portés par la facture
        $or  = $avoir->ordreReparation;
        $veh = $or ? $or->vehicule : $avoir->facture?->vehicule;
        $bc  = $or ? $or->devis?->bonCommande : $avoir->facture?->livraisonFlotte?->bonCommande;
    @endphp
    <div class="client-grid" style="margin-top:8px;">
        <div class="client-left">
            <div class="client-row">
                <span class="client-lbl">DOIT :</span>
                <span class="client-val" style="font-weight:700;">{{ $avoir->payeur_nom }}</span>
            </div>
            @if(!$avoir->marque_garantie_id && $avoir->client->nif)
            <div class="client-row">
                <span class="client-lbl">CODE NIF :</span>
                <span class="client-val">{{ $avoir->client->nif }}</span>
            </div>
            @endif
            @if(!$avoir->marque_garantie_id && $avoir->client->rc)
            <div class="client-row">
                <span class="client-lbl">RC :</span>
                <span class="client-val">{{ $avoir->client->rc }}</span>
            </div>
            @endif
            <div class="client-row">
                <span class="client-lbl">KM :</span>
                <span class="client-val">{{ number_format($veh->kilometrage ?? 0, 0, ',', ' ') }}</span>
            </div>
            @if($bc)
            <div class="client-row">
                <span class="client-lbl">N° BON DE COMMANDE :</span>
                <span class="client-val" style="font-weight:700;">{{ $bc->numero }}</span>
            </div>
            @endif
        </div>
        <div class="client-right">
            <div class="client-row">
                <span class="client-lbl">N° du véhicule :</span>
                <span class="client-val" style="font-weight:700;font-family:monospace;">{{ $veh?->immatriculation ?? '—' }}</span>
            </div>
            <div class="client-row">
                <span class="client-lbl">Type de service :</span>
                <span class="client-val">{{ $or ? $or->getServiceLabel() : 'Livraison de pièces' }}</span>
            </div>
            {{-- "Propriétaire" n'est utile que si le payeur (DOIT, ci-contre) diffère du
                 propriétaire du véhicule — cas d'une facture adressée au compte garantie
                 constructeur. Sinon DOIT affiche déjà le même nom, pas besoin de le répéter. --}}
            @if($avoir->marque_garantie_id)
            <div class="client-row">
                <span class="client-lbl">Propriétaire :</span>
                <span class="client-val">{{ $avoir->client->nom_complet }}</span>
            </div>
            @endif
            @if($avoir->date_echeance)
            <div class="client-row">
                <span class="client-lbl">Échéance :</span>
                <span class="client-val" style="font-weight:700;color:#c00;">{{ $avoir->date_echeance->format('d/m/Y') }}</span>
            </div>
            @endif
        </div>
    </div>

    <hr class="thin">

    {{-- ── Facture annulée par cet avoir ── --}}
    <div class="avoir-ref">
        <div><strong>Avoir sur la facture N° {{ $avoir->facture->numero }}</strong> du {{ $avoir->facture->date_emission->format('d/m/Y') }}
            — annule cette facture en totalité{{ $avoir->factureRemplacement ? ', remplacée par la facture N° ' . $avoir->factureRemplacement->numero : '' }}.</div>
        <div>Motif : {{ $avoir->motif }}</div>
        @if($avoir->montant_deja_paye > 0)
        <div>Montant déjà réglé sur la facture : {{ number_format($avoir->montant_deja_paye, 0, ',', ' ') }} FDJ
            @if($avoir->montant_a_rembourser > 0)
            — à rembourser : <strong>{{ number_format($avoir->montant_a_rembourser, 0, ',', ' ') }} FDJ</strong>{{ $avoir->rembourse_le ? ' (remboursé le ' . $avoir->rembourse_le->format('d/m/Y') . ' — ' . $avoir->getModeRemboursementLabel() . ')' : '' }}
            @else
            — reporté sur la facture N° {{ $avoir->factureRemplacement?->numero }}
            @endif
        </div>
        @endif
    </div>

    {{-- ── Tableau des prestations ── --}}
    @php
        $pieces      = $avoir->lignes->where('type', 'piece');
        $mo          = $avoir->lignes->whereIn('type', ['main_oeuvre', 'forfait', 'autre']);
        $totalPieces = $pieces->sum('total_ht');
        $totalMo     = $mo->sum('total_ht');
        $dateFacture = $avoir->date_emission->format('d/m/Y');

        /* Remise : visible uniquement si au moins une ligne en a une */
        $hasRemiseF  = $avoir->lignes->where('remise', '>', 0)->isNotEmpty();
        $totalBrutF  = $avoir->lignes->sum(fn($l) => round($l->quantite * $l->prix_unitaire, 2));
        $totalRemiseF = round($totalBrutF - $avoir->montant_ht, 2);
        $colSpanF    = $hasRemiseF ? 7 : 6;
    @endphp

    @php $nbColsF = $hasRemiseF ? 8 : 7; @endphp

    <table style="table-layout:fixed;">
        <colgroup>
            <col style="width:65px;">
            <col style="width:80px;">
            <col>
            <col style="width:38px;">
            <col style="width:40px;">
            <col style="width:82px;">
            @if($hasRemiseF)<col style="width:52px;">@endif
            <col style="width:150px;">
        </colgroup>
        <thead>
            <tr>
                <th>DATE</th>
                <th>REFERENCE</th>
                <th>DESIGNATION</th>
                <th class="c">QTE</th>
                <th class="c">UNITE</th>
                <th class="r">PRIX UNIT.</th>
                @if($hasRemiseF)<th class="c">REMISE</th>@endif
                <th class="r">MONTANT</th>
            </tr>
        </thead>
        <tbody>

            {{-- Section Pièces détachées --}}
            @if($pieces->count())
            <tr class="section-sep-row">
                <td colspan="{{ $nbColsF }}"><span class="sep-inner">Pièces détachées</span></td>
            </tr>
            @foreach($pieces as $l)
            <tr>
                <td style="font-size:8pt;color:#555;">{{ $dateFacture }}</td>
                <td style="font-family:monospace;font-size:8.5pt;">{{ $l->reference ?: '' }}</td>
                <td style="font-weight:600;">{{ $l->designation }}</td>
                <td class="c">{{ rtrim(rtrim(number_format($l->quantite, 2, ',', ' '), '0'), ',') }}</td>
                <td class="c" style="font-size:8.5pt;">{{ $l->unite ?: 'PCS' }}</td>
                <td class="r">{{ number_format($l->prix_unitaire, 0, ',', ' ') }}</td>
                @if($hasRemiseF)
                <td class="c" @if($l->remise > 0) style="color:#c00;font-weight:700;" @else style="color:#999;" @endif>
                    {{ $l->remise > 0 ? number_format($l->remise, 0).' %' : '—' }}
                </td>
                @endif
                <td class="r" style="font-weight:700;">{{ number_format($l->total_ht, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="{{ $nbColsF - 1 }}" class="r" style="padding-right:10px;white-space:nowrap;">Total Pièces détachées</td>
                <td class="r">{{ number_format($totalPieces, 0, ',', ' ') }}</td>
            </tr>
            @endif

            {{-- Section Main d'Oeuvre --}}
            @if($mo->count())
            <tr class="section-sep-row">
                <td colspan="{{ $nbColsF }}"><span class="sep-inner">Main d'Œuvre</span></td>
            </tr>
            @foreach($mo as $l)
            <tr>
                <td style="font-size:8pt;color:#555;">{{ $dateFacture }}</td>
                <td></td>
                <td style="font-weight:600;">{{ $l->designation }}</td>
                <td class="c">{{ $l->quantite > 0 ? rtrim(rtrim(number_format($l->quantite, 2, ',', ''), '0'), ',') : '' }}</td>
                <td class="c" style="font-size:8.5pt;">{{ $l->unite ?: ($l->type === 'main_oeuvre' ? 'H' : '') }}</td>
                <td class="r">{{ $l->prix_unitaire > 0 ? number_format($l->prix_unitaire, 0, ',', ' ') : '' }}</td>
                @if($hasRemiseF)
                <td class="c" @if($l->remise > 0) style="color:#c00;font-weight:700;" @else style="color:#999;" @endif>
                    {{ $l->remise > 0 ? number_format($l->remise, 0).' %' : '—' }}
                </td>
                @endif
                <td class="r" style="font-weight:700;">{{ $l->total_ht > 0 ? number_format($l->total_ht, 0, ',', ' ') : '' }}</td>
            </tr>
            @endforeach
            @endif

        </tbody>
    </table>

    {{-- ── Totaux bas ── --}}
    {{-- Totaux dans le même tableau pour l'alignement --}}
    <table style="table-layout:fixed;">
        <colgroup>
            <col style="width:65px;">
            <col style="width:80px;">
            <col>
            <col style="width:38px;">
            <col style="width:40px;">
            <col style="width:82px;">
            @if($hasRemiseF)<col style="width:52px;">@endif
            <col style="width:150px;">
        </colgroup>
        <tbody>
            @if($hasRemiseF)
            <tr class="subtotal-row">
                <td colspan="{{ $nbColsF - 1 }}" class="r" style="padding-right:10px;color:#555;white-space:nowrap;">Sous-total HT brut</td>
                <td class="r" style="white-space:nowrap;">{{ number_format($totalBrutF, 0, ',', ' ') }}</td>
            </tr>
            <tr class="subtotal-row">
                <td colspan="{{ $nbColsF - 1 }}" class="r" style="padding-right:10px;color:#c00;font-weight:700;white-space:nowrap;">Remise totale</td>
                <td class="r" style="color:#c00;font-weight:700;white-space:nowrap;">- {{ number_format($totalRemiseF, 0, ',', ' ') }}</td>
            </tr>
            @endif
            <tr class="subtotal-row">
                <td colspan="{{ $nbColsF - 1 }}" class="r" style="padding-right:10px;white-space:nowrap;">Total HT net</td>
                <td class="r" style="white-space:nowrap;">{{ number_format($avoir->montant_ht, 0, ',', ' ') }}</td>
            </tr>
            <tr class="subtotal-row">
                <td colspan="{{ $nbColsF - 1 }}" class="r" style="padding-right:10px;color:#555;white-space:nowrap;">TVA ({{ (int) $avoir->taux_tva }}%)</td>
                <td class="r" style="white-space:nowrap;">{{ number_format($avoir->montant_tva, 0, ',', ' ') }}</td>
            </tr>
            @if(($avoir->frais_timbre ?? 0) > 0)
            <tr class="subtotal-row">
                <td colspan="{{ $nbColsF - 1 }}" class="r" style="padding-right:10px;font-weight:700;white-space:nowrap;">FRAIS DE TIMBRE</td>
                <td class="r" style="font-weight:700;white-space:nowrap;">{{ number_format($avoir->frais_timbre, 0, ',', ' ') }}</td>
            </tr>
            @endif
            <tr style="background:#111;color:#fff;">
                <td colspan="{{ $nbColsF - 1 }}" class="r" style="padding:6px 10px;font-weight:900;font-size:11pt;white-space:nowrap;">TOTAL DE L'AVOIR</td>
                <td class="r" style="padding:6px 6px;font-weight:900;font-size:11pt;white-space:nowrap;">{{ number_format($avoir->totalGeneral(), 0, ',', ' ') }} FDJ</td>
            </tr>
        </tbody>
    </table>

    {{-- Montant en lettres --}}
    <div class="montant-lettres">
        <strong>Le présent avoir s'élève à la somme de :</strong> {{ strtoupper($avoir->montantEnLettres()) }}
    </div>

    {{-- ── Signatures ── --}}
    <div class="sig-zone">
        <div class="sig-box">
            <div class="sig-label">Accusée de Réception du Client</div>
        </div>
        <div class="sig-box">
            <div class="sig-label">Cachet de la Société</div>
        </div>
    </div>

    {{-- ── Pied de page ── --}}
    <div class="footer">
        STCD MOTORS — Service Après-Vente &nbsp;|&nbsp;
        Djibouti &nbsp;|&nbsp;
        Imprimé le {{ now()->format('d/m/Y à H:i') }}
        @if($avoir->notes)
         &nbsp;|&nbsp; <em>{{ $avoir->notes }}</em>
        @endif
    </div>

</div>
<script>
@if(!request('apercu'))
window.onafterprint = function () { window.close(); };
window.addEventListener('load', function () { setTimeout(window.print, 400); });
@endif
</script>
</body>
</html>
