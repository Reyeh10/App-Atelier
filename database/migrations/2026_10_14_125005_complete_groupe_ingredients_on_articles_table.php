<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingrédients de peinture / antirouille (v5) : chaque ingrédient (peinture,
 * mastic, balise, papier cache…) reste une pièce à part — son stock, son BC,
 * son BT — mais les pièces d'un même groupe s'impriment en UNE seule ligne
 * sur le devis, la facture et l'avoir (cf. GroupeIngredientsService).
 */
return new class extends Migration
{
    private array $tables = ['lignes_devis', 'lignes_facture', 'lignes_avoir'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('groupe', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('groupe');
            });
        }
    }
};
