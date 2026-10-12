<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bus leasing : bus loués, réparés dans un autre garage (pas d'OR à l'atelier).
 * L'atelier fait directement un BC par bus (un par un ou par import Excel), le
 * magasin livre les pièces (BT), « Tout reçu » — jamais de facture. Chaque mois,
 * le récap « bus » : forfait par bus (15 000 FDJ) + les pièces de ses BC reçus.
 *
 * - vehicules_leasing.categorie : « stcd » (parc STCD) ou « bus » ; vehicule_id :
 *   la fiche du bus dans l'atelier (créée à l'import de la liste des bus) ;
 * - bons_commande.leasing_bus : BC fait directement pour un bus en leasing ;
 * - lignes_recap_leasing.bcs : BC repris dans la ligne du récap (bus) ;
 * - parametres_atelier.forfait_bus_leasing : forfait mensuel par bus (15 000 FDJ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicules_leasing', function (Blueprint $table) {
            $table->string('categorie', 10)->default('stcd')->after('id');
            $table->foreignId('vehicule_id')->nullable()->after('categorie')->constrained('vehicules')->nullOnDelete();
        });

        Schema::table('bons_commande', function (Blueprint $table) {
            $table->boolean('leasing_bus')->default(false)->after('vehicule_id');
        });

        Schema::table('lignes_recap_leasing', function (Blueprint $table) {
            $table->json('bcs')->nullable()->after('ors');
        });

        Schema::table('parametres_atelier', function (Blueprint $table) {
            $table->unsignedInteger('forfait_bus_leasing')->default(15000);
        });
    }

    public function down(): void
    {
        Schema::table('parametres_atelier', fn (Blueprint $t) => $t->dropColumn('forfait_bus_leasing'));
        Schema::table('lignes_recap_leasing', fn (Blueprint $t) => $t->dropColumn('bcs'));
        Schema::table('bons_commande', fn (Blueprint $t) => $t->dropColumn('leasing_bus'));
        Schema::table('vehicules_leasing', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicule_id');
            $table->dropColumn('categorie');
        });
    }
};
