<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use App\Models\Devis;
use App\Models\DossierReception;
use App\Models\EntretienTache;
use App\Models\LigneDevis;
use App\Models\Reservation;
use App\Models\Vehicule;
use App\Services\EntretienService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Contrôleur des Dossiers de Réception.
 *
 * Un dossier de réception couvre le parcours Réception → Diagnostic → Devis,
 * avant que l'Ordre de Réparation (OR) n'existe. L'OR n'est créé qu'une fois
 * le devis accepté (cf. DevisController::accepter / DossierReception::creerOrDepuisDossier).
 *
 * Pour une Panne, le motif n'est pas classé à l'accueil : la réception se fait
 * directement, et c'est le diagnostic (méthode diagnostiquer(), après examen du
 * véhicule) qui détermine s'il s'agit d'une panne électrique, mécanique, ou
 * d'un cas de garantie.
 */
class DossierReceptionController extends Controller
{
    /**
     * Liste les dossiers de réception, du plus récent au plus ancien.
     * Filtre optionnel par statut (ex: en_attente_client pour la file de relance).
     */
    public function index(Request $request)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->hasPermission('voir_dossiers')) {
            abort(403);
        }

        $query = DossierReception::with(['client', 'vehicule', 'conseiller'])
            ->orderByDesc('date_entree')
            ->orderByDesc('heure_entree');

        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        $dossiers = $query->paginate(20)->withQueryString();

        return view('dossiers-reception.index', compact('dossiers'));
    }

    /**
     * Affiche le formulaire de capture de la réception, une fois le motif de
     * visite choisi sur l'écran d'entrée (route `reception.index`).
     */
    public function create(Request $request)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->hasPermission('voir_dossiers')) {
            abort(403);
        }

        $motifVisite   = $request->get('motif_visite');
        $typePanne     = $request->get('type_panne');
        $canalService  = $request->get('canal_service');
        $canalArrivee  = $request->get('canal_arrivee');
        $reservationId = $request->get('reservation_id');
        $serviceCle    = $request->get('service_cle');

        if (! in_array($motifVisite, ['panne', 'accident', 'service_rapide'])) {
            return redirect()->route('reception.index')->with('error', 'Veuillez choisir un motif de visite.');
        }

        $clients = Client::orderBy('nom')->get();
        $clientSelectionne  = null;
        $vehiculeSelectionne = null;
        // Pré-remplissage du motif d'entrée : depuis la réservation honorée, ou depuis le
        // service choisi dans le sous-menu "Autre" du wizard (query ?tache=...)
        $tacheReservation = $request->get('tache');

        if ($reservationId) {
            $reservation = Reservation::find($reservationId);
            if ($reservation) {
                $clientSelectionne   = Client::with('vehicules')->find($reservation->client_id);
                $vehiculeSelectionne = Vehicule::find($reservation->vehicule_id);
                $tacheReservation    = $reservation->tache;
                $serviceCle          = $reservation->service_cle;
            }
        } elseif ($request->get('client_id')) {
            $clientSelectionne   = Client::with('vehicules')->find($request->get('client_id'));
            $vehiculeSelectionne = $request->get('vehicule_id') ? Vehicule::find($request->get('vehicule_id')) : null;
        }

        $motifLabel = $this->libelleMotif($motifVisite, $typePanne, $canalService, $canalArrivee);

        return view('dossiers-reception.create', compact(
            'clients', 'clientSelectionne', 'vehiculeSelectionne',
            'motifVisite', 'typePanne', 'canalService', 'canalArrivee', 'reservationId', 'motifLabel', 'tacheReservation', 'serviceCle'
        ));
    }

    /**
     * Enregistre un nouveau dossier de réception.
     * Même capture d'état du véhicule que l'ancien formulaire OR direct, mais
     * ne crée pas d'OR : celui-ci n'est généré qu'à l'acceptation du devis.
     */
    public function store(Request $request)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->hasPermission('voir_dossiers')) {
            abort(403);
        }

        $data = $request->validate([
            'client_id'              => ['required', 'exists:clients,id'],
            'vehicule_id'            => ['required', 'exists:vehicules,id'],
            'motif_visite'           => ['required', 'in:panne,accident,service_rapide'],
            'type_panne'             => ['nullable', 'in:electrique,mecanique'],
            'canal_service'          => ['nullable', 'in:entretien_periodique,autre'],
            'service_cle'            => ['nullable', 'string'],
            'canal_arrivee'          => ['nullable', 'in:sans_rdv,avec_rdv'],
            // Service Rapide n'a lieu que si une réservation a été honorée (jamais de
            // réception directe walk-in) — cf. règle métier : on réserve d'abord le
            // créneau, la réception n'a lieu qu'à l'heure venue.
            'reservation_id'         => [
                \Illuminate\Validation\Rule::requiredIf(fn () => $request->input('motif_visite') === 'service_rapide'),
                'nullable', 'exists:reservations,id',
            ],
            'type_moteur_id'         => ['nullable', 'exists:types_moteur,id'],
            'kilometrage_entree'     => ['required', 'integer', 'min:0'],
            'niveau_carburant'       => ['required', 'in:vide,1/4,1/2,3/4,plein'],
            'proprete_interne'       => ['nullable', 'in:bon,acceptable,mauvais'],
            'proprete_externe'       => ['nullable', 'in:bon,acceptable,mauvais'],
            'etat_exterieur'         => ['nullable', 'string'],
            // Optionnel : le motif choisi au wizard (Panne, Accident...) suffit déjà en soi —
            // ce champ ne sert qu'à noter un détail supplémentaire donné par le client.
            'motif_entree'           => ['nullable', 'string'],
            'accessoires_presents'   => ['boolean'],
            'liste_accessoires'      => ['nullable', 'string'],
            'date_entree'            => ['required', 'date'],
            'heure_entree'           => ['nullable', 'date_format:H:i'],
            'urgence'                => ['required', 'in:normal,urgent,tres_urgent'],
            'notes_internes'         => ['nullable', 'string'],
            'dommages_carrosserie'   => ['nullable', 'string'],
            'signature_client'       => ['boolean'],
            'photos_vehicule'        => ['required', 'array', 'min:1', 'max:10'],
            'photos_vehicule.*'      => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'client_id.required'          => 'Veuillez sélectionner un client.',
            'client_id.exists'            => 'Le client sélectionné est introuvable.',
            'vehicule_id.required'        => 'Veuillez sélectionner un véhicule.',
            'vehicule_id.exists'          => 'Le véhicule sélectionné est introuvable.',
            'kilometrage_entree.required' => 'Le kilométrage à l\'entrée est obligatoire.',
            'kilometrage_entree.integer'  => 'Le kilométrage doit être un nombre entier.',
            'kilometrage_entree.min'      => 'Le kilométrage ne peut pas être négatif.',
            'niveau_carburant.required'   => 'Veuillez indiquer le niveau de carburant.',
            'reservation_id.required'     => 'Le Service Rapide nécessite une réservation — réservez d\'abord un créneau pour ce client.',
            'date_entree.required'        => 'La date d\'entrée est obligatoire.',
            'heure_entree.date_format'    => 'L\'heure d\'entrée doit être au format HH:MM.',
            'urgence.required'            => 'Veuillez sélectionner le niveau d\'urgence.',
            'photos_vehicule.required'    => 'Au moins une photo du véhicule est obligatoire à la réception.',
            'photos_vehicule.min'         => 'Au moins une photo du véhicule est obligatoire à la réception.',
        ]);

        // Le motif choisi au wizard suffit déjà — si le réceptionniste n'a rien ajouté,
        // on l'utilise comme texte par défaut (le champ motif_entree n'est pas nullable en base).
        if (empty($data['motif_entree'])) {
            $data['motif_entree'] = $this->libelleMotif(
                $data['motif_visite'], $data['type_panne'] ?? null, $data['canal_service'] ?? null, $data['canal_arrivee'] ?? null
            );
        }

        $data['equipements'] = $request->input('equipements', []);

        if (!empty($data['dommages_carrosserie'])) {
            $decoded = json_decode($data['dommages_carrosserie'], true);
            $data['dommages_carrosserie'] = is_array($decoded) ? $decoded : [];
        } else {
            $data['dommages_carrosserie'] = [];
        }

        $data['numero']               = DossierReception::genererNumero();
       $data['conseiller_id'] = Auth::id();
        $data['statut']               = 'nouveau';
        $data['accessoires_presents'] = $request->boolean('accessoires_presents');
        $data['signature_client']     = $request->boolean('signature_client');

        $typeMoteurId = $data['type_moteur_id'] ?? null;
        unset($data['type_moteur_id']);

        $entretienPalier = null;
        if ($data['motif_visite'] === 'service_rapide' && $data['canal_service'] === 'entretien_periodique' && $typeMoteurId) {
            $vehicule = Vehicule::find($data['vehicule_id']);
            $entretienPalier = EntretienService::resoudrePalier($vehicule, $data['kilometrage_entree'], $typeMoteurId);
            $data['entretien_km_seuil'] = $entretienPalier;
        }

        $dossier = DossierReception::create($data);

        $dossier->vehicule->update(array_filter([
            'kilometrage'    => $data['kilometrage_entree'],
            'type_moteur_id' => $typeMoteurId,
        ], fn ($v) => $v !== null));

        // Photos prises à la réception — stockées maintenant que l'id du dossier est connu
        if ($request->hasFile('photos_vehicule')) {
            $photos = [];
            foreach ($request->file('photos_vehicule') as $file) {
                $path = $file->store("photos-dossier/{$dossier->id}", 'public');
                $photos[] = [
                    'chemin'       => $path,
                    'nom_original' => $file->getClientOriginalName(),
                    'taille'       => $file->getSize(),
                ];
            }
            $dossier->update(['photos' => $photos]);
        }

        // Service Rapide : devis auto-généré en brouillon (main d'œuvre à prix fixe
        // + pièces du barème si entretien périodique) — le chef d'atelier le relit,
        // l'ajuste si besoin, puis le valide (envoyé → accepté) comme un devis normal.
        if ($dossier->motif_visite === 'service_rapide') {
            $this->genererDevisServiceRapide($dossier, $typeMoteurId, $entretienPalier);
        }

        // Si ce dossier honore une réservation prise à l'avance, on la marque comme honorée
        if ($dossier->reservation_id) {
            $dossier->reservation->update(['statut' => 'honore']);
        }

        Activite::journaliser('creer_dossier', "Création du dossier {$dossier->numero} — {$dossier->client->nom_complet} / {$dossier->vehicule->immatriculation}", $dossier);

        return redirect()->route('dossiers-reception.show', $dossier)
            ->with('success', "Dossier {$dossier->numero} créé avec succès.");
    }

    /**
     * Affiche la fiche du dossier (avant transformation en OR : diagnostic, devis en cours).
     */
    public function show(DossierReception $dossier)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->hasPermission('voir_dossiers')) {
            abort(403);
        }

        $dossier->load(['client', 'vehicule.typeMoteur', 'conseiller', 'reservation', 'devis.lignes', 'ordreReparation']);

        return view('dossiers-reception.show', ['dossier' => $dossier]);
    }

    /**
     * Supprime définitivement un dossier de réception (ex: essai créé par erreur).
     * Bloqué si un OR a déjà été créé à partir de ce dossier — l'historique réel
     * d'une intervention ne se supprime jamais par ce biais (cf. OrdreReparation).
     * Supprime en cascade les devis brouillon/en cours liés et leurs lignes, ainsi
     * que les photos stockées sur le disque.
     */
    public function destroy(DossierReception $dossier)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->hasPermission('voir_dossiers')) {
            abort(403);
        }

        if ($dossier->or_id) {
            return back()->with('error', "Impossible de supprimer {$dossier->numero} : un OR a déjà été créé à partir de ce dossier.");
        }

        $numero = $dossier->numero;

        DB::transaction(function () use ($dossier) {
            foreach ($dossier->devis as $devis) {
                $devis->lignes()->delete();
                $devis->delete();
            }

            foreach ($dossier->photos ?? [] as $photo) {
                Storage::disk('public')->delete($photo['chemin']);
            }

            if ($dossier->reservation_id) {
                $dossier->reservation()->update(['statut' => 'planifie']);
            }

            $dossier->delete();
        });

        Activite::journaliser('supprimer_dossier', "Suppression du dossier {$numero}");

        return redirect()->route('dossiers-reception.index')->with('success', "Dossier {$numero} supprimé.");
    }

    /**
     * Enregistre la fiche de réception signée (scan ou photo du document papier)
     * sur le dossier — utile tant que l'OR n'existe pas encore (le dossier est
     * généralement transformé bien après la réception). Reprise automatiquement
     * sur l'OR au moment de sa création (cf. DossierReception::creerOrDepuisDossier).
     */
    public function uploadFicheSignee(Request $request, DossierReception $dossier)
    {
       /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->hasPermission('voir_dossiers')) {
            abort(403);
        }

        $request->validate([
            'fiche_signee' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'fiche_signee.required' => 'Veuillez sélectionner un fichier à uploader.',
            'fiche_signee.file'     => 'Le fichier uploadé est invalide.',
            'fiche_signee.mimes'    => 'Le fichier doit être au format PDF, JPG ou PNG.',
            'fiche_signee.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

       if ($dossier->fiche_signee) {
            Storage::disk('public')->delete($dossier->fiche_signee);
        }
        
        $path = $request->file('fiche_signee')->store('fiches-signees', 'public');
        $dossier->update(['fiche_signee' => $path]);

        // Si le dossier a déjà été transformé en OR, on met aussi à jour l'OR
        // pour qu'elle reste visible dessus sans devoir la ré-uploader.
        $dossier->ordreReparation?->update(['fiche_signee' => $path]);

        return back()->with('success', 'Fiche de réception signée enregistrée.');
    }

    /**
     * Diagnostic d'un dossier Panne : détermination du type de panne après
     * avoir examiné le véhicule — jamais avant, la réception a déjà eu lieu.
     *
     * Le véhicule est-il éligible au circuit garantie (cf.
     * Vehicule::estEligibleGarantie()) ? Deux parcours strictement exclusifs :
     *   - Éligible : seule l'équipe garantie (permission traiter_garanties)
     *     peut diagnostiquer, et le seul choix possible est "garantie" — un
     *     véhicule inéligible ne peut en aucun cas y être orienté.
     *   - Non éligible : parcours normal, réservé à gerer_devis, choix entre
     *     électrique et mécanique (le chef de garage détermine, fait le
     *     devis, puis affecte).
     *
     * Garantie : pas de devis client à ce stade — l'OR est créé immédiatement,
     * avec le workflow d'approbation garantie déjà existant (statut_garantie,
     * notification aux responsables garantie — cf. DossierReception::creerOrDepuisDossier).
     * Électrique/Mécanique : le dossier passe en "diagnostic", prêt pour la
     * création du devis (bouton déjà existant sur la fiche du dossier).
     */
    public function diagnostiquer(Request $request, DossierReception $dossier)
    {
        if ($dossier->motif_visite !== 'panne' || $dossier->or_id) {
            abort(403);
        }

        $eligibleGarantie = $dossier->vehicule->estEligibleGarantie();

        if ($eligibleGarantie) {
           /** @var User|null $user */
            $user = Auth::user();

            if (!$user || !$user->hasPermission('voir_dossiers')) {
                abort(403);
            }
            $typesAutorises = ['garantie'];
        } else {
          /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->hasPermission('voir_dossiers')) {
            abort(403);
        }
            $typesAutorises = ['electrique', 'mecanique'];
        }

        $data = $request->validate([
            'type_panne' => ['required', 'in:' . implode(',', $typesAutorises)],
        ], [
            'type_panne.required' => 'Veuillez indiquer le type de panne.',
            'type_panne.in'       => $eligibleGarantie
                ? 'Ce véhicule est éligible à la garantie — seule l\'équipe garantie peut le diagnostiquer.'
                : 'Ce véhicule n\'est pas éligible à la garantie.',
        ]);

        $dossier->update(['type_panne' => $data['type_panne'], 'statut' => 'diagnostic']);
        Activite::journaliser('diagnostiquer_dossier', "Diagnostic du dossier {$dossier->numero} : {$data['type_panne']}", $dossier);

        if ($data['type_panne'] === 'garantie') {
            $or = $dossier->creerOrDepuisDossier();
            return redirect()->route('ordres-reparations.show', $or)
                ->with('success', "Dossier orienté vers le processus Garantie — OR {$or->numero} créé.");
        }

        return redirect()->route('dossiers-reception.show', $dossier)
            ->with('success', 'Diagnostic enregistré — vous pouvez maintenant créer le devis.');
    }

    /**
     * Construit le libellé lisible du motif de visite choisi à l'étape précédente.
     */
    private function libelleMotif(?string $motifVisite, ?string $typePanne, ?string $canalService, ?string $canalArrivee = null): string
    {
        return match($motifVisite) {
            'panne' => 'Panne' . match($typePanne) {
                'electrique' => ' — Électrique',
                'mecanique'  => ' — Mécanique',
                'garantie'   => ' — Garantie',
                default      => '',
            },
            'accident' => 'Accident',
            'service_rapide' => 'Service Rapide — ' . match($canalService) {
                'entretien_periodique' => 'Entretien périodique',
                'autre'                => 'Autre (filtres, pneus, lavage...)',
                default                => '',
            } . ($canalArrivee ? ' (' . ($canalArrivee === 'sans_rdv' ? 'Sans RDV' : 'RDV') . ')' : ''),
            default => 'Réception',
        };
    }

    /**
     * Crée automatiquement le devis Service Rapide en brouillon (prix de main
     * d'œuvre pré-rempli au tarif fixe du service — cf. Réglages atelier /
     * ReservationService::tarif()) :
     *   - Toujours une ligne main d'œuvre au tarif fixe du service choisi.
     *   - Si Entretien périodique : + les pièces à remplacer du barème
     *     constructeur (prix laissé à 0 en attendant la réponse du fournisseur).
     * Le devis part immédiatement en bon de commande vers stcd-magasin (cf.
     * DevisWorkflowService::genererBonCommande()) : le prix de vente réel et
     * la disponibilité reviennent se remplir sur les lignes pièces avant même
     * que le chef d'atelier n'ait validé le devis. Le devis reste en brouillon
     * pour qu'il le relise et l'ajuste si besoin, puis le marque envoyé/accepté
     * comme un devis normal.
     *
     * Exception : si le service est gratuit (tarif à 0) et qu'il n'y a aucune
     * pièce à remplacer, il n'y a rien à faire valider — l'OR est créé
     * directement (sans devis) pour que le travail reste tracé normalement.
     */
    private function genererDevisServiceRapide(DossierReception $dossier, ?int $typeMoteurId, ?int $palier): void
    {
        if ($dossier->canal_service === 'entretien_periodique') {
            $designation = 'Entretien périodique — Main d\'œuvre';
            $tarifCle    = 'entretien_periodique';
        } else {
            $designation = $dossier->motif_entree;
            $tarifCle    = $dossier->service_cle;
        }

        $prixMainOeuvre = $tarifCle ? \App\Services\ReservationService::tarif($tarifCle) : 0;

        $piecesARemplacer = ($palier !== null && $typeMoteurId)
            ? EntretienTache::where('type_moteur_id', $typeMoteurId)
                ->where('km_seuil', $palier)
                ->where('action', 'remplacer')
                ->orderBy('designation')
                ->get()
            : collect();

        if ((float) $prixMainOeuvre === 0.0 && $piecesARemplacer->isEmpty()) {
            $dossier->creerOrDepuisDossier(serviceGratuit: true);
            return;
        }

        $devis = DB::transaction(function () use ($dossier, $designation, $prixMainOeuvre, $piecesARemplacer) {
            $devis = Devis::create([
                'numero'     => Devis::genererNumero(),
                'dossier_id' => $dossier->id,
                'taux_tva'   => 10,
                'statut'     => 'brouillon',
            ]);

            LigneDevis::create([
                'devis_id'      => $devis->id,
                'type'          => 'main_oeuvre',
                'designation'   => $designation,
                'quantite'      => 1,
                'prix_unitaire' => $prixMainOeuvre,
                'total_ht'      => $prixMainOeuvre,
            ]);

            foreach ($piecesARemplacer as $tache) {
                LigneDevis::create([
                    'devis_id'      => $devis->id,
                    'type'          => 'piece',
                    'designation'   => $tache->designation,
                    'quantite'      => 1,
                    'prix_unitaire' => 0,
                    'total_ht'      => 0,
                ]);
            }

            $devis->recalculer();
            $dossier->update(['statut' => 'devis_en_cours']);

            return $devis;
        });

        // Envoi immédiat au fournisseur (hors transaction — appel HTTP externe).
        $devis->load('lignes');
        \App\Services\DevisWorkflowService::genererBonCommande($devis);
    }
}
