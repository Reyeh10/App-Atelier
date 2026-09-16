<?php

use App\Http\Controllers\Api\FournisseurReponseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes API de l'application.
|
*/

/*
|--------------------------------------------------------------------------
| Utilisateur connecté
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Bons de commande — réponse du fournisseur (stcd-magasin)
|--------------------------------------------------------------------------
|
| stcd-magasin renvoie ici la disponibilité, le prix et une éventuelle note
| pour chaque ligne d'un bon de commande, dès qu'un vendeur l'a identifiée
| manuellement (cf. App\Services\FournisseurApiService côté envoi et
| App\Http\Middleware\VerifyFournisseurToken pour l'authentification par
| jeton secret partagé, indépendante des comptes utilisateurs).
|
*/

Route::middleware('fournisseur.token')->patch(
    '/bons-commande/{numero}/lignes/{index}',
    [FournisseurReponseController::class, 'updateLigne']
)->name('api.bons-commande.lignes.update');
