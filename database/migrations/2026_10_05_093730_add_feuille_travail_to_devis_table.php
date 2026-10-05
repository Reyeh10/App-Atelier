<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque devis complémentaire accepté a sa propre feuille de travail : son
 * propre technicien (qui peut être différent de celui de la feuille 1), son
 * propre pointage (début / fin) et sa propre impression — cf.
 * OrdreReparationController::affecterFeuille() / demarrerFeuille() /
 * terminerFeuille(). La feuille 1 (premier devis accepté) continue d'utiliser
 * les champs équivalents de l'OR, pour ne rien changer aux rapports et au
 * tableau de bord.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->foreignId('technicien_id')->nullable()->after('statut')->constrained('techniciens')->nullOnDelete();
            $table->enum('service', ['rapide', 'mecanique', 'electricite', 'carrosserie', 'peinture'])->nullable()->after('technicien_id');
            $table->foreignId('chef_id')->nullable()->after('service')->constrained('users')->nullOnDelete();
            $table->timestamp('date_affectation')->nullable()->after('chef_id');
            $table->decimal('duree_estimee', 5, 2)->nullable()->after('date_affectation');
            $table->timestamp('heure_debut_travaux')->nullable()->after('duree_estimee');
            $table->timestamp('heure_fin_travaux')->nullable()->after('heure_debut_travaux');
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Technicien::class, 'technicien_id');
            $table->dropForeignIdFor(\App\Models\User::class, 'chef_id');
            $table->dropColumn(['technicien_id', 'service', 'chef_id', 'date_affectation', 'duree_estimee', 'heure_debut_travaux', 'heure_fin_travaux']);
        });
    }
};
