<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de transfert {{ $bt->numero }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; background: #fff; }
        .page { width: 210mm; min-height: 297mm; padding: 12mm 14mm; }
        @media screen {
            body { background: #d1d5db; }
            .page { margin: 55px auto 40px; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.18); }
        }
        /* ── En-tête (identique à la facture et au devis) ── */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
        .company-logo { height: 56px; width: auto; }
        .company-name { font-size: 11pt; font-weight: 900; text-transform: uppercase; color: #111; margin-top: 4px; }
        .company-sub  { font-size: 7.5pt; color: #333; font-style: italic; }
        .doc-meta  { text-align: right; white-space: nowrap; flex-shrink: 0; margin-left: 12px; }
        .doc-city  { font-size: 9pt; color: #333; margin-bottom: 4px; }
        .doc-title { font-size: 16pt; font-weight: 900; letter-spacing: 1px; color: #111; }
        .doc-num   { font-size: 13pt; font-weight: 900; color: #111; }
        hr { border: none; border-top: 2px solid #111; margin: 6px 0; }
        .infos { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; margin: 8px 0 12px; font-size: 9.5pt; }
        .infos b { display: inline-block; min-width: 150px; }
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        th { background: #f0f0f0; text-align: left; padding: 5px 6px; border: 1px solid #ccc; }
        td { padding: 5px 6px; border: 1px solid #ddd; }
        td.r, th.r { text-align: right; }
        .sig { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 24px; }
        .sig div { border: 1px solid #ccc; min-height: 60px; padding: 6px 10px; font-size: 8pt; font-weight: 700; text-transform: uppercase; }
        .pied { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7pt; color: #666; text-align: center; }
        .btn-bar { position: fixed; top: 16px; left: 50%; transform: translateX(-50%); display: flex; gap: 10px; }
        .btn { background: #111; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn.retour { background: #dc2626; }
        @media print { .btn-bar { display: none; } }
    </style>
</head>
<body>
@php
    // Un BC créé pendant le devis n'est pas rattaché directement à l'OR : on passe par le devis
    $or       = $bc->ordreReparation ?? $bc->devis?->ordreReparation;
    $vehicule = $or?->vehicule ?? $bc->dossier?->vehicule;
    $client   = $or?->client ?? $bc->dossier?->client;
    $lignes   = collect($bt->lignes ?? []);
@endphp
<div class="btn-bar">
    <a href="{{ route('bons-commande.show', $bc) }}#bon-transfert" class="btn retour">← Retour</a>
    <button onclick="window.print()" class="btn">🖨 Imprimer</button>
</div>

<div class="page">
    <div class="header">
        <div>
            <img src="{{ asset('logo.jpg') }}" alt="STCD" class="company-logo">
            <div class="company-name">Société de Transport en Commun de Djibouti</div>
            <div class="company-sub">Le professionnalisme au quotidien</div>
        </div>
        <div class="doc-meta">
            <div class="doc-city">Djibouti, le {{ ($bt->date_transfert ?? $bt->created_at)?->format('d/m/Y') }}</div>
            <div class="doc-title">BON DE TRANSFERT</div>
            <div class="doc-num">N° {{ $bt->numero }}</div>
        </div>
    </div>
    <hr>

    <div class="infos">
        <div><b>Date :</b> {{ $bt->date_transfert?->format('d/m/Y') ?? '—' }}</div>
        <div><b>Bon de commande :</b> {{ $bc->numero }}</div>
        <div><b>Dépôt :</b> {{ $bt->depot ?? '—' }}</div>
        <div><b>Ordre de réparation :</b> {{ $or?->numero ?? ($bc->dossier?->numero ?? '—') }}</div>
        <div><b>Véhicule :</b> {{ $vehicule?->immatriculation ?? '—' }} {{ $vehicule ? '— ' . $vehicule->marque . ' ' . $vehicule->modele : '' }}</div>
        <div><b>Client :</b> {{ $client?->nom_complet ?? '—' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th style="width:120px;">Référence</th>
                <th>Désignation</th>
                <th class="r" style="width:50px;">Qté</th>
                <th class="r" style="width:85px;">Prix unit.</th>
                <th class="r" style="width:95px;">Montant</th>
            </tr>
        </thead>
        <tbody>
            {{-- Lignes reçues du magasin, sinon les pièces du bon de commande ; prix du devis
                 (arrondi compris) : même « Total des pièces » que le devis et le BC --}}
            @php $totalPieces = 0; $lignesBc = $bc->lignes->values(); @endphp
            @forelse($lignes->isNotEmpty() ? $lignes : $bc->lignes->map(fn ($l) => ['reference' => $l->reference, 'designation' => $l->designation, 'quantite' => $l->quantite]) as $i => $l)
            @php
                $ligneBc = $lignesBc->get($l['index'] ?? $i);
                $prix    = $ligneBc?->prixUnitaireAffiche();
                $montant = $ligneBc?->montantAffiche(isset($l['quantite']) ? (float) $l['quantite'] : null);
                $totalPieces += $montant ?? 0;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-family:monospace;">{{ $l['reference'] ?? '' }}</td>
                <td>{{ $l['designation'] ?? '' }}</td>
                <td class="r">{{ isset($l['quantite']) ? rtrim(rtrim(number_format((float) $l['quantite'], 2, ',', ' '), '0'), ',') : '' }}</td>
                <td class="r">{{ $prix ? number_format($prix, 0, ',', ' ') : '—' }}</td>
                <td class="r" style="font-weight:700;">{{ $montant ? number_format($montant, 0, ',', ' ') : '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#999;">Aucune ligne</td></tr>
            @endforelse
            <tr>
                <td colspan="5" class="r" style="background:#f8f8f8;font-weight:700;border-top:1.5px solid #999;">Total des pièces</td>
                <td class="r" style="background:#f8f8f8;font-weight:700;border-top:1.5px solid #999;white-space:nowrap;">{{ number_format($totalPieces, 0, ',', ' ') }} FDJ</td>
            </tr>
        </tbody>
    </table>

    @if($bt->notes)
    <p style="margin-top:10px;font-size:9pt;"><b>Notes :</b> {{ $bt->notes }}</p>
    @endif

    <div class="sig">
        <div>Remis par (magasin)</div>
        <div>Reçu par (atelier)</div>
    </div>

    <div class="pied">
        STCD MOTORS — Bon de transfert {{ $bt->numero }} ({{ $bt->getSourceLabel() }}) — Imprimé le {{ now()->format('d/m/Y à H:i') }}
    </div>
</div>
</body>
</html>
