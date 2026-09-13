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
                $table->timestamp('vu_at')
                    ->nullable()
                    ->after('vente_id');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'external_bons_commande',
            function (Blueprint $table) {
                $table->dropColumn('vu_at');
            }
        );
    }
};
