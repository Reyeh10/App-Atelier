<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bus du transport public : même fonctionnement que le bus leasing (liste des bus,
 * BC directs, pas de facture), avec sa propre liste (vehicules_leasing.categorie
 * = « transport »), son propre récap (recaps_leasing.type = « transport ») et son
 * forfait mensuel par bus (15 000 FDJ par défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->unsignedInteger('forfait_bus_transport')->default(15000);
        });
    }

    public function down(): void
    {
        Schema::table('parametres_atelier', fn (Blueprint $t) => $t->dropColumn('forfait_bus_transport'));
    }
};
