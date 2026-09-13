@extends('layouts.layoutMaster')

@section('content')

<style>

    .fc-card {
        border: none;
        border-radius: 18px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 4px 18px rgba(0,0,0,.04);
    }

    .fc-header {
        padding: 25px 30px;
        border-bottom: 1px solid #f1f1f1;
        background: #fff;
    }

    .fc-title {
        font-size: 28px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 4px;
    }

    .fc-subtitle {
        color: #94a3b8;
        font-size: 14px;
    }

    .fc-btn-back {
        background: #f1f5f9;
        border: none;
        color: #334155;
        padding: 10px 18px;
        border-radius: 12px;
        font-weight: 600;
        transition: .2s;
    }

    .fc-btn-back:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .fc-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .fc-info-box {
        background: #f8fafc;
        border-radius: 14px;
        padding: 18px 20px;
    }

    .fc-info-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #94a3b8;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .fc-info-value {
        font-size: 15px;
        font-weight: 700;
        color: #334155;
    }

    .fc-info-sub {
        font-size: 13px;
        color: #64748b;
        margin-top: 2px;
    }

    .fc-table {
        margin: 0;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 12px;
    }

    .fc-table thead th {
        border: none !important;
        background: transparent;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        padding: 0 20px 10px;
    }

    .fc-table tbody tr {
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(0,0,0,.03);
        border-radius: 14px;
    }

    .fc-table tbody td {
        padding: 18px 20px;
        border: none !important;
        vertical-align: middle;
        background: white;
    }

    .fc-table tbody tr td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
    }

    .fc-table tbody tr td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
    }

    .badge-dispo {
        background: rgba(34,197,94,.12);
        color: #16a34a;
        padding: 8px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
    }

    .badge-indispo {
        background: rgba(239,68,68,.12);
        color: #dc2626;
        padding: 8px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
    }

    .badge-attente {
        background: rgba(148,163,184,.15);
        color: #64748b;
        padding: 8px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
    }

</style>

