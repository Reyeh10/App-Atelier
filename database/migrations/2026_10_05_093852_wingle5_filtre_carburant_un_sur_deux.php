<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wingle 5 diesel : les véhicules entretenus n'ont pas le moteur 4D20B
 * (confirmé par l'utilisateur le 28/09/2026). Le filtre à carburant suit donc
 * la ligne « autres » du tableau constructeur : I à 5, 16, 28… 100 000 km,
 * R à 10, 22, 34… 94 000 km (un palier sur deux), au lieu de R à chaque palier.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('types_moteur')->where('code', 'WINGLE5')->value('id');
        if (! $id) {
            return;
        }

        DB::table('entretien_taches')
            ->where('type_moteur_id', $id)
            ->where('designation', 'Filtre à carburant')
            ->whereIn('km_seuil', [5000, 16000, 28000, 40000, 52000, 64000, 76000, 88000, 100000])
            ->update(['action' => 'inspecter']);
    }

    public function down(): void
    {
        $id = DB::table('types_moteur')->where('code', 'WINGLE5')->value('id');
        if (! $id) {
            return;
        }

        DB::table('entretien_taches')
            ->where('type_moteur_id', $id)
            ->where('designation', 'Filtre à carburant')
            ->update(['action' => 'remplacer']);
    }
};
