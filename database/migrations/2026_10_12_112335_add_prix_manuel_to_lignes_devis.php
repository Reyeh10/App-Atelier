<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devis sans réception (devis libre) : le prix d'une pièce peut être saisi à la
 * main au lieu d'attendre le prix du magasin. prix_manuel = vrai → le prix saisi
 * est gardé, la réponse du magasin ne l'écrase pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            $table->boolean('prix_manuel')->default(false)->after('prix_unitaire');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_devis', function (Blueprint $table) {
            $table->dropColumn('prix_manuel');
        });
    }
};
