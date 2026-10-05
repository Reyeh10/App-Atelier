<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\TypeMoteur;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\DevisAvanceService;
use App\Services\EntretienService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Devis en avance — établi AVANT toute réception :
 *   - depuis une réservation : le client connaît le prix avant son RDV ; le jour
 *     de la réception, le dossier reprend ce devis (cf. DossierReceptionController::store()) ;
 *   - devis libre : simple demande de prix pour un client / véhicule.
 *
 * Entretien périodique : on saisit le kilométrage prévu, et le palier est
 * trouvé comme à la réception — kilométrage OU délai en mois, compté jusqu'à
 * la date du RDV (cf. EntretienService::resoudrePalier()).
 */
class DevisAvanceController extends Controller
{
    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_devis')) {
            abort(403);
        }

        $reservation = null;
        if ($request->filled('reservation_id')) {
            $reservation = Reservation::with(['client', 'vehicule', 'devis'])->findOrFail($request->reservation_id);
            if ($reservation->devis) {
                return redirect()->route('devis.show', $reservation->devis)
                    ->with('success', "Cette réservation a déjà un devis en avance ({$reservation->devis->numero}).");
            }
        }

        return view('devis.avance', [
            'reservation' => $reservation,
            'clients'     => $reservation ? collect() : Client::orderBy('nom')->get(),
            'services'    => ReservationService::servicesAutre(),
            'typesMoteur' => TypeMoteur::orderBy('modele')->get(),
        ]);
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_devis')) {
            abort(403);
        }

        $request->validate([
            'reservation_id'    => ['nullable', 'exists:reservations,id'],
            'client_id'         => ['nullable', 'required_without:reservation_id', 'exists:clients,id'],
            'vehicule_id'       => ['nullable', 'required_without:reservation_id', 'exists:vehicules,id'],
            'type'              => ['required', 'in:entretien_periodique,service_rapide,libre'],
            'kilometrage_prevu' => ['nullable', 'required_if:type,entretien_periodique', 'integer', 'min:0'],
            'date_prevue'       => ['nullable', 'date'],
            'type_moteur_id'    => ['nullable', 'exists:types_moteur,id'],
            'service_cle'       => ['nullable', 'required_if:type,service_rapide', 'string'],
        ], [
            'client_id.required_without'   => 'Veuillez choisir le client.',
            'vehicule_id.required_without' => 'Veuillez choisir le véhicule.',
            'type.required'                => 'Veuillez choisir le type de prestation.',
            'kilometrage_prevu.required_if' => 'Indiquez le kilométrage prévu le jour du rendez-vous.',
            'kilometrage_prevu.integer'    => 'Le kilométrage doit être un nombre entier.',
            'service_cle.required_if'      => 'Veuillez choisir le service.',
        ]);

        // Réservation : client, véhicule et date viennent de la réservation
        $reservation = $request->filled('reservation_id')
            ? Reservation::with(['vehicule', 'devis'])->findOrFail($request->reservation_id)
            : null;

        if ($reservation) {
            if ($reservation->devis) {
                return redirect()->route('devis.show', $reservation->devis)
                    ->with('success', "Cette réservation a déjà un devis en avance ({$reservation->devis->numero}).");
            }
            $vehicule = $reservation->vehicule;
            $clientId = $reservation->client_id;
            $date     = Carbon::parse($reservation->date_rdv);
        } else {
            $vehicule = Vehicule::findOrFail($request->vehicule_id);
            if ((int) $vehicule->client_id !== (int) $request->client_id) {
                return back()->withInput()->with('error', 'Ce véhicule n\'appartient pas au client choisi.');
            }
            $clientId = (int) $request->client_id;
            $date     = $request->filled('date_prevue') ? Carbon::parse($request->date_prevue) : today();
        }

        $type         = $request->type;
        $typeMoteurId = $request->type_moteur_id ?: $vehicule->type_moteur_id;
        $palier       = null;

        if ($type === 'entretien_periodique') {
            if (! $typeMoteurId) {
                return back()->withInput()->with('error', 'Choisissez le type de moteur du véhicule pour trouver le palier d\'entretien.');
            }
            if (! $vehicule->type_moteur_id) {
                $vehicule->update(['type_moteur_id' => $typeMoteurId]);
            }
            $palier = EntretienService::resoudrePalier($vehicule, (int) $request->kilometrage_prevu, (int) $typeMoteurId, $date);
            if ($palier === null) {
                return back()->withInput()->with('error', 'Aucun barème d\'entretien n\'est défini pour ce type de moteur.');
            }
        }

        if ($type === 'service_rapide' && ! array_key_exists($request->service_cle, ReservationService::servicesAutre())) {
            return back()->withInput()->with('error', 'Le service choisi est introuvable.');
        }

        $lignes = DevisAvanceService::lignes($type, $request->service_cle, $reservation?->tache, $typeMoteurId ? (int) $typeMoteurId : null, $palier);

        $devis = DevisAvanceService::creer([
            'reservation_id'     => $reservation?->id,
            'client_id'          => $clientId,
            'vehicule_id'        => $vehicule->id,
            'kilometrage_prevu'  => $request->filled('kilometrage_prevu') ? (int) $request->kilometrage_prevu : null,
            'date_prevue'        => $date->toDateString(),
            'entretien_km_seuil' => $palier,
        ], $lignes);

        Activite::journaliser(
            'creer_devis',
            "Devis en avance {$devis->numero} — {$vehicule->immatriculation}" . ($reservation ? " (réservation {$reservation->numero})" : ' (devis libre)'),
            $devis
        );

        // Travaux libres : aucune ligne encore — on ouvre directement le formulaire
        // pour les saisir. Sinon, le devis est prêt : relecture puis envoi au client.
        if (empty($lignes)) {
            return redirect()->route('devis.edit', $devis)
                ->with('success', "Devis {$devis->numero} créé — ajoutez les lignes de travaux.");
        }

        $message = "Devis en avance {$devis->numero} créé";
        if ($palier) {
            $message .= ' — palier d\'entretien ' . number_format($palier, 0, ',', ' ') . ' km';
        }

        return redirect()->route('devis.show', $devis)->with('success', $message . '. Le prix des pièces sera complété à la réponse du fournisseur.');
    }
}
