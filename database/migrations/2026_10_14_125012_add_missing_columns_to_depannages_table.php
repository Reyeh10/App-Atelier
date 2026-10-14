<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dépannage (v5) : pièces (+ main-d'œuvre) prises sans réception ni devis.
 * Les pièces partent au magasin par un BC (bons_commande.depannage_id) ; une
 * fois le BT reçu, un véhicule leasing passe au récap (pièces seulement), un
 * client normal reçoit une facture (factures.depannage_id).
 */
return new class extends Migration
{
    public function up(): void
    {
       Schema::table('depannages', function (Blueprint $t) {
                $t->string('numero')->unique();
                $t->foreignId('client_id')->constrained('clients');
                $t->foreignId('vehicule_id')->constrained('vehicules');
                $t->foreignId('cree_par_id')->nullable()->constrained('users')->nullOnDelete();
                $t->date('date_depannage');
                $t->text('description')->nullable();
                $t->boolean('leasing')->default(false);
                $t->foreignId('facture_id')->nullable()->constrained('factures')->nullOnDelete();
            });

        // Main-d'œuvre du dépannage (les pièces sont les lignes du BC)
        Schema::create('lignes_depannage', function (Blueprint $t) {
            $t->id();
            $t->foreignId('depannage_id')->constrained('depannages')->cascadeOnDelete();
            $t->string('designation');
            $t->decimal('quantite', 10, 2)->default(1);
            $t->decimal('prix_unitaire', 12, 2)->default(0);
            $t->timestamps();
        });

        Schema::table('bons_commande', function (Blueprint $t) {
            $t->foreignId('depannage_id')->nullable()->constrained('depannages')->nullOnDelete();
        });

        Schema::table('factures', function (Blueprint $t) {
            $t->foreignId('depannage_id')->nullable()->constrained('depannages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $t) {
            $t->dropConstrainedForeignId('depannage_id');
        });
        Schema::table('bons_commande', function (Blueprint $t) {
            $t->dropConstrainedForeignId('depannage_id');
        });
        Schema::dropIfExists('lignes_depannage');
       Schema::table('depannages', function (Blueprint $t) {
    $t->dropForeign(['client_id']);
    $t->dropForeign(['vehicule_id']);
    $t->dropForeign(['cree_par_id']);
    $t->dropForeign(['facture_id']);

    $t->dropUnique(['numero']);

    $t->dropColumn([
        'numero',
        'client_id',
        'vehicule_id',
        'cree_par_id',
        'date_depannage',
        'description',
        'leasing',
        'facture_id',
    ]);
});
    }
};
