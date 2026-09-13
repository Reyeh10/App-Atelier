<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_bons_commande', function (Blueprint $table) {
            $table->id();

            $table->string('numero')->unique();

            $table->string('source_system')
                ->default('app-atelier');

            $table->string('vehicule_marque')
                ->nullable();

            $table->string('vehicule_modele')
                ->nullable();

            $table->string('vehicule_immatriculation')
                ->nullable();

            $table->string('client_nom')
                ->nullable();

            $table->string('client_telephone')
                ->nullable();

            $table->string('statut')
                ->default('recu');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_bons_commande');
    }
};
