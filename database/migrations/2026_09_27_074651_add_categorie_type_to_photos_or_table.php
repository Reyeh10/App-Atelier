<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la catégorie (VIN, tableau de bord, pièce endommagée, pièce neuve,
 * référence pièce, vidéo du bruit...) et le type (photo/vidéo) aux fichiers
 * d'un OR — utilisés pour constituer le dossier de preuves d'une réclamation
 * garantie constructeur (cf. OrdreReparationController::uploadPhotosGarantie()).
 * Les photos déjà en base restent `categorie = null` (photos génériques du
 * véhicule, réception/restitution) et `type = 'photo'`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos_or', function (Blueprint $table) {
            $table->string('categorie')->nullable()->after('moment');
            $table->enum('type', ['photo', 'video'])->default('photo')->after('categorie');
        });
    }

    public function down(): void
    {
        Schema::table('photos_or', function (Blueprint $table) {
            $table->dropColumn(['categorie', 'type']);
        });
    }
};
