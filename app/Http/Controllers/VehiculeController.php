<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VehiculeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | AUTORISATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Retourne l'utilisateur connecté.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function utilisateurConnecte(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(401, 'Vous devez être connecté.');
        }

        return $user;
    }

    /**
     * Vérifie que l'utilisateur peut gérer les véhicules.
     */
    private function verifierPermissionVehicules(): User
    {
        $user = $this->utilisateurConnecte();

        if (!$user->hasPermission('gerer_vehicules')) {
            abort(
                403,
                'Vous n\'avez pas la permission de gérer les véhicules.'
            );
        }

        return $user;
    }

    /**
     * Vérifie que l'utilisateur est administrateur.
     */
    private function verifierAdministrateur(): User
    {
        $user = $this->utilisateurConnecte();

        if (!$user->isAdmin()) {
            abort(
                403,
                'Cette action est réservée à l\'administrateur.'
            );
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | LISTE DES VÉHICULES
    |--------------------------------------------------------------------------
    */

    /**
     * Liste tous les véhicules avec recherche et filtre par marque.
     */
    public function index(Request $request): View
    {
        $query = Vehicule::query()
            ->with('client')
            ->orderBy('immatriculation');

        /*
        |--------------------------------------------------------------------------
        | Recherche
        |--------------------------------------------------------------------------
        */

        $search = trim((string) $request->get('q', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where(
                    'immatriculation',
                    'like',
                    "%{$search}%"
                )
                    ->orWhere(
                        'marque',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'modele',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'vin',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'client',
                        function ($clientQuery) use ($search) {
                            $clientQuery->where(
                                'nom',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre par marque
        |--------------------------------------------------------------------------
        */

        $marque = trim(
            (string) $request->get('marque', '')
        );

        if ($marque !== '') {
            $query->where('marque', $marque);
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $vehicules = $query
            ->paginate(20)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Marques disponibles
        |--------------------------------------------------------------------------
        */

        $marques = Vehicule::query()
            ->whereNotNull('marque')
            ->where('marque', '!=', '')
            ->distinct()
            ->orderBy('marque')
            ->pluck('marque');

        return view(
            'vehicules.index',
            compact(
                'vehicules',
                'marques'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CRÉATION
    |--------------------------------------------------------------------------
    */

    /**
     * Affiche le formulaire de création.
     */
    public function create(Request $request): View
    {
        $this->verifierPermissionVehicules();

        $clients = Client::query()
            ->orderBy('nom')
            ->get();

        $clientSelectionne = null;

        if ($request->filled('client_id')) {
            $clientSelectionne = Client::find(
                $request->input('client_id')
            );
        }

        return view(
            'vehicules.create',
            compact(
                'clients',
                'clientSelectionne'
            )
        );
    }

    /**
     * Enregistre un nouveau véhicule.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->verifierPermissionVehicules();

        /*
        |--------------------------------------------------------------------------
        | Normalisation du VIN
        |--------------------------------------------------------------------------
        */

        $request->merge([
            'vin' => strtoupper(
                trim(
                    (string) $request->input('vin')
                )
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $data = $request->validate(
            [
                'client_id' => [
                    'required',
                    'exists:clients,id',
                ],

                'immatriculation' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:vehicules,immatriculation',
                ],

                'vin' => [
                    'required',
                    'string',
                    'size:17',
                    'regex:/^[A-Z0-9]{17}$/',
                    'unique:vehicules,vin',
                ],

                'marque' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'modele' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'version' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'categorie' => [
                    'nullable',
                    'in:pick-up,suv',
                ],

                'annee' => [
                    'nullable',
                    'integer',
                    'min:1960',
                    'max:' . (date('Y') + 1),
                ],

                'couleur' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'motorisation' => [
                    'required',
                    'in:essence,diesel,hybride,electrique,gpl,autre',
                ],

                'cylindree' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'puissance_fiscale' => [
                    'nullable',
                    'string',
                    'max:10',
                ],

                'kilometrage' => [
                    'required',
                    'integer',
                    'min:0',
                ],

                'date_mise_circulation' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                ],

                'date_expiration_assurance' => [
                    'nullable',
                    'date',
                ],

                'date_expiration_vignette' => [
                    'nullable',
                    'date',
                ],

                'sous_garantie' => [
                    'boolean',
                ],

                'fin_garantie' => [
                    'nullable',
                    'date',
                ],

                'garantie_couverture' => [
                    'nullable',
                    'string',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],
            ],
            $this->messagesValidation()
        );

        /*
        |--------------------------------------------------------------------------
        | Garantie
        |--------------------------------------------------------------------------
        */

        $data['sous_garantie'] = $request->boolean(
            'sous_garantie'
        );

        /*
        |--------------------------------------------------------------------------
        | Création
        |--------------------------------------------------------------------------
        */

        $vehicule = Vehicule::create($data);

        /*
        |--------------------------------------------------------------------------
        | Journal d'activité
        |--------------------------------------------------------------------------
        */

        Activite::journaliser(
            'creer_vehicule',
            "Enregistrement véhicule {$vehicule->immatriculation} — "
            . "{$vehicule->marque} {$vehicule->modele}",
            $vehicule
        );

        return redirect()
            ->route(
                'vehicules.show',
                $vehicule
            )
            ->with(
                'success',
                "Véhicule {$vehicule->immatriculation} enregistré avec succès."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | AFFICHAGE
    |--------------------------------------------------------------------------
    */

    /**
     * Affiche la fiche détaillée d'un véhicule.
     */
    public function show(Vehicule $vehicule): View
    {
        $vehicule->load([
            'client',

            'ordresReparations' => function ($query) {
                $query
                    ->with('client')
                    ->latest()
                    ->limit(20);
            },
        ]);

        /*
        |--------------------------------------------------------------------------
        | Clients disponibles pour transfert
        |--------------------------------------------------------------------------
        */

        $user = $this->utilisateurConnecte();

        if ($user->hasPermission('gerer_vehicules')) {
            $clients = Client::query()
                ->where(
                    'id',
                    '!=',
                    $vehicule->client_id
                )
                ->orderBy('nom')
                ->get();
        } else {
            $clients = collect();
        }

        return view(
            'vehicules.show',
            compact(
                'vehicule',
                'clients'
            )
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
    public function edit(Vehicule $vehicule): View
    {
        $this->verifierPermissionVehicules();

        $clients = Client::query()
            ->orderBy('nom')
            ->get();

        return view(
            'vehicules.edit',
            compact(
                'vehicule',
                'clients'
            )
        );
    }

    /**
     * Met à jour un véhicule existant.
     */
    public function update(
        Request $request,
        Vehicule $vehicule
    ): RedirectResponse {
        $this->verifierPermissionVehicules();

        /*
        |--------------------------------------------------------------------------
        | Normalisation du VIN
        |--------------------------------------------------------------------------
        */

        $request->merge([
            'vin' => strtoupper(
                trim(
                    (string) $request->input('vin')
                )
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $data = $request->validate(
            [
                'immatriculation' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:vehicules,immatriculation,'
                    . $vehicule->id,
                ],

                'vin' => [
                    'required',
                    'string',
                    'size:17',
                    'regex:/^[A-Z0-9]{17}$/',
                    'unique:vehicules,vin,'
                    . $vehicule->id,
                ],

                'marque' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'modele' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'version' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'categorie' => [
                    'nullable',
                    'in:pick-up,suv',
                ],

                'annee' => [
                    'nullable',
                    'integer',
                    'min:1960',
                    'max:' . (date('Y') + 1),
                ],

                'couleur' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'motorisation' => [
                    'required',
                    'in:essence,diesel,hybride,electrique,gpl,autre',
                ],

                'cylindree' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'puissance_fiscale' => [
                    'nullable',
                    'string',
                    'max:10',
                ],

                'kilometrage' => [
                    'required',
                    'integer',
                    'min:0',
                ],

                'date_mise_circulation' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                ],

                'date_expiration_assurance' => [
                    'nullable',
                    'date',
                ],

                'date_expiration_vignette' => [
                    'nullable',
                    'date',
                ],

                'sous_garantie' => [
                    'boolean',
                ],

                'fin_garantie' => [
                    'nullable',
                    'date',
                ],

                'garantie_couverture' => [
                    'nullable',
                    'string',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],
            ],
            $this->messagesValidation()
        );

        /*
        |--------------------------------------------------------------------------
        | Règle de garantie
        |--------------------------------------------------------------------------
        |
        | Un véhicule qui n'est plus sous garantie ne peut pas être remis
        | sous garantie depuis ce formulaire.
        |
        */

        $data['sous_garantie'] = $vehicule->sous_garantie
            ? $request->boolean('sous_garantie')
            : false;

        /*
        |--------------------------------------------------------------------------
        | Mise à jour
        |--------------------------------------------------------------------------
        */

        $vehicule->update($data);

        /*
        |--------------------------------------------------------------------------
        | Journalisation
        |--------------------------------------------------------------------------
        */

        Activite::journaliser(
            'modifier_vehicule',
            "Modification véhicule {$vehicule->immatriculation} — "
            . "{$vehicule->marque} {$vehicule->modele}",
            $vehicule
        );

        return redirect()
            ->route(
                'vehicules.show',
                $vehicule
            )
            ->with(
                'success',
                'Véhicule mis à jour avec succès.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSFERT DE PROPRIÉTAIRE
    |--------------------------------------------------------------------------
    */

    /**
     * Transfère le véhicule vers un autre client.
     */
    public function transfererProprietaire(
        Request $request,
        Vehicule $vehicule
    ): RedirectResponse {
        $this->verifierPermissionVehicules();

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $data = $request->validate(
            [
                'nouveau_client_id' => [
                    'required',
                    'exists:clients,id',
                ],
            ],
            [
                'nouveau_client_id.required' =>
                    'Veuillez sélectionner le nouveau propriétaire.',

                'nouveau_client_id.exists' =>
                    'Le client sélectionné est introuvable.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Vérification
        |--------------------------------------------------------------------------
        */

        if (
            (int) $data['nouveau_client_id']
            ===
            (int) $vehicule->client_id
        ) {
            return back()->with(
                'error',
                'Ce client est déjà le propriétaire de ce véhicule.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ancien propriétaire
        |--------------------------------------------------------------------------
        */

        $vehicule->loadMissing('client');

        $ancienProprietaire =
            $vehicule->client?->nom_complet
            ?? $vehicule->client?->nom
            ?? 'Propriétaire inconnu';

        /*
        |--------------------------------------------------------------------------
        | Nouveau propriétaire
        |--------------------------------------------------------------------------
        */

        $nouveauClient = Client::findOrFail(
            $data['nouveau_client_id']
        );

        $nouveauProprietaire =
            $nouveauClient->nom_complet
            ?? $nouveauClient->nom
            ?? 'Nouveau propriétaire';

        /*
        |--------------------------------------------------------------------------
        | Mise à jour du véhicule
        |--------------------------------------------------------------------------
        */

        $vehicule->update([
            'client_id' => $nouveauClient->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Journalisation
        |--------------------------------------------------------------------------
        */

        Activite::journaliser(
            'transferer_vehicule',
            "Véhicule {$vehicule->immatriculation} transféré "
            . "de {$ancienProprietaire} "
            . "à {$nouveauProprietaire}",
            $vehicule
        );

        return redirect()
            ->route(
                'vehicules.show',
                $vehicule
            )
            ->with(
                'success',
                "Véhicule transféré à {$nouveauProprietaire}. "
                . "L'historique des interventions reste consultable "
                . "quel que soit le propriétaire à l'époque."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | CRÉATION RAPIDE AJAX
    |--------------------------------------------------------------------------
    */

    /**
     * Crée rapidement un véhicule depuis un formulaire d'OR.
     */
    public function storeRapide(
        Request $request
    ): JsonResponse {
        $this->verifierPermissionVehicules();

        /*
        |--------------------------------------------------------------------------
        | Normalisation VIN
        |--------------------------------------------------------------------------
        */

        $request->merge([
            'vin' => strtoupper(
                trim(
                    (string) $request->input('vin')
                )
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $data = $request->validate(
            [
                'client_id' => [
                    'required',
                    'exists:clients,id',
                ],

                'immatriculation' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:vehicules,immatriculation',
                ],

                'marque' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'modele' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'annee' => [
                    'nullable',
                    'integer',
                    'min:1950',
                    'max:' . (date('Y') + 1),
                ],

                'motorisation' => [
                    'required',
                    'in:essence,diesel,hybride,electrique,gpl,autre',
                ],

                'categorie' => [
                    'nullable',
                    'in:pick-up,suv',
                ],

                'sous_garantie' => [
                    'required',
                    'boolean',
                ],

                'vin' => [
                    'required',
                    'string',
                    'size:17',
                    'regex:/^[A-Z0-9]{17}$/',
                    'unique:vehicules,vin',
                ],

                'date_mise_circulation' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                ],

                'kilometrage' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
            ],
            [
                'client_id.required' =>
                    'Veuillez sélectionner un client.',

                'client_id.exists' =>
                    'Le client sélectionné est introuvable.',

                'immatriculation.required' =>
                    'Le numéro d\'immatriculation est obligatoire.',

                'immatriculation.unique' =>
                    'Ce numéro d\'immatriculation existe déjà dans le système.',

                'marque.required' =>
                    'La marque du véhicule est obligatoire.',

                'modele.required' =>
                    'Le modèle du véhicule est obligatoire.',

                'motorisation.required' =>
                    'Veuillez sélectionner le type de motorisation.',

                'motorisation.in' =>
                    'Le type de motorisation sélectionné est invalide.',

                'categorie.in' =>
                    'La catégorie sélectionnée est invalide.',

                'sous_garantie.required' =>
                    'Veuillez indiquer si le véhicule est sous garantie constructeur.',

                'vin.required' =>
                    'Le numéro de châssis (VIN) est obligatoire.',

                'vin.size' =>
                    'Le numéro de châssis (VIN) doit comporter exactement 17 caractères.',

                'vin.regex' =>
                    'Le numéro de châssis (VIN) doit contenir exactement 17 caractères alphanumériques.',

                'vin.unique' =>
                    'Ce numéro de châssis (VIN) existe déjà dans le système.',

                'date_mise_circulation.required' =>
                    'La date de mise en circulation est obligatoire.',

                'date_mise_circulation.date' =>
                    'La date de mise en circulation n\'est pas valide.',

                'date_mise_circulation.before_or_equal' =>
                    'La date de mise en circulation ne peut pas être dans le futur.',

                'kilometrage.integer' =>
                    'Le kilométrage doit être un nombre entier.',

                'kilometrage.min' =>
                    'Le kilométrage ne peut pas être négatif.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Valeurs par défaut
        |--------------------------------------------------------------------------
        */

        $data['kilometrage'] =
            $data['kilometrage'] ?? 0;

        $data['sous_garantie'] =
            $request->boolean('sous_garantie');

        /*
        |--------------------------------------------------------------------------
        | Création
        |--------------------------------------------------------------------------
        */

        $vehicule = Vehicule::create($data);

        /*
        |--------------------------------------------------------------------------
        | Journalisation
        |--------------------------------------------------------------------------
        */

        Activite::journaliser(
            'creer_vehicule',
            "Création rapide véhicule {$vehicule->immatriculation} — "
            . "{$vehicule->marque} {$vehicule->modele}",
            $vehicule
        );

        /*
        |--------------------------------------------------------------------------
        | Réponse JSON
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'message' =>
                'Véhicule créé avec succès.',

            'id' =>
                $vehicule->id,

            'client_id' =>
                $vehicule->client_id,

            'immatriculation' =>
                $vehicule->immatriculation,

            'marque' =>
                $vehicule->marque,

            'modele' =>
                $vehicule->modele,

            'vin' =>
                $vehicule->vin ?? '',

            'kilometrage' =>
                $vehicule->kilometrage ?? 0,

            'sous_garantie' =>
                $vehicule->sous_garantie
                    ? '1'
                    : '0',

            'limite_km_garantie' =>
                $vehicule->limite_km_garantie,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUPPRESSION
    |--------------------------------------------------------------------------
    */

    /**
     * Supprime définitivement un véhicule.
     *
     * Réservé à l'administrateur.
     */
    public function destroy(
        Vehicule $vehicule
    ): RedirectResponse {
        $this->verifierAdministrateur();

        /*
        |--------------------------------------------------------------------------
        | Protection historique
        |--------------------------------------------------------------------------
        */

        if (
            $vehicule
                ->ordresReparations()
                ->exists()
        ) {
            return back()->with(
                'error',
                'Impossible de supprimer : ce véhicule possède '
                . 'des ordres de réparation.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Informations avant suppression
        |--------------------------------------------------------------------------
        */

        $immat = $vehicule->immatriculation;

        /*
        |--------------------------------------------------------------------------
        | Journalisation
        |--------------------------------------------------------------------------
        */

        Activite::journaliser(
            'supprimer_vehicule',
            "Suppression véhicule {$vehicule->immatriculation} — "
            . "{$vehicule->marque} {$vehicule->modele}",
            $vehicule
        );

        /*
        |--------------------------------------------------------------------------
        | Suppression
        |--------------------------------------------------------------------------
        */

        $vehicule->delete();

        return redirect()
            ->route('vehicules.index')
            ->with(
                'success',
                "Véhicule {$immat} supprimé."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGES DE VALIDATION
    |--------------------------------------------------------------------------
    */

    /**
     * Messages communs aux formulaires véhicule.
     */
    private function messagesValidation(): array
    {
        return [
            'client_id.required' =>
                'Veuillez sélectionner un client.',

            'client_id.exists' =>
                'Le client sélectionné est introuvable.',

            'immatriculation.required' =>
                'Le numéro d\'immatriculation est obligatoire.',

            'immatriculation.unique' =>
                'Ce numéro d\'immatriculation existe déjà dans le système.',

            'immatriculation.max' =>
                'Le numéro d\'immatriculation ne doit pas dépasser 20 caractères.',

            'vin.required' =>
                'Le numéro de châssis (VIN) est obligatoire.',

            'vin.size' =>
                'Le numéro de châssis (VIN) doit comporter exactement 17 caractères.',

            'vin.regex' =>
                'Le numéro de châssis (VIN) doit contenir exactement '
                . '17 caractères alphanumériques (lettres et chiffres uniquement).',

            'vin.unique' =>
                'Ce numéro de châssis (VIN) existe déjà dans le système.',

            'marque.required' =>
                'La marque du véhicule est obligatoire.',

            'modele.required' =>
                'Le modèle du véhicule est obligatoire.',

            'categorie.in' =>
                'La catégorie sélectionnée est invalide.',

            'motorisation.required' =>
                'Veuillez sélectionner le type de motorisation.',

            'motorisation.in' =>
                'Le type de motorisation sélectionné est invalide.',

            'kilometrage.required' =>
                'Le kilométrage est obligatoire.',

            'kilometrage.integer' =>
                'Le kilométrage doit être un nombre entier.',

            'kilometrage.min' =>
                'Le kilométrage ne peut pas être négatif.',

            'annee.integer' =>
                'L\'année doit être un nombre entier.',

            'annee.min' =>
                'L\'année doit être supérieure ou égale à 1960.',

            'annee.max' =>
                'L\'année ne peut pas dépasser l\'année suivante.',

            'date_mise_circulation.required' =>
                'La date de mise en circulation est obligatoire.',

            'date_mise_circulation.date' =>
                'La date de mise en circulation n\'est pas valide.',

            'date_mise_circulation.before_or_equal' =>
                'La date de mise en circulation ne peut pas être dans le futur.',

            'date_expiration_assurance.date' =>
                'La date d\'expiration de l\'assurance n\'est pas valide.',

            'date_expiration_vignette.date' =>
                'La date d\'expiration de la vignette n\'est pas valide.',

            'fin_garantie.date' =>
                'La date de fin de garantie n\'est pas valide.',
        ];
    }
}
