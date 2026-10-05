<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nouveau type « Wingle 5 essence » (moteur 4G69S4N) et son barème, d'après le
 * tableau constructeur « Maintenance Item For Wingle 5 - gasoline » fourni par
 * l'utilisateur le 28/09/2026 (parallélisme, rotule, filtre à carburant et
 * canister recopiés par l'utilisateur depuis le fichier Excel).
 */
return new class extends Migration
{
    private const KM   = [5000, 10000, 16000, 22000, 28000, 34000, 40000, 46000, 52000, 58000, 64000, 70000, 76000, 82000, 88000, 94000, 100000];
    private const MOIS = [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84, 90, 96, 102];

    public function up(): void
    {
        if (DB::table('types_moteur')->where('code', 'WINGLE5-4G69')->exists()) {
            return;
        }

        $tous12Mois = [10000, 22000, 34000, 46000, 58000, 70000, 82000, 94000];
        $tous18Mois = [16000, 34000, 52000, 70000, 88000];
        $tous24Mois = [22000, 46000, 70000, 94000];
        $unSurDeux  = [5000, 16000, 28000, 40000, 52000, 64000, 76000, 88000, 100000];

        $bareme = [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Rondelle du bouchon de vidange du carter d'huile" => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->aux($tous18Mois, 'nettoyer'),
            "Intérieur et tuyaux de raccordement de l'échangeur intermédiaire (intercooler)" => $this->aux($tous18Mois, 'nettoyer'),
            "Bougies d'allumage"                               => $this->aux($tous18Mois, 'remplacer'),
            'Courroie de distribution'                         => $this->aux([76000], 'remplacer'),   // au maximum 80 000 km
            "Courroie d'alternateur / pompe à eau"             => $this->aux([100000], 'remplacer'),  // au maximum 100 000 km
            'Courroie de pompe de direction assistée'          => $this->aux([100000], 'remplacer'),  // au maximum 100 000 km
            'Huile de boîte de vitesses manuelle'              => $this->aux($unSurDeux, 'remplacer'),
            'Huile de différentiel'                            => $this->aux($unSurDeux, 'remplacer'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Frein à tambour'                                  => $this->tous('inspecter'),
            'Frein de stationnement'                           => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Permutation des pneus'                            => $this->tous('remplacer'),
            'Parallélisme des quatre roues'                    => $this->aux($tous12Mois, 'inspecter'),
            'Rotule et soufflet de protection'                 => $this->aux($tous12Mois, 'inspecter'),
            'Filtre à carburant'                               => $this->aux($tous12Mois, 'remplacer'),
            'Élément de filtre à air'                          => fn (int $i) => $i % 2 === 0 ? 'nettoyer' : 'remplacer',
            'Radiateur (aspect extérieur)'                     => $this->tous('inspecter'),
            'Échangeur intermédiaire - intercooler (aspect extérieur)' => $this->tous('inspecter'),
            'Canister et filtre du canister'                   => $this->aux($tous24Mois, 'nettoyer'),
            'Filtre de climatisation'                          => fn (int $i) => $i % 2 === 0 ? 'nettoyer' : 'remplacer',
            'Liquide de refroidissement moteur'                => $this->sauf($tous24Mois, 'remplacer', 'inspecter'),
            'Liquide de frein'                                 => $this->sauf($tous24Mois, 'remplacer', 'inspecter'),
            'Liquide de direction assistée'                    => $this->sauf($tous24Mois, 'remplacer', 'inspecter'),
            'Batterie'                                         => $this->tous('inspecter'),
            'Serrure de porte'                                 => $this->tous('lubrifier'),
            'Câble de la trappe à carburant'                   => $this->tous('lubrifier'),
            'Fuites sur le véhicule (huile / eau / électricité / air)' => $this->tous('inspecter'),
            'Éclairage du véhicule'                            => $this->tous('inspecter'),
        ];

        DB::transaction(function () use ($bareme) {
            $id = DB::table('types_moteur')->insertGetId([
                'code'       => 'WINGLE5-4G69',
                'marque'     => 'GWM',
                'modele'     => 'Wingle 5 essence',
                'libelle'    => 'Wingle 5 essence (4G69S4N)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = [];
            foreach ($bareme as $designation => $regle) {
                foreach (self::KM as $i => $km) {
                    if ($action = $regle($i, $km)) {
                        $rows[] = $this->ligne($id, $designation, $action, $km, self::MOIS[$i]);
                    }
                }
            }
            // Hors calendrier (au-delà du dernier palier), gardée pour mémoire
            $rows[] = $this->ligne($id, 'Huile de boîte de transfert — remplacement à 148 000 km, puis tous les 150 000 km', 'remplacer', 148000, null);

            foreach (array_chunk($rows, 200) as $lot) {
                DB::table('entretien_taches')->insert($lot);
            }
        });
    }

    public function down(): void
    {
        $id = DB::table('types_moteur')->where('code', 'WINGLE5-4G69')->value('id');
        if ($id) {
            DB::table('entretien_taches')->where('type_moteur_id', $id)->delete();
            DB::table('types_moteur')->where('id', $id)->delete();
        }
    }

    private function tous(string $action): callable
    {
        return fn (int $i, int $km) => $action;
    }

    private function aux(array $kms, string $action): callable
    {
        return fn (int $i, int $km) => in_array($km, $kms, true) ? $action : null;
    }

    private function sauf(array $kms, string $action, string $sinon): callable
    {
        return fn (int $i, int $km) => in_array($km, $kms, true) ? $action : $sinon;
    }

    private function ligne(int $id, string $designation, string $action, int $km, ?int $mois): array
    {
        return [
            'type_moteur_id' => $id,
            'designation'    => $designation,
            'action'         => $action,
            'km_seuil'       => $km,
            'mois_seuil'     => $mois,
            'ordre'          => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];
    }
};
