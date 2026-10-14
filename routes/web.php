<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ActiviteController;
use App\Http\Controllers\BonCommandeController;
use App\Http\Controllers\GarantieController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevisAvanceController;
use App\Http\Controllers\DevisController;
use App\Http\Controllers\DepannageController;
use App\Http\Controllers\DossierReceptionController;
use App\Http\Controllers\EncaissementGlobalController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrdreReparationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\ParametreAtelierController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SurveillanceAtelierController;
use App\Http\Controllers\TechnicienController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\VehiculeController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('login'));

// ── Visiteurs non connectés ────────────────────────────────────
// Manifeste de l'application (iPad / iPhone : ajout à l'écran d'accueil pour les notifications)
Route::get('/manifest.webmanifest', [NotificationController::class, 'manifeste'])->name('manifeste');

Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,5');

    Route::get('/forgot-password',        [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password',       [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('throttle:5,5');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password',        [ResetPasswordController::class, 'reset'])->name('password.update');
});

// ── Utilisateurs connectés ─────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/recherche', [RechercheController::class, 'index'])->name('recherche');

    // ── Notifications ───────────────────────────────────────────
    Route::get('/notifications/{notification}/ouvrir', [NotificationController::class, 'ouvrir'])->name('notifications.ouvrir');
    Route::post('/notifications/tout-marquer-lu', [NotificationController::class, 'toutMarquerLu'])->name('notifications.tout-marquer-lu');
    Route::patch('/factures/{facture}/mode-paiement', [\App\Http\Controllers\FactureController::class, 'changerModePaiement'])->name('factures.mode-paiement');
    Route::get('/notifications/nouvelles', [NotificationController::class, 'nouvelles'])->name('notifications.nouvelles');
    Route::post('/notifications/push/abonner', [NotificationController::class, 'abonner'])->name('notifications.push.abonner');
    Route::post('/notifications/push/desabonner', [NotificationController::class, 'desabonner'])->name('notifications.push.desabonner');

    // ── Profil personnel (tout compte connecté) ────────────────
    Route::get('/profil',              [ProfilController::class, 'edit'])->name('profil.edit');
    Route::patch('/profil/mot-de-passe', [ProfilController::class, 'updatePassword'])->name('profil.password');
    Route::patch('/profil/langue',       [ProfilController::class, 'updateLangue'])->name('profil.langue');

    // ── Clients ──────────────────────────────────────────────
    // create doit être avant {client} pour éviter le conflit de route
    Route::get('/clients/create',        [ClientController::class, 'create'])->name('clients.create')->middleware('perm:gerer_clients');
    Route::get('/clients',               [ClientController::class, 'index'])->name('clients.index')->middleware('perm:voir_clients');
    Route::post('/clients',              [ClientController::class, 'store'])->name('clients.store')->middleware('perm:gerer_clients');
    Route::get('/clients/{client}',      [ClientController::class, 'show'])->name('clients.show')->middleware('perm:voir_clients');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit')->middleware('perm:gerer_clients');
    Route::put('/clients/{client}',      [ClientController::class, 'update'])->name('clients.update')->middleware('perm:gerer_clients');
    Route::patch('/clients/{client}',    [ClientController::class, 'update'])->middleware('perm:gerer_clients');
    Route::delete('/clients/{client}',   [ClientController::class, 'destroy'])->name('clients.destroy')->middleware('perm:gerer_clients');

    // ── Techniciens ───────────────────────────────────────────
    // create/import doivent être avant {technicien} pour éviter le conflit de route
    Route::get('/techniciens/import',        [TechnicienController::class, 'importForm'])->name('techniciens.import.form')->middleware('perm:gerer_techniciens');
    Route::post('/techniciens/import',        [TechnicienController::class, 'import'])->name('techniciens.import')->middleware('perm:gerer_techniciens');
    Route::get('/techniciens/create',        [TechnicienController::class, 'create'])->name('techniciens.create')->middleware('perm:gerer_techniciens');
    Route::get('/techniciens',               [TechnicienController::class, 'index'])->name('techniciens.index')->middleware('perm:voir_techniciens');
    Route::post('/techniciens',              [TechnicienController::class, 'store'])->name('techniciens.store')->middleware('perm:gerer_techniciens');
    Route::get('/techniciens/{technicien}/edit', [TechnicienController::class, 'edit'])->name('techniciens.edit')->middleware('perm:gerer_techniciens');
    Route::put('/techniciens/{technicien}',      [TechnicienController::class, 'update'])->name('techniciens.update')->middleware('perm:gerer_techniciens');
    Route::patch('/techniciens/{technicien}/actif', [TechnicienController::class, 'toggleActif'])->name('techniciens.toggle-actif')->middleware('perm:gerer_techniciens');
    Route::delete('/techniciens/{technicien}',   [TechnicienController::class, 'destroy'])->name('techniciens.destroy')->middleware('perm:gerer_techniciens');

    // ── Véhicules ─────────────────────────────────────────────
    Route::get('/vehicules/create',          [VehiculeController::class, 'create'])->name('vehicules.create')->middleware('perm:gerer_vehicules');
    Route::get('/vehicules',                 [VehiculeController::class, 'index'])->name('vehicules.index')->middleware('perm:voir_vehicules');
    Route::post('/vehicules',                [VehiculeController::class, 'store'])->name('vehicules.store')->middleware('perm:gerer_vehicules');
    Route::get('/vehicules/{vehicule}',      [VehiculeController::class, 'show'])->name('vehicules.show')->middleware('perm:voir_vehicules');
    Route::get('/vehicules/{vehicule}/edit', [VehiculeController::class, 'edit'])->name('vehicules.edit')->middleware('perm:gerer_vehicules');
    Route::put('/vehicules/{vehicule}',      [VehiculeController::class, 'update'])->name('vehicules.update')->middleware('perm:gerer_vehicules');
    Route::patch('/vehicules/{vehicule}/transferer', [VehiculeController::class, 'transfererProprietaire'])->name('vehicules.transferer')->middleware('perm:gerer_vehicules');
    Route::patch('/vehicules/{vehicule}',    [VehiculeController::class, 'update'])->middleware('perm:gerer_vehicules');
    Route::delete('/vehicules/{vehicule}',   [VehiculeController::class, 'destroy'])->name('vehicules.destroy')->middleware('perm:gerer_vehicules');

    // ── API interne (nécessite les mêmes permissions) ─────────
    Route::get('/api/clients/{client}/vehicules', function (\App\Models\Client $client) {
        return response()->json(
            $client->vehicules()
                ->get(['id', 'immatriculation', 'marque', 'modele', 'vin', 'kilometrage', 'sous_garantie', 'categorie', 'date_mise_circulation', 'garantie_sortie_le', 'type_moteur_id', 'leasing_tiers'])
                ->map(fn ($v) => [
                    'id'                 => $v->id,
                    'immatriculation'    => $v->immatriculation,
                    'marque'             => $v->marque,
                    'modele'             => $v->modele,
                    'vin'                => $v->vin,
                    'kilometrage'        => $v->kilometrage,
                    'type_moteur_id'     => $v->type_moteur_id,
                    'date_mise_circulation' => $v->date_mise_circulation?->format('Y-m-d'),
                    // Éligibilité réelle (garantie + catégorie/âge/km + jamais signalé
                    // "sorti") — pas seulement le champ sous_garantie brut, cf.
                    // Vehicule::estEligibleGarantie(). Même logique que le rendu
                    // initial de dossiers-reception/create.blade.php (@foreach
                    // $clientSelectionne->vehicules) pour que le badge affiché soit
                    // identique, que le véhicule vienne du chargement de page ou de
                    // ce changement de client en AJAX.
                    'eligible_garantie'  => $v->estEligibleGarantie(),
                    'garantie_sortie'    => $v->sous_garantie && ! $v->estEligibleGarantie(),
                    'garantie_motif'     => $v->getMotifSortieGarantieLabel(),
                    'limite_km_garantie' => $v->limite_km_garantie,
                    // Liste leasing (parc, tiers, service, bus) : pas de facture, pièces au récap
                    'leasing'            => \App\Models\Depannage::vehiculeEnLeasing($v),
                ])
        );
    })->name('api.client.vehicules')->middleware('perm:voir_vehicules');

    Route::post('/api/clients/rapide',   [ClientController::class,  'storeRapide'])->name('api.clients.rapide')->middleware('perm:gerer_clients');
    Route::post('/api/vehicules/rapide', [VehiculeController::class, 'storeRapide'])->name('api.vehicules.rapide')->middleware('perm:gerer_vehicules');

    // ── Réception (Panne / Accident / Service Rapide) ──────────
    Route::get('/reception', [ReceptionController::class, 'index'])->name('reception.index')->middleware('perm:creer_dossiers');

    Route::middleware('perm:creer_dossiers')->group(function () {
        Route::get('/dossiers-reception/create', [DossierReceptionController::class, 'create'])->name('dossiers-reception.create');
        Route::post('/dossiers-reception',        [DossierReceptionController::class, 'store'])->name('dossiers-reception.store');
        Route::patch('/dossiers-reception/{dossier}/upload-fiche', [DossierReceptionController::class, 'uploadFicheSignee'])->name('dossiers-reception.upload-fiche');
        // Accident : qui paie (client ou assurance)
        Route::patch('/dossiers-reception/{dossier}/payeur',       [DossierReceptionController::class, 'changerPayeur'])->name('dossiers-reception.payeur');
    });
    Route::middleware('perm:voir_dossiers')->group(function () {
        Route::get('/dossiers-reception',            [DossierReceptionController::class, 'index'])->name('dossiers-reception.index');
        Route::get('/dossiers-reception/{dossier}',  [DossierReceptionController::class, 'show'])->name('dossiers-reception.show');
        Route::get('/dossiers-reception/{dossier}/imprimer', [DossierReceptionController::class, 'imprimer'])->name('dossiers-reception.imprimer');
        Route::delete('/dossiers-reception/{dossier}', [DossierReceptionController::class, 'destroy'])->name('dossiers-reception.destroy');
    });
    Route::middleware('perm:gerer_devis')->group(function () {
        Route::get('/dossiers-reception/{dossier}/devis/creer', [DevisController::class, 'createPourDossier'])->name('dossiers-reception.devis.create');
        Route::post('/dossiers-reception/{dossier}/devis',      [DevisController::class, 'storePourDossier'])->name('dossiers-reception.devis.store');
        Route::patch('/dossiers-reception/{dossier}/diagnostic', [DossierReceptionController::class, 'diagnostiquer'])->name('dossiers-reception.diagnostiquer');
    });

    // ── Dépannage (pièces + main-d'œuvre sans réception ni devis) ──
    Route::middleware('perm:creer_depannages')->group(function () {
        Route::get('/depannages/create', [DepannageController::class, 'create'])->name('depannages.create');
        Route::post('/depannages',       [DepannageController::class, 'store'])->name('depannages.store');
    });
    Route::middleware('perm:voir_depannages')->group(function () {
        Route::get('/depannages',              [DepannageController::class, 'index'])->name('depannages.index');
        Route::get('/depannages/{depannage}',  [DepannageController::class, 'show'])->name('depannages.show');
    });
    Route::post('/depannages/{depannage}/facturer', [DepannageController::class, 'facturer'])->name('depannages.facturer')->middleware('perm:creer_factures');

    // ── Réservations (RDV Service Rapide) ──────────────────────
    Route::middleware('perm:gerer_reservations')->group(function () {
        Route::get('/reservations/create',              [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/reservations',                     [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/honorer',   [ReservationController::class, 'honorer'])->name('reservations.honorer');
        Route::patch('/reservations/{reservation}/annuler',   [ReservationController::class, 'annuler'])->name('reservations.annuler');
        Route::patch('/reservations/{reservation}/no-show',   [ReservationController::class, 'marquerNoShow'])->name('reservations.no-show');
    });
    // Corrections administrateur — réservations et dossiers de réception
    Route::middleware('admin')->group(function () {
        Route::get('/reservations/{reservation}/corriger',       [ReservationController::class, 'corriger'])->name('reservations.corriger');
        Route::put('/reservations/{reservation}/corriger',       [ReservationController::class, 'enregistrerCorrection'])->name('reservations.corriger.enregistrer');
        Route::delete('/reservations/{reservation}',             [ReservationController::class, 'supprimer'])->name('reservations.supprimer');
        Route::get('/dossiers-reception/{dossier}/corriger',     [DossierReceptionController::class, 'corriger'])->name('dossiers-reception.corriger');
        Route::put('/dossiers-reception/{dossier}/corriger',     [DossierReceptionController::class, 'enregistrerCorrection'])->name('dossiers-reception.corriger.enregistrer');
    });

    Route::middleware('perm:voir_reservations')->group(function () {
        Route::get('/reservations',              [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/planning',     [ReservationController::class, 'planning'])->name('reservations.planning');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    });

    // ── Ordres de Réparation ──────────────────────────────────
    // create doit être avant {ordresReparation}
    Route::get('/ordres-reparations/create', [OrdreReparationController::class, 'create'])->name('ordres-reparations.create')->middleware('perm:creer_ordres');
    Route::get('/ordres-reparations',        [OrdreReparationController::class, 'index'])->name('ordres-reparations.index')->middleware('perm:voir_ordres');
    Route::get('/surveillance-atelier',      [SurveillanceAtelierController::class, 'index'])->name('surveillance-atelier.index')->middleware('perm:voir_ordres');
    Route::post('/ordres-reparations',       [OrdreReparationController::class, 'store'])->name('ordres-reparations.store')->middleware('perm:creer_ordres');

    Route::middleware('perm:voir_ordres')->group(function () {
        Route::get('/ordres-reparations/{ordresReparation}',                   [OrdreReparationController::class, 'show'])->name('ordres-reparations.show');
        Route::get('/ordres-reparations/{ordresReparation}/imprimer',          [OrdreReparationController::class, 'imprimer'])->name('ordres-reparations.imprimer');
        Route::get('/ordres-reparations/{ordresReparation}/feuille-travail',   [OrdreReparationController::class, 'feuilletTravail'])->name('ordres-reparations.feuille-travail');
        Route::get('/ordres-reparations/{ordresReparation}/feuille-travail/{devis}', [OrdreReparationController::class, 'feuilletTravail'])->name('ordres-reparations.feuille-travail.devis');
    });

    // Ces routes délèguent le contrôle d'accès à leur permission spécifique
    // (déjà vérifiée dans le contrôleur : affecter_technicien, valider_qualite,
    // valider_lavage, restituer_vehicule, traiter_garanties...). Pas de
    // middleware "perm:gerer_ordres" ici, sinon ça bloquerait par exemple un
    // réceptionniste qui n'a que restituer_vehicule mais pas gerer_ordres.
    Route::middleware('auth')->group(function () {
        Route::patch('/ordres-reparations/{ordresReparation}/statut',          [OrdreReparationController::class, 'changerStatut'])->name('ordres-reparations.statut');
        Route::patch('/ordres-reparations/{ordresReparation}/garantie',        [OrdreReparationController::class, 'changerStatutGarantie'])->name('ordres-reparations.garantie');
        Route::patch('/ordres-reparations/{ordresReparation}/affecter',        [OrdreReparationController::class, 'affecter'])->name('ordres-reparations.affecter');
        Route::patch('/ordres-reparations/{ordresReparation}/demarrer',        [OrdreReparationController::class, 'demarrerTravaux'])->name('ordres-reparations.demarrer');
        Route::patch('/ordres-reparations/{ordresReparation}/terminer',        [OrdreReparationController::class, 'terminerTravaux'])->name('ordres-reparations.terminer');
        // Feuilles de travail des devis complémentaires (une par devis accepté)
        Route::patch('/ordres-reparations/{ordresReparation}/feuilles/{devis}/affecter', [OrdreReparationController::class, 'affecterFeuille'])->name('ordres-reparations.feuilles.affecter');
        Route::patch('/ordres-reparations/{ordresReparation}/feuilles/{devis}/demarrer', [OrdreReparationController::class, 'demarrerFeuille'])->name('ordres-reparations.feuilles.demarrer');
        Route::patch('/ordres-reparations/{ordresReparation}/feuilles/{devis}/terminer', [OrdreReparationController::class, 'terminerFeuille'])->name('ordres-reparations.feuilles.terminer');
        // Corrections administrateur (erreur de saisie) — sauf OR facturé pour la suppression
        Route::middleware('admin')->group(function () {
            Route::get('/ordres-reparations/{ordresReparation}/corriger', [OrdreReparationController::class, 'corriger'])->name('ordres-reparations.corriger');
            Route::put('/ordres-reparations/{ordresReparation}/corriger', [OrdreReparationController::class, 'enregistrerCorrection'])->name('ordres-reparations.corriger.enregistrer');
            Route::delete('/ordres-reparations/{ordresReparation}',       [OrdreReparationController::class, 'supprimer'])->name('ordres-reparations.supprimer');
        });
        Route::patch('/ordres-reparations/{ordresReparation}/valider-qualite', [OrdreReparationController::class, 'validerQualite'])->name('ordres-reparations.valider-qualite');
        Route::patch('/ordres-reparations/{ordresReparation}/terminer-lavage', [OrdreReparationController::class, 'terminerLavage'])->name('ordres-reparations.terminer-lavage');
        Route::get('/ordres-reparations/{ordresReparation}/restitution',        [OrdreReparationController::class, 'restitution'])->name('ordres-reparations.restitution');
        Route::post('/ordres-reparations/{ordresReparation}/restituer',         [OrdreReparationController::class, 'restituer'])->name('ordres-reparations.restituer');
        Route::get('/ordres-reparations/{ordresReparation}/imprimer-restitution', [OrdreReparationController::class, 'imprimerRestitution'])->name('ordres-reparations.imprimer-restitution');
        // Délègue au contrôleur (creer_dossiers / restituer_vehicule) — pas de
        // perm:gerer_ordres ici, sinon le réceptionniste ne pourrait pas uploader
        // ses propres fiches signées (réception ou restitution).
        Route::patch('/ordres-reparations/{ordresReparation}/upload-fiche',             [OrdreReparationController::class, 'uploadFicheSignee'])->name('ordres-reparations.upload-fiche');
        // Accident : qui paie (client ou assurance), jusqu'à la facture
        Route::patch('/ordres-reparations/{ordresReparation}/payeur',                   [OrdreReparationController::class, 'changerPayeur'])->name('ordres-reparations.payeur');
        Route::patch('/ordres-reparations/{ordresReparation}/upload-fiche-restitution', [OrdreReparationController::class, 'uploadFicheSigneeRestitution'])->name('ordres-reparations.upload-fiche-restitution');
    });

    Route::middleware('perm:gerer_ordres')->group(function () {
        Route::post('/ordres-reparations/{ordresReparation}/photos',           [OrdreReparationController::class, 'uploadPhotos'])->name('ordres-reparations.photos.upload');
        Route::post('/ordres-reparations/{ordresReparation}/video-garantie',   [OrdreReparationController::class, 'uploadVideoGarantie'])->name('ordres-reparations.video-garantie.upload');
        Route::delete('/ordres-reparations/{ordresReparation}/photos/{photo}', [OrdreReparationController::class, 'supprimerPhoto'])->name('ordres-reparations.photos.supprimer');
        Route::get('/ordres-reparations/{ordresReparation}/photos/telecharger', [OrdreReparationController::class, 'telechargerPhotos'])->name('ordres-reparations.photos.telecharger');
    });

    // ── Devis ─────────────────────────────────────────────────
    Route::middleware('perm:voir_devis')->group(function () {
        Route::get('/devis',              [DevisController::class, 'index'])->name('devis.index');
        Route::get('/devis/{devis}',      [DevisController::class, 'show'])->name('devis.show');
        Route::get('/devis/{devis}/imprimer', [DevisController::class, 'imprimer'])->name('devis.imprimer');
    });
    Route::middleware('perm:gerer_devis')->group(function () {
        Route::get('/ordres-reparations/{ordresReparation}/devis/creer',  [DevisController::class, 'create'])->name('devis.create');
        // Devis en avance (depuis une réservation, ou devis libre) — sans réception
        Route::get('/devis-en-avance/creer',                              [DevisAvanceController::class, 'create'])->name('devis-avance.create');
        Route::post('/devis-en-avance',                                   [DevisAvanceController::class, 'store'])->name('devis-avance.store');
        Route::post('/ordres-reparations/{ordresReparation}/devis',       [DevisController::class, 'store'])->name('devis.store');
        Route::get('/devis/{devis}/modifier',                             [DevisController::class, 'edit'])->name('devis.edit');
        Route::put('/devis/{devis}',                                      [DevisController::class, 'update'])->name('devis.update');
        Route::delete('/devis/{devis}',                                   [DevisController::class, 'destroy'])->name('devis.destroy');
    });
    // Validation au client (envoyer/accepter/refuser/upload signature) : délègue au
    // contrôleur (peutValiderDevis() = gerer_devis OU valider_devis) — pas de
    // middleware perm:gerer_devis ici, sinon quelqu'un ayant seulement valider_devis
    // serait bloqué avant même d'atteindre le contrôleur.
    Route::middleware('perm:voir_devis')->group(function () {
        Route::patch('/devis/{devis}/envoyer',          [DevisController::class, 'marquerEnvoye'])->name('devis.envoyer');
        Route::patch('/devis/{devis}/accepter',         [DevisController::class, 'accepter'])->name('devis.accepter');
        Route::patch('/devis/{devis}/refuser',          [DevisController::class, 'refuser'])->name('devis.refuser');
        Route::patch('/devis/{devis}/upload-signature', [DevisController::class, 'uploadSignature'])->name('devis.upload-signature');
        // Correction administrateur : accepté ↔ refusé
        Route::patch('/devis/{devis}/changer-decision', [DevisController::class, 'changerDecision'])->name('devis.changer-decision')->middleware('admin');
    });

    // ── Factures ──────────────────────────────────────────────
    Route::middleware('perm:voir_factures')->group(function () {
        Route::get('/factures',                        [FactureController::class, 'index'])->name('factures.index');
        Route::get('/factures/bons-commande-clients',  [FactureController::class, 'bonsCommandeClients'])->name('factures.bons-commande-clients');
        Route::get('/factures/a-facturer',             [FactureController::class, 'aFacturer'])->name('factures.a-facturer');
        Route::get('/factures/{facture}',              [FactureController::class, 'show'])->name('factures.show');
        Route::get('/factures/{facture}/imprimer',     [FactureController::class, 'imprimer'])->name('factures.imprimer');
    });
    Route::middleware('perm:creer_factures')->group(function () {
        Route::get('/ordres-reparations/{ordresReparation}/facture/creer', [FactureController::class, 'create'])->name('factures.create');
        Route::post('/ordres-reparations/{ordresReparation}/facture',      [FactureController::class, 'store'])->name('factures.store');
    });
    Route::patch('/factures/{facture}/payer',          [FactureController::class, 'marquerPayee'])->name('factures.payer')->middleware('perm:encaisser_factures');
    // Avoirs : annuler ou corriger une facture émise (administrateur uniquement)
    Route::middleware('admin')->group(function () {
        Route::patch('/factures/{facture}/annuler',        [FactureController::class, 'annuler'])->name('factures.annuler');
        Route::get('/factures/{facture}/corriger',         [FactureController::class, 'corriger'])->name('factures.corriger');
        Route::put('/factures/{facture}/corriger',         [FactureController::class, 'enregistrerCorrection'])->name('factures.corriger.enregistrer');
    });
    Route::middleware('perm:voir_factures')->group(function () {
        Route::get('/avoirs',                              [FactureController::class, 'avoirs'])->name('avoirs.index');
        Route::get('/avoirs/{avoir}/imprimer',             [FactureController::class, 'imprimerAvoir'])->name('avoirs.imprimer');
    });
    Route::patch('/avoirs/{avoir}/rembourser',             [FactureController::class, 'rembourserAvoir'])->name('avoirs.rembourser')->middleware('perm:encaisser_factures');
    Route::middleware('perm:gerer_compte_credit')->group(function () {
        Route::patch('/factures/{facture}/credit',         [FactureController::class, 'accorderCredit'])->name('factures.credit');
        Route::patch('/factures/{facture}/revoquer-credit',[FactureController::class, 'revoquerCredit'])->name('factures.revoquer-credit');
    });

    // ── Encaissements globaux ─────────────────────────────────
    Route::get('/encaissements-globaux/creer', [EncaissementGlobalController::class, 'create'])->name('encaissements-globaux.create')->middleware('perm:gerer_encaissements');
    Route::middleware('perm:voir_encaissements')->group(function () {
        Route::get('/encaissements-globaux',                                [EncaissementGlobalController::class, 'index'])->name('encaissements-globaux.index');
        Route::get('/encaissements-globaux/{encaissementsGlobaux}',         [EncaissementGlobalController::class, 'show'])->name('encaissements-globaux.show');
    });
    Route::middleware('perm:gerer_encaissements')->group(function () {
        Route::post('/encaissements-globaux',                               [EncaissementGlobalController::class, 'store'])->name('encaissements-globaux.store');
        Route::patch('/encaissements-globaux/{encaissementsGlobaux}/payer', [EncaissementGlobalController::class, 'marquerPaye'])->name('encaissements-globaux.payer');
    });

    // ── Bons de commande (suivi disponibilité fournisseur) ─────
    Route::middleware('perm:voir_bons_commande')->group(function () {
        Route::get('/bons-commande',                               [BonCommandeController::class, 'index'])->name('bons-commande.index');
        Route::get('/bons-commande/{bonCommande}/bon-transfert', [BonCommandeController::class, 'voirBonTransfert'])->name('bons-commande.bon-transfert');
        Route::get('/bons-commande/{bonCommande}',                 [BonCommandeController::class, 'show'])->name('bons-commande.show');
    });
    Route::middleware('perm:gerer_bons_commande')->group(function () {
        Route::post('/bons-commande/{bonCommande}/bon-transfert',                  [BonCommandeController::class, 'enregistrerBonTransfert'])->name('bons-commande.bon-transfert.enregistrer');
        Route::patch('/bons-commande/{bonCommande}/recevoir',                      [BonCommandeController::class, 'marquerRecu'])->name('bons-commande.recevoir');
        Route::patch('/bons-commande/{bonCommande}/ligne/{ligneId}/recu',          [BonCommandeController::class, 'marquerLigneRecue'])->name('bons-commande.ligne-recu');
        // Corrections administrateur — même après « Tout reçu »
        Route::middleware('admin')->group(function () {
            Route::put('/bons-commande/{bonCommande}/corriger',  [BonCommandeController::class, 'corriger'])->name('bons-commande.corriger');
            Route::patch('/bons-commande/{bonCommande}/rouvrir', [BonCommandeController::class, 'rouvrir'])->name('bons-commande.rouvrir');
            Route::delete('/bons-commande/{bonCommande}',        [BonCommandeController::class, 'supprimer'])->name('bons-commande.supprimer');
        });
    });

    // ── Parc leasing : liste du parc (sans facture au passage) et récaps de période ──
    // Droits vérifiés dans LeasingController : listes = administrateur, récaps = caissière
    Route::group([], function () {
        Route::get('/leasing',                          [\App\Http\Controllers\LeasingController::class, 'index'])->name('leasing.index');
        Route::post('/leasing',                         [\App\Http\Controllers\LeasingController::class, 'store'])->name('leasing.store');
        Route::delete('/leasing/{vehiculeLeasing}',     [\App\Http\Controllers\LeasingController::class, 'destroy'])->name('leasing.destroy');
        Route::get('/leasing/recaps',                   [\App\Http\Controllers\LeasingController::class, 'recaps'])->name('leasing.recaps');
        Route::post('/leasing/recaps',                  [\App\Http\Controllers\LeasingController::class, 'generer'])->name('leasing.recaps.generer');
        Route::get('/leasing/recaps/{recap}',           [\App\Http\Controllers\LeasingController::class, 'show'])->name('leasing.recaps.show');
        Route::get('/leasing/recaps/{recap}/imprimer',  [\App\Http\Controllers\LeasingController::class, 'imprimer'])->name('leasing.recaps.imprimer');
        Route::get('/leasing/recaps/{recap}/excel',     [\App\Http\Controllers\LeasingController::class, 'exporter'])->name('leasing.recaps.excel');
        // Leasing tiers : véhicules de particuliers loués à STCD
        // ── Véhicules de service (affectés à un service / un directeur) : même logique que le parc STCD ──
        Route::get('/vehicules-service',                      [\App\Http\Controllers\LeasingController::class, 'index'])->defaults('categorie', 'service')->name('vehicules-service.index');
        Route::post('/vehicules-service',                     [\App\Http\Controllers\LeasingController::class, 'store'])->defaults('categorie', 'service')->name('vehicules-service.store');
        Route::patch('/vehicules-service/{vehiculeLeasing}',  [\App\Http\Controllers\LeasingController::class, 'changerAffectation'])->whereNumber('vehiculeLeasing')->defaults('categorie', 'service')->name('vehicules-service.affectation');
        Route::delete('/vehicules-service/{vehiculeLeasing}', [\App\Http\Controllers\LeasingController::class, 'destroy'])->whereNumber('vehiculeLeasing')->defaults('categorie', 'service')->name('vehicules-service.destroy');
        Route::get('/vehicules-service/modele-liste',         [\App\Http\Controllers\LeasingController::class, 'modeleParc'])->defaults('categorie', 'service')->name('vehicules-service.parc.modele');
        Route::post('/vehicules-service/liste/apercu',        [\App\Http\Controllers\LeasingController::class, 'apercuParc'])->defaults('categorie', 'service')->name('vehicules-service.parc.apercu');
        Route::post('/vehicules-service/liste/importer',      [\App\Http\Controllers\LeasingController::class, 'importerParc'])->defaults('categorie', 'service')->name('vehicules-service.parc.importer');
        Route::get('/leasing/modele-liste',                   [\App\Http\Controllers\LeasingController::class, 'modeleParc'])->name('leasing.parc.modele');
        Route::post('/leasing/liste/apercu',                  [\App\Http\Controllers\LeasingController::class, 'apercuParc'])->name('leasing.parc.apercu');
        Route::post('/leasing/liste/importer',                [\App\Http\Controllers\LeasingController::class, 'importerParc'])->name('leasing.parc.importer');
        Route::get('/vehicules-service/recaps',               [\App\Http\Controllers\LeasingController::class, 'recapsService'])->name('vehicules-service.recaps');
        Route::patch('/leasing/{vehiculeLeasing}/proprietaire', [\App\Http\Controllers\LeasingController::class, 'changerAffectation'])->whereNumber('vehiculeLeasing')->name('leasing.affectation');

        Route::get('/leasing-tiers/recaps',             [\App\Http\Controllers\LeasingController::class, 'recapsTiers'])->name('leasing-tiers.recaps');
        Route::patch('/clients/{client}/leasing-tiers', [\App\Http\Controllers\LeasingController::class, 'vehiculesTiers'])->name('leasing-tiers.vehicules');
        Route::get('/leasing/recaps/{recap}/client/{client}', [\App\Http\Controllers\LeasingController::class, 'imprimerClient'])->name('leasing.recaps.client');

        // ── Bus leasing : bus réparés dans un autre garage, BC directs, récap mensuel (pas de facture) ──
        // Droits vérifiés dans le contrôleur : liste des bus → administrateur ; BC → gestion des BC ; récaps → caissière
        Route::get('/leasing-bus',                      [\App\Http\Controllers\BusLeasingController::class, 'index'])->name('leasing-bus.index');
        Route::get('/leasing-bus/modele-liste',         [\App\Http\Controllers\BusLeasingController::class, 'modeleParc'])->name('leasing-bus.parc.modele');
        Route::post('/leasing-bus/liste/apercu',        [\App\Http\Controllers\BusLeasingController::class, 'apercuParc'])->name('leasing-bus.parc.apercu');
        Route::post('/leasing-bus/liste/importer',      [\App\Http\Controllers\BusLeasingController::class, 'importerParc'])->name('leasing-bus.parc.importer');
        Route::delete('/leasing-bus/{vehiculeLeasing}', [\App\Http\Controllers\BusLeasingController::class, 'destroy'])->whereNumber('vehiculeLeasing')->name('leasing-bus.destroy');
        Route::get('/leasing-bus/bons-commande/creer',  [\App\Http\Controllers\BusLeasingController::class, 'creerBc'])->name('leasing-bus.bc.create');
        Route::post('/leasing-bus/bons-commande',       [\App\Http\Controllers\BusLeasingController::class, 'enregistrerBc'])->name('leasing-bus.bc.store');
        Route::get('/leasing-bus/modele-pieces',        [\App\Http\Controllers\BusLeasingController::class, 'modeleBc'])->name('leasing-bus.bc.modele');
        Route::post('/leasing-bus/bons-commande/apercu',   [\App\Http\Controllers\BusLeasingController::class, 'apercuBc'])->name('leasing-bus.bc.apercu');
        Route::post('/leasing-bus/bons-commande/importer', [\App\Http\Controllers\BusLeasingController::class, 'importerBc'])->name('leasing-bus.bc.importer');
        Route::get('/leasing-bus/recaps',               [\App\Http\Controllers\LeasingController::class, 'recapsBus'])->name('leasing-bus.recaps');

        // ── Bus du transport public : même fonctionnement que le bus leasing, liste et récap à part ──
        Route::get('/bus-transport',                      [\App\Http\Controllers\BusLeasingController::class, 'index'])->defaults('groupe', 'transport')->name('bus-transport.index');
        Route::get('/bus-transport/modele-liste',         [\App\Http\Controllers\BusLeasingController::class, 'modeleParc'])->defaults('groupe', 'transport')->name('bus-transport.parc.modele');
        Route::post('/bus-transport/liste/apercu',        [\App\Http\Controllers\BusLeasingController::class, 'apercuParc'])->defaults('groupe', 'transport')->name('bus-transport.parc.apercu');
        Route::post('/bus-transport/liste/importer',      [\App\Http\Controllers\BusLeasingController::class, 'importerParc'])->defaults('groupe', 'transport')->name('bus-transport.parc.importer');
        Route::delete('/bus-transport/{vehiculeLeasing}', [\App\Http\Controllers\BusLeasingController::class, 'destroy'])->whereNumber('vehiculeLeasing')->defaults('groupe', 'transport')->name('bus-transport.destroy');
        Route::get('/bus-transport/bons-commande/creer',  [\App\Http\Controllers\BusLeasingController::class, 'creerBc'])->defaults('groupe', 'transport')->name('bus-transport.bc.create');
        Route::post('/bus-transport/bons-commande',       [\App\Http\Controllers\BusLeasingController::class, 'enregistrerBc'])->defaults('groupe', 'transport')->name('bus-transport.bc.store');
        Route::get('/bus-transport/modele-pieces',        [\App\Http\Controllers\BusLeasingController::class, 'modeleBc'])->defaults('groupe', 'transport')->name('bus-transport.bc.modele');
        Route::post('/bus-transport/bons-commande/apercu',   [\App\Http\Controllers\BusLeasingController::class, 'apercuBc'])->defaults('groupe', 'transport')->name('bus-transport.bc.apercu');
        Route::post('/bus-transport/bons-commande/importer', [\App\Http\Controllers\BusLeasingController::class, 'importerBc'])->defaults('groupe', 'transport')->name('bus-transport.bc.importer');
        Route::get('/bus-transport/recaps',               [\App\Http\Controllers\LeasingController::class, 'recapsBus'])->defaults('groupe', 'transport')->name('bus-transport.recaps');
    });

    // ── Garanties ─────────────────────────────────────────────
    Route::get('/garanties', [GarantieController::class, 'index'])->name('garanties.index')->middleware('perm:voir_garanties');

    // ── Rapports ──────────────────────────────────────────────
    Route::middleware('perm:voir_rapports')->group(function () {
        Route::get('/rapports',                     [RapportController::class, 'index'])->name('rapports.index');
        Route::get('/rapports/historique-vehicule', [RapportController::class, 'historiqueVehicule'])->name('rapports.historique-vehicule');
        Route::get('/rapports/historique-client',   [RapportController::class, 'historiqueClient'])->name('rapports.historique-client');
    });

    // ── Admin uniquement ──────────────────────────────────────
    Route::middleware('admin')->group(function () {
        Route::get('/activites', [ActiviteController::class, 'index'])->name('activites.index');

        Route::get('/utilisateurs',                          [UtilisateurController::class, 'index'])->name('utilisateurs.index');
        Route::patch('/utilisateurs/{utilisateur}',          [UtilisateurController::class, 'update'])->name('utilisateurs.update');
        Route::patch('/utilisateurs/{utilisateur}/password', [UtilisateurController::class, 'resetPassword'])->name('utilisateurs.reset-password');
        Route::delete('/utilisateurs/{utilisateur}',         [UtilisateurController::class, 'destroy'])->name('utilisateurs.destroy');

        Route::get('/utilisateurs/{utilisateur}/permissions',  [PermissionController::class, 'edit'])->name('utilisateurs.permissions.edit');
        Route::put('/utilisateurs/{utilisateur}/permissions',  [PermissionController::class, 'update'])->name('utilisateurs.permissions.update');

        Route::get('/register',  [RegisterController::class, 'showRegisterForm'])->name('register');
        Route::post('/register', [RegisterController::class, 'register']);
    });

    // ── Paramètres de l'atelier (horaires, pauses, capacité Service Rapide) ──
    // Accessible à l'admin et au chef de garage (pas admin-only : le chef
    // d'atelier doit pouvoir régler la capacité Service Rapide lui-même).
    Route::middleware('perm:gerer_parametres_atelier')->group(function () {
        Route::get('/parametres',                                    [ParametreAtelierController::class, 'index'])->name('parametres.index');
        Route::patch('/parametres',                                  [ParametreAtelierController::class, 'update'])->name('parametres.update');
        Route::patch('/parametres/capacite',                         [ParametreAtelierController::class, 'updateCapacite'])->name('parametres.capacite.update');
        Route::patch('/parametres/tarifs',                           [ParametreAtelierController::class, 'updateTarifs'])->name('parametres.tarifs.update');
        Route::patch('/parametres/controle-qualite',                 [ParametreAtelierController::class, 'updateControleQualiteTechnicien'])->name('parametres.controle-qualite.update');
        Route::patch('/parametres/forfait-leasing',                  [ParametreAtelierController::class, 'updateForfaitLeasing'])->name('parametres.forfait-leasing.update');
        Route::patch('/parametres/forfait-bus-leasing',              [ParametreAtelierController::class, 'updateForfaitBusLeasing'])->name('parametres.forfait-bus-leasing.update');
        Route::patch('/parametres/majoration-assurance',             [ParametreAtelierController::class, 'updateMajorationAssurance'])->name('parametres.majoration-assurance.update');
        Route::post('/parametres/pauses',                            [ParametreAtelierController::class, 'storePause'])->name('parametres.pauses.store');
        Route::patch('/parametres/pauses/{pause}/toggle',            [ParametreAtelierController::class, 'togglePause'])->name('parametres.pauses.toggle');
        Route::delete('/parametres/pauses/{pause}',                  [ParametreAtelierController::class, 'destroyPause'])->name('parametres.pauses.destroy');
        Route::post('/parametres/marques-garantie',                          [ParametreAtelierController::class, 'storeMarqueGarantie'])->name('parametres.marques-garantie.store');
        Route::patch('/parametres/marques-garantie/{marqueGarantie}',        [ParametreAtelierController::class, 'updateMarqueGarantie'])->name('parametres.marques-garantie.update');
        Route::patch('/parametres/marques-garantie/{marqueGarantie}/toggle', [ParametreAtelierController::class, 'toggleMarqueGarantie'])->name('parametres.marques-garantie.toggle');
        Route::delete('/parametres/marques-garantie/{marqueGarantie}',       [ParametreAtelierController::class, 'destroyMarqueGarantie'])->name('parametres.marques-garantie.destroy');
        Route::post('/parametres/services-rapides',                          [ParametreAtelierController::class, 'storeServiceRapide'])->name('parametres.services-rapides.store');
        Route::patch('/parametres/services-rapides/{serviceRapide}',         [ParametreAtelierController::class, 'updateServiceRapide'])->name('parametres.services-rapides.update');
        Route::patch('/parametres/services-rapides/{serviceRapide}/toggle',  [ParametreAtelierController::class, 'toggleServiceRapide'])->name('parametres.services-rapides.toggle');
        Route::delete('/parametres/services-rapides/{serviceRapide}',        [ParametreAtelierController::class, 'destroyServiceRapide'])->name('parametres.services-rapides.destroy');
    });
});
