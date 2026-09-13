<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préférence de langue d'affichage par utilisateur.
 * Valeurs : 'fr' (défaut), 'en', 'ar'. La bascule effective de l'interface
 * (fichiers de traduction + sens droite-à-gauche pour l'arabe) sera activée
 * dans un second temps — pour l'instant seule la préférence est mémorisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('fr')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
