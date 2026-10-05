<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avoirs : une facture émise ne se supprime jamais. Pour l'annuler ou la
 * corriger, l'administrateur émet un avoir (facture négative) qui l'annule
 * en totalité — suivi, en cas de correction, d'une nouvelle facture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avoirs', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('facture_id')->constrained('factures');
            $table->foreignId('facture_remplacement_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->foreignId('or_id')->constrained('ordres_reparations');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('marque_garantie_id')->nullable()->constrained('marques_garantie')->nullOnDelete();
            $table->enum('type', ['annulation', 'correction']);
            $table->text('motif');
            $table->date('date_emission');
            $table->decimal('montant_ht', 10, 2)->default(0);
            $table->decimal('taux_tva', 5, 2)->default(10);
            $table->decimal('montant_tva', 10, 2)->default(0);
            $table->decimal('montant_ttc', 10, 2)->default(0);
            $table->decimal('frais_timbre', 10, 2)->default(0);
            $table->decimal('montant_deja_paye', 10, 2)->default(0);
            $table->decimal('montant_a_rembourser', 10, 2)->default(0);
            // Remboursement effectif au client (ou déduction sur la nouvelle facture)
            $table->date('rembourse_le')->nullable();
            $table->string('mode_remboursement', 30)->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lignes_avoir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avoir_id')->constrained('avoirs')->cascadeOnDelete();
            $table->enum('type', ['main_oeuvre', 'piece', 'forfait', 'autre'])->default('main_oeuvre');
            $table->string('designation');
            $table->string('reference', 100)->nullable();
            $table->string('unite', 20)->nullable();
            $table->decimal('quantite', 8, 2)->default(1);
            $table->decimal('prix_unitaire', 10, 2)->default(0);
            $table->decimal('remise', 5, 2)->default(0);
            $table->decimal('total_ht', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_avoir');
        Schema::dropIfExists('avoirs');
    }
};
