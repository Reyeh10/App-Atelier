<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    |
    | Liste des clients.
    |
    | Recherche :
    | - nom
    | - prénom
    | - raison sociale
    | - téléphone
    | - email
    |
    | Filtre :
    | - particulier
    | - societe
    | - assurance
    |
    */
    public function index(Request $request): View
    {
        $query = Client::query()
            ->withCount([
                'vehicules',
                'ordresReparations',
            ])
            ->orderBy('nom')
            ->orderBy('prenom');

        /*
        |--------------------------------------------------------------------------
        | RECHERCHE
        |--------------------------------------------------------------------------
        */
        if ($request->filled('q')) {
            $search = trim(
                (string) $request->input('q')
            );

            $query->where(function ($q) use ($search) {
                $q
                    ->where(
                        'nom',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'prenom',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'raison_sociale',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'telephone',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'telephone2',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'email',
                        'like',
                        '%' . $search . '%'
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRE TYPE
        |--------------------------------------------------------------------------
        */
        if ($request->filled('type')) {
            $type = (string) $request->input('type');

            if (
                in_array(
                    $type,
                    [
                        'particulier',
                        'societe',
                        'assurance',
                    ],
                    true
                )
            ) {
                $query->where(
                    'type',
                    $type
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $clients = $query
            ->paginate(20)
            ->withQueryString();

        return view(
            'clients.index',
            compact('clients')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create(): View
    {
        $this->requireAuthenticatedUser();

        /*
        |--------------------------------------------------------------------------
        | AU MOINS UNE PERMISSION CLIENT
        |--------------------------------------------------------------------------
        */
        if (
            !$this->hasPermission('gerer_clients')
            && !$this->hasPermission('gerer_clients_societe')
            && !$this->hasPermission('gerer_clients_assurance')
        ) {
            abort(
                403,
                'Vous n\'êtes pas autorisé à créer des clients.'
            );
        }

        $typesAutorises =
            $this->typesAutorises();

        return view(
            'clients.create',
            compact('typesAutorises')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(
        Request $request
    ): RedirectResponse {
        $this->requireAuthenticatedUser();

        $type = (string) $request->input(
            'type',
            'particulier'
        );

        /*
        |--------------------------------------------------------------------------
        | TYPE VALIDE AVANT CONTRÔLE PERMISSION
        |--------------------------------------------------------------------------
        */
        if (
            !in_array(
                $type,
                [
                    'particulier',
                    'societe',
                    'assurance',
                ],
                true
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'type' =>
                        'Le type de client sélectionné est invalide.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | PERMISSION DU TYPE
        |--------------------------------------------------------------------------
        */
        if (
            !$this->hasPermission(
                $this->permissionPourType($type)
            )
        ) {
            abort(
                403,
                'Vous n\'êtes pas autorisé à créer un client de ce type.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SOCIÉTÉ / ASSURANCE
        |--------------------------------------------------------------------------
        |
        | Ces deux types utilisent une raison sociale.
        |
        */
        $typeEntreprise =
            in_array(
                $type,
                [
                    'societe',
                    'assurance',
                ],
                true
            );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */
        $data = $request->validate(
            [
                'type' => [
                    'required',
                    Rule::in([
                        'particulier',
                        'societe',
                        'assurance',
                    ]),
                ],

                'nom' => [
                    Rule::requiredIf(
                        !$typeEntreprise
                    ),
                    'nullable',
                    'string',
                    'max:100',
                ],

                'prenom' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'raison_sociale' => [
                    Rule::requiredIf(
                        $typeEntreprise
                    ),
                    'nullable',
                    'string',
                    'max:200',
                ],

                'rc' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'nif' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'contact_nom' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'telephone' => [
                    'required',
                    'string',
                    'max:20',
                ],

                'telephone2' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:150',
                ],

                'adresse' => [
                    'nullable',
                    'string',
                ],

                'ville' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'wilaya' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],

                /*
                |--------------------------------------------------------------------------
                | COMPTE CRÉDIT
                |--------------------------------------------------------------------------
                */
                'compte_actif' => [
                    'nullable',
                    'boolean',
                ],

                'plafond_compte' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
            ],
            $this->validationMessages()
        );

        /*
        |--------------------------------------------------------------------------
        | NOM SOCIÉTÉ / ASSURANCE
        |--------------------------------------------------------------------------
        |
        | Pour faciliter les recherches et l'affichage,
        | nom contient également la raison sociale.
        |
        */
        if ($typeEntreprise) {
            $data['nom'] =
                trim(
                    (string) $data['raison_sociale']
                );

            $data['prenom'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | COMPTE CRÉDIT
        |--------------------------------------------------------------------------
        |
        | Seuls les utilisateurs disposant de gerer_compte_credit
        | peuvent activer/modifier ces informations.
        |
        */
        if (
            $this->hasPermission(
                'gerer_compte_credit'
            )
        ) {
            $data['compte_actif'] =
                $request->boolean(
                    'compte_actif'
                );

            $data['plafond_compte'] =
                $request->filled(
                    'plafond_compte'
                )
                    ? (float) $request->input(
                        'plafond_compte'
                    )
                    : null;
        } else {
            $data['compte_actif'] =
                false;

            $data['plafond_compte'] =
                null;
        }

        /*
        |--------------------------------------------------------------------------
        | CRÉATION
        |--------------------------------------------------------------------------
        */
        $client =
            Client::create($data);

        /*
        |--------------------------------------------------------------------------
        | JOURNAL ACTIVITÉ
        |--------------------------------------------------------------------------
        */
        Activite::journaliser(
            'creer_client',
            'Création client '
                . $client->type
                . ' : '
                . $client->nom_complet,
            $client
        );

        return redirect()
            ->route(
                'clients.show',
                $client
            )
            ->with(
                'success',
                'Client « '
                    . $client->nom_complet
                    . ' » créé avec succès.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(
        Client $client
    ): View {
        $client->load([
            'vehicules',

            'ordresReparations' =>
                function ($query) {
                    $query
                        ->with('vehicule')
                        ->latest()
                        ->limit(10);
                },
        ]);

        return view(
            'clients.show',
            compact('client')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    public function edit(
        Client $client
    ): View {
        $this->requireAuthenticatedUser();

        /*
        |--------------------------------------------------------------------------
        | PERMISSION SUR LE TYPE ACTUEL
        |--------------------------------------------------------------------------
        */
        if (
            !$this->hasPermission(
                $this->permissionPourType(
                    $client->type
                )
            )
        ) {
            abort(
                403,
                'Vous n\'êtes pas autorisé à modifier ce client.'
            );
        }

        $typesAutorises =
            $this->typesAutorises();

        return view(
            'clients.edit',
            compact(
                'client',
                'typesAutorises'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(
        Request $request,
        Client $client
    ): RedirectResponse {
        $this->requireAuthenticatedUser();

        $type = (string) $request->input(
            'type',
            $client->type
        );

        /*
        |--------------------------------------------------------------------------
        | TYPE VALIDE
        |--------------------------------------------------------------------------
        */
        if (
            !in_array(
                $type,
                [
                    'particulier',
                    'societe',
                    'assurance',
                ],
                true
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'type' =>
                        'Le type de client sélectionné est invalide.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | PERMISSION SUR LE CLIENT ACTUEL
        |--------------------------------------------------------------------------
        |
        | Empêche un utilisateur sans permission sur le type actuel
        | de contourner la sécurité en changeant simplement le type.
        |
        */
        if (
            !$this->hasPermission(
                $this->permissionPourType(
                    $client->type
                )
            )
        ) {
            abort(
                403,
                'Vous n\'êtes pas autorisé à modifier ce client.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PERMISSION SUR LE NOUVEAU TYPE
        |--------------------------------------------------------------------------
        */
        if (
            !$this->hasPermission(
                $this->permissionPourType(
                    $type
                )
            )
        ) {
            abort(
                403,
                'Vous n\'êtes pas autorisé à attribuer ce type au client.'
            );
        }

        $typeEntreprise =
            in_array(
                $type,
                [
                    'societe',
                    'assurance',
                ],
                true
            );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */
        $data = $request->validate(
            [
                'type' => [
                    'required',
                    Rule::in([
                        'particulier',
                        'societe',
                        'assurance',
                    ]),
                ],

                'nom' => [
                    Rule::requiredIf(
                        !$typeEntreprise
                    ),
                    'nullable',
                    'string',
                    'max:100',
                ],

                'prenom' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'raison_sociale' => [
                    Rule::requiredIf(
                        $typeEntreprise
                    ),
                    'nullable',
                    'string',
                    'max:200',
                ],

                'rc' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'nif' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'contact_nom' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'telephone' => [
                    'required',
                    'string',
                    'max:20',
                ],

                'telephone2' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:150',
                ],

                'adresse' => [
                    'nullable',
                    'string',
                ],

                'ville' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'wilaya' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],

                'compte_actif' => [
                    'nullable',
                    'boolean',
                ],

                'plafond_compte' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
            ],
            $this->validationMessages()
        );

        /*
        |--------------------------------------------------------------------------
        | SOCIÉTÉ / ASSURANCE
        |--------------------------------------------------------------------------
        */
        if ($typeEntreprise) {
            $data['nom'] =
                trim(
                    (string) $data['raison_sociale']
                );

            $data['prenom'] =
                null;
        }

        /*
        |--------------------------------------------------------------------------
        | PARTICULIER
        |--------------------------------------------------------------------------
        |
        | Nettoyer les champs entreprise si on transforme une société/
        | assurance en particulier.
        |
        */
        if (!$typeEntreprise) {
            $data['raison_sociale'] =
                null;

            $data['rc'] =
                null;

            $data['nif'] =
                null;

            $data['contact_nom'] =
                null;
        }

        /*
        |--------------------------------------------------------------------------
        | COMPTE CRÉDIT
        |--------------------------------------------------------------------------
        */
        if (
            $this->hasPermission(
                'gerer_compte_credit'
            )
        ) {
            $data['compte_actif'] =
                $request->boolean(
                    'compte_actif'
                );

            $data['plafond_compte'] =
                $request->filled(
                    'plafond_compte'
                )
                    ? (float) $request->input(
                        'plafond_compte'
                    )
                    : null;
        } else {
            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            |
            | Si l'utilisateur n'a pas la permission crédit,
            | on retire ces champs du tableau pour conserver les valeurs
            | existantes du client.
            |
            */
            unset(
                $data['compte_actif'],
                $data['plafond_compte']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | MISE À JOUR
        |--------------------------------------------------------------------------
        */
        $client->update($data);

        /*
        |--------------------------------------------------------------------------
        | JOURNAL ACTIVITÉ
        |--------------------------------------------------------------------------
        */
        Activite::journaliser(
            'modifier_client',
            'Modification client : '
                . $client->nom_complet,
            $client
        );

        return redirect()
            ->route(
                'clients.show',
                $client
            )
            ->with(
                'success',
                'Client mis à jour avec succès.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE RAPIDE
    |--------------------------------------------------------------------------
    |
    | Création AJAX d'un client depuis un autre formulaire,
    | par exemple la création d'un ordre de réparation.
    |
    */
    public function storeRapide(
        Request $request
    ): JsonResponse {
        $this->requireAuthenticatedUser();

        $type = (string) $request->input(
            'type',
            'particulier'
        );

        /*
        |--------------------------------------------------------------------------
        | TYPE VALIDE
        |--------------------------------------------------------------------------
        */
        if (
            !in_array(
                $type,
                [
                    'particulier',
                    'societe',
                    'assurance',
                ],
                true
            )
        ) {
            return response()->json(
                [
                    'message' =>
                        'Le type de client sélectionné est invalide.',
                ],
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PERMISSION
        |--------------------------------------------------------------------------
        */
        if (
            !$this->hasPermission(
                $this->permissionPourType(
                    $type
                )
            )
        ) {
            return response()->json(
                [
                    'message' =>
                        'Vous n\'êtes pas autorisé à créer un client de ce type.',
                ],
                403
            );
        }

        $typeEntreprise =
            in_array(
                $type,
                [
                    'societe',
                    'assurance',
                ],
                true
            );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */
        $data = $request->validate(
            [
                'type' => [
                    'required',
                    Rule::in([
                        'particulier',
                        'societe',
                        'assurance',
                    ]),
                ],

                'nom' => [
                    Rule::requiredIf(
                        !$typeEntreprise
                    ),
                    'nullable',
                    'string',
                    'max:100',
                ],

                'prenom' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'raison_sociale' => [
                    Rule::requiredIf(
                        $typeEntreprise
                    ),
                    'nullable',
                    'string',
                    'max:200',
                ],

                'telephone' => [
                    'required',
                    'string',
                    'max:20',
                ],

                'telephone2' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:150',
                ],

                'adresse' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
            ],
            [
                'type.required' =>
                    'Veuillez sélectionner le type de client.',

                'type.in' =>
                    'Le type de client sélectionné est invalide.',

                'nom.required' =>
                    'Le nom du client est obligatoire.',

                'nom.max' =>
                    'Le nom ne doit pas dépasser 100 caractères.',

                'raison_sociale.required' =>
                    'La raison sociale est obligatoire pour une société ou une assurance.',

                'raison_sociale.max' =>
                    'La raison sociale ne doit pas dépasser 200 caractères.',

                'telephone.required' =>
                    'Le numéro de téléphone est obligatoire.',

                'telephone.max' =>
                    'Le numéro de téléphone ne doit pas dépasser 20 caractères.',

                'email.email' =>
                    'L\'adresse e-mail saisie n\'est pas valide.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ENTREPRISE
        |--------------------------------------------------------------------------
        */
        if ($typeEntreprise) {
            $data['nom'] =
                trim(
                    (string) $data['raison_sociale']
                );

            $data['prenom'] =
                null;
        }

        /*
        |--------------------------------------------------------------------------
        | SÉCURITÉ COMPTE CRÉDIT
        |--------------------------------------------------------------------------
        |
        | La création rapide ne doit jamais activer implicitement
        | un compte crédit.
        |
        */
        $data['compte_actif'] =
            false;

        $data['plafond_compte'] =
            null;

        /*
        |--------------------------------------------------------------------------
        | CRÉATION
        |--------------------------------------------------------------------------
        */
        $client =
            Client::create($data);

        /*
        |--------------------------------------------------------------------------
        | JOURNAL
        |--------------------------------------------------------------------------
        */
        Activite::journaliser(
            'creer_client',
            'Création rapide client '
                . $client->type
                . ' : '
                . $client->nom_complet,
            $client
        );

        /*
        |--------------------------------------------------------------------------
        | JSON
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'id' =>
                $client->id,

            'nom_complet' =>
                $client->nom_complet,

            'telephone' =>
                $client->telephone ?? '',

            'adresse' =>
                $client->adresse ?? '',

            'type' =>
                $client->getTypeLabel(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */
    public function destroy(
        Client $client
    ): RedirectResponse {
        $this->requireAuthenticatedUser();

        /*
        |--------------------------------------------------------------------------
        | ADMIN UNIQUEMENT
        |--------------------------------------------------------------------------
        */
        if (!$this->isAdmin()) {
            abort(
                403,
                'La suppression de clients est réservée à l\'administrateur.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VÉHICULES
        |--------------------------------------------------------------------------
        */
        if (
            $client
                ->vehicules()
                ->exists()
        ) {
            return back()
                ->with(
                    'error',
                    'Impossible de supprimer : ce client a des véhicules enregistrés.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | ORDRES DE RÉPARATION
        |--------------------------------------------------------------------------
        */
        if (
            $client
                ->ordresReparations()
                ->exists()
        ) {
            return back()
                ->with(
                    'error',
                    'Impossible de supprimer : ce client possède un historique d\'ordres de réparation.'
                );
        }

        $nom =
            $client->nom_complet;

        /*
        |--------------------------------------------------------------------------
        | JOURNAL AVANT SUPPRESSION
        |--------------------------------------------------------------------------
        */
        Activite::journaliser(
            'supprimer_client',
            'Suppression client : '
                . $nom
        );

        /*
        |--------------------------------------------------------------------------
        | SUPPRESSION
        |--------------------------------------------------------------------------
        */
        $client->delete();

        return redirect()
            ->route(
                'clients.index'
            )
            ->with(
                'success',
                'Client « '
                    . $nom
                    . ' » supprimé.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | TYPES AUTORISÉS
    |--------------------------------------------------------------------------
    */
    private function typesAutorises(): array
    {
        $types = [];

        if (
            $this->hasPermission(
                'gerer_clients'
            )
        ) {
            $types[] =
                'particulier';
        }

        if (
            $this->hasPermission(
                'gerer_clients_societe'
            )
        ) {
            $types[] =
                'societe';
        }

        if (
            $this->hasPermission(
                'gerer_clients_assurance'
            )
        ) {
            $types[] =
                'assurance';
        }

        return $types;
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSION PAR TYPE
    |--------------------------------------------------------------------------
    */
    private function permissionPourType(
        string $type
    ): string {
        return match ($type) {
            'societe' =>
                'gerer_clients_societe',

            'assurance' =>
                'gerer_clients_assurance',

            default =>
                'gerer_clients',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | UTILISATEUR CONNECTÉ
    |--------------------------------------------------------------------------
    |
    | Utiliser Auth::user() ici évite de répéter auth()->user()
    | partout dans le contrôleur.
    |
    */
    private function currentUser(): ?Authenticatable
    {
        return Auth::user();
    }

    /*
    |--------------------------------------------------------------------------
    | EXIGER AUTHENTIFICATION
    |--------------------------------------------------------------------------
    */
    private function requireAuthenticatedUser(): Authenticatable
    {
        $user =
            $this->currentUser();

        if (!$user) {
            abort(
                401,
                'Vous devez être connecté.'
            );
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | VÉRIFIER UNE PERMISSION
    |--------------------------------------------------------------------------
    |
    | IMPORTANT :
    |
    | On utilise is_callable + call_user_func afin qu'Intelephense
    | ne signale plus :
    |
    | Undefined method 'hasPermission'
    |
    | tout en conservant votre méthode hasPermission() personnalisée
    | sur le modèle utilisateur.
    |
    */
    private function hasPermission(
        string $permission
    ): bool {
        $user =
            $this->currentUser();

        if (!$user) {
            return false;
        }

        if (
            !is_callable([
                $user,
                'hasPermission',
            ])
        ) {
            return false;
        }

        return (bool) call_user_func(
            [
                $user,
                'hasPermission',
            ],
            $permission
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    |
    | Compatible avec votre méthode personnalisée isAdmin().
    |
    */
    private function isAdmin(): bool
    {
        $user =
            $this->currentUser();

        if (!$user) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | MÉTHODE isAdmin()
        |--------------------------------------------------------------------------
        */
        if (
            is_callable([
                $user,
                'isAdmin',
            ])
        ) {
            return (bool) call_user_func([
                $user,
                'isAdmin',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK ROLE
        |--------------------------------------------------------------------------
        |
        | Permet aussi de fonctionner si le modèle dispose simplement
        | d'un attribut role = admin.
        |
        */
        if (
            isset($user->role)
            && $user->role === 'admin'
        ) {
            return true;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGES DE VALIDATION
    |--------------------------------------------------------------------------
    */
    private function validationMessages(): array
    {
        return [
            'type.required' =>
                'Veuillez sélectionner le type de client (particulier, société ou assurance).',

            'type.in' =>
                'Le type de client sélectionné est invalide.',

            'nom.required' =>
                'Le nom du client est obligatoire.',

            'nom.string' =>
                'Le nom du client doit être du texte.',

            'nom.max' =>
                'Le nom ne doit pas dépasser 100 caractères.',

            'prenom.string' =>
                'Le prénom doit être du texte.',

            'prenom.max' =>
                'Le prénom ne doit pas dépasser 100 caractères.',

            'raison_sociale.required' =>
                'La raison sociale est obligatoire pour une société ou une assurance.',

            'raison_sociale.string' =>
                'La raison sociale doit être du texte.',

            'raison_sociale.max' =>
                'La raison sociale ne doit pas dépasser 200 caractères.',

            'rc.max' =>
                'Le registre de commerce ne doit pas dépasser 50 caractères.',

            'nif.max' =>
                'Le NIF ne doit pas dépasser 50 caractères.',

            'contact_nom.max' =>
                'Le nom du contact ne doit pas dépasser 100 caractères.',

            'telephone.required' =>
                'Le numéro de téléphone est obligatoire.',

            'telephone.string' =>
                'Le numéro de téléphone est invalide.',

            'telephone.max' =>
                'Le numéro de téléphone ne doit pas dépasser 20 caractères.',

            'telephone2.max' =>
                'Le deuxième numéro de téléphone ne doit pas dépasser 20 caractères.',

            'email.email' =>
                'L\'adresse e-mail saisie n\'est pas valide.',

            'email.max' =>
                'L\'adresse e-mail ne doit pas dépasser 150 caractères.',

            'ville.max' =>
                'La ville ne doit pas dépasser 100 caractères.',

            'wilaya.max' =>
                'La wilaya ne doit pas dépasser 100 caractères.',

            'compte_actif.boolean' =>
                'La valeur du compte crédit est invalide.',

            'plafond_compte.numeric' =>
                'Le plafond du compte doit être un nombre.',

            'plafond_compte.min' =>
                'Le plafond du compte ne peut pas être négatif.',
        ];
    }
}
