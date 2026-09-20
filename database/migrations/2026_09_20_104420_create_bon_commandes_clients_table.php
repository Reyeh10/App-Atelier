<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->string('numero_bon_commande_client')->nullable()->after('mode_paiement');
            $table->string('bon_commande_client_chemin')->nullable()->after('numero_bon_commande_client');
            $table->string('bon_commande_client_nom_original')->nullable()->after('bon_commande_client_chemin');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn(['numero_bon_commande_client', 'bon_commande_client_chemin', 'bon_commande_client_nom_original']);
        });
    }
};
