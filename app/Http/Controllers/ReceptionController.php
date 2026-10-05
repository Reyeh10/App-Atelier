<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Reservation;
use App\Models\Vehicule;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Écran d'entrée de la Réception : choix du motif de visite du client
 * (Panne / Accident / Service Rapide), qui détermine ensuite le parcours
 * (formulaire de réception directe, ou passage par une réservation).
 */
class ReceptionController extends Controller
{
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('creer_dossiers')) {
            abort(403);
        }

        $capacite        = ReservationService::capacite();
        $placesRestantes = ReservationService::placesLibresMaintenant();

        $reservationsAujourdhui = Reservation::with(['client', 'vehicule'])
            ->whereDate('date_rdv', now())
            ->where('statut', 'planifie')
            ->orderBy('id')
            ->get();

        $servicesRapides   = ReservationService::servicesAutre();
        $planningAujourdhui = ReservationService::planningJour(now());

        // Client/véhicule déjà connus (ex: bouton "Nouvelle Réception" depuis la fiche
        // véhicule/client) — reportés vers chaque branche du wizard une fois le motif
        // choisi, pour ne pas faire ressaisir/rechercher ce qui est déjà su.
        $clientPreselectionne   = $request->get('client_id')   ? Client::find($request->get('client_id'))     : null;
        $vehiculePreselectionne = $request->get('vehicule_id') ? Vehicule::find($request->get('vehicule_id')) : null;

        return view('reception.choix-motif', compact(
            'capacite', 'placesRestantes', 'reservationsAujourdhui', 'servicesRapides', 'planningAujourdhui',
            'clientPreselectionne', 'vehiculePreselectionne'
        ));
    }
}
