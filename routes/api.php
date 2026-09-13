<?php

use App\Http\Controllers\Api\FournisseurBonCommandeController;
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
| Bons de commande
|--------------------------------------------------------------------------
|
| Reçoit les bons de commande envoyés en temps réel par l'application
| externe.
|
*/

Route::middleware('auth:sanctum')->post(
    '/bons-commande',
    [FournisseurBonCommandeController::class, 'store']
)->name('api.bons-commande.store');
