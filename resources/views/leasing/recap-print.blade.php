<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Récap leasing {{ $recap->periode_label }}@isset($client) — {{ $client->nom_complet }}@endisset</title>
    {{-- Même présentation que la facture (en-tête, tableau, totaux, signatures)… mais ce n'est pas une facture --}}
    <style>
        @page { size: A4; margin: 0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { width: 210mm; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; background: #fff; }
        .page { width: 210mm; min-height: 297mm; padding: 10mm 14mm 12mm; }
        @media screen {
            html, body { width: 100%; background: #d1d5db; }
            .page { margin: 55px auto 40px; box-shadow: 0 4px 24px rgba(0,0,0,.18); background: #fff; }
        }

        /* ── En-tête ── */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
        .company-logo { height: 56px; width: auto; }
        .company-name { font-size: 11pt; font-weight: 900; text-transform: uppercase; color: #111; margin-top: 4px; }
        .company-addr { font-size: 7pt; color: #444; margin-top: 2px; white-space: nowrap; }

        .doc-meta  { text-align: right; white-space: nowrap; flex-shrink: 0; margin-left: 12px; }
        .doc-city  { font-size: 9pt; color: #333; margin-bottom: 4px; }
        .doc-title { font-size: 16pt; font-weight: 900; letter-spacing: 1px; color: #111; }
        .doc-num   { font-size: 10pt; font-weight: 900; color: #111; }

        hr.thick { border: none; border-top: 2px solid #111; margin: 6px 0; }
        hr.thin  { border: none; border-top: 1px solid #ccc; margin: 4px 0; }

        /* ── Bloc destinataire / période ── */
        .client-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin: 8px 0; }
        .client-left, .client-right { font-size: 9pt; }
        .client-row  { display: flex; margin-bottom: 2px; }
        .client-lbl  { font-weight: 700; min-width: 150px; flex-shrink: 0; }
        .client-val  { flex: 1; }

        .mention { border: 1px solid #999; background: #f8f8f8; padding: 4px 8px; margin: 6px 0; font-size: 8.5pt; text-align: center; }
        .mention strong { letter-spacing: 0.5px; }

        /* ── Tableau ── */
        table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
        thead tr { background: #f0f0f0; }
        th { padding: 5px 6px; text-align: left; font-size: 8.5pt; font-weight: 700; border: 1px solid #ccc; }
        th.c, td.c { text-align: center; }
        th.r, td.r { text-align: right; }
        td { padding: 4px 6px; border: 1px solid #ddd; vertical-align: middle; }

        tr.section-sep-row td { border-left:none!important; border-right:none!important; border-bottom:none!important; border-top:6px solid #fff!important; padding:0; background:#fff; }
        tr.section-sep-row .sep-inner { display:block; background:#f0f0f0; border-left:4px solid #333; padding:4px 10px; font-size:9.5pt; font-weight:700; letter-spacing:0.5px; }
        .subtotal-row td { background: #f8f8f8; font-weight: 700; border-top: 1.5px solid #999; }
        .ors { font-size: 7.5pt; color: #555; font-family: monospace; }

        /* ── Lettres ── */
        .montant-lettres { margin-top: 6px; border: 1px solid #ccc; padding: 5px 8px; font-size: 8.5pt; font-style: italic; }
        .montant-lettres strong { font-style: normal; }

        /* ── Signatures ── */
        .sig-zone { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
        .sig-box  { border: 1px solid #ccc; padding: 6px 10px; min-height: 60px; }
        .sig-label { font-size: 8pt; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }

        /* ── Pied ── */
        .footer { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7pt; color: #666; text-align: center; }

        /* ── Boutons (comme la facture) ── */
        .btn-bar { position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
                   display: flex; gap: 10px; z-index: 99; }
        .print-btn { background: #111; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .back-btn  { background: #dc2626; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
        @media print { .btn-bar { display: none; } }
    </style>
</head>
<body>
@php
    $fmt     = fn ($n) => (float) $n > 0 ? number_format((float) $n, 0, ',', ' ') : '—';
    $tiers   = $recap->estGroupe();   // une section + sous-total par propriétaire (tiers) ou par affectation (service)
    $groupes = $recap->lignesGroupees();
    $total   = (float) $recap->lignes->sum('total');
    $retour  = isset($client) ? route('clients.show', $client) : route('leasing.recaps.show', $recap);
@endphp
<div class="btn-bar">
    @if(request('apercu'))
    <button onclick="window.close()" class="back-btn">← Fermer</button>
    @else
    <a href="{{ $retour }}" class="back-btn">← Retour</a>
    @endif
    <button onclick="window.print()" class="print-btn">🖨 Imprimer</button>
</div>

<div class="page">

    {{-- ── En-tête ── --}}
    <div class="header">
        <div>
            <img src="{{ asset('logo.jpg') }}" alt="STCD" class="company-logo">
            <div class="company-name">Garage « S.T.C.D » — Centre de maintenance</div>
            <div class="company-addr">Boulevard Guelleh Batal — Tél. Garage : (+253) 34 49 65 — Tél. : (+253) 35 67 73 — Fax : (+253) 35 30 09</div>
        </div>
        <div class="doc-meta">
            <div class="doc-city">Djibouti, le {{ now()->format('d/m/Y') }}</div>
            <div class="doc-title">RÉCAPITULATIF</div>
            <div class="doc-num">{{ mb_strtoupper($recap->titre) }}</div>
        </div>
    </div>

    <hr class="thick">

    {{-- ── Destinataire / période ── --}}
    <div class="client-grid">
        <div class="client-left">
            <div class="client-row">
                <span class="client-lbl">{{ isset($client) ? 'PROPRIÉTAIRE :' : 'POUR :' }}</span>
                <span class="client-val" style="font-weight:700;">{{ isset($client) ? $client->nom_complet : ($recap->estTiers() ? 'Propriétaires des véhicules loués à STCD' : ($recap->estService() ? 'Services et directions — STCD' : ($recap->lignes->first()?->proprietaire ?? 'STCD'))) }}</span>
            </div>
            @isset($client)
            @if($client->telephone)
            <div class="client-row">
                <span class="client-lbl">Téléphone :</span>
                <span class="client-val">{{ $client->telephone }}</span>
            </div>
            @endif
            @endisset
            <div class="client-row">
                <span class="client-lbl">Nombre de véhicules :</span>
                <span class="client-val">{{ $recap->lignes->count() }}</span>
            </div>
        </div>
        <div class="client-right">
            <div class="client-row">
                <span class="client-lbl">Période :</span>
                <span class="client-val" style="font-weight:700;">{{ $recap->periode_label }}</span>
            </div>
            <div class="client-row">
                <span class="client-lbl">Forfait par véhicule :</span>
                <span class="client-val">{{ number_format($recap->forfait, 0, ',', ' ') }} FDJ</span>
            </div>
        </div>
    </div>

    <div class="mention">
        <strong>DOCUMENT RÉCAPITULATIF — CE N'EST PAS UNE FACTURE.</strong>
        Montants HT, sans TVA : forfait mensuel par {{ $recap->estBus() ? 'bus + pièces détachées de ses bons de commande' : 'véhicule + pièces détachées des passages au garage' }} pendant la période.
    </div>

    {{-- ── Tableau ── --}}
    <table>
        <colgroup>
            <col style="width:34px;">
            <col style="width:125px;">
            <col>
            <col style="width:95px;">
            <col style="width:95px;">
            <col style="width:105px;">
        </colgroup>
        <thead>
            <tr>
                <th class="c">N°</th>
                <th>IMMATRICULATION</th>
                <th>{{ $recap->estBus() ? 'BONS DE COMMANDE (PIÈCES)' : 'PASSAGES AU GARAGE (OR)' }}</th>
                <th class="r">PIÈCES DÉTACHÉES</th>
                <th class="r">FORFAIT / M.O.</th>
                <th class="r">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groupes as $lignesGroupe)
            {{-- Une section par propriétaire (leasing tiers), comme les sections de la facture --}}
            @if($tiers && ! isset($client))
            <tr class="section-sep-row">
                <td colspan="6"><span class="sep-inner">{{ $lignesGroupe->first()->proprietaire }}</span></td>
            </tr>
            @endif
            @foreach($lignesGroupe as $l)
            <tr>
                <td class="c" style="color:#555;">{{ $l->position }}</td>
                <td style="font-family:monospace;font-weight:700;">{{ $l->immatriculation }}</td>
                <td class="ors">{{ collect($l->bcs ?? [])->merge($l->ors ?? [])->pluck('numero')->implode(', ') ?: '—' }}</td>
                <td class="r">{{ $fmt($l->montant_pieces) }}</td>
                <td class="r">{{ $fmt($l->forfait) }}</td>
                <td class="r" style="font-weight:700;">{{ $fmt($l->total) }}</td>
            </tr>
            @endforeach
            @if($tiers && ! isset($client))
            <tr class="subtotal-row">
                <td colspan="3" class="r" style="padding-right:10px;">Sous-total {{ $lignesGroupe->first()->proprietaire }}</td>
                <td class="r">{{ number_format((float) $lignesGroupe->sum('montant_pieces'), 0, ',', ' ') }}</td>
                <td class="r">{{ number_format((float) $lignesGroupe->sum('forfait'), 0, ',', ' ') }}</td>
                <td class="r">{{ number_format((float) $lignesGroupe->sum('total'), 0, ',', ' ') }}</td>
            </tr>
            @endif
            @endforeach

            {{-- ── Totaux ── --}}
            <tr class="subtotal-row">
                <td colspan="5" class="r" style="padding-right:10px;">Total pièces détachées</td>
                <td class="r">{{ number_format((float) $recap->lignes->sum('montant_pieces'), 0, ',', ' ') }}</td>
            </tr>
            <tr class="subtotal-row">
                <td colspan="5" class="r" style="padding-right:10px;">Total forfaits ({{ $recap->lignes->count() }} × {{ number_format($recap->forfait, 0, ',', ' ') }})</td>
                <td class="r">{{ number_format((float) $recap->lignes->sum('forfait'), 0, ',', ' ') }}</td>
            </tr>
            <tr style="background:#111;color:#fff;">
                <td colspan="5" class="r" style="padding:6px 10px;font-weight:900;font-size:11pt;border-color:#111;">TOTAL HT</td>
                <td class="r" style="padding:6px 6px;font-weight:900;font-size:11pt;white-space:nowrap;border-color:#111;">{{ number_format($total, 0, ',', ' ') }} FDJ</td>
            </tr>
        </tbody>
    </table>

    {{-- Montant en lettres --}}
    <div class="montant-lettres">
        <strong>Le présent récapitulatif s'élève à la somme de :</strong> {{ strtoupper(\App\Models\Facture::sommeEnLettres($total)) }} (HT)
    </div>

    {{-- ── Signatures ── --}}
    <div class="sig-zone">
        <div class="sig-box">
            <div class="sig-label">{{ isset($client) ? 'Visa du propriétaire' : 'Visa du client' }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-label">Centre de maintenance — Cachet</div>
        </div>
    </div>

    {{-- ── Pied de page ── --}}
    <div class="footer">
        STCD MOTORS — Service Après-Vente &nbsp;|&nbsp; Djibouti &nbsp;|&nbsp;
        Récap généré le {{ $recap->updated_at?->format('d/m/Y à H:i') }} &nbsp;|&nbsp; Imprimé le {{ now()->format('d/m/Y à H:i') }}
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
