<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flotte (ex : société de bus qui a son propre atelier) : les pièces sont
 * livrées sans que le véhicule passe par le garage — ni réception, ni OR.
 *
 *   import Excel → une livraison par bus et par date → un BC par livraison
 *   (envoyé au magasin) → BT du magasin → une facture par livraison.
 *
 * Les colonnes ajoutées aux tables existantes sont toutes facultatives : les
 * factures, BC et avoirs issus d'un OR ne changent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_flotte', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('client_id')->constrained('clients');
            $table->string('fichier_nom_original')->nullable();
            $table->string('fichier_chemin')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Livraison = un bus, une date : c'est l'unité facturée (une facture par livraison)
        Schema::create('livraisons_flotte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_flotte_id')->constrained('imports_flotte')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('vehicule_id')->constrained('vehicules');
            $table->date('date_livraison');
            $table->unsignedInteger('kilometrage')->nullable();
            $table->foreignId('bon_commande_id')->nullable()->constrained('bons_commande')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lignes_livraison_flotte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livraison_flotte_id')->constrained('livraisons_flotte')->cascadeOnDelete();
            $table->enum('type', ['piece', 'main_oeuvre'])->default('piece');
            $table->string('reference', 100)->nullable();
            $table->string('designation');
            $table->decimal('quantite', 10, 2);
            $table->decimal('prix_unitaire', 12, 2)->nullable();   // prix du fichier Excel
            $table->decimal('remise', 5, 2)->default(0);
            $table->foreignId('ligne_bon_commande_id')->nullable()->constrained('lignes_bon_commande')->nullOnDelete();
            $table->timestamps();
        });

        // BC sans devis : véhicule et client portés directement par le BC
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropForeign(['devis_id']);
        });
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->foreignId('devis_id')->nullable()->change();
            $table->foreign('devis_id')->references('id')->on('devis')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->after('dossier_id')->constrained('clients')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->after('client_id')->constrained('vehicules')->nullOnDelete();
        });

        // Facture sans OR : véhicule porté par la facture, liée à sa livraison flotte
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['or_id']);
        });
        Schema::table('factures', function (Blueprint $table) {
            $table->foreignId('or_id')->nullable()->change();
            $table->foreign('or_id')->references('id')->on('ordres_reparations');
            $table->foreignId('vehicule_id')->nullable()->after('client_id')->constrained('vehicules')->nullOnDelete();
            $table->foreignId('livraison_flotte_id')->nullable()->after('vehicule_id')->constrained('livraisons_flotte')->nullOnDelete();
        });

        // Avoir d'une facture flotte : pas d'OR non plus
        Schema::table('avoirs', function (Blueprint $table) {
            $table->dropForeign(['or_id']);
        });
        Schema::table('avoirs', function (Blueprint $table) {
            $table->foreignId('or_id')->nullable()->change();
            $table->foreign('or_id')->references('id')->on('ordres_reparations');
        });
    }

    public function down(): void
    {
        Schema::table('avoirs', function (Blueprint $table) {
            $table->dropForeign(['or_id']);
        });
        Schema::table('avoirs', function (Blueprint $table) {
            $table->foreignId('or_id')->nullable(false)->change();
            $table->foreign('or_id')->references('id')->on('ordres_reparations');
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['livraison_flotte_id']);
            $table->dropForeign(['vehicule_id']);
            $table->dropColumn(['livraison_flotte_id', 'vehicule_id']);
            $table->dropForeign(['or_id']);
        });
        Schema::table('factures', function (Blueprint $table) {
            $table->foreignId('or_id')->nullable(false)->change();
            $table->foreign('or_id')->references('id')->on('ordres_reparations');
        });

        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropForeign(['vehicule_id']);
            $table->dropForeign(['client_id']);
            $table->dropColumn(['vehicule_id', 'client_id']);
            $table->dropForeign(['devis_id']);
        });
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->foreignId('devis_id')->nullable(false)->change();
            $table->foreign('devis_id')->references('id')->on('devis')->cascadeOnDelete();
        });

        Schema::dropIfExists('lignes_livraison_flotte');
        Schema::dropIfExists('livraisons_flotte');
        Schema::dropIfExists('imports_flotte');
    }
};
