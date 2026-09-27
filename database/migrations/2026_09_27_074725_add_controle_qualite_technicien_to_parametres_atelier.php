<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsable qualité fixe (cf. Réglages atelier), utilisé automatiquement pour
 * tous les OR au moment de valider le contrôle qualité — plus besoin de choisir
 * un technicien à chaque validation (cf. OrdreReparationController::validerQualite()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->foreignId('controle_qualite_technicien_id')->nullable()->constrained('techniciens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Technicien::class, 'controle_qualite_technicien_id');
            $table->dropColumn('controle_qualite_technicien_id');
        });
    }
};
