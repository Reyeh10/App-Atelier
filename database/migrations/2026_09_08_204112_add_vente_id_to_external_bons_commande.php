<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'external_bons_commande',
            function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('vente_id')
                    ->nullable()
                    ->after('statut');

                $table->index('vente_id');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'external_bons_commande',
            function (Blueprint $table) {
                $table->dropIndex([
                    'vente_id',
                ]);

                $table->dropColumn(
                    'vente_id'
                );
            }
        );
    }
};
