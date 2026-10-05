<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration corrective pour la table bons_transfert.
 *
 * La migration initiale 2026_10_05_093522_create_bons_transfert_table
 * a déjà été enregistrée comme exécutée sur certains environnements,
 * alors que la table bons_transfert n'a pas été créée.
 *
 * Cette migration vérifie donc l'existence de la table avant de la créer.
 */
return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CRÉATION DE LA TABLE BONS_TRANSFERT
        |--------------------------------------------------------------------------
        |
        | On protège la création avec Schema::hasTable() afin que cette
        | migration puisse être exécutée sans erreur sur un environnement
        | où la table existe déjà.
        |
        */

        if (! Schema::hasTable('bons_transfert')) {
            Schema::create('bons_transfert', function (Blueprint $table) {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | BON DE COMMANDE ASSOCIÉ
                |--------------------------------------------------------------------------
                |
                | Un seul bon de transfert est autorisé par bon de commande.
                |
                */

                $table->foreignId('bon_commande_id')
                    ->unique()
                    ->constrained('bons_commande')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | INFORMATIONS DU BON DE TRANSFERT
                |--------------------------------------------------------------------------
                */

                $table->string('numero', 50);

                $table->date('date_transfert')
                    ->nullable();

                $table->string('depot', 100)
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | LIGNES DU BON DE TRANSFERT
                |--------------------------------------------------------------------------
                |
                | Les pièces transférées sont conservées au format JSON.
                |
                */

                $table->json('lignes')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | DOCUMENT / SCAN DU BON DE TRANSFERT
                |--------------------------------------------------------------------------
                */

                $table->string('fichier_chemin')
                    ->nullable();

                $table->string('fichier_nom_original')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | SOURCE DU BON
                |--------------------------------------------------------------------------
                |
                | magasin : reçu automatiquement depuis STCD Magasin
                | manuel  : ajouté manuellement par l'atelier
                |
                */

                $table->enum('source', ['magasin', 'manuel'])
                    ->default('manuel');

                /*
                |--------------------------------------------------------------------------
                | NOTES
                |--------------------------------------------------------------------------
                */

                $table->text('notes')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | UTILISATEUR AYANT SAISI LE BON
                |--------------------------------------------------------------------------
                */

                $table->foreignId('saisi_par')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();
            });
        }
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('bons_transfert');
    }
};
