<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Réception — {{ $or->numero }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 210mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; background: #fff; }
        .page { width: 210mm; min-height: 297mm; padding: 10mm; }
        @media screen {
            html, body { width: 100%; background: #d1d5db; }
            .page { margin: 55px auto 40px; box-shadow: 0 4px 24px rgba(0,0,0,.18); }
        }

        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 8px; }
        .header .logo-text { font-size: 18px; font-weight: bold; color: #1e293b; }
        .header .fiche-title { font-size: 20px; font-weight: bold; text-align: center; flex: 1; }
        .header .ref { font-size: 10px; text-align: right; }

        .meta-row { display: flex; gap: 8px; margin-bottom: 8px; }
        .meta-box { border: 1px solid #000; padding: 4px 8px; flex: 1; }
        .meta-box label { font-size: 9px; font-weight: bold; color: #555; display: block; }
        .meta-box span { font-size: 12px; font-weight: bold; }

        h3 { font-size: 10px; font-weight: bold; background: #1e293b; color: white; padding: 4px 8px; margin-bottom: 0; }

        .section { border: 1px solid #000; margin-bottom: 8px; }
        .section-body { padding: 6px 8px; }

        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }

        .field { margin-bottom: 5px; }
        .field label { font-size: 9px; font-weight: bold; color: #555; display: block; margin-bottom: 2px; }
        .field .val { border-bottom: 1px solid #000; min-height: 16px; padding: 1px 3px; font-size: 11px; }

        table { width: 100%; border-collapse: collapse; }
        table th { background: #334155; color: white; padding: 4px 6px; font-size: 9px; text-align: center; border: 1px solid #000; }
        table td { padding: 5px 6px; border: 1px solid #000; font-size: 10px; text-align: center; }
        table td.label-col { text-align: left; font-weight: bold; background: #f1f5f9; }

        .car-schema { display: flex; gap: 15px; align-items: center; justify-content: center; padding: 8px; }

        .equip-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
        .equip-item { display: flex; align-items: center; gap: 4px; font-size: 10px; }
        .equip-box { width: 12px; height: 12px; border: 1px solid #000; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 10px; font-weight: bold; }

        .dommage-tag { display: inline-block; background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 2px 6px; border-radius: 4px; margin: 2px; font-size: 9px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 8px; }
        .sig-box { border: 1px solid #000; padding: 8px; text-align: center; min-height: 60px; }
        .sig-box .sig-label { font-size: 9px; font-weight: bold; margin-bottom: 4px; }
        .sig-box .sig-area { min-height: 40px; border-top: 1px dashed #999; margin-top: 5px; }

        .carburant-bar { width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; display: inline-block; vertical-align: middle; }
        .carburant-fill { height: 100%; background: #f97316; border-radius: 5px; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Bouton impression (masqué à l'impression) --}}
    <div class="no-print" style="position:fixed;top:14px;left:50%;transform:translateX(-50%);display:flex;gap:10px;z-index:99;">
        @if(request('apercu'))
        <button onclick="window.close()" style="background:#dc2626;color:#fff;border:none;padding:8px 22px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:bold;">← Fermer</button>
        @else
        <a href="{{ $retour ?? route('ordres-reparations.show', $or) }}" style="background:#dc2626;color:#fff;border:none;padding:8px 22px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:bold;text-decoration:none;">← Retour</a>
        @endif
        <button onclick="window.print()" style="background:#f97316;color:white;border:none;padding:8px 22px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:bold;">🖨 Imprimer</button>
    </div>

    {{-- En-tête --}}
    <div class="header">
        <div>
            <div class="logo-text">STCD Motors</div>
            <div style="font-size:9px;color:#64748b;">Atelier mécanique</div>
        </div>
        <div class="fiche-title">FICHE DE RÉCEPTION</div>
        <div class="ref">
            <div style="font-size:9px;">N° : <strong>{{ $or->numero }}</strong></div>
            <div style="font-size:9px;">Date : <strong>{{ $or->date_entree->format('d/m/Y') }}</strong></div>
            <div style="font-size:9px;">Heure : <strong>{{ $or->heure_entree ?? '—' }}</strong></div>
        </div>
    </div>

    {{-- Client & Véhicule --}}
    <div class="section">
        <h3>INFORMATIONS CLIENT & VÉHICULE</h3>
        <div class="section-body grid2">
            <div>
                <div class="field">
                    <label>NOM / RAISON SOCIALE</label>
                    <div class="val">{{ $or->client->nom_complet }}</div>
                </div>
                <div class="field">
                    <label>TÉLÉPHONE</label>
                    <div class="val">{{ $or->client->telephone }}</div>
                </div>
                <div class="field">
                    <label>ADRESSE</label>
                    <div class="val">{{ $or->client->adresse ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>TYPE CLIENT</label>
                    <div class="val">{{ $or->client->getTypeLabel() }}</div>
                </div>
            </div>
            <div>
                <div class="field">
                    <label>NUMÉRO D'IMMATRICULATION</label>
                    <div class="val" style="font-weight:bold;font-size:14px;letter-spacing:2px;">{{ $or->vehicule->immatriculation }}</div>
                </div>
                <div class="field">
                    <label>MARQUE</label>
                    <div class="val">{{ $or->vehicule->marque }}</div>
                </div>
                <div class="field">
                    <label>MODÈLE</label>
                    <div class="val">{{ $or->vehicule->modele }}</div>
                </div>
                <div class="field">
                    <label>N° CHÂSSIS (VIN)</label>
                    <div class="val" style="font-size:9px;font-family:monospace;">{{ $or->vehicule->vin ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Motif --}}
    <div class="section">
        <h3>MOTIF DE LA VISITE / RÉCLAMATION DU CLIENT</h3>
        <div class="section-body">
            <div style="display:flex;gap:6px;margin-bottom:6px;">
                @php $typeCoche = $typeFiche ?? $or->type; @endphp
                @foreach(['normal' => 'Normal', 'garantie' => 'Garantie', 'sinistre' => 'Sinistre', 'entretien' => 'Entretien'] as $v => $l)
                <span style="border:1px solid #000;padding:2px 8px;font-size:10px;background:{{ $typeCoche === $v ? '#1e293b' : 'white' }};color:{{ $typeCoche === $v ? 'white' : '#000' }}">
                    {{ $l }}
                </span>
                @endforeach
            </div>
            <div style="border:1px solid #ddd;min-height:35px;padding:5px;font-size:11px;">
                {{ $or->motif_entree }}
            </div>
        </div>
    </div>

    {{-- État à la réception --}}
    <div class="section">
        <h3>VÉHICULE À LA RÉCEPTION</h3>
        <div class="section-body">
            <table style="margin-bottom:8px;">
                <thead>
                    <tr>
                        <th style="width:80px;">ÉTAT</th>
                        <th>KILOMÉTRAGE</th>
                        <th>PROPRETÉ INTERNE</th>
                        <th>PROPRETÉ EXTERNE</th>
                        <th>CARBURANT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="label-col">ENTRÉE</td>
                        <td><strong>{{ number_format($or->kilometrage_entree) }} km</strong></td>
                        <td>{{ ucfirst($or->proprete_interne ?? 'Bon') }}</td>
                        <td>{{ ucfirst($or->proprete_externe ?? 'Bon') }}</td>
                        <td>
                            <div>{{ $or->niveau_carburant }}</div>
                            @php
                                $pct = match($or->niveau_carburant) { 'vide'=>5,'1/4'=>25,'1/2'=>50,'3/4'=>75,'plein'=>100, default=>50 };
                            @endphp
                            <div class="carburant-bar"><div class="carburant-fill" style="width:{{ $pct }}%"></div></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">SORTIE</td>
                        <td>_____________</td>
                        <td>_____________</td>
                        <td>_____________</td>
                        <td>
                            <div class="carburant-bar"><div class="carburant-fill" style="width:0%"></div></div>
                        </td>
                    </tr>
                </tbody>
            </table>

            @if($or->etat_exterieur)
            <div style="margin-bottom:8px;">
                <strong style="font-size:9px;">OBSERVATIONS :</strong>
                <div style="border:1px solid #ddd;padding:4px;font-size:10px;min-height:20px;">{{ $or->etat_exterieur }}</div>
            </div>
            @endif

            {{-- Schéma carrosserie --}}
            <div style="display:flex;gap:15px;align-items:flex-start;">
                <div>
                    <div style="font-size:9px;font-weight:bold;margin-bottom:4px;text-align:center;">SCHÉMA CARROSSERIE</div>
                    @php
                    $zoneMap = [
                        'Pare-choc avant'      => ['x'=>52,'y'=>24, 'w'=>96,'h'=>28, 'rx'=>16],
                        'Aile avant gauche'    => ['x'=>52,'y'=>52, 'w'=>22,'h'=>40, 'rx'=>0],
                        'Capot'                => ['x'=>74,'y'=>52, 'w'=>52,'h'=>40, 'rx'=>0],
                        'Aile avant droite'    => ['x'=>126,'y'=>52,'w'=>22,'h'=>40, 'rx'=>0],
                        'Porte avant gauche'   => ['x'=>52,'y'=>92, 'w'=>22,'h'=>72, 'rx'=>0],
                        'Toit'                 => ['x'=>74,'y'=>92, 'w'=>52,'h'=>136,'rx'=>0],
                        'Porte avant droite'   => ['x'=>126,'y'=>92,'w'=>22,'h'=>72, 'rx'=>0],
                        'Porte arrière gauche' => ['x'=>52,'y'=>164,'w'=>22,'h'=>64, 'rx'=>0],
                        'Porte arrière droite' => ['x'=>126,'y'=>164,'w'=>22,'h'=>64,'rx'=>0],
                        'Aile arrière gauche'  => ['x'=>52,'y'=>228,'w'=>22,'h'=>40, 'rx'=>0],
                        'Coffre'               => ['x'=>74,'y'=>228,'w'=>52,'h'=>40, 'rx'=>0],
                        'Aile arrière droite'  => ['x'=>126,'y'=>228,'w'=>22,'h'=>40,'rx'=>0],
                        'Pare-choc arrière'    => ['x'=>52,'y'=>268,'w'=>96,'h'=>38, 'rx'=>16],
                    ];
                    @endphp
                    <svg width="140" height="240" viewBox="0 0 200 330" style="-webkit-print-color-adjust:exact;print-color-adjust:exact;">
                        <defs>
                            <!-- Hachuré rouge diagonal pour zones endommagées (s'imprime toujours) -->
                            <pattern id="hachure" patternUnits="userSpaceOnUse" width="6" height="6" patternTransform="rotate(45)">
                                <rect width="6" height="6" fill="#fecaca"/>
                                <line x1="0" y1="0" x2="0" y2="6" stroke="#dc2626" stroke-width="2.5"/>
                            </pattern>
                        </defs>
                        <!-- Carrosserie -->
                        <rect x="52" y="24" width="96" height="282" rx="22" fill="#dbeafe" stroke="#000" stroke-width="2"/>
                        <!-- Toit intérieur -->
                        <rect x="64" y="90" width="72" height="138" rx="7" fill="#bfdbfe" stroke="#000" stroke-width="1"/>
                        <!-- Pare-brise -->
                        <rect x="62" y="64" width="76" height="28" rx="4" fill="#e0f2fe" stroke="#000" stroke-width="1"/>
                        <!-- Lunette arrière -->
                        <rect x="62" y="226" width="76" height="22" rx="4" fill="#e0f2fe" stroke="#000" stroke-width="1"/>
                        <!-- Roues avant -->
                        <rect x="24"  y="34" width="26" height="44" rx="6" fill="#334155"/>
                        <rect x="150" y="34" width="26" height="44" rx="6" fill="#334155"/>
                        <!-- Roues arrière -->
                        <rect x="24"  y="240" width="26" height="44" rx="6" fill="#334155"/>
                        <rect x="150" y="240" width="26" height="44" rx="6" fill="#334155"/>
                        <!-- Séparateurs de zones -->
                        <line x1="52"  y1="52"  x2="148" y2="52"  stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3"/>
                        <line x1="52"  y1="92"  x2="148" y2="92"  stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3"/>
                        <line x1="52"  y1="164" x2="148" y2="164" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3"/>
                        <line x1="52"  y1="228" x2="148" y2="228" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3"/>
                        <line x1="52"  y1="268" x2="148" y2="268" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3"/>
                        <line x1="74"  y1="52"  x2="74"  y2="268" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3"/>
                        <line x1="126" y1="52"  x2="126" y2="268" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3"/>
                        <!-- Zones endommagées (hachuré rouge) -->
                        @foreach((array)$or->dommages_carrosserie as $zone)
                            @if(isset($zoneMap[$zone]))
                            <rect x="{{ $zoneMap[$zone]['x'] }}" y="{{ $zoneMap[$zone]['y'] }}"
                                  width="{{ $zoneMap[$zone]['w'] }}" height="{{ $zoneMap[$zone]['h'] }}"
                                  rx="{{ $zoneMap[$zone]['rx'] }}"
                                  fill="url(#hachure)" stroke="#dc2626" stroke-width="2"/>
                            @endif
                        @endforeach
                        <!-- Légendes -->
                        <text x="100" y="15"  text-anchor="middle" font-size="9" fill="#000" font-weight="bold">AVANT</text>
                        <text x="100" y="320" text-anchor="middle" font-size="9" fill="#000" font-weight="bold">ARRIÈRE</text>
                        <text x="8"   y="168" text-anchor="middle" font-size="8" fill="#000" transform="rotate(-90,8,168)">GAUCHE</text>
                        <text x="192" y="168" text-anchor="middle" font-size="8" fill="#000" transform="rotate(90,192,168)">DROIT</text>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div style="font-size:9px;font-weight:bold;margin-bottom:4px;">ZONES ENDOMMAGÉES <span style="font-weight:normal;color:#dc2626;">(▨ hachuré rouge)</span> :</div>
                    @if($or->dommages_carrosserie && count($or->dommages_carrosserie) > 0)
                        @foreach($or->dommages_carrosserie as $d)
                        <span class="dommage-tag">● {{ $d }}</span>
                        @endforeach
                    @else
                        <span style="font-size:10px;color:#64748b;font-style:italic;">Aucun dommage signalé</span>
                    @endif

                    <div style="margin-top:10px;">
                        <div style="font-size:9px;font-weight:bold;margin-bottom:4px;">ÉQUIPEMENTS PRÉSENTS :</div>
                        @php
                        $eq_labels = ['roue_secours'=>'Roue de secours','cric'=>'Cric','cle_roue'=>'Clé de roue','cable_demarrage'=>'Câble démarrage','triangle'=>'Triangle','gilet'=>'Gilet','extincteur'=>'Extincteur','trousse_secours'=>'Trousse secours','carnet_bord'=>'Carnet de bord','carte_grise'=>'Carte grise','autoradio'=>'Autoradio','autre_equip'=>'Autre'];
                        $equip = $or->equipements ?? [];
                        @endphp
                        <div class="equip-grid">
                            @foreach($eq_labels as $key => $lbl)
                            <div class="equip-item">
                                <div class="equip-box">{{ in_array($key, $equip) ? '✓' : '' }}</div>
                                <span>{{ $lbl }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-label">SIGNATURE DU CLIENT</div>
            <div class="sig-area">
                @if($or->signature_client_image)
                    <img src="{{ asset('storage/' . $or->signature_client_image) }}" alt="Signature du client" style="max-height:60px;max-width:100%;margin-top:4px;">
                @elseif($or->signature_client)
                    <p style="color:green;font-size:10px;padding-top:10px;">✓ Signature obtenue</p>
                @endif
            </div>
        </div>
        <div class="sig-box">
            <div class="sig-label">SIGNATURE DU CONTRÔLEUR / RÉCEPTIONNISTE</div>
            <div class="sig-area">
                <p style="font-size:10px;padding-top:5px;color:#555;">{{ $or->conseiller->name }}</p>
            </div>
        </div>
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
