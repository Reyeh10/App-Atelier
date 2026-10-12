<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Véhicules de service : véhicules affectés à un service ou à un directeur /
 * responsable. Même logique que le parc leasing STCD (OR sans facture, pièces
 * reprises dans un récap à part, regroupé par affectation) — liste dans
 * vehicules_leasing (catégorie « service », l'affectation dans « proprietaire »),
 * récap recaps_leasing.type = « service ». Forfait mensuel : 0 FDJ pour l'instant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->unsignedInteger('forfait_service')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('parametres_atelier', fn (Blueprint $t) => $t->dropColumn('forfait_service'));
    }
};
