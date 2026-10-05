<?php

namespace App\Http\Controllers;

use App\Models\NotificationInterne;
use App\Models\OrdreReparation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class GarantieController extends Controller
{
    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_garanties')) {
            abort(403);
        }

        // Marquer toutes les notifications garantie comme lues
        NotificationInterne::where('destinataire_id', $user->id)
            ->whereNull('lu_at')
            ->update(['lu_at' => now()]);

        // Pas de filtre sur `type` : un OR refusé redevient "normal" (cf.
        // OrdreReparationController::changerStatutGarantie) mais doit rester
        // visible dans l'historique "Refusé" de l'équipe garantie.
        $enAttente = OrdreReparation::where('statut_garantie', 'en_attente')
            ->with(['client', 'vehicule', 'conseiller'])
            ->latest()
            ->get();

        $approuves = OrdreReparation::where('statut_garantie', 'approuve')
            ->with(['client', 'vehicule'])
            ->latest()
            ->limit(30)
            ->get();

        $refuses = OrdreReparation::where('statut_garantie', 'refuse')
            ->with(['client', 'vehicule'])
            ->latest()
            ->limit(30)
            ->get();

        return view('garanties.index', compact('enAttente', 'approuves', 'refuses'));
    }
}
