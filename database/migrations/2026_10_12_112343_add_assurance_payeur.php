<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accident payé par une assurance : le véhicule reste au client (pas de changement
 * de propriétaire), mais le dossier, l'OR et la facture indiquent l'assurance qui
 * paie (un client de type « assurance ») et le n° de sinistre.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['dossiers_reception', 'ordres_reparations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('assurance_id')->nullable()->after('client_id')->constrained('clients')->nullOnDelete();
                $t->string('numero_sinistre', 100)->nullable()->after('assurance_id');
            });
        }

        Schema::table('factures', function (Blueprint $t) {
            $t->foreignId('assurance_id')->nullable()->after('client_id')->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assurance_id');
        });

        foreach (['dossiers_reception', 'ordres_reparations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('assurance_id');
                $t->dropColumn('numero_sinistre');
            });
        }
    }
};
