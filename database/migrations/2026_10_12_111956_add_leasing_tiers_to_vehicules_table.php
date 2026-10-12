<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leasing tiers : véhicules appartenant à des particuliers (clients) et loués à
 * STCD. Même logique que le parc STCD (pas de facture au passage ; chaque mois,
 * forfait + pièces), mais le récap est séparé et regroupé par propriétaire.
 * - vehicules.leasing_tiers : véhicule du client loué à STCD (choisi sur la fiche client) ;
 * - recaps_leasing.type : « stcd » (parc STCD) ou « tiers » ;
 * - lignes_recap_leasing.client_id / vehicule_id : propriétaire et véhicule de la ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->boolean('leasing_tiers')->default(false);
        });

        Schema::table('recaps_leasing', function (Blueprint $table) {
            $table->string('type', 10)->default('stcd')->after('id');
            $table->dropUnique(['date_debut', 'date_fin']);
            $table->unique(['type', 'date_debut', 'date_fin']);
        });

        Schema::table('lignes_recap_leasing', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('recap_leasing_id')->constrained('clients')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->after('client_id')->constrained('vehicules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lignes_recap_leasing', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicule_id');
            $table->dropConstrainedForeignId('client_id');
        });

        // Sans la colonne « type », seuls les récaps du parc STCD peuvent rester
        // (une même période ne peut plus avoir qu'un récap)
        \Illuminate\Support\Facades\DB::table('recaps_leasing')->where('type', '!=', 'stcd')->delete();

        Schema::table('recaps_leasing', function (Blueprint $table) {
            $table->dropUnique(['type', 'date_debut', 'date_fin']);
            $table->unique(['date_debut', 'date_fin']);
            $table->dropColumn('type');
        });

        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn('leasing_tiers');
        });
    }
};
