<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une marque garantie constructeur a elle aussi un plafond de crédit
 * (cf. MarqueGarantie::plafond_credit) et peut accumuler plusieurs factures à
 * regrouper en un seul encaissement, exactement comme un client à compte
 * crédit — cf. EncaissementGlobalController. `client_id` devient facultatif :
 * un encaissement groupé cible soit un client, soit une marque garantie,
 * jamais les deux.
 *
 * Rendre `client_id` nullable via SQL brut (plutôt que Blueprint::change(),
 * qui exige doctrine/dbal, absent de ce projet) — sans risque pour la clé
 * étrangère existante, MySQL/InnoDB autorise NULL sur une colonne FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE encaissements_globaux MODIFY client_id BIGINT UNSIGNED NULL');

        Schema::table('encaissements_globaux', function (Blueprint $table) {
            $table->foreignId('marque_garantie_id')->nullable()->after('client_id')->constrained('marques_garantie');
        });
    }

    public function down(): void
    {
        Schema::table('encaissements_globaux', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\MarqueGarantie::class);
            $table->dropColumn('marque_garantie_id');
        });

        DB::statement('ALTER TABLE encaissements_globaux MODIFY client_id BIGINT UNSIGNED NOT NULL');
    }
};
