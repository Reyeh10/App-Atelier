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
        font-size: 32px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 4px;
    }

    .fc-subtitle {
        color: #94a3b8;
        font-size: 14px;
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
        transition: .2s;
        box-shadow: 0 2px 10px rgba(0,0,0,.03);
        border-radius: 14px;
    }

    .fc-table tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,.05);
    }

    .fc-table tbody td {
        padding: 22px 20px;
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

    .fc-numero {
        font-weight: 700;
        color: #334155;
        font-size: 15px;
    }

    .fc-sub {
        color: #94a3b8;
        font-size: 12.5px;
    }

    .badge-dispo {
        background: rgba(34,197,94,.12);
        color: #16a34a;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 11.5px;
        font-weight: 700;
    }

    .badge-indispo {
        background: rgba(239,68,68,.12);
        color: #dc2626;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 11.5px;
        font-weight: 700;
    }

    .badge-attente {
        background: rgba(148,163,184,.15);
        color: #64748b;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 11.5px;
        font-weight: 700;
    }

    .fc-empty {
        padding: 60px 20px;
        text-align: center;
        color: #94a3b8;
    }

    .fc-filters input.form-control,
    .fc-filters select.form-select {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        font-size: 13.5px;
    }

    .fc-filters .btn {
        border-radius: 12px;
        font-size: 13.5px;
    }

</style>

<div class="card fc-card">

    {{-- HEADER --}}
    <div class="fc-header d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div>
            <div class="fc-title">Commandes du garage</div>
            <div class="fc-subtitle">Bons de commande reçus en temps réel depuis app-atelier</div>
        </div>

    </div>

    {{-- FILTRES --}}
    <div class="px-4 pt-4">
        <form method="GET" action="{{ route('fournisseur-commandes.index') }}" class="row g-2 align-items-center fc-filters">

            <div class="col-md-5">
                <input type="text" name="search" value="{{ $search }}" class="form-control"
                       placeholder="Rechercher : n° BC, client ou véhicule...">
            </div>

            <div class="col-md-3">
                <select name="dispo" class="form-select">
                    <option value="">Toutes les disponibilités</option>
                    <option value="en_attente" {{ $dispo === 'en_attente' ? 'selected' : '' }}>En attente</option>
                    <option value="tout" {{ $dispo === 'tout' ? 'selected' : '' }}>Tout disponible</option>
                    <option value="partiel" {{ $dispo === 'partiel' ? 'selected' : '' }}>Partiellement disponible</option>
                    <option value="rien" {{ $dispo === 'rien' ? 'selected' : '' }}>Rien disponible</option>
                </select>
            </div>

            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-search"></i> Rechercher
                </button>
                @if($search !== '' || $dispo !== '')
                <a href="{{ route('fournisseur-commandes.index') }}" class="btn btn-outline-secondary">
                    Réinitialiser
                </a>
                @endif
            </div>

        </form>
    </div>

    {{-- BODY --}}
    <div class="card-body p-4">

        <div class="table-responsive">

            <table class="table fc-table align-middle">

                <thead>
                    <tr>
                        <th>N° bon de commande</th>
                        <th>Véhicule</th>
                        <th>Client</th>
                        <th>Pièces</th>
                        <th>Disponibilité</th>
                        <th>Reçu le</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($commandes as $commande)

                        @php
                            $total = $commande->lignes->count();
                            $repondues = $commande->lignes->whereNotNull('disponible')->count();
                            $disponibles = $commande->lignes->where('disponible', true)->count();
                        @endphp

                        <tr>

                            <td>
                                <div class="fc-numero">{{ $commande->numero }}</div>
                                <div class="fc-sub">{{ $commande->source_system }}</div>
                            </td>

                            <td>
                                {{ $commande->vehicule_marque }} {{ $commande->vehicule_modele }}
                                <div class="fc-sub">{{ $commande->vehicule_immatriculation }}</div>
                            </td>

                            <td>
                                {{ $commande->client_nom }}
                                <div class="fc-sub">{{ $commande->client_telephone }}</div>
                            </td>

                            <td>{{ $total }} pièce(s)</td>

                            <td>
                                @if($total === 0 || $repondues === 0)
                                    <span class="badge-attente">En attente</span>
                                @elseif($disponibles === $total)
                                    <span class="badge-dispo">Tout disponible</span>
                                @elseif($disponibles === 0)
                                    <span class="badge-indispo">Rien disponible</span>
                                @else
                                    <span class="badge-attente">{{ $disponibles }}/{{ $total }} disponibles</span>
                                @endif
                            </td>

                            <td class="fc-sub">{{ $commande->created_at->format('d/m/Y H:i') }}</td>

                            <td>
                                <a href="{{ route('fournisseur-commandes.show', $commande) }}"
                                   class="btn btn-info text-white">
                                    <i class="bx bx-show"></i>
                                </a>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7">
                                <div class="fc-empty">
                                    <i class="bx bx-package bx-lg mb-3"></i>
                                    <div>
                                        @if($search !== '' || $dispo !== '')
                                            Aucune commande ne correspond à cette recherche
                                        @else
                                            Aucune commande reçue du garage pour le moment
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>
@endsection
