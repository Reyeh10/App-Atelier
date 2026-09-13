<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Devis;
use App\Models\DossierReception;
use App\Models\LigneDevis;
use App\Models\OrdreReparation;
use App\Models\User;
use App\Services\DevisWorkflowService;
use App\Services\OperationsMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DevisController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | AUTHENTIFICATION / AUTORISATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Retourne l'utilisateur connecté avec un type connu par Intelephense.
     */
    private function utilisateurConnecte(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(
                401,
                'Vous devez être connecté pour effectuer cette action.'
            );
        }

        return $user;
    }

    /**
     * Vérifie la permission de gestion des devis.
     */
    private function verifierPermissionDevis(): User
    {
        $user = $this->utilisateurConnecte();

        if (!$user->hasPermission('gerer_devis')) {
            abort(
                403,
                'Vous n\'avez pas la permission de gérer les devis.'
            );
        }

        return $user;
    }

    /**
     * Vérifie que l'utilisateur peut valider un devis.
     */
    private function verifierPermissionValidationDevis(): User
    {
        $user = $this->utilisateurConnecte();

        if (!$user->peutValiderDevis()) {
            abort(
                403,
                'Vous n\'avez pas la permission de valider ce devis.'
            );
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | LISTE
    |--------------------------------------------------------------------------
    */

    /**
     * Liste tous les devis.
     */
    public function index(): View
    {
        $devis = Devis::query()
            ->with([
                'ordreReparation.client',
                'ordreReparation.vehicule',
                'dossier.client',
                'dossier.vehicule',
            ])
            ->latest()
            ->paginate(25);

        return view(
            'devis.index',
            compact('devis')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CRÉATION POUR UN ORDRE DE RÉPARATION
    |--------------------------------------------------------------------------
    */

    /**
     * Affiche le formulaire de création d'un devis pour un OR.
     */
    public function create(
        OrdreReparation $ordresReparation
    ): View {
        $this->verifierPermissionDevis();

        return view(
            'devis.create',
            [
                'parent' => $ordresReparation,

                'parentLabel' =>
                    'Ordre de réparation',

                'formAction' => route(
                    'devis.store',
                    $ordresReparation
                ),

                'backHref' => route(
                    'ordres-reparations.show',
                    $ordresReparation
                ),

                'operationsMaintenance' =>
                    OperationsMaintenanceService::liste(),

                'dureesOperations' =>
                    OperationsMaintenanceService::dureesParDesignation(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CRÉATION POUR UN DOSSIER DE RÉCEPTION
    |--------------------------------------------------------------------------
    */

    /**
     * Affiche le formulaire de création d'un devis pour un dossier.
     */
    public function createPourDossier(
        DossierReception $dossier
    ): View {
        $this->verifierPermissionDevis();

        return view(
            'devis.create',
            [
                'parent' =>
                    $dossier,

                'parentLabel' =>
                    'Dossier de réception',

                'formAction' => route(
                    'dossiers-reception.devis.store',
                    $dossier
                ),

                'backHref' => route(
                    'dossiers-reception.show',
                    $dossier
                ),

                'operationsMaintenance' =>
                    OperationsMaintenanceService::liste(),

                'dureesOperations' =>
                    OperationsMaintenanceService::dureesParDesignation(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ENREGISTREMENT POUR OR
    |--------------------------------------------------------------------------
    */

    /**
     * Enregistre un nouveau devis pour un ordre de réparation.
     */
    public function store(
        Request $request,
        OrdreReparation $ordresReparation
    ): RedirectResponse {
        $this->verifierPermissionDevis();

        $this->validerDonneesDevis($request);

        $devis = DB::transaction(
            function () use (
                $request,
                $ordresReparation
            ) {
                /*
                |--------------------------------------------------------------------------
                | Création du devis
                |--------------------------------------------------------------------------
                */

                $devis = Devis::create([
                    'numero' =>
                        Devis::genererNumero(),

                    'or_id' =>
                        $ordresReparation->id,

                    'taux_tva' =>
                        $request->input('taux_tva'),

                    'notes' =>
                        $request->input('notes'),

                    'statut' =>
                        'brouillon',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Création des lignes
                |--------------------------------------------------------------------------
                */

                $this->creerLignesDevis(
                    $devis,
                    $request->input('lignes', [])
                );

                /*
                |--------------------------------------------------------------------------
                | Totaux
                |--------------------------------------------------------------------------
                */

                $devis->recalculer();

                /*
                |--------------------------------------------------------------------------
                | Mise à jour OR
                |--------------------------------------------------------------------------
                */

                $ordresReparation->update([
                    'statut' => 'diagnostic',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Journal
                |--------------------------------------------------------------------------
                */

                Activite::journaliser(
                    'creer_devis',
                    "Création du devis {$devis->numero} "
                    . "pour l'OR {$ordresReparation->numero}",
                    $devis
                );

                return $devis;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Génération bon de commande fournisseur
        |--------------------------------------------------------------------------
        */

        $devis->load('lignes');

        DevisWorkflowService::genererBonCommande(
            $devis
        );

        return redirect()
            ->route(
                'ordres-reparations.show',
                $ordresReparation
            )
            ->with(
                'success',
                'Devis créé avec succès.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ENREGISTREMENT POUR DOSSIER
    |--------------------------------------------------------------------------
    */

    /**
     * Enregistre un devis lié à un dossier de réception.
     */
    public function storePourDossier(
        Request $request,
        DossierReception $dossier
    ): RedirectResponse {
        $this->verifierPermissionDevis();

        $this->validerDonneesDevis($request);

        $devis = DB::transaction(
            function () use (
                $request,
                $dossier
            ) {
                /*
                |--------------------------------------------------------------------------
                | Création du devis
                |--------------------------------------------------------------------------
                */

                $devis = Devis::create([
                    'numero' =>
                        Devis::genererNumero(),

                    'dossier_id' =>
                        $dossier->id,

                    'taux_tva' =>
                        $request->input('taux_tva'),

                    'notes' =>
                        $request->input('notes'),

                    'statut' =>
                        'brouillon',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Lignes
                |--------------------------------------------------------------------------
                */

                $this->creerLignesDevis(
                    $devis,
                    $request->input('lignes', [])
                );

                /*
                |--------------------------------------------------------------------------
                | Recalcul
                |--------------------------------------------------------------------------
                */

                $devis->recalculer();

                /*
                |--------------------------------------------------------------------------
                | Dossier
                |--------------------------------------------------------------------------
                */

                $dossier->update([
                    'statut' =>
                        'devis_en_cours',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Journal
                |--------------------------------------------------------------------------
                */

                Activite::journaliser(
                    'creer_devis',
                    "Création du devis {$devis->numero} "
                    . "pour le dossier {$dossier->numero}",
                    $devis
                );

                return $devis;
            }
        );

        $devis->load('lignes');

        DevisWorkflowService::genererBonCommande(
            $devis
        );

        return redirect()
            ->route(
                'dossiers-reception.show',
                $dossier
            )
            ->with(
                'success',
                'Devis créé avec succès.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MODIFICATION
    |--------------------------------------------------------------------------
    */

    /**
     * Affiche le formulaire de modification.
     */
    public function edit(
        Devis $devis
    ): View|RedirectResponse {
        $this->verifierPermissionDevis();

        if (
            !in_array(
                $devis->statut,
                [
                    'brouillon',
                    'envoye',
                ],
                true
            )
        ) {
            return back()->with(
                'error',
                'Ce devis ne peut plus être modifié.'
            );
        }

        $devis->load([
            'ordreReparation.client',
            'ordreReparation.vehicule',
            'dossier.client',
            'dossier.vehicule',
            'lignes',
        ]);

        $operationsMaintenance =
            OperationsMaintenanceService::liste();

        $dureesOperations =
            OperationsMaintenanceService::dureesParDesignation();

        return view(
            'devis.edit',
            compact(
                'devis',
                'operationsMaintenance',
                'dureesOperations'
            )
        );
    }

    /**
     * Enregistre les modifications.
     */
    public function update(
        Request $request,
        Devis $devis
    ): RedirectResponse {
        $this->verifierPermissionDevis();

        if (
            !in_array(
                $devis->statut,
                [
                    'brouillon',
                    'envoye',
                ],
                true
            )
        ) {
            abort(
                403,
                'Ce devis ne peut plus être modifié.'
            );
        }

        $this->validerDonneesDevis(
            $request,
            true
        );

        DB::transaction(
            function () use (
                $request,
                $devis
            ) {
                /*
                |--------------------------------------------------------------------------
                | Mise à jour entête
                |--------------------------------------------------------------------------
                */

                $devis->update([
                    'taux_tva' =>
                        $request->input('taux_tva'),

                    'notes' =>
                        $request->input('notes'),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Suppression anciennes lignes
                |--------------------------------------------------------------------------
                */

                $devis
                    ->lignes()
                    ->delete();

                /*
                |--------------------------------------------------------------------------
                | Recréation des lignes
                |--------------------------------------------------------------------------
                */

                $this->creerLignesDevis(
                    $devis,
                    $request->input(
                        'lignes',
                        []
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | Recalcul
                |--------------------------------------------------------------------------
                */

                $devis->recalculer();

                /*
                |--------------------------------------------------------------------------
                | Journal
                |--------------------------------------------------------------------------
                */

                Activite::journaliser(
                    'modifier_devis',
                    "Devis {$devis->numero} modifié",
                    $devis
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Synchronisation fournisseur
        |--------------------------------------------------------------------------
        */

        $devis->load('lignes');

        DevisWorkflowService::resynchroniserBonCommande(
            $devis
        );

        return redirect()
            ->route(
                'devis.show',
                $devis
            )
            ->with(
                'success',
                'Devis mis à jour.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SUPPRESSION
    |--------------------------------------------------------------------------
    */

    /**
     * Supprime un devis brouillon ou envoyé.
     */
    public function destroy(
        Devis $devis
    ): RedirectResponse {
        $this->verifierPermissionDevis();

        if (
            !in_array(
                $devis->statut,
                [
                    'brouillon',
                    'envoye',
                ],
                true
            )
        ) {
            return back()->with(
                'error',
                'Un devis accepté ne peut pas être supprimé.'
            );
        }

        $ordreReparation =
            $devis->ordreReparation;

        $dossier =
            $devis->dossier;

        DB::transaction(
            function () use (
                $devis,
                $ordreReparation,
                $dossier
            ) {
                /*
                |--------------------------------------------------------------------------
                | Journal
                |--------------------------------------------------------------------------
                */

                Activite::journaliser(
                    'supprimer_devis',
                    "Devis {$devis->numero} supprimé",
                    $devis
                );

                /*
                |--------------------------------------------------------------------------
                | Suppression
                |--------------------------------------------------------------------------
                */

                $devis
                    ->lignes()
                    ->delete();

                $devis->delete();

                /*
                |--------------------------------------------------------------------------
                | Retour OR vers diagnostic
                |--------------------------------------------------------------------------
                */

                if (
                    $ordreReparation
                    &&
                    !$ordreReparation
                        ->allDevis()
                        ->exists()
                ) {
                    $ordreReparation->update([
                        'statut' =>
                            'diagnostic',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Retour dossier vers nouveau
                |--------------------------------------------------------------------------
                */

                if (
                    $dossier
                    &&
                    !$dossier
                        ->devis()
                        ->exists()
                ) {
                    $dossier->update([
                        'statut' =>
                            'nouveau',
                    ]);
                }
            }
        );

        if ($ordreReparation) {
            return redirect()
                ->route(
                    'ordres-reparations.show',
                    $ordreReparation
                )
                ->with(
                    'success',
                    'Devis supprimé.'
                );
        }

        return redirect()
            ->route(
                'dossiers-reception.show',
                $dossier
            )
            ->with(
                'success',
                'Devis supprimé.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | AFFICHAGE
    |--------------------------------------------------------------------------
    */

    /**
     * Affiche le devis.
     */
    public function show(
        Devis $devis
    ): View {
        $devis->load([
            'ordreReparation.client',
            'ordreReparation.vehicule',
            'dossier.client',
            'dossier.vehicule',
            'lignes',
        ]);

        return view(
            'devis.show',
            compact('devis')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IMPRESSION
    |--------------------------------------------------------------------------
    */

    /**
     * Page d'impression A4.
     */
    public function imprimer(
        Devis $devis
    ): View {
        $devis->load([
            'ordreReparation.client',
            'ordreReparation.vehicule',
            'dossier.client',
            'dossier.vehicule',
            'lignes',
        ]);

        return view(
            'devis.print',
            compact('devis')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MARQUER COMME ENVOYÉ
    |--------------------------------------------------------------------------
    */

    /**
     * Marque le devis comme envoyé.
     */
    public function marquerEnvoye(
        Devis $devis
    ): RedirectResponse {
        $this->verifierPermissionValidationDevis();

        $devis->load('lignes');

        if (
            $devis->attendReponseFournisseur()
        ) {
            return back()->with(
                'error',
                'Impossible : le fournisseur n\'a pas encore '
                . 'confirmé la disponibilité de toutes les pièces.'
            );
        }

        $devis->update([
            'statut' =>
                'envoye',

            'date_envoi' =>
                now(),
        ]);

        if ($devis->ordreReparation) {
            $devis
                ->ordreReparation
                ->update([
                    'statut' =>
                        'devis_envoye',
                ]);
        }

        Activite::journaliser(
            'envoyer_devis',
            "Devis {$devis->numero} marqué envoyé au client",
            $devis
        );

        return back()->with(
            'success',
            'Devis marqué comme envoyé.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ACCEPTATION
    |--------------------------------------------------------------------------
    */

    /**
     * Accepte le devis.
     */
    public function accepter(
        Devis $devis
    ): RedirectResponse {
        $this->verifierPermissionValidationDevis();

        $devis->load('lignes');

        if (
            $devis->attendReponseFournisseur()
        ) {
            return back()->with(
                'error',
                'Impossible de valider : le fournisseur '
                . 'n\'a pas encore confirmé la disponibilité '
                . 'de toutes les pièces.'
            );
        }

        DevisWorkflowService::accepter(
            $devis
        );

        Activite::journaliser(
            'accepter_devis',
            "Devis {$devis->numero} accepté par le client "
            . "({$devis->montant_ttc} DA TTC)",
            $devis
        );

        return back()->with(
            'success',
            'Devis accepté — OR prêt pour affectation.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REFUS
    |--------------------------------------------------------------------------
    */

    /**
     * Refuse le devis.
     */
    public function refuser(
        Devis $devis
    ): RedirectResponse {
        $this->verifierPermissionValidationDevis();

        $devis->load('lignes');

        if (
            $devis->attendReponseFournisseur()
        ) {
            return back()->with(
                'error',
                'Impossible : le fournisseur n\'a pas encore '
                . 'confirmé la disponibilité de toutes les pièces.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Statut devis
        |--------------------------------------------------------------------------
        */

        $devis->update([
            'statut' =>
                'refuse',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Statut OR
        |--------------------------------------------------------------------------
        */

        if ($devis->ordreReparation) {
            $devis
                ->ordreReparation
                ->update([
                    'statut' =>
                        'diagnostic',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Statut dossier
        |--------------------------------------------------------------------------
        */

        if ($devis->dossier) {
            $devis
                ->dossier
                ->update([
                    'statut' =>
                        'en_attente_client',
                ]);
        }

        Activite::journaliser(
            'refuser_devis',
            "Devis {$devis->numero} refusé par le client",
            $devis
        );

        return back()->with(
            'success',
            'Devis refusé.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD DEVIS SIGNÉ
    |--------------------------------------------------------------------------
    */

    /**
     * Téléverse le devis signé par le client.
     */
    public function uploadSignature(
        Request $request,
        Devis $devis
    ): RedirectResponse {
        $this->verifierPermissionValidationDevis();

        $devis->load('lignes');

        if (
            $devis->attendReponseFournisseur()
        ) {
            return back()->with(
                'error',
                'Impossible de valider : le fournisseur '
                . 'n\'a pas encore confirmé la disponibilité '
                . 'de toutes les pièces.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validation fichier
        |--------------------------------------------------------------------------
        */

        $request->validate(
            [
                'fichier_signe' => [
                    'required',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                ],
            ],
            [
                'fichier_signe.required' =>
                    'Veuillez sélectionner un fichier à uploader.',

                'fichier_signe.file' =>
                    'Le fichier uploadé est invalide.',

                'fichier_signe.mimes' =>
                    'Le fichier doit être au format PDF, JPG ou PNG.',

                'fichier_signe.max' =>
                    'Le fichier ne doit pas dépasser 5 Mo.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Sauvegarde fichier
        |--------------------------------------------------------------------------
        */

        $fichier =
            $request->file('fichier_signe');

        if (!$fichier) {
            return back()->with(
                'error',
                'Le fichier signé est introuvable.'
            );
        }

        $path = $fichier->store(
            'devis-signes',
            'public'
        );

        /*
        |--------------------------------------------------------------------------
        | Acceptation
        |--------------------------------------------------------------------------
        */

        DevisWorkflowService::accepter(
            $devis,
            [
                'fichier_signe' =>
                    $path,
            ]
        );

        Activite::journaliser(
            'upload_devis_signe',
            "Devis signé {$devis->numero} téléversé",
            $devis
        );

        return back()->with(
            'success',
            'Devis signé uploadé — bon de commande pièces généré.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION COMMUNE DES DEVIS
    |--------------------------------------------------------------------------
    */

    /**
     * Validation commune création / modification.
     */
    private function validerDonneesDevis(
        Request $request,
        bool $modification = false
    ): void {
        /*
         * Dans votre code original, la création autorisait :
         *
         * main_oeuvre,piece,forfait,autre
         *
         * alors que la modification autorisait seulement :
         *
         * main_oeuvre,piece
         *
         * On conserve ce comportement.
         */

        $types = $modification
            ? 'main_oeuvre,piece'
            : 'main_oeuvre,piece,forfait,autre';

        $request->validate(
            [
                'taux_tva' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:100',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],

                'lignes' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'lignes.*.type' => [
                    'required',
                    'in:' . $types,
                ],

                'lignes.*.designation' => [
                    'required',
                    'string',
                ],

                'lignes.*.reference' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'lignes.*.quantite' => [
                    'required',
                    'numeric',
                    'min:0.01',
                ],

                'lignes.*.prix_unitaire' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'lignes.*.remise' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:100',
                ],
            ],
            [
                'taux_tva.required' =>
                    'Le taux de TVA est obligatoire.',

                'taux_tva.numeric' =>
                    'Le taux de TVA doit être un nombre.',

                'taux_tva.min' =>
                    'Le taux de TVA ne peut pas être négatif.',

                'taux_tva.max' =>
                    'Le taux de TVA ne peut pas dépasser 100%.',

                'lignes.required' =>
                    'Le devis doit contenir au moins une ligne.',

                'lignes.array' =>
                    'Les lignes du devis sont invalides.',

                'lignes.min' =>
                    'Le devis doit contenir au moins une ligne.',

                'lignes.*.type.required' =>
                    'Veuillez sélectionner le type pour chaque ligne.',

                'lignes.*.type.in' =>
                    'Le type de ligne sélectionné est invalide.',

                'lignes.*.designation.required' =>
                    'La désignation est obligatoire pour chaque ligne.',

                'lignes.*.quantite.required' =>
                    'La quantité est obligatoire pour chaque ligne.',

                'lignes.*.quantite.numeric' =>
                    'La quantité doit être un nombre.',

                'lignes.*.quantite.min' =>
                    'La quantité doit être supérieure à zéro.',

                'lignes.*.prix_unitaire.numeric' =>
                    'Le prix unitaire doit être un nombre.',

                'lignes.*.prix_unitaire.min' =>
                    'Le prix unitaire ne peut pas être négatif.',

                'lignes.*.remise.numeric' =>
                    'La remise doit être un nombre.',

                'lignes.*.remise.min' =>
                    'La remise ne peut pas être négative.',

                'lignes.*.remise.max' =>
                    'La remise ne peut pas dépasser 100%.',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CRÉATION DES LIGNES
    |--------------------------------------------------------------------------
    */

    /**
     * Crée toutes les lignes d'un devis.
     */
    private function creerLignesDevis(
        Devis $devis,
        array $lignes
    ): void {
        foreach ($lignes as $ligne) {
            /*
            |--------------------------------------------------------------------------
            | Valeurs
            |--------------------------------------------------------------------------
            */

            $quantite =
                (float) ($ligne['quantite'] ?? 0);

            $prixUnitaire =
                (float) ($ligne['prix_unitaire'] ?? 0);

            $remise =
                (float) ($ligne['remise'] ?? 0);

            /*
            |--------------------------------------------------------------------------
            | Total HT
            |--------------------------------------------------------------------------
            */

            $totalHt = round(
                $quantite
                * $prixUnitaire
                * (1 - ($remise / 100)),
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Référence
            |--------------------------------------------------------------------------
            */

            $reference = null;

            if (
                ($ligne['type'] ?? null)
                ===
                'piece'
            ) {
                $reference =
                    $ligne['reference']
                    ?? null;
            }

            /*
            |--------------------------------------------------------------------------
            | Création
            |--------------------------------------------------------------------------
            */

            LigneDevis::create([
                'devis_id' =>
                    $devis->id,

                'type' =>
                    $ligne['type'],

                'designation' =>
                    $ligne['designation'],

                'reference' =>
                    $reference,

                'quantite' =>
                    $quantite,

                'prix_unitaire' =>
                    $prixUnitaire,

                'remise' =>
                    $remise,

                'total_ht' =>
                    $totalHt,
            ]);
        }
    }
}
