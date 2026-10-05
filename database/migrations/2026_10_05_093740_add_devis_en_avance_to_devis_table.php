<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devis en avance : un devis peut désormais être établi AVANT toute réception —
 * soit rattaché à une réservation (le client connaît le prix avant son RDV),
 * soit « libre » (simple demande de prix pour un client / véhicule). Le jour de
 * la réception, le devis de la réservation est repris par le dossier (cf.
 * DossierReceptionController::store()).
 *
 * client_id / vehicule_id : renseignés pour ces devis sans OR ni dossier.
 * kilometrage_prevu / date_prevue : estimation utilisée pour trouver le palier
 * d'entretien (kilométrage OU délai en mois à la date du RDV).
 * entretien_km_seuil : palier retenu, comparé à celui calculé à la réception.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->after('dossier_id')->constrained('reservations')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('reservation_id')->constrained('clients')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->after('client_id')->constrained('vehicules')->nullOnDelete();
            $table->unsignedInteger('kilometrage_prevu')->nullable()->after('vehicule_id');
            $table->date('date_prevue')->nullable()->after('kilometrage_prevu');
            $table->unsignedInteger('entretien_km_seuil')->nullable()->after('date_prevue');
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Reservation::class, 'reservation_id');
            $table->dropForeignIdFor(\App\Models\Client::class, 'client_id');
            $table->dropForeignIdFor(\App\Models\Vehicule::class, 'vehicule_id');
            $table->dropColumn(['reservation_id', 'client_id', 'vehicule_id', 'kilometrage_prevu', 'date_prevue', 'entretien_km_seuil']);
        });
    }
};
