<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Majoration accident payé par une assurance (v5) : tous les prix du devis
 * (pièces et main-d'œuvre) sont augmentés du taux de l'assurance, sans ligne
 * ni mention visible — seuls les prix unitaires montent.
 *
 *  - parametres_atelier.taux_majoration_assurance : taux par défaut (35 %)
 *  - clients.taux_majoration : taux propre à une assurance (ex. 20 %), sinon le défaut
 *  - devis.taux_majoration   : taux appliqué au devis (0 = pas de majoration)
 *  - lignes_devis.prix_base  : prix avant majoration (prix du magasin ou saisi) ;
 *    prix_unitaire reste le prix final (majoré + arrondi FDJ) repris partout
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_atelier', function (Blueprint $t) {
            $t->decimal('taux_majoration_assurance', 5, 2)->default(35);
        });

        Schema::table('clients', function (Blueprint $t) {
            $t->decimal('taux_majoration', 5, 2)->nullable();
        });

        Schema::table('devis', function (Blueprint $t) {
            $t->decimal('taux_majoration', 5, 2)->default(0);
        });

        Schema::table('lignes_devis', function (Blueprint $t) {
            $t->decimal('prix_base', 12, 2)->nullable()->after('prix_unitaire');
        });

        // Devis existants : jamais majorés, le prix de base est le prix actuel
        DB::table('lignes_devis')->update(['prix_base' => DB::raw('prix_unitaire')]);
    }

    public function down(): void
    {
        Schema::table('lignes_devis', function (Blueprint $t) {
            $t->dropColumn('prix_base');
        });

        Schema::table('devis', function (Blueprint $t) {
            $t->dropColumn('taux_majoration');
        });

        Schema::table('clients', function (Blueprint $t) {
            $t->dropColumn('taux_majoration');
        });

        Schema::table('parametres_atelier', function (Blueprint $t) {
            $t->dropColumn('taux_majoration_assurance');
        });
    }
};
