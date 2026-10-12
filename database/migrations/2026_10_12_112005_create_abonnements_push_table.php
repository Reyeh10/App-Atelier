<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications avec son, à chaque étape :
 * - abonnements_push : appareils (PC, tablette, téléphone) qui ont autorisé les
 *   notifications « push » — elles arrivent avec la sonnerie de l'appareil, même
 *   système fermé ;
 * - notifications_internes.lien : page ouverte au clic (BC, devis, facture...),
 *   quand ce n'est pas l'OR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->string('appareil')->nullable();
            $table->timestamps();
        });

        Schema::table('notifications_internes', function (Blueprint $table) {
            $table->string('lien')->nullable()->after('or_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications_internes', function (Blueprint $table) {
            $table->dropColumn('lien');
        });

        Schema::dropIfExists('abonnements_push');
    }
};
