<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wingle 5 diesel : le tableau constructeur a deux lignes « Filtre à carburant »
 * selon le moteur (précisé par l'utilisateur le 28/09/2026) :
 *  - 4D20B : remplacé à chaque révision (tous les 6 mois) ;
 *  - autres moteurs diesel : contrôlé aux révisions impaires (6, 18, 30… mois),
 *    remplacé aux révisions paires (12, 24, 36… mois) — barème WINGLE5 existant.
 *
 * Nouveau type « WINGLE5-4D20B » : copie du barème WINGLE5, filtre à carburant
 * remplacé à chaque palier. Le type existant est renommé « Wingle 5 diesel »
 * pour que les deux se distinguent dans les listes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $source = DB::table('types_moteur')->where('code', 'WINGLE5')->first();
        if (! $source || DB::table('types_moteur')->where('code', 'WINGLE5-4D20B')->exists()) {
            return;
        }

        DB::transaction(function () use ($source) {
            DB::table('types_moteur')->where('id', $source->id)->update([
                'modele'     => 'Wingle 5 diesel',
                'libelle'    => 'Wingle 5 diesel',
                'updated_at' => now(),
            ]);

            $id = DB::table('types_moteur')->insertGetId([
                'code'       => 'WINGLE5-4D20B',
                'marque'     => $source->marque,
                'modele'     => 'Wingle 5 diesel',
                'libelle'    => 'Wingle 5 diesel 4D20B',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = DB::table('entretien_taches')
                ->where('type_moteur_id', $source->id)
                ->get()
                ->map(fn ($t) => [
                    'type_moteur_id' => $id,
                    'designation'    => $t->designation,
                    'action'         => $t->designation === 'Filtre à carburant' ? 'remplacer' : $t->action,
                    'km_seuil'       => $t->km_seuil,
                    'mois_seuil'     => $t->mois_seuil,
                    'ordre'          => $t->ordre,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ])
                ->all();

            foreach (array_chunk($rows, 200) as $lot) {
                DB::table('entretien_taches')->insert($lot);
            }
        });
    }

    public function down(): void
    {
        $id = DB::table('types_moteur')->where('code', 'WINGLE5-4D20B')->value('id');
        if ($id) {
            DB::table('entretien_taches')->where('type_moteur_id', $id)->delete();
            DB::table('types_moteur')->where('id', $id)->delete();
        }
        DB::table('types_moteur')->where('code', 'WINGLE5')->update(['modele' => 'Wingle 5', 'libelle' => 'Wingle 5']);
    }
};
