<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bon de transfert (BT) : quand l'atelier envoie un bon de commande au magasin
 * (stcd-magasin), le magasin crée un BT pour transférer les pièces au garage.
 * Un seul BT par bon de commande (un nouveau BT remplace le précédent).
 * Il arrive automatiquement par l'API, ou est joint à la main (scan du papier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bons_transfert', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bon_commande_id')
                ->unique()
                ->constrained('bons_commande')
                ->cascadeOnDelete();

            $table->string('numero', 50);
            $table->date('date_transfert')->nullable();
            $table->string('depot', 100)->nullable();
            $table->json('lignes')->nullable();
            $table->string('fichier_chemin')->nullable();
            $table->string('fichier_nom_original')->nullable();

            $table->enum('source', ['magasin', 'manuel'])
                ->default('manuel');

            $table->text('notes')->nullable();

            $table->foreignId('saisi_par')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bons_transfert');
    }
};
