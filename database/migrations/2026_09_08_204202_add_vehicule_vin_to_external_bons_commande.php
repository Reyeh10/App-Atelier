<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le VIN reçu depuis app-atelier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'external_bons_commande',
            function (Blueprint $table) {
                $table->string(
                    'vehicule_vin',
                    100
                )
                    ->nullable()
                    ->after('vehicule_immatriculation');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'external_bons_commande',
            function (Blueprint $table) {
                $table->dropColumn('vehicule_vin');
            }
        );
    }
};
