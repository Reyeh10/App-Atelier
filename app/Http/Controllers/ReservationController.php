<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Vehicule;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur des Réservations (RDV Service Rapide).
 *
 * Une réservation occupe un créneau [heure_rdv, heure_rdv+duree_estimee[ un
 * jour donné. La capacité (cf. ReservationService) est le nombre de postes
 * Service Rapide disponibles EN MÊME TEMPS — deux créneaux qui ne se
 * chevauchent pas peuvent partager le même poste. Le jour J, le
 * réceptionniste "honore" la réservation, ce qui ouvre un dossier de
 * réception préfilli.
 */
class ReservationController extends Controller
{
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_reservations')) {
            abort(403);
        }

        $query = Reservation::with(['client', 'vehicule'])->orderBy('date_rdv')->orderBy('heure_rdv');

        if ($request->boolean('aujourdhui')) {
            $query->whereDate('date_rdv', now());
        }
        if ($request->has('statut')) {
            if ($request->get('statut') !== '') {
                $query->where('statut', $request->get('statut'));
            }
        } else {
            $query->where('statut', 'planifie');
        }

        $reservations = $query->paginate(20)->withQueryString();

        return view('reservations.index', compact('reservations'));
    }

    /**
     * Planning visuel du jour : postes Service Rapide en colonnes, créneaux
     * horaires en lignes. Navigation par date via ?date=AAAA-MM-JJ.
     */
    public function planning(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_reservations')) {
            abort(403);
        }

        $date = $request->get('date') ? \Carbon\Carbon::parse($request->get('date')) : now();
        $planning = ReservationService::planningJour($date);
        $capacite = ReservationService::capacite();

        return view('reservations.planning', compact('date', 'planning', 'capacite'));
    }

    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_reservations')) {
            abort(403);
        }

        $clients         = Client::orderBy('nom')->get();
        $canalService    = $request->get('canal_service', 'autre');
        $capacite        = ReservationService::capacite();
        $servicesRapides = ReservationService::servicesAutre();

        // Client/véhicule déjà connus (reportés depuis le wizard de réception) —
        // préremplissent le formulaire au lieu de les faire ressaisir/rechercher.
        $clientPreselectionneId   = $request->get('client_id')   ? (int) $request->get('client_id')   : null;
        $vehiculePreselectionneId = $request->get('vehicule_id') ? (int) $request->get('vehicule_id') : null;

        return view('reservations.create', compact(
            'clients', 'canalService', 'capacite', 'servicesRapides', 'clientPreselectionneId', 'vehiculePreselectionneId'
        ));
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_reservations')) {
            abort(403);
        }

        $data = $request->validate([
            'client_id'     => ['required', 'exists:clients,id'],
            'vehicule_id'   => ['required', 'exists:vehicules,id'],
            'canal_service' => ['required', 'in:entretien_periodique,autre'],
            'service_cle'   => ['nullable', 'string'],
            'tache'         => ['required', 'string', 'max:255'],
            'date_rdv'      => ['required', 'date', 'after_or_equal:today'],
            'heure_rdv'     => ['required', 'date_format:H:i'],
            'duree_estimee' => ['required', 'numeric', 'min:0.25', 'max:8'],
            'notes'         => ['nullable', 'string'],
        ], [
            'client_id.required'   => 'Veuillez sélectionner un client.',
            'vehicule_id.required' => 'Veuillez sélectionner un véhicule.',
            'tache.required'       => 'Veuillez indiquer la tâche prévue.',
            'date_rdv.required'    => 'Veuillez choisir une date de rendez-vous.',
            'date_rdv.after_or_equal' => 'La date du rendez-vous ne peut pas être dans le passé.',
            'heure_rdv.required'   => 'Veuillez choisir une heure de rendez-vous.',
            'heure_rdv.date_format' => 'L\'heure doit être au format HH:MM.',
            'duree_estimee.required' => 'Veuillez indiquer la durée estimée (en heures).',
            'duree_estimee.min'    => 'La durée doit être d\'au moins 15 minutes (0.25h).',
            'duree_estimee.max'    => 'La durée ne peut pas dépasser 8 heures.',
        ]);

        // La date seule peut être aujourd'hui (after_or_equal:today) mais l'heure doit
        // elle aussi être à venir — sinon on pourrait réserver 7h alors qu'il est 9h.
        if (\Carbon\Carbon::parse($data['date_rdv'])->isToday() && \Carbon\Carbon::parse($data['heure_rdv'])->lt(now())) {
            return back()->withInput()->with('error', 'Cette heure est déjà passée — choisissez un créneau à venir.');
        }

        if (! ReservationService::creneauLibre($data['date_rdv'], $data['heure_rdv'], (float) $data['duree_estimee'])) {
            return back()->withInput()->with('error', 'Complet sur ce créneau — choisissez une autre heure ou une autre date.');
        }

        $colonne = ReservationService::colonneClePour($data['canal_service'], $data['service_cle'] ?? null, $data['tache']);
        if (! ReservationService::creneauLibrePourService($colonne, $data['date_rdv'], $data['heure_rdv'], (float) $data['duree_estimee'])) {
            $nomColonne = ReservationService::colonnesPlanning()[$colonne] ?? 'ce service';
            return back()->withInput()->with('error', "Ce créneau est déjà réservé pour « {$nomColonne} » à cette heure — choisissez une autre heure.");
        }

        $data['numero']         = Reservation::genererNumero();
        $data['conseiller_id'] = Auth::id();
        $data['statut']         = 'planifie';

        $reservation = Reservation::create($data);

        Activite::journaliser('creer_reservation', "Réservation {$reservation->numero} pour {$reservation->client->nom_complet} le {$reservation->date_rdv->format('d/m/Y')} à {$data['heure_rdv']}", $reservation);

        return redirect()->route('reservations.planning', ['date' => $reservation->date_rdv->toDateString()])
            ->with('success', "Réservation {$reservation->numero} enregistrée.");
    }

    public function show(Reservation $reservation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_reservations')) {
            abort(403);
        }
        $reservation->load(['client', 'vehicule', 'conseiller', 'dossier']);
        return view('reservations.show', compact('reservation'));
    }

    /**
     * Le client honore sa réservation le jour J : redirige vers la création
     * du dossier de réception, préfilli avec le client/véhicule/canal_service
     * de la réservation. La réservation elle-même passe à "honoré" seulement
     * une fois le dossier réellement enregistré (cf. DossierReceptionController::store).
     */
    public function honorer(Reservation $reservation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_reservations')) {
            abort(403);
        }
        if ($reservation->statut !== 'planifie') {
            return back()->with('error', 'Cette réservation n\'est plus active.');
        }

        return redirect()->route('dossiers-reception.create', [
            'motif_visite'   => 'service_rapide',
            'canal_service'  => $reservation->canal_service,
            'canal_arrivee'  => 'avec_rdv',
            'reservation_id' => $reservation->id,
        ]);
    }

    public function annuler(Reservation $reservation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_reservations')) {
            abort(403);
        }
        $reservation->update(['statut' => 'annule']);
        Activite::journaliser('annuler_reservation', "Réservation {$reservation->numero} annulée", $reservation);
        return back()->with('success', 'Réservation annulée.');
    }

    public function marquerNoShow(Reservation $reservation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_reservations')) {
            abort(403);
        }
        $reservation->update(['statut' => 'no_show']);
        Activite::journaliser('no_show_reservation', "Réservation {$reservation->numero} — client absent", $reservation);
        return back()->with('success', 'Réservation marquée comme absente (no-show).');
    }

    /**
     * Correction administrateur d'une réservation (date, heure, service…), sans
     * les contrôles de capacité du planning — c'est une correction d'erreur.
     */
    public function corriger(Reservation $reservation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $reservation->load(['client', 'vehicule']);

        return view('reservations.corriger', [
            'reservation' => $reservation,
            'services'    => \App\Services\ReservationService::servicesAutre(),
        ]);
    }

    public function enregistrerCorrection(Request $request, Reservation $reservation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'canal_service' => ['required', 'in:entretien_periodique,autre'],
            'service_cle'   => ['nullable', 'required_if:canal_service,autre', 'string'],
            'tache'         => ['required', 'string', 'max:255'],
            'date_rdv'      => ['required', 'date'],
            'heure_rdv'     => ['required', 'date_format:H:i'],
            'duree_estimee' => ['required', 'numeric', 'min:0.25', 'max:8'],
            'statut'        => ['required', 'in:planifie,honore,annule,no_show'],
            'notes'         => ['nullable', 'string'],
        ], [
            'service_cle.required_if' => 'Choisissez le service.',
        ]);
        if ($data['canal_service'] === 'entretien_periodique') {
            $data['service_cle'] = null;
        }

        $reservation->update($data);

        Activite::journaliser('corriger_reservation', "Correction administrateur de la réservation {$reservation->numero}", $reservation);

        return redirect()->route('reservations.show', $reservation)->with('success', 'Réservation corrigée.');
    }

    /**
     * Supprime une réservation (administrateur). Son devis en avance, s'il n'a
     * pas encore été repris par une réception, est supprimé avec elle.
     */
    public function supprimer(Reservation $reservation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $numero = $reservation->numero;
        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation) {
            \App\Models\Devis::where('reservation_id', $reservation->id)
                ->whereNull('or_id')->whereNull('dossier_id')->get()->each->delete();
            $reservation->delete();
        });

        Activite::journaliser('supprimer_reservation', "Suppression par l'administrateur de la réservation {$numero}");

        return redirect()->route('reservations.index')->with('success', "Réservation {$numero} supprimée.");
    }
}
