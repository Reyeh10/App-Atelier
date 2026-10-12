<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parc leasing (véhicules de location d'une société, ex. STCD) :
 * - vehicules_leasing : la liste du parc (immatriculations) — ces véhicules ne
 *   sont jamais facturés au passage ;
 * - recaps_leasing / lignes_recap_leasing : le récapitulatif d'une période (31 jours
 *   au plus) : par véhicule, les pièces de ses OR entrés dans la période + le
 *   forfait mensuel (HT, sans TVA) — enregistré pour être retrouvé, sans suivi de
 *   paiement ;
 * - ordres_reparations.parc_leasing : l'OR d'un véhicule du parc (pas de facture) ;
 * - parametres_atelier.forfait_leasing : forfait mensuel par véhicule (10 000 FDJ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules_leasing', function (Blueprint $table) {
            $table->id();
            $table->string('immatriculation', 30);
            $table->string('immatriculation_normalisee', 30)->unique();
            $table->string('proprietaire', 100)->default('STCD');
            $table->foreignId('ajoute_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recaps_leasing', function (Blueprint $table) {
            $table->id();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedInteger('forfait');
            $table->unsignedInteger('nb_vehicules');
            $table->decimal('total_pieces', 12, 2)->default(0);
            $table->decimal('total_forfaits', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['date_debut', 'date_fin']);
        });

        Schema::create('lignes_recap_leasing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recap_leasing_id')->constrained('recaps_leasing')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('immatriculation', 30);
            $table->string('proprietaire', 100);
            $table->decimal('montant_pieces', 12, 2)->default(0);
            $table->decimal('forfait', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('ors')->nullable();
        });

        Schema::table('ordres_reparations', function (Blueprint $table) {
            $table->boolean('parc_leasing')->default(false)->after('service_gratuit');
        });

        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->unsignedInteger('forfait_leasing')->default(10000);
        });
    }

    public function down(): void
    {
        Schema::table('parametres_atelier', fn (Blueprint $t) => $t->dropColumn('forfait_leasing'));
        Schema::table('ordres_reparations', fn (Blueprint $t) => $t->dropColumn('parc_leasing'));
        Schema::dropIfExists('lignes_recap_leasing');
        Schema::dropIfExists('recaps_leasing');
        Schema::dropIfExists('vehicules_leasing');
    }
};
