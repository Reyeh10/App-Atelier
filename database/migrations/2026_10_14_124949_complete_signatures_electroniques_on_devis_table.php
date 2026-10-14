<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signature électronique du client (v5) : dessinée à l'écran à la réception et
 * à la restitution, stockée en PNG (disque public, dossier signatures/) et
 * reproduite sur la fiche imprimée. Le booléen signature_client /
 * signature_restitution existant passe à vrai dès qu'une signature est enregistrée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers_reception', function (Blueprint $t) {
            $t->string('signature_client_image')->nullable()->after('signature_client');
        });

        Schema::table('ordres_reparations', function (Blueprint $t) {
            $t->string('signature_client_image')->nullable()->after('signature_client');
            $t->string('signature_restitution_image')->nullable()->after('signature_restitution');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers_reception', function (Blueprint $t) {
            $t->dropColumn('signature_client_image');
        });

        Schema::table('ordres_reparations', function (Blueprint $t) {
            $t->dropColumn(['signature_client_image', 'signature_restitution_image']);
        });
    }
};
