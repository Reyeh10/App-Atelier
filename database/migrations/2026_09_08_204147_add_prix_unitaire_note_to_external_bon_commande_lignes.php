<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le garage envoie désormais le bon de commande dès la création du devis,
 * afin que le prix et la disponibilité puissent être renseignés avant
 * validation finale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'external_bon_commande_lignes',
            function (Blueprint $table) {
                $table->decimal('prix_unitaire', 12, 2)
                    ->nullable()
                    ->after('disponible');

                $table->string('note')
                    ->nullable()
                    ->after('prix_unitaire');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'external_bon_commande_lignes',
            function (Blueprint $table) {
                $table->dropColumn([
                    'prix_unitaire',
                    'note',
                ]);
            }
        );
    }
};
