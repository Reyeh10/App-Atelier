<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use App\Models\Devis;
use App\Models\LigneDevis;
use App\Models\NotificationInterne;
use App\Models\OrdreReparation;
use App\Models\PhotoOr;
use App\Models\Technicien;
use App\Models\TypeMoteur;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\EntretienService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Contrôleur des Ordres de Réparation (OR).
 *
 * Un OR est créé à l'entrée du véhicule et suit toute la vie du dossier :
 * réception → diagnostic → devis → travaux → contrôle → lavage → prêt → facturation → restitution.
 */
class OrdreReparationController extends Controller
{
    // Au-delà de cette marge de dépassement (en km), on signale un entretien en retard
    private const ENTRETIEN_MARGE_DEPASSEMENT = 500;

    /**
     * Liste tous les ordres de réparation avec filtres par statut, type et recherche texte.
     * "Toutes les OR" montre réellement tout, quel que soit le rôle — filtrer sur un
     * statut précis se fait via le paramètre `statut` (ou `pret_restitution`), jamais
     * en cachant silencieusement des résultats par défaut.
     */
    public function index(Request $request)
    {
        $query = OrdreReparation::with(['client', 'vehicule', 'conseiller'])
            ->orderByDesc('date_entree')
            ->orderByDesc('heure_entree');

        // Recherche par numéro OR, motif, nom client ou immatriculation
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhere('motif_entree', 'like', "%{$search}%")
                  ->orWhereHas('client', fn($q) => $q->where('nom', 'like', "%{$search}%"))
                  ->orWhereHas('vehicule', fn($q) => $q->where('immatriculation', 'like', "%{$search}%"));
            });
        }

        // Filtre spécial : véhicules facturés dont la facture est réglée (payée
        // ou accordée à crédit), ou service gratuit déjà prêt — prêts à être
        // physiquement restitués au client (cf. OrdreReparation::scopePretsARestituer).
        if ($request->boolean('pret_restitution')) {
            $query->pretsARestituer();
        }
        // Filtre optionnel par statut (ex: en_cours, pret...)
        elseif ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        // Filtre optionnel par type (normal, garantie, sinistre, entretien)
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Pagination : 20 OR par page, en conservant les paramètres de filtre dans les liens
        $ordres = $query->paginate(20)->withQueryString();

        // Compteurs pour les statistiques affichées en haut de la liste
        $stats = [
            'ouverts'   => OrdreReparation::whereIn('statut', ['ouvert', 'diagnostic', 'devis_envoye', 'devis_accepte'])->count(),
            'en_cours'  => OrdreReparation::where('statut', 'en_cours')->count(),
            'prets'     => OrdreReparation::where('statut', 'pret')->count(),
            'garanties' => OrdreReparation::where('type', 'garantie')->where('statut_garantie', 'en_attente')->count(),
        ];

        return view('ordres-reparations.index', compact('ordres', 'stats'));
    }

    /**
     * Affiche le formulaire de création d'un nouvel OR.
     * Si client_id ou vehicule_id sont passés en paramètre URL,
     * les champs correspondants sont pré-remplis automatiquement.
     */
    public function create(Request $request)
    {
        $clients     = Client::orderBy('nom')->get();
        $techniciens = Technicien::where('actif', true)->orderBy('nom')->get();

        // Pré-sélection du client et du véhicule si fournis en paramètre GET
        $clientSelectionne  = $request->get('client_id')  ? Client::with('vehicules')->find($request->get('client_id'))  : null;
        $vehiculeSelectionne = $request->get('vehicule_id') ? Vehicule::find($request->get('vehicule_id')) : null;

        return view('ordres-reparations.create', compact(
            'clients', 'techniciens', 'clientSelectionne', 'vehiculeSelectionne'
        ));
    }

    /**
     * Enregistre un nouvel OR en base de données.
     * Réservé aux utilisateurs ayant la permission 'gerer_ordres'.
     * Étapes :
     *   1. Validation des données du formulaire
     *   2. Traitement des équipements (checkboxes) et des dommages carrosserie (JSON)
     *   3. Génération automatique du numéro OR (ex: OR-2026-0001)
     *   4. Création de l'OR en base
     *   5. Mise à jour du kilométrage du véhicule
     *   6. Enregistrement des photos si fournies
     *   7. Journalisation de l'activité
     */
    public function store(Request $request)
    {
       // if (! auth()->user()->hasPermission('creer_ordres')) abort(403);
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('creer_ordres')) {
            abort(403);
        }

        $data = $request->validate([
            'client_id'              => ['required', 'exists:clients,id'],
            'vehicule_id'            => ['required', 'exists:vehicules,id'],
            'type'                   => ['required', 'in:normal,garantie,sinistre,entretien'],
            // OR d'entretien : sans type de moteur, aucun palier → devis et feuille de travail vides
            'type_moteur_id'         => ['nullable', 'required_if:type,entretien', 'exists:types_moteur,id'],
            'kilometrage_entree'     => ['required', 'integer', 'min:0'],
            'niveau_carburant'       => ['required', 'in:vide,1/4,1/2,3/4,plein'],
            'proprete_interne'       => ['nullable', 'in:bon,acceptable,mauvais'],
            'proprete_externe'       => ['nullable', 'in:bon,acceptable,mauvais'],
            'etat_exterieur'         => ['nullable', 'string'],
            'motif_entree'           => ['required', 'string'],
            'accessoires_presents'   => ['boolean'],
            'liste_accessoires'      => ['nullable', 'string'],
            'date_entree'            => ['required', 'date'],
            'heure_entree'           => ['nullable', 'date_format:H:i'],
            'date_sortie_prevue'     => ['nullable', 'date', 'after_or_equal:date_entree'],
            'technicien_id'          => ['nullable', 'exists:techniciens,id'],
            'urgence'                => ['required', 'in:normal,urgent,tres_urgent'],
            'notes_internes'         => ['nullable', 'string'],
            'dommages_carrosserie'   => ['nullable', 'string'],
            'signature_client'       => ['boolean'],
            'photos_vehicule'        => ['required', 'array', 'min:1', 'max:10'],
            'photos_vehicule.*'      => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'type_moteur_id.required_if'     => "Choisissez le type de moteur : c'est lui qui donne les pièces et les contrôles du barème d'entretien.",
            'client_id.required'          => 'Veuillez sélectionner un client.',
            'client_id.exists'            => 'Le client sélectionné est introuvable.',
            'vehicule_id.required'        => 'Veuillez sélectionner un véhicule.',
            'vehicule_id.exists'          => 'Le véhicule sélectionné est introuvable.',
            'type.required'               => 'Veuillez sélectionner le type d\'ordre de réparation.',
            'type.in'                     => 'Le type d\'ordre sélectionné est invalide.',
            'photos_vehicule.required'    => 'Au moins une photo du véhicule est obligatoire à la réception.',
            'photos_vehicule.min'         => 'Au moins une photo du véhicule est obligatoire à la réception.',
            'kilometrage_entree.required' => 'Le kilométrage à l\'entrée est obligatoire.',
            'kilometrage_entree.integer'  => 'Le kilométrage doit être un nombre entier.',
            'kilometrage_entree.min'      => 'Le kilométrage ne peut pas être négatif.',
            'niveau_carburant.required'   => 'Veuillez indiquer le niveau de carburant.',
            'niveau_carburant.in'         => 'Le niveau de carburant sélectionné est invalide.',
            'motif_entree.required'       => 'Le motif d\'entrée est obligatoire — décrivez le problème signalé.',
            'date_entree.required'        => 'La date d\'entrée est obligatoire.',
            'date_entree.date'            => 'La date d\'entrée n\'est pas valide.',
            'heure_entree.date_format'    => 'L\'heure d\'entrée doit être au format HH:MM.',
            'date_sortie_prevue.date'     => 'La date de sortie prévue n\'est pas valide.',
            'date_sortie_prevue.after_or_equal' => 'La date de sortie prévue ne peut pas être avant la date d\'entrée.',
            'technicien_id.exists'        => 'Le technicien sélectionné est introuvable.',
            'urgence.required'            => 'Veuillez sélectionner le niveau d\'urgence.',
            'urgence.in'                  => 'Le niveau d\'urgence sélectionné est invalide.',
        ]);

        // Les équipements présents sont envoyés sous forme de tableau de checkboxes
        $data['equipements'] = $request->input('equipements', []);

        // Les dommages carrosserie arrivent en JSON depuis le schéma cliquable du formulaire
        if (!empty($data['dommages_carrosserie'])) {
            $decoded = json_decode($data['dommages_carrosserie'], true);
            $data['dommages_carrosserie'] = is_array($decoded) ? $decoded : [];
        } else {
            $data['dommages_carrosserie'] = [];
        }

        // Numéro automatique, conseiller = utilisateur connecté, statut initial = ouvert
        $data['numero']               = OrdreReparation::genererNumero();
        $data['conseiller_id']        = Auth::id();
        $data['statut']               = 'ouvert';
        $data['accessoires_presents'] = $request->boolean('accessoires_presents');
        $data['signature_client']     = $request->boolean('signature_client');

        // Les OR de type garantie démarrent avec un statut garantie "en attente" de validation
        if ($data['type'] === 'garantie') {
            $data['statut_garantie'] = 'en_attente';
        }

        // type_moteur_id concerne le véhicule (mémorisé sur sa fiche), pas l'OR lui-même
        $typeMoteurId = $data['type_moteur_id'] ?? null;
        unset($data['type_moteur_id']);

        // Entretien périodique : on résout le palier constructeur avant de créer l'OR,
        // pour pouvoir l'enregistrer directement dessus (traçabilité).
        $entretienPalier = null;
        if ($data['type'] === 'entretien' && $typeMoteurId) {
            $vehicule = Vehicule::find($data['vehicule_id']);
            $entretienPalier = EntretienService::resoudrePalier($vehicule, $data['kilometrage_entree'], $typeMoteurId);
            $data['entretien_km_seuil'] = $entretienPalier;
        }

        $or = OrdreReparation::create($data);

        // On met à jour le kilométrage (et le type de moteur, une fois connu) du véhicule
        $or->vehicule->update(array_filter([
            'kilometrage'    => $data['kilometrage_entree'],
            'type_moteur_id' => $typeMoteurId,
        ], fn ($v) => $v !== null));

        // Génère automatiquement un devis brouillon avec les pièces à remplacer
        // du barème constructeur, pour que le chef de garage n'ait plus qu'à
        // ajouter la main d'œuvre et les prix.
        if ($entretienPalier !== null) {
            $this->genererDevisEntretien($or, $typeMoteurId, $entretienPalier);
        }

        // Enregistrement des photos prises lors de la réception du véhicule
        if ($request->hasFile('photos_vehicule')) {
            foreach ($request->file('photos_vehicule') as $file) {
                $path = $file->store("photos-or/{$or->id}", 'public');
                PhotoOr::create([
                    'or_id'        => $or->id,
                    'chemin'       => $path,
                    'nom_original' => $file->getClientOriginalName(),
                    'taille'       => $file->getSize(),
                ]);
            }
        }

        Activite::journaliser('creer_or', "Création de l'OR {$or->numero} — {$or->client->nom_complet} / {$or->vehicule->immatriculation}", $or);

        // Notifier les responsables garantie si le type est garantie
        if ($or->type === 'garantie') {
            NotificationInterne::notifierResponsablesGarantie(
                titre: "Nouvelle demande de garantie — {$or->numero}",
                corps: "Véhicule : {$or->vehicule->immatriculation} ({$or->vehicule->marque} {$or->vehicule->modele})\nClient : {$or->client->nom_complet}\nMotif : {$or->motif_entree}",
                orId: $or->id
            );
        }

        return redirect()->route('ordres-reparations.show', $or)
            ->with('success', "Ordre de réparation {$or->numero} créé avec succès.");
    }

    /**
     * Affiche la fiche détaillée d'un OR.
     * Charge toutes les relations nécessaires pour éviter les requêtes N+1.
     */
    public function show(OrdreReparation $ordresReparation)
    {
        $ordresReparation->load(['client', 'vehicule.typeMoteur', 'conseiller', 'technicien', 'chef', 'controleQualitePar', 'photos', 'photosOr', 'devis', 'allDevis.lignes', 'allDevis.bonCommande.bonTransfert', 'allDevis.technicien', 'facture', 'dossier.reservation', 'bonsCommande.bonTransfert', 'factures.avoir']);
        return view('ordres-reparations.show', ['or' => $ordresReparation]);
    }

    /**
     * Ajoute des photos à un OR déjà existant (depuis la fiche OR).
     * Chaque photo est stockée dans le dossier public/photos-or/{id}.
     * Si `categorie` est fournie (VIN, tableau de bord, pièce endommagée...),
     * les photos rejoignent le dossier de preuves garantie constructeur
     * (cf. PhotoOr::CATEGORIES) plutôt que les photos génériques du véhicule.
     */
    public function uploadPhotos(Request $request, OrdreReparation $ordresReparation)
    {
        $request->validate([
            'photos_vehicule'   => ['required', 'array', 'max:10'],
            'photos_vehicule.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'categorie'         => ['nullable', 'string', 'in:' . implode(',', array_keys(PhotoOr::CATEGORIES))],
        ], [
            'photos_vehicule.required'   => 'Veuillez sélectionner au moins une photo.',
            'photos_vehicule.*.mimes'    => 'Les photos doivent être au format JPG, PNG ou WEBP.',
            'photos_vehicule.*.max'      => 'Chaque photo ne doit pas dépasser 8 Mo.',
        ]);

        foreach ($request->file('photos_vehicule') as $file) {
            $path = $file->store("photos-or/{$ordresReparation->id}", 'public');
            PhotoOr::create([
                'or_id'        => $ordresReparation->id,
                'chemin'       => $path,
                'nom_original' => $file->getClientOriginalName(),
                'taille'       => $file->getSize(),
                'categorie'    => $request->categorie,
                'type'         => 'photo',
            ]);
        }

        return back()->with('success', count($request->file('photos_vehicule')) . ' photo(s) ajoutée(s).');
    }

    /**
     * Ajoute la vidéo du bruit/panne au dossier de preuves garantie
     * constructeur d'un OR (cf. PhotoOr::CATEGORIES — catégorie fixe
     * 'video_bruit', une seule vidéo attendue mais rien n'empêche d'en
     * réenvoyer une seconde, l'ancienne reste consultable).
     */
    public function uploadVideoGarantie(Request $request, OrdreReparation $ordresReparation)
    {
        $request->validate([
            'video_bruit' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/3gpp', 'max:51200'],
        ], [
            'video_bruit.required'  => 'Veuillez sélectionner une vidéo.',
            'video_bruit.mimetypes' => 'La vidéo doit être au format MP4, MOV ou WEBM.',
            'video_bruit.max'       => 'La vidéo ne doit pas dépasser 50 Mo.',
        ]);

        $file = $request->file('video_bruit');
        $path = $file->store("photos-or/{$ordresReparation->id}", 'public');
        PhotoOr::create([
            'or_id'        => $ordresReparation->id,
            'chemin'       => $path,
            'nom_original' => $file->getClientOriginalName(),
            'taille'       => $file->getSize(),
            'categorie'    => 'video_bruit',
            'type'         => 'video',
        ]);

        return back()->with('success', 'Vidéo ajoutée.');
    }

    /**
     * Supprime une photo (ou vidéo) liée à un OR.
     * Vérifie que la photo appartient bien à cet OR avant de supprimer
     * le fichier physique du disque et l'entrée en base.
     */
    public function supprimerPhoto(OrdreReparation $ordresReparation, PhotoOr $photo)
    {
        // Sécurité : on refuse si la photo n'appartient pas à cet OR
        if ($photo->or_id !== $ordresReparation->id) abort(403);

        Storage::disk('public')->delete($photo->chemin);
        $photo->delete();

        return back()->with('success', 'Photo supprimée.');
    }

    /**
     * Télécharge en une seule archive .zip toutes les photos et vidéos d'un
     * OR (réception, restitution, dossier de preuves garantie) — pour les
     * réutiliser ailleurs (ex: transmission à la marque pour la réclamation
     * garantie constructeur).
     */
    public function telechargerPhotos(OrdreReparation $ordresReparation)
    {
        $ordresReparation->load('photosOr');
        if ($ordresReparation->photosOr->isEmpty()) {
            return back()->with('error', 'Aucune photo à télécharger.');
        }

        $nomZip = "photos-{$ordresReparation->numero}.zip";
        $cheminZip = storage_path("app/tmp-{$ordresReparation->id}-" . uniqid() . '.zip');

        $zip = new \ZipArchive();
        $zip->open($cheminZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $nomsUtilises = [];
        foreach ($ordresReparation->photosOr as $photo) {
            $cheminDisque = Storage::disk('public')->path($photo->chemin);
            if (! is_file($cheminDisque)) continue;

            $prefixe = $photo->getCategorieLabel() ?? ($photo->moment === 'sortie' ? 'Restitution' : 'Reception');
            $nom = $prefixe . '_' . ($photo->nom_original ?: basename($photo->chemin));

            // Évite d'écraser un fichier dans le zip si deux photos portent le même nom
            $nomFinal = $nom;
            $i = 1;
            while (in_array($nomFinal, $nomsUtilises, true)) {
                $nomFinal = pathinfo($nom, PATHINFO_FILENAME) . "_{$i}." . pathinfo($nom, PATHINFO_EXTENSION);
                $i++;
            }
            $nomsUtilises[] = $nomFinal;

            $zip->addFile($cheminDisque, $nomFinal);
        }
        $zip->close();

        return response()->download($cheminZip, $nomZip)->deleteFileAfterSend(true);
    }

    /**
     * Démarre les travaux sur un OR.
     * Accessible par le chef de garage / admin (gerer_ordres) — le technicien n'a pas
     * de compte de connexion, c'est le chef qui pointe le début des travaux pour lui.
     * Enregistre l'heure de début et passe le statut à "en_cours".
     */
    public function demarrerTravaux(OrdreReparation $ordresReparation)
    {


         /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_ordres')) {
            abort(403);
        }

        // Même règle que le bouton « Démarrer les travaux »
        $ordresReparation->load('allDevis.bonCommande');
        if (! $ordresReparation->peutDemarrerTravaux()) {
            return back()->with('error', $ordresReparation->raisonEtapeRefusee('démarrer les travaux'));
        }

        $ordresReparation->update([
            'heure_debut_travaux' => now(),
            'statut'              => 'en_cours',
        ]);
        Activite::journaliser('demarrer_travaux', "Démarrage des travaux sur {$ordresReparation->numero}", $ordresReparation);
        return back()->with('success', 'Travaux démarrés — heure enregistrée.');
    }

    /**
     * Clôture les travaux sur un OR.
     * Enregistre l'heure de fin, la durée estimée si fournie,
     * et passe le statut à "controle_qualite" pour validation par le chef.
     * Exception : un service gratuit (cf. service_gratuit — Service Rapide "Autre"
     * à tarif 0, créé sans devis) saute le contrôle qualité et le lavage, et
     * passe directement en "prêt" pour restitution — pas de facture non plus.
     */
    public function terminerTravaux(Request $request, OrdreReparation $ordresReparation)
    {

         /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_ordres')) {
            abort(403);
        }

        // Même règle que le bouton « Terminer les travaux »
        if (! $ordresReparation->peutTerminerTravaux()) {
            return back()->with('error', $ordresReparation->raisonEtapeRefusee('terminer les travaux'));
        }

        $request->validate([
            'duree_estimee' => ['nullable', 'numeric', 'min:0.5'],
        ], [
            'duree_estimee.numeric' => 'La durée estimée doit être un nombre.',
            'duree_estimee.min'     => 'La durée estimée doit être d\'au moins 0,5 heure.',
        ]);

        $ordresReparation->update(['heure_fin_travaux' => now()]);
        if ($request->filled('duree_estimee')) {
            $ordresReparation->update(['duree_estimee' => (float) $request->duree_estimee]);
        }
        Activite::journaliser('terminer_travaux', "Fin des travaux sur {$ordresReparation->numero}", $ordresReparation);

        // Le véhicule ne passe à l'étape suivante que quand toutes les feuilles
        // (feuille 1 + devis complémentaires) sont terminées.
        $ordresReparation->load('allDevis');
        if (! $ordresReparation->feuillesComplementairesTerminees()) {
            return back()->with('success', 'Feuille 1 terminée — le véhicule reste en cours tant que les feuilles des devis complémentaires ne sont pas terminées.');
        }
        $ordresReparation->update(['statut' => $ordresReparation->service_gratuit ? 'pret' : 'controle_qualite']);

        return $ordresReparation->service_gratuit
            ? back()->with('success', 'Travaux terminés — service gratuit, véhicule prêt (pas de facturation, pas de contrôle qualité/lavage).')
            : back()->with('success', 'Travaux terminés — passage en contrôle qualité.');
    }

    /**
     * Formulaire de correction d'un OR — administrateur uniquement. Rattrape une
     * erreur de saisie de la réception (kilométrage, type de moteur oublié…)
     * sans avoir à tout refaire.
     */
    public function corriger(OrdreReparation $ordresReparation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $ordresReparation->load(['client', 'vehicule.typeMoteur', 'allDevis.lignes', 'facture']);

        return view('ordres-reparations.corriger', [
            'or'          => $ordresReparation,
            'typesMoteur' => TypeMoteur::orderBy('modele')->get(),
            'paliers'     => $ordresReparation->vehicule->type_moteur_id
                ? EntretienService::paliers($ordresReparation->vehicule->type_moteur_id)->pluck('km_seuil')
                : collect(),
        ]);
    }

    /**
     * Enregistre la correction d'un OR (administrateur). Pour un entretien, le
     * palier est recalculé (kilométrage OU délai à la date d'entrée), et les
     * pièces du nouveau palier peuvent être ajoutées au devis principal.
     */
    public function enregistrerCorrection(Request $request, OrdreReparation $ordresReparation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        // Un OR passé par le circuit garantie garde son type (géré par l'équipe garantie)
        $garantie = $ordresReparation->type === 'garantie' || $ordresReparation->statut_garantie !== null;

        $data = $request->validate([
            'type'                  => [$garantie ? 'nullable' : 'required', 'in:normal,sinistre,entretien'],
            'kilometrage_entree'    => ['required', 'integer', 'min:0'],
            'niveau_carburant'      => ['required', 'in:vide,1/4,1/2,3/4,plein'],
            'date_entree'           => ['required', 'date'],
            'heure_entree'          => ['nullable', 'date_format:H:i'],
            'date_sortie_prevue'    => ['nullable', 'date', 'after_or_equal:date_entree'],
            'urgence'               => ['required', 'in:normal,urgent,tres_urgent'],
            'motif_entree'          => ['nullable', 'string'],
            'notes_internes'        => ['nullable', 'string'],
            'type_moteur_id'        => ['nullable', 'exists:types_moteur,id'],
            'palier_manuel'         => ['nullable', 'integer', 'min:0'],
            'ajouter_pieces_palier' => ['nullable', 'boolean'],
        ], [
            'kilometrage_entree.required'      => 'Le kilométrage d\'entrée est obligatoire.',
            'date_sortie_prevue.after_or_equal' => 'La date de sortie prévue ne peut pas précéder la date d\'entrée.',
        ]);

        $vehicule = $ordresReparation->vehicule;
        if ($request->filled('type_moteur_id') && (int) $request->type_moteur_id !== (int) $vehicule->type_moteur_id) {
            $vehicule->update(['type_moteur_id' => (int) $request->type_moteur_id]);
        }

        $type         = $garantie ? $ordresReparation->type : $data['type'];
        $ancienPalier = $ordresReparation->entretien_km_seuil;
        $palier       = null;
        if ($type === 'entretien' && $vehicule->type_moteur_id) {
            $palier = $request->filled('palier_manuel')
                ? (int) $request->palier_manuel
                : EntretienService::resoudrePalier($vehicule, (int) $data['kilometrage_entree'], (int) $vehicule->type_moteur_id, \Carbon\Carbon::parse($data['date_entree']), $ordresReparation->id);
        }

        $ordresReparation->update([
            'type'               => $type,
            'kilometrage_entree' => $data['kilometrage_entree'],
            'niveau_carburant'   => $data['niveau_carburant'],
            'date_entree'        => $data['date_entree'],
            'heure_entree'       => $data['heure_entree'] ?? null,
            'date_sortie_prevue' => $data['date_sortie_prevue'] ?? null,
            'urgence'            => $data['urgence'],
            'motif_entree'       => $data['motif_entree'] ?? $ordresReparation->motif_entree,
            'notes_internes'     => $data['notes_internes'] ?? null,
            'entretien_km_seuil' => $type === 'entretien' ? $palier : null,
        ]);

        // Pièces du palier ajoutées au devis principal (sans doublon, prix à venir du fournisseur)
        $ajoutees = 0;
        if ($request->boolean('ajouter_pieces_palier') && $palier && $vehicule->type_moteur_id) {
            $ordresReparation->load('allDevis.lignes', 'allDevis.ordreReparation.facture');
            $devis = $ordresReparation->devisPrincipal()
                ?? $ordresReparation->allDevis->whereIn('statut', ['brouillon', 'envoye'])->last();
            if ($devis && ! $devis->estFige()) {
                $existantes = $devis->lignes->pluck('designation')->map(fn ($d) => mb_strtolower(trim($d)));
                $taches = EntretienService::piecesDuPalier($vehicule->type_moteur_id, $palier);
                foreach ($taches as $tache) {
                    $libelle = EntretienService::libellePiece($tache->designation);
                    if ($existantes->contains(mb_strtolower($libelle)) || $existantes->contains(mb_strtolower(trim($tache->designation)))) continue;
                    LigneDevis::create([
                        'devis_id'      => $devis->id,
                        'type'          => 'piece',
                        'designation'   => $libelle,
                        'quantite'      => 1,
                        'prix_unitaire' => 0,
                        'total_ht'      => 0,
                    ]);
                    $ajoutees++;
                }
                foreach (EntretienService::mainOeuvreDuPalier($vehicule->type_moteur_id, $palier) as $operation) {
                    if ($existantes->contains(mb_strtolower($operation['designation']))) continue;
                    LigneDevis::create([
                        'devis_id'      => $devis->id,
                        'type'          => 'main_oeuvre',
                        'designation'   => $operation['designation'],
                        'quantite'      => 1,
                        'prix_unitaire' => $operation['prix_unitaire'],
                        'total_ht'      => $operation['prix_unitaire'],
                    ]);
                    $ajoutees++;
                }
                if ($ajoutees > 0) {
                    $devis->load('lignes');
                    $devis->recalculer();
                    \App\Services\DevisWorkflowService::resynchroniserBonCommande($devis->fresh('lignes'));
                }
            }
        }

        $details = [];
        if ((int) $ancienPalier !== (int) $palier) {
            $details[] = 'palier d\'entretien ' . ($ancienPalier ? number_format($ancienPalier, 0, ',', ' ') . ' km' : 'aucun')
                . ' → ' . ($palier ? number_format($palier, 0, ',', ' ') . ' km' : 'aucun');
        }
        if ($ajoutees > 0) {
            $details[] = "{$ajoutees} pièce(s) du palier ajoutée(s) au devis (prix à venir du fournisseur)";
        }

        Activite::journaliser('corriger_or', "Correction administrateur de l'OR {$ordresReparation->numero}" . ($details ? ' — ' . implode(' ; ', $details) : ''), $ordresReparation);

        return redirect()->route('ordres-reparations.show', $ordresReparation)
            ->with('success', 'OR corrigé.' . ($details ? ' ' . ucfirst(implode(' ; ', $details)) . '.' : ''));
    }

    /**
     * Supprime entièrement un OR (administrateur) : devis, bons de commande,
     * photos, notifications. Impossible s'il a déjà une facture. Le dossier de
     * réception d'origine est conservé et repasse au statut « Nouveau ».
     */
    public function supprimer(OrdreReparation $ordresReparation)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        // Même annulée par avoir, une facture reste en comptabilité : l'OR est conservé
        if ($ordresReparation->factures()->exists()) {
            return back()->with('error', 'Impossible de supprimer cet OR : il a déjà été facturé (même une facture annulée par avoir est conservée).');
        }

        $numero   = $ordresReparation->numero;
        $vehicule = $ordresReparation->vehicule?->immatriculation;

        DB::transaction(function () use ($ordresReparation) {
            // Devis (leurs lignes et bons de commande partent avec eux), puis BC restants
            $ordresReparation->allDevis()->get()->each->delete();
            \App\Models\BonCommande::where('or_id', $ordresReparation->id)->delete();
            $ordresReparation->dossier?->update(['statut' => 'nouveau']);
            $ordresReparation->delete();
        });
        // Photos ajoutées directement sur l'OR (celles de la réception restent au dossier)
        Storage::disk('public')->deleteDirectory("photos-or/{$ordresReparation->id}");

        Activite::journaliser('supprimer_or', "Suppression par l'administrateur de l'OR {$numero}" . ($vehicule ? " ({$vehicule})" : ''));

        return redirect()->route('ordres-reparations.index')->with('success', "OR {$numero} supprimé.");
    }

    /**
     * Retrouve un devis complémentaire accepté de cet OR (feuille 2, 3...), ou
     * 404 s'il n'appartient pas à l'OR ou n'est pas une feuille complémentaire.
     */
    private function feuilleComplementaire(OrdreReparation $ordresReparation, Devis $devis): Devis
    {
        $ordresReparation->load('allDevis.bonCommande');
        $feuille = $ordresReparation->devisComplementaires()->firstWhere('id', $devis->id);
        if (! $feuille) abort(404);

        return $feuille;
    }

    /**
     * Affecte un technicien à la feuille de travail d'un devis complémentaire —
     * indépendamment de la feuille 1 et des autres feuilles. Bloqué tant que le
     * bon de commande pièces de CE devis n'est pas entièrement reçu.
     */
    public function affecterFeuille(Request $request, OrdreReparation $ordresReparation, Devis $devis)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('affecter_technicien')) {
            abort(403);
        }

        $devis = $this->feuilleComplementaire($ordresReparation, $devis);
        if ($ordresReparation->travauxClos() || $devis->heure_debut_travaux) {
            return back()->with('error', "Impossible d'affecter : la feuille du devis {$devis->numero} est déjà démarrée ou le véhicule n'est plus en travaux.");
        }
        if ($devis->attendPieces()) {
            return back()->with('error', "Impossible d'affecter : le bon de commande pièces du devis {$devis->numero} n'est pas encore marqué \"Tout reçu\".");
        }

        $request->validate([
            'technicien_id' => ['required', 'exists:techniciens,id'],
            'service'       => ['required', 'in:rapide,mecanique,electricite,carrosserie,peinture'],
            'duree_estimee' => ['nullable', 'numeric', 'min:0.25'],
        ], [
            'technicien_id.required' => 'Veuillez sélectionner un technicien.',
            'technicien_id.exists'   => 'Le technicien sélectionné est introuvable.',
            'service.required'       => 'Veuillez sélectionner le service concerné.',
            'service.in'             => 'Le service sélectionné est invalide.',
            'duree_estimee.numeric'  => 'La durée estimée doit être un nombre.',
            'duree_estimee.min'      => 'La durée estimée doit être d\'au moins 15 minutes (0,25 heure).',
        ]);

        $devis->update([
            'technicien_id'    => $request->technicien_id,
            'service'          => $request->service,
            'chef_id'          => Auth::id(),
            'date_affectation' => now(),
            'duree_estimee'    => $request->duree_estimee ? (float) $request->duree_estimee : null,
        ]);

        $tech = Technicien::find($request->technicien_id);
        Activite::journaliser('affecter_technicien', "Affectation de {$tech->name} sur {$ordresReparation->numero} — devis complémentaire {$devis->numero}", $ordresReparation);
        return back()->with('success', "Technicien affecté à la feuille du devis {$devis->numero}.");
    }

    /**
     * Démarre les travaux d'une feuille complémentaire. Le véhicule passe (ou
     * reste) « En cours ».
     */
    public function demarrerFeuille(OrdreReparation $ordresReparation, Devis $devis)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_ordres')) {
            abort(403);
        }

        $devis = $this->feuilleComplementaire($ordresReparation, $devis);
        if (! $devis->isAffecte()) {
            return back()->with('error', "Affectez d'abord un technicien à la feuille du devis {$devis->numero}.");
        }
        if ($ordresReparation->travauxClos() || $devis->heure_debut_travaux) {
            return back()->with('error', "Impossible de démarrer : la feuille du devis {$devis->numero} est déjà démarrée ou le véhicule n'est plus en travaux.");
        }

        $devis->update(['heure_debut_travaux' => now()]);
        if ($ordresReparation->estAvantAcceptationDevis() || $ordresReparation->statut === 'devis_accepte') {
            $ordresReparation->update(['statut' => 'en_cours']);
        }

        Activite::journaliser('demarrer_travaux', "Démarrage des travaux sur {$ordresReparation->numero} — devis complémentaire {$devis->numero}", $ordresReparation);
        return back()->with('success', "Travaux du devis {$devis->numero} démarrés — heure enregistrée.");
    }

    /**
     * Termine les travaux d'une feuille complémentaire. Quand la feuille 1 et
     * toutes les feuilles complémentaires sont terminées, le véhicule passe au
     * contrôle qualité (ou prêt, pour un service gratuit).
     */
    public function terminerFeuille(OrdreReparation $ordresReparation, Devis $devis)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_ordres')) {
            abort(403);
        }

        $devis = $this->feuilleComplementaire($ordresReparation, $devis);
        if (! $devis->heure_debut_travaux) {
            return back()->with('error', "Les travaux du devis {$devis->numero} n'ont pas encore démarré.");
        }
        if ($ordresReparation->travauxClos() || $devis->heure_fin_travaux) {
            return back()->with('error', "Impossible de terminer : la feuille du devis {$devis->numero} est déjà terminée ou le véhicule n'est plus en travaux.");
        }

        $devis->update(['heure_fin_travaux' => now()]);
        Activite::journaliser('terminer_travaux', "Fin des travaux sur {$ordresReparation->numero} — devis complémentaire {$devis->numero}", $ordresReparation);

        $ordresReparation->load('allDevis');
        if ($ordresReparation->heure_fin_travaux && $ordresReparation->feuillesComplementairesTerminees()) {
            $ordresReparation->update(['statut' => $ordresReparation->service_gratuit ? 'pret' : 'controle_qualite']);
            return back()->with('success', "Travaux du devis {$devis->numero} terminés — toutes les feuilles sont terminées, passage à l'étape suivante.");
        }

        return back()->with('success', "Travaux du devis {$devis->numero} terminés — le véhicule reste en cours tant que les autres feuilles ne sont pas terminées.");
    }

    /**
     * Valide le contrôle qualité et envoie le véhicule en lavage.
     * Pas de signature électronique ni de choix à faire à chaque fois : le
     * responsable qualité est un technicien fixe, désigné une fois pour toutes
     * dans Réglages atelier (cf. ParametreAtelierController::updateControleQualiteTechnicien()) —
     * c'est simplement son nom qui apparaît ensuite dans le cadre "Contrôle
     * qualité" de la feuille de travail imprimée.
     */
    public function validerQualite(OrdreReparation $ordresReparation)
    {
       // if (! auth()->user()->hasPermission('valider_qualite')) abort(403);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('valider_qualite')) {
            abort(403);
        }

        if ($ordresReparation->statut !== 'controle_qualite') {
            return back()->with('error', "Impossible de valider le contrôle qualité : l'OR {$ordresReparation->numero} n'est pas en contrôle qualité (statut « {$ordresReparation->getStatutLabel()} »).");
        }

        $responsableQualite = \App\Models\ParametreAtelier::get()->controle_qualite_technicien_id;
        if (! $responsableQualite) {
            return back()->with('error', 'Aucun responsable qualité n\'est configuré — définissez-le dans Réglages atelier avant de valider.');
        }

        $ordresReparation->update([
            'statut'                         => 'lavage',
            'controle_qualite_technicien_id' => $responsableQualite,
        ]);
        return back()->with('success', 'Contrôle qualité validé — véhicule en lavage.');
    }

    /**
     * Marque le lavage comme terminé et passe le véhicule en statut "prêt".
     * À ce stade, le client peut être contacté pour venir récupérer son véhicule.
     */
    public function terminerLavage(OrdreReparation $ordresReparation)
    {
      //  if (! auth()->user()->hasPermission('valider_lavage')) abort(403);

       /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('valider_lavage')) {
            abort(403);
        }

        if ($ordresReparation->statut !== 'lavage') {
            return back()->with('error', "Impossible de terminer le lavage : l'OR {$ordresReparation->numero} n'est pas au lavage (statut « {$ordresReparation->getStatutLabel()} »).");
        }

        $ordresReparation->update(['statut' => 'pret']);

        $ordresReparation->loadMissing('client', 'vehicule');
        NotificationInterne::notifierVehiculePret(
            'Véhicule prêt à restituer',
            "{$ordresReparation->numero} — {$ordresReparation->vehicule->immatriculation} ({$ordresReparation->client->nom_complet}) est prêt, le client peut venir le récupérer.",
            $ordresReparation->id
        );

        return back()->with('success', 'Lavage terminé — véhicule prêt, client peut être contacté.');
    }

    /**
     * Modifie manuellement le statut d'un OR.
     * Utilisé en cas de correction ou de cas particulier.
     * La modification est tracée dans le journal d'activité.
     */
    public function changerStatut(Request $request, OrdreReparation $ordresReparation)
    {
       // if (! auth()->user()->hasPermission('gerer_ordres')) abort(403);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_ordres')) {
            abort(403);
        }

        // Tant que l'OR est de type garantie, il appartient exclusivement à
        // l'équipe garantie — le chef de garage ne peut pas en changer le
        // statut (cf. changerStatutGarantie() pour approuver/refuser).
        if ($ordresReparation->type === 'garantie') abort(403);

        $request->validate([
            'statut' => ['required', 'in:ouvert,diagnostic,devis_envoye,devis_accepte,en_cours,controle_qualite,lavage,pret,facture,livre,annule'],
        ], [
            'statut.required' => 'Veuillez sélectionner un statut.',
            'statut.in'       => 'Le statut sélectionné est invalide.',
        ]);

        $ordresReparation->update(['statut' => $request->statut]);
        Activite::journaliser('modifier_statut_or', "Statut OR {$ordresReparation->numero} → {$ordresReparation->getStatutLabel()}", $ordresReparation);

        return back()->with('success', "Statut mis à jour : {$ordresReparation->getStatutLabel()}.");
    }

    /**
     * Génère la page d'impression de la fiche de réception (OR).
     * S'ouvre dans un nouvel onglet et lance l'impression automatiquement.
     */
    public function imprimer(OrdreReparation $ordresReparation)
    {
        $ordresReparation->load(['client', 'vehicule', 'conseiller', 'technicien']);
        return view('ordres-reparations.print', ['or' => $ordresReparation]);
    }

    /**
     * Affecte un mécanicien à un OR et définit le service concerné.
     * Cette action est réservée au chef de garage.
     * Elle met aussi à jour la durée estimée des travaux si fournie,
     * et passe le statut à "devis_accepte" pour démarrer la préparation.
     */
    public function affecter(Request $request, OrdreReparation $ordresReparation)
    {
       // if (! auth()->user()->hasPermission('affecter_technicien')) abort(403);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('affecter_technicien')) {
            abort(403);
        }
        // Plus d'affectation une fois le véhicule sorti des travaux (contrôle, prêt, facturé…)
        if (! $ordresReparation->peutEtreAffecte()) {
            return back()->with('error', $ordresReparation->raisonEtapeRefusee('affecter un technicien'));
        }
        // Bloquer si le BC pièces de la feuille 1 n'est pas encore entièrement reçu
        // (les BC des devis complémentaires ne bloquent que leur propre feuille)
        $ordresReparation->load('allDevis.bonCommande');
        if ($ordresReparation->bcBloquantFeuille1()) {
            return back()->with('error', 'Impossible d\'affecter : le bon de commande pièces n\'est pas encore marqué "Tout reçu".');
        }

        $request->validate([
            'technicien_id' => ['required', 'exists:techniciens,id'],
            'service'       => ['required', 'in:rapide,mecanique,electricite,carrosserie,peinture'],
            // min 0.25h (au lieu de 0.5h) pour accepter les durées Service Rapide reprises
            // telles quelles depuis la réservation (ex: 0.25h pour un contrôle pression pneus).
            'duree_estimee' => ['nullable', 'numeric', 'min:0.25'],
        ], [
            'technicien_id.required' => 'Veuillez sélectionner un technicien.',
            'technicien_id.exists'   => 'Le technicien sélectionné est introuvable.',
            'service.required'       => 'Veuillez sélectionner le service concerné.',
            'service.in'             => 'Le service sélectionné est invalide.',
            'duree_estimee.numeric'  => 'La durée estimée doit être un nombre.',
            'duree_estimee.min'      => 'La durée estimée doit être d\'au moins 15 minutes (0,25 heure).',
        ]);

        $ordresReparation->update([
            'technicien_id'    => $request->technicien_id,
            'service'          => $request->service,
            'chef_id'          => Auth::id(),  // L'utilisateur qui affecte devient le chef responsable
            'date_affectation' => now(),
            'duree_estimee'    => $request->duree_estimee ? (float) $request->duree_estimee : null,
        ]);
        // Une réaffectation en cours de travaux ne doit pas faire reculer le véhicule
        if ($ordresReparation->estAvantAcceptationDevis() || $ordresReparation->statut === 'devis_accepte') {
            $ordresReparation->update(['statut' => 'devis_accepte']);
        }

        $tech = Technicien::find($request->technicien_id);
        Activite::journaliser('affecter_technicien', "Affectation de {$tech->name} sur {$ordresReparation->numero}", $ordresReparation);
        return back()->with('success', 'Technicien affecté avec succès.');
    }

    /**
     * Enregistre la fiche de réception signée (scan ou photo du document papier).
     * Si une fiche existait déjà, elle est remplacée et l'ancien fichier supprimé du disque.
     */
    public function uploadFicheSignee(Request $request, OrdreReparation $ordresReparation)
    {
       // if (! auth()->user()->hasPermission('creer_dossiers')) abort(403);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('creer_dossiers')) {
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

        // Suppression de l'ancienne fiche signée si elle existait déjà
        if ($ordresReparation->fiche_signee) {
            Storage::disk('public')->delete($ordresReparation->fiche_signee);
        }

        $path = $request->file('fiche_signee')->store('fiches-signees', 'public');
        $ordresReparation->update(['fiche_signee' => $path]);

        return back()->with('success', 'Fiche de réception signée enregistrée.');
    }

    /**
     * Enregistre la fiche de restitution signée (scan ou photo du document
     * papier remis au client à la sortie) — même principe que la fiche de
     * réception, côté restitution.
     */
    public function uploadFicheSigneeRestitution(Request $request, OrdreReparation $ordresReparation)
    {
        //if (! auth()->user()->hasPermission('restituer_vehicule')) abort(403);

         /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('restituer_vehicule')) {
            abort(403);
        }

        $request->validate([
            'fiche_signee_restitution' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'fiche_signee_restitution.required' => 'Veuillez sélectionner un fichier à uploader.',
            'fiche_signee_restitution.file'     => 'Le fichier uploadé est invalide.',
            'fiche_signee_restitution.mimes'    => 'Le fichier doit être au format PDF, JPG ou PNG.',
            'fiche_signee_restitution.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        if ($ordresReparation->fiche_signee_restitution) {
            Storage::disk('public')->delete($ordresReparation->fiche_signee_restitution);
        }

        $path = $request->file('fiche_signee_restitution')->store('fiches-signees-restitution', 'public');
        $ordresReparation->update(['fiche_signee_restitution' => $path]);

        return back()->with('success', 'Fiche de restitution signée enregistrée.');
    }

    /**
     * Affiche la feuille de travail du mécanicien pour un OR.
     * Inclut les lignes du devis (pièces et main d'œuvre) pour guider les travaux.
     */
    public function feuilletTravail(OrdreReparation $ordresReparation, ?Devis $devis = null)
    {
        $ordresReparation->load(['client', 'vehicule.typeMoteur', 'conseiller', 'technicien', 'chef', 'controleQualitePar', 'allDevis.lignes', 'allDevis.technicien', 'allDevis.chef']);

        $complementaires = $ordresReparation->devisComplementaires();

        // Feuille d'un devis complémentaire : uniquement ses propres lignes, son
        // technicien et son pointage — pas les tableaux d'entretien périodique.
        if ($devis) {
            $index = $complementaires->search(fn ($d) => $d->id === $devis->id);
            if ($index === false) abort(404);
            $devis = $complementaires[$index];

            $feuille = [
                'numero'           => $index + 2,
                'total'            => $complementaires->count() + 1,
                'complementaire'   => true,
                'devis'            => collect([$devis]),
                'technicien'       => $devis->technicien?->name,
                'chef'             => $devis->chef?->name,
                'service_label'    => $devis->getServiceLabel(),
                'date_affectation' => $devis->date_affectation,
                'duree_estimee'    => $devis->duree_estimee !== null ? (float) $devis->duree_estimee : null,
                'heure_debut'      => $devis->heure_debut_travaux,
                'heure_fin'        => $devis->heure_fin_travaux,
                'duree_reelle'     => $devis->getDureeReelleHeures(),
            ];

            return view('ordres-reparations.feuille-travail', ['or' => $ordresReparation, 'tachesEntretien' => null, 'feuille' => $feuille]);
        }

        // Feuille 1 : premier devis accepté (sans devis accepté : tous les devis
        // non refusés, comme avant), avec l'affectation et le pointage de l'OR.
        $principal = $ordresReparation->devisPrincipal();
        $feuille = [
            'numero'           => 1,
            'total'            => $complementaires->count() + 1,
            'complementaire'   => false,
            'devis'            => $principal ? collect([$principal]) : $ordresReparation->allDevis->where('statut', '!=', 'refuse')->values(),
            'technicien'       => $ordresReparation->technicien?->name,
            'chef'             => $ordresReparation->chef?->name,
            'service_label'    => $ordresReparation->getServiceLabel(),
            'date_affectation' => $ordresReparation->date_affectation,
            'duree_estimee'    => $ordresReparation->duree_estimee !== null ? (float) $ordresReparation->duree_estimee : null,
            'heure_debut'      => $ordresReparation->heure_debut_travaux,
            'heure_fin'        => $ordresReparation->heure_fin_travaux,
            'duree_reelle'     => $ordresReparation->getDureeReelleHeures(),
        ];

        $tachesEntretien = null;
        if ($ordresReparation->type === 'entretien' && $ordresReparation->entretien_km_seuil && $ordresReparation->vehicule->type_moteur_id) {
            $tachesEntretien = EntretienService::controlesDuPalier(
                (int) $ordresReparation->vehicule->type_moteur_id,
                (int) $ordresReparation->entretien_km_seuil
            );
        }

        return view('ordres-reparations.feuille-travail', ['or' => $ordresReparation, 'tachesEntretien' => $tachesEntretien, 'feuille' => $feuille]);
    }

    /**
     * Affiche le formulaire de restitution du véhicule au client.
     * Réservé au réceptionniste et à l'admin — le chef de garage ne peut pas restituer.
     * Ce formulaire capture l'état du véhicule à la sortie :
     * kilométrage, carburant, propreté, équipements présents, notes.
     */
    public function restitution(OrdreReparation $ordresReparation)
    {
        //if (! auth()->user()->hasPermission('restituer_vehicule')) abort(403);
         /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('restituer_vehicule')) {
            abort(403);
        }

        // Même contrôle que le bouton « Restituer » — un lien ou un onglet resté ouvert ne suffit plus
        if (! $ordresReparation->peutEtreRestitue()) {
            return redirect()->route('ordres-reparations.show', $ordresReparation)->with('error', $ordresReparation->raisonNonRestituable());
        }

        $ordresReparation->load(['client', 'vehicule', 'conseiller', 'technicien', 'photosOr']);
        return view('ordres-reparations.restitution', ['or' => $ordresReparation]);
    }

    /**
     * Enregistre la restitution du véhicule et clôture l'OR.
     * Réservé au réceptionniste et à l'admin.
     * Conditions vérifiées ici (et plus seulement par le bouton de la vue) : facture
     * payée ou crédit accordé, ou service gratuit terminé.
     * Enregistre l'état de sortie complet et passe le statut à "livre".
     */
    public function restituer(Request $request, OrdreReparation $ordresReparation)
    {
       // if (! auth()->user()->hasPermission('restituer_vehicule')) abort(403);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('restituer_vehicule')) {
            abort(403);
        }

        if (! $ordresReparation->peutEtreRestitue()) {
            return redirect()->route('ordres-reparations.show', $ordresReparation)->with('error', $ordresReparation->raisonNonRestituable());
        }

        $request->validate([
            'kilometrage_sortie'     => ['required', 'integer', 'min:0'],
            'niveau_carburant_sortie'=> ['required', 'in:vide,1/4,1/2,3/4,plein'],
            'proprete_interne_sortie'=> ['required', 'in:bon,acceptable,mauvais'],
            'proprete_externe_sortie'=> ['required', 'in:bon,acceptable,mauvais'],
            'date_sortie_reelle'     => ['required', 'date'],
            'notes_restitution'      => ['nullable', 'string'],
            'dommages_carrosserie_sortie' => ['nullable', 'string'],
            'photos_vehicule_sortie'      => ['required', 'array', 'min:1', 'max:10'],
            'photos_vehicule_sortie.*'    => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'photos_vehicule_sortie.required' => 'Au moins une photo du véhicule est obligatoire à la restitution.',
            'photos_vehicule_sortie.min'       => 'Au moins une photo du véhicule est obligatoire à la restitution.',
        ]);

        // Les équipements présents à la sortie sont cochés dans le formulaire
        $equipements = $request->input('equipements_sortie', []);

        $dommagesSortie = [];
        if ($request->filled('dommages_carrosserie_sortie')) {
            $decoded = json_decode($request->dommages_carrosserie_sortie, true);
            $dommagesSortie = is_array($decoded) ? $decoded : [];
        }

        $ordresReparation->update([
            'kilometrage_sortie'      => $request->kilometrage_sortie,
            'niveau_carburant_sortie' => $request->niveau_carburant_sortie,
            'proprete_interne_sortie' => $request->proprete_interne_sortie,
            'proprete_externe_sortie' => $request->proprete_externe_sortie,
            'equipements_sortie'      => $equipements,
            'dommages_carrosserie_sortie' => $dommagesSortie,
            'notes_restitution'       => $request->notes_restitution,
            'signature_restitution'   => $request->boolean('signature_restitution'),
            'date_sortie_reelle'      => $request->date_sortie_reelle,
            'restitue_par_id'         => Auth::id(),  // Qui a effectué la restitution
            'statut'                  => 'livre',       // L'OR est clôturé
        ]);

        // Photos prises à la restitution, distinguées des photos de réception (moment)
        if ($request->hasFile('photos_vehicule_sortie')) {
            foreach ($request->file('photos_vehicule_sortie') as $file) {
                $path = $file->store("photos-or/{$ordresReparation->id}", 'public');
                PhotoOr::create([
                    'or_id'        => $ordresReparation->id,
                    'moment'       => 'sortie',
                    'chemin'       => $path,
                    'nom_original' => $file->getClientOriginalName(),
                    'taille'       => $file->getSize(),
                ]);
            }
        }

        Activite::journaliser('restituer_vehicule', "Restitution du véhicule — OR {$ordresReparation->numero}", $ordresReparation);

        return redirect()->route('ordres-reparations.show', $ordresReparation)
            ->with('success', "Véhicule restitué — OR {$ordresReparation->numero} clôturé.");
    }

    /**
     * Génère la fiche de restitution imprimable (format A4).
     * Affiche le comparatif entrée/sortie (kilométrage, carburant, équipements, propreté)
     * ainsi que les zones de signature client et réceptionniste.
     */
    public function imprimerRestitution(OrdreReparation $ordresReparation)
    {
       // if (! auth()->user()->hasPermission('restituer_vehicule')) abort(403);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('restituer_vehicule')) {
            abort(403);
        }

        $ordresReparation->load(['client', 'vehicule', 'conseiller', 'restitueePar']);
        return view('ordres-reparations.print-restitution', ['or' => $ordresReparation]);
    }

    /**
     * Traite la décision de garantie (approuvé ou refusé) pour un OR de type garantie.
     * Dans les deux cas, un devis reste à établir avant les travaux (comme un OR
     * normal) — seul le payeur final diffère :
     *   - Approuvée : l'OR repasse en statut "diagnostic" pour permettre de créer un
     *     devis ; la facturation ira ensuite au compte garantie de la marque
     *     (cf. FactureController, MarqueGarantie::pourMarque()).
     *   - Refusée : le motif est enregistré et l'OR redevient un OR normal, pour
     *     établir un devis client et suivre le parcours standard jusqu'à la livraison.
     */
    public function changerStatutGarantie(Request $request, OrdreReparation $ordresReparation)
    {
       // if (! auth()->user()->hasPermission('traiter_garanties')) abort(403);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('traiter_garanties')) {
            abort(403);
        }

        $request->validate([
            'statut_garantie'            => ['required', 'in:approuve,refuse'],
            'motif_approbation_garantie' => ['required_if:statut_garantie,approuve', 'nullable', 'string'],
            'motif_refus_garantie'       => ['required_if:statut_garantie,refuse', 'nullable', 'string'],
            'sortie_garantie'            => ['nullable', 'boolean'],
        ], [
            'statut_garantie.required'               => 'Veuillez sélectionner une décision pour la garantie.',
            'statut_garantie.in'                     => 'La décision sélectionnée est invalide.',
            'motif_approbation_garantie.required_if' => 'Veuillez indiquer le motif de l\'approbation de garantie.',
            'motif_refus_garantie.required_if'       => 'Veuillez indiquer le motif du refus de garantie.',
        ]);

        $update = ['statut_garantie' => $request->statut_garantie];

        // Garantie approuvée → motif enregistré, un devis (facturé à la garantie
        // constructeur) reste à créer avant de démarrer les travaux, comme pour
        // un OR normal.
        if ($request->statut_garantie === 'approuve') {
            $update['statut'] = 'diagnostic';
            $update['motif_approbation_garantie'] = $request->motif_approbation_garantie;
        }
        // Garantie refusée → motif enregistré, l'OR redevient normal (devis client possible)
        elseif ($request->statut_garantie === 'refuse') {
            $update['motif_refus_garantie'] = $request->motif_refus_garantie;
            $update['type'] = 'normal';
        }

        $ordresReparation->update($update);

        // Signalement définitif : ce véhicule ne sera plus jamais proposé au
        // circuit garantie, quelle que soit sa catégorie/son âge par la suite
        // — cf. Vehicule::estEligibleGarantie().
        if ($request->statut_garantie === 'refuse' && $request->boolean('sortie_garantie')) {
            $ordresReparation->vehicule->update(['garantie_sortie_le' => now()]);
            Activite::journaliser(
                'sortie_garantie_vehicule',
                "Véhicule {$ordresReparation->vehicule->immatriculation} marqué définitivement sorti de la garantie (OR {$ordresReparation->numero})",
                $ordresReparation->vehicule
            );
        }

        $motif = $request->statut_garantie === 'refuse' ? $request->motif_refus_garantie : $request->motif_approbation_garantie;
        Activite::journaliser(
            'decision_garantie',
            "Garantie {$request->statut_garantie} pour l'OR {$ordresReparation->numero} — motif : {$motif}",
            $ordresReparation
        );

        return back()->with('success', 'Décision garantie enregistrée.');
    }

    /**
     * Crée automatiquement un devis brouillon avec les pièces à remplacer
     * du barème constructeur pour le palier résolu. Le chef de garage n'a
     * plus qu'à ajouter la main d'œuvre et les prix des pièces.
     */
    private function genererDevisEntretien(OrdreReparation $or, int $typeMoteurId, int $palier): void
    {
        $piecesARemplacer = EntretienService::piecesDuPalier($typeMoteurId, $palier);
        // Opérations du barème facturées en main-d'œuvre (ex : permutation des pneus)
        $operations = EntretienService::mainOeuvreDuPalier($typeMoteurId, $palier);

        if ($piecesARemplacer->isEmpty() && empty($operations)) return;

        DB::transaction(function () use ($or, $piecesARemplacer, $operations) {
            $devis = Devis::create([
                'numero'   => Devis::genererNumero(),
                'or_id'    => $or->id,
                'taux_tva' => 10,
                'statut'   => 'brouillon',
            ]);

            foreach ($operations as $operation) {
                LigneDevis::create([
                    'devis_id'      => $devis->id,
                    'type'          => 'main_oeuvre',
                    'designation'   => $operation['designation'],
                    'quantite'      => 1,
                    'prix_unitaire' => $operation['prix_unitaire'],
                    'total_ht'      => $operation['prix_unitaire'],
                ]);
            }

            foreach ($piecesARemplacer as $tache) {
                LigneDevis::create([
                    'devis_id'      => $devis->id,
                    'type'          => 'piece',
                    'designation'   => EntretienService::libellePiece($tache->designation),
                    'quantite'      => 1,
                    'prix_unitaire' => 0,
                    'total_ht'      => 0,
                ]);
            }

            $devis->recalculer();
        });
    }
}
