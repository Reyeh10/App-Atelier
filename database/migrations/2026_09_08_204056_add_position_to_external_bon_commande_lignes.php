<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'external_bon_commande_lignes',
            function (Blueprint $table) {
                $table->unsignedInteger('position')
                    ->default(0)
                    ->after('external_bon_commande_id');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'external_bon_commande_lignes',
            function (Blueprint $table) {
                $table->dropColumn('position');
            }
        );
    }
};
