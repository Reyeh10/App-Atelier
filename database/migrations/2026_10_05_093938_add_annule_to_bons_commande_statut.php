<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bon de commande « annulé » : devis refusé par le client, ou plus aucune pièce
 * dans le devis. Le BC reste visible (historique) mais ne bloque plus l'OR et
 * le magasin est prévenu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bons_commande MODIFY statut ENUM('en_attente', 'commande', 'recu', 'annule') NOT NULL DEFAULT 'en_attente'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('bons_commande')->where('statut', 'annule')->update(['statut' => 'en_attente']);
            DB::statement("ALTER TABLE bons_commande MODIFY statut ENUM('en_attente', 'commande', 'recu') NOT NULL DEFAULT 'en_attente'");
        }
    }
};
