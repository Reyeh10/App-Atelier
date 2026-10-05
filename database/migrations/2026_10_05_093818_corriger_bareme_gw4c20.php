<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligne le barème GW4C20 (Poer essence) sur le tableau constructeur (capture
 * fournie par l'utilisateur le 28/09/2026). Lignes corrigées :
 *
 *  - Filtre à carburant : R tous les 12 mois (12,5 / 27,5 / 42,5… 102,5 000 km),
 *    et non à chaque palier à partir de 27 500 km — il manquait au palier 12 500.
 *  - Permutation des pneus : à chaque palier à partir de 12 500 km (et non tous
 *    les 24 mois). Opération facturée en main-d'œuvre (action « inspecter »).
 *  - Papillon des gaz, Intérieur de l'échangeur et tuyauterie : C à 20 / 42,5 /
 *    65 / 87,5 / 110 000 km.
 *  - Bougie d'allumage : R à 20 / 42,5 / 65 / 87,5 / 110 000 km.
 *  - Huile de boîte de vitesses (BVM) : R à 5 / 42,5 / 80 000 km, I ailleurs.
 *  - Filtre du réservoir à charbon actif : C à 20 / 65 / 110, R à 42,5 / 87,5.
 *  - Liquide de direction assistée : R à 27,5 / 57,5 / 87,5, I ailleurs.
 *  - Parallélisme des 4 roues, Rotule et soufflet : I tous les 12 mois seulement.
 */
return new class extends Migration
{
    private const PALIERS = [
        5000 => 6, 12500 => 12, 20000 => 18, 27500 => 24, 35000 => 30,
        42500 => 36, 50000 => 42, 57500 => 48, 65000 => 54, 72500 => 60,
        80000 => 66, 87500 => 72, 95000 => 78, 102500 => 84, 110000 => 90,
    ];

    private const ANNUELS = [12500, 27500, 42500, 57500, 72500, 87500, 102500];
    private const TOUS_18_MOIS = [20000, 42500, 65000, 87500, 110000];

    public function up(): void
    {
        $id = DB::table('types_moteur')->where('code', 'GW4C20')->value('id');
        if (! $id) {
            return;
        }

        $dans = fn (array $kms, string $action) => fn (int $km) => in_array($km, $kms, true) ? $action : null;

        DB::transaction(function () use ($id, $dans) {
            $this->remplacer($id, 'Filtre à carburant', $dans(self::ANNUELS, 'remplacer'));
            $this->remplacer($id, 'Permutation des pneus', fn (int $km) => $km >= 12500 ? 'inspecter' : null);
            $this->remplacer($id, 'Papillon des gaz', $dans(self::TOUS_18_MOIS, 'nettoyer'));
            $this->remplacer($id, "Intérieur de l'échangeur et tuyauterie", $dans(self::TOUS_18_MOIS, 'nettoyer'));
            $this->remplacer($id, "Bougie d'allumage", $dans(self::TOUS_18_MOIS, 'remplacer'));
            $this->remplacer($id, 'Huile de boîte de vitesses (BVM)',
                fn (int $km) => in_array($km, [5000, 42500, 80000], true) ? 'remplacer' : 'inspecter');
            $this->remplacer($id, 'Filtre du réservoir à charbon actif (canister)', fn (int $km) => match ($km) {
                20000, 65000, 110000 => 'nettoyer',
                42500, 87500         => 'remplacer',
                default              => null,
            });
            $this->remplacer($id, 'Liquide de direction assistée',
                fn (int $km) => in_array($km, [27500, 57500, 87500], true) ? 'remplacer' : 'inspecter');
            $this->remplacer($id, 'Parallélisme des 4 roues', $dans(self::ANNUELS, 'inspecter'));
            $this->remplacer($id, 'Rotule et soufflet de protection', $dans(self::ANNUELS, 'inspecter'));
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
