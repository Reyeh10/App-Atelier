<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige la permutation des pneus du GW4D20 d'après le tableau constructeur
 * (capture fournie par l'utilisateur) : R tous les 12 mois, soit 10, 22, 34,
 * 46, 58, 70, 82 et 94 000 km. La transcription d'origine ne la plaçait
 * qu'aux 24 mois (22, 46, 70, 94 000 km) — elle manquait notamment au devis
 * du palier 10 000 km.
 *
 * Action « inspecter » : c'est une opération facturée en main-d'œuvre, pas
 * une pièce (même convention que 2026_09_27_000002_corriger_baremes_entretien).
 */
return new class extends Migration
{
    private const PALIERS = [
        10000 => 12, 22000 => 24, 34000 => 36, 46000 => 48,
        58000 => 60, 70000 => 72, 82000 => 84, 94000 => 96,
    ];

    public function up(): void
    {
        $id = DB::table('types_moteur')->where('code', 'GW4D20')->value('id');
        if (! $id) {
            return;
        }

        DB::transaction(function () use ($id) {
            DB::table('entretien_taches')
                ->where('type_moteur_id', $id)
                ->where('designation', 'Permutation des pneus')
                ->delete();

            $rows = [];
            foreach (self::PALIERS as $km => $mois) {
                $rows[] = [
                    'type_moteur_id' => $id,
                    'designation'    => 'Permutation des pneus',
                    'action'         => 'inspecter',
                    'km_seuil'       => $km,
                    'mois_seuil'     => $mois,
                    'ordre'          => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
            DB::table('entretien_taches')->insert($rows);
        });
    }

    public function down(): void
    {
        $id = DB::table('types_moteur')->where('code', 'GW4D20')->value('id');
        if (! $id) {
            return;
        }

        DB::table('entretien_taches')
            ->where('type_moteur_id', $id)
            ->where('designation', 'Permutation des pneus')
            ->whereIn('km_seuil', [10000, 34000, 58000, 82000])
            ->delete();
    }
};
