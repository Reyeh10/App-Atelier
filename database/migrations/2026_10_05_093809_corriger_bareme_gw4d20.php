<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligne le barème GW4D20 sur le tableau constructeur (captures fournies par
 * l'utilisateur le 28/09/2026). Lignes corrigées :
 *
 *  - Huile de boîte de vitesses (BVM) : R à 5, 40 et 76 000 km, I ailleurs.
 *    Remplace la correction 2026_08_12_095528 (R à 22 000 km), contredite par
 *    le tableau : la BVM arrivait à tort dans le devis du palier 22 000 km.
 *  - Liquide de direction assistée : R à 22, 46, 70 et 94 000 km (tous les
 *    24 mois), I ailleurs — il n'était jamais remplacé.
 *  - Parallélisme des 4 roues, Rotule et soufflet de protection : I tous les
 *    12 mois seulement (10, 22, 34… 94 000 km), pas à chaque palier.
 *  - Échangeur (intercooler) et tuyauterie : C à 16, 34, 52, 70 et 88 000 km
 *    (et non 28, 52, 76, 100).
 */
return new class extends Migration
{
    private const PALIERS = [
        5000 => 6, 10000 => 12, 16000 => 18, 22000 => 24, 28000 => 30, 34000 => 36,
        40000 => 42, 46000 => 48, 52000 => 54, 58000 => 60, 64000 => 66, 70000 => 72,
        76000 => 78, 82000 => 84, 88000 => 90, 94000 => 96, 100000 => 102,
    ];

    private const ANNUELS = [10000, 22000, 34000, 46000, 58000, 70000, 82000, 94000];

    public function up(): void
    {
        $id = DB::table('types_moteur')->where('code', 'GW4D20')->value('id');
        if (! $id) {
            return;
        }

        DB::transaction(function () use ($id) {
            $this->remplacer($id, 'Huile de boîte de vitesses (BVM)', function (int $km) {
                return in_array($km, [5000, 40000, 76000], true) ? 'remplacer' : 'inspecter';
            });

            $this->remplacer($id, 'Liquide de direction assistée', function (int $km) {
                return in_array($km, [22000, 46000, 70000, 94000], true) ? 'remplacer' : 'inspecter';
            });

            foreach (['Parallélisme des 4 roues', 'Rotule et soufflet de protection'] as $designation) {
                $this->remplacer($id, $designation, fn (int $km) => in_array($km, self::ANNUELS, true) ? 'inspecter' : null);
            }

            $this->remplacer($id, 'Échangeur (intercooler) et tuyauterie de raccordement', function (int $km) {
                return in_array($km, [16000, 34000, 52000, 70000, 88000], true) ? 'nettoyer' : null;
            });
        });
    }

    public function down(): void
    {
        // Correction de données d'après le tableau constructeur : pas de retour arrière.
    }

    /**
     * Réécrit toute la ligne d'une désignation : $action(km) donne l'action à
     * ce palier, ou null si rien n'est prévu.
     */
    private function remplacer(int $typeMoteurId, string $designation, callable $action): void
    {
        DB::table('entretien_taches')
            ->where('type_moteur_id', $typeMoteurId)
            ->where('designation', $designation)
            ->delete();

        $rows = [];
        foreach (self::PALIERS as $km => $mois) {
            $a = $action($km);
            if ($a === null) {
                continue;
            }
            $rows[] = [
                'type_moteur_id' => $typeMoteurId,
                'designation'    => $designation,
                'action'         => $a,
                'km_seuil'       => $km,
                'mois_seuil'     => $mois,
                'ordre'          => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }
        DB::table('entretien_taches')->insert($rows);
    }
};