<div class="card fc-card">

    {{-- HEADER --}}
    <div class="fc-header d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div>
            <div class="fc-title">{{ $commande->numero }}</div>
            <div class="fc-subtitle">Reçu le {{ $commande->created_at->format('d/m/Y à H:i') }} depuis {{ $commande->source_system }}</div>
        </div>

        <div class="d-flex gap-2">
            @if($commande->vente_id)
                <a href="{{ route('sales.show', $commande->vente_id) }}" class="btn btn-success">
                    <i class="bx bx-receipt"></i> Voir la facture
                </a>
            @elseif($commande->toutesPiecesDisponibles())
                <form method="POST" action="{{ route('fournisseur-commandes.creer-vente', $commande) }}">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-cart-add"></i> Créer la vente
                    </button>
                </form>
            @endif

            <a href="{{ route('fournisseur-commandes.index') }}" class="fc-btn-back">
                <i class="bx bx-arrow-back"></i>
                Retour à la liste
            </a>
        </div>

    </div>

    <div class="card-body p-4">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- INFOS VÉHICULE / CLIENT --}}
        <div class="fc-info-grid">

            <div class="fc-info-box">
                <div class="fc-info-label">Véhicule</div>
                <div class="fc-info-value">{{ $commande->vehicule_marque }} {{ $commande->vehicule_modele }}</div>
                <div class="fc-info-sub">{{ $commande->vehicule_immatriculation }}</div>
                @if($commande->vehicule_vin)
                <div class="fc-info-sub">VIN : {{ $commande->vehicule_vin }}</div>
                @endif
            </div>

            <div class="fc-info-box">
                <div class="fc-info-label">Client</div>
                <div class="fc-info-value">{{ $commande->client_nom }}</div>
                <div class="fc-info-sub">{{ $commande->client_telephone }}</div>
            </div>

            <div class="fc-info-box">
                <div class="fc-info-label">Statut</div>
                <div class="fc-info-value">{{ ucfirst($commande->statut) }}</div>
            </div>

        </div>

        {{-- LIGNES DE PIÈCES --}}
        <div class="table-responsive">

            <table class="table fc-table align-middle">

                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Désignation (garage)</th>
                        <th>Quantité demandée</th>
                        <th>Stock chez nous</th>
                        <th>Prix de vente</th>
                        <th style="min-width:340px;">Identification / Disponibilité</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach($commande->lignes as $ligne)

                        <tr>

                            <td class="fw-bold">{{ $ligne->reference ?: '—' }}</td>

                            <td>{{ $ligne->designation }}</td>

                            <td>{{ (float) $ligne->quantite_demandee }}</td>

                            <td>{{ $ligne->product ? (float) $ligne->quantite_disponible : '—' }}</td>

                            <td>{{ $ligne->prix_unitaire !== null ? number_format($ligne->prix_unitaire, 2) : '—' }}</td>

                            <td>
                                @if(is_null($ligne->reference))

                                    {{-- Pas de référence transmise par le garage : le vendeur retrouve
                                         lui-même la pièce (via marque/modèle véhicule + désignation)
                                         et indique la disponibilité. Le prix de vente part automatiquement
                                         avec la pièce choisie — pas de saisie manuelle de prix ici. La note
                                         n'est utile que si aucune pièce n'est sélectionnée (pour expliquer
                                         l'indisponibilité) : elle se masque dès qu'une pièce est choisie. --}}
                                    <form method="POST"
                                          action="{{ route('fournisseur-commandes.lignes.update', [$commande, $ligne]) }}"
                                          class="d-flex flex-wrap align-items-center gap-2 ligne-identification-form">

                                        @csrf
                                        @method('PUT')

                                        <select name="product_id" class="form-select ligne-product-select" style="width:260px;">
                                            <option value="">— Rechercher une pièce —</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}"
                                                    data-reference="{{ $product->reference }}"
                                                    data-designation="{{ $product->designation }}"
                                                    data-stock="{{ (float) $product->quantity }}"
                                                    @selected($ligne->product_id === $product->id)>
                                                    {{ $product->reference }} — {{ $product->designation }}
                                                    ({{ $product->brand->name ?? '' }} {{ $product->model->name ?? '' }})
                                                    — stock: {{ (float) $product->quantity }}
                                                    @if($product->quantity <= 0) — INDISPONIBLE @endif
                                                </option>
                                            @endforeach
                                        </select>

                                        <input type="text" name="note" value="{{ $ligne->note }}"
                                               placeholder="Note (optionnel — ex: raison d'indisponibilité)"
                                               class="form-control form-control-sm ligne-note-input" style="width:220px;"
                                               @if($ligne->product_id) hidden @endif>

                                        <button type="submit" class="btn btn-sm btn-primary flex-shrink-0">Valider</button>

                                    </form>

                                    <div class="mt-2">
                                        @if($ligne->product_id)
                                            @if($ligne->disponible)
                                                <span class="badge-dispo">Disponible (identifiée manuellement)</span>
                                            @else
                                                <span class="badge-indispo">Indisponible</span>
                                            @endif
                                        @elseif(! is_null($ligne->disponible))
                                            <span class="badge-indispo">Marquée indisponible</span>
                                        @else
                                            <span class="badge-attente">Sans référence — à vérifier</span>
                                        @endif
                                        @if($ligne->note)
                                            <span class="text-muted small d-block mt-1">{{ $ligne->note }}</span>
                                        @endif
                                    </div>

                                @elseif($ligne->disponible)
                                    <span class="badge-dispo">Disponible</span>
                                @else
                                    <span class="badge-indispo">Indisponible</span>
                                @endif
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Recherche restreinte à la référence et à la désignation de la pièce
        // (ignore la marque/modèle véhicule et le stock affichés dans la liste).
        function matchReferenceDesignation(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.element === 'undefined') return null;

            var $el = $(data.element);
            var reference = ($el.data('reference') || '').toString().toUpperCase();
            var designation = ($el.data('designation') || '').toString().toUpperCase();
            var term = params.term.toUpperCase();

            if (reference.indexOf(term) > -1 || designation.indexOf(term) > -1) {
                return data;
            }
            return null;
        }

        function styleOption(data) {
            if (!data.id || typeof data.element === 'undefined') return data.text;
            var stock = parseFloat($(data.element).data('stock'));
            var $result = $('<span></span>').text(data.text);
            if (!isNaN(stock) && stock <= 0) {
                $result.css('color', '#dc2626').css('font-weight', '600');
            }
            return $result;
        }

        $('.ligne-product-select').select2({
            width: '260px',
            placeholder: '— Rechercher une pièce —',
            matcher: matchReferenceDesignation,
            templateResult: styleOption,
        });

        // La note ne sert qu'à expliquer une indisponibilité : elle ne s'affiche
        // que quand aucune pièce n'est sélectionnée dans le menu déroulant.
        $('.ligne-identification-form').each(function () {
            var $form = $(this);
            var $select = $form.find('.ligne-product-select');
            var $note = $form.find('.ligne-note-input');

            function toggleNote() {
                $note.prop('hidden', !!$select.val());
            }

            $select.on('change', toggleNote);
            toggleNote();
        });
    });
</script>
@endsection
