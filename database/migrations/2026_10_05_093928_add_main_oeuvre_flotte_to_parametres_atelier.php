<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Main-d'œuvre ajoutée automatiquement à chaque facture flotte (une par bus),
 * sauf si le fichier Excel en contient déjà une pour ce bus. 0 = désactivé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->unsignedInteger('main_oeuvre_flotte')->default(5000)->after('tarifs_service_rapide');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->dropColumn('main_oeuvre_flotte');
        });
    }
};
