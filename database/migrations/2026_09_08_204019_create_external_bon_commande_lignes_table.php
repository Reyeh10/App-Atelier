<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'external_bon_commande_lignes',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'external_bon_commande_id'
                )
                    ->constrained(
                        'external_bons_commande'
                    )
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | PRODUCT_ID EXTERNE
                |--------------------------------------------------------------------------
                |
                | App-Atelier ne possède pas la table products.
                | On stocke donc simplement l'identifiant du produit venant
                | de l'application Gestion_PiecesDetachees, sans FK locale.
                |
                */
                $table->unsignedBigInteger(
                    'product_id'
                )
                    ->nullable();

                $table->string(
                    'reference'
                );

                $table->string(
                    'designation'
                )
                    ->nullable();

                $table->decimal(
                    'quantite_demandee',
                    10,
                    2
                );

                $table->decimal(
                    'quantite_disponible',
                    10,
                    2
                )
                    ->nullable();

                $table->boolean(
                    'disponible'
                )
                    ->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | INDEX
                |--------------------------------------------------------------------------
                */
                $table->index(
                    'product_id'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'external_bon_commande_lignes'
        );
    }
};
