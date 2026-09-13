<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE external_bon_commande_lignes MODIFY reference VARCHAR(255) NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE external_bon_commande_lignes MODIFY reference VARCHAR(255) NOT NULL'
        );
    }
};
