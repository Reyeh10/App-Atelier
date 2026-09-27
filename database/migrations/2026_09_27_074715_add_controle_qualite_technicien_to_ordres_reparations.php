<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enregistre quel technicien (parmi la liste habituelle, cf. affectation) a
 * réalisé le contrôle qualité — pas d'interface de signature électronique :
 * son nom est simplement repris sur la feuille de travail imprimée, dans le
 * cadre "Contrôle qualité" (cf. OrdreReparationController::validerQualite()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordres_reparations', function (Blueprint $table) {
            $table->foreignId('controle_qualite_technicien_id')->nullable()->after('technicien_id')->constrained('techniciens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordres_reparations', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Technicien::class, 'controle_qualite_technicien_id');
            $table->dropColumn('controle_qualite_technicien_id');
        });
    }
};
