<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligne les barèmes sur les tableaux constructeur fournis par l'utilisateur
 * le 28/09/2026 (PDF Wingle 5 diesel, Wingle 7, Tank 400, Tank 700).
 *
 *  - Wingle 5 : réécrit en entier. Il avait été saisi comme une copie du
 *    Wingle 7 (huile de BVM à 5/40/76 000 km au lieu d'un palier sur deux,
 *    huile de différentiel, permutation à chaque palier, frein à tambour
 *    absent…). Filtre à carburant : ligne « 4D20B » (R à chaque palier).
 *  - Tank 400 et Tank 700 : réécrits en entier. Les consignes en texte
 *    (« au maximum tous les X mois ou Y km ») n'étaient notées qu'une fois ;
 *    elles sont maintenant déroulées sur tout le calendrier, au dernier palier
 *    qui ne dépasse ni X mois ni Y km depuis la fois précédente. Tank 700 :
 *    tableau « Brunei, Laos, Cambodge, Émirats, Arabie saoudite… Égypte »
 *    (5 000 km puis tous les 10 000 km), celui déjà utilisé.
 *  - Permutation des pneus : les cases R (permutation à faire, facturée en
 *    main-d'œuvre) passent en « remplacer », les cases I restent de simples
 *    contrôles. Tous les barèmes déjà en base ne contenaient que des cases R
 *    (transformées en « inspecter » par 2026_09_27_000002).
 *  - Huile de différentiel « 50 000 km / 36 mois, puis tous les 100 000 km /
 *    36 mois » (GW4D20, GW4C20, Wingle 7) : les 36 mois arrivent avant les
 *    50 000 km, elle est placée aux paliers de 36 et 72 mois.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->wingle5();
            $this->tank400();
            $this->tank700();
            $this->permutationsAFaire();
            $this->huileDifferentiel();
        });
    }

    public function down(): void
    {
        // Correction de données d'après les tableaux constructeur : pas de retour arrière.
    }

    // ── Wingle 5 diesel ─────────────────────────────────────────────────

    private function wingle5(): void
    {
        $paliers = $this->grille(
            [5, 10, 16, 22, 28, 34, 40, 46, 52, 58, 64, 70, 76, 82, 88, 94, 100],
            [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84, 90, 96, 102]
        );
        $unSurDeux  = [5000, 16000, 28000, 40000, 52000, 64000, 76000, 88000, 100000];
        $tous24Mois = [22000, 46000, 70000, 94000];

        $this->reecrire('WINGLE5', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Rondelle du bouchon de vidange du carter d'huile" => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Filtre à carburant'                               => $this->tous('remplacer'),
            'Préfiltre à carburant'                            => $this->tous('remplacer'),
            'Galet tendeur, galet de renvoi et poulies'        => $this->aux([22000, 40000, 58000, 76000, 94000], 'inspecter'),
            'Vanne EGR'                                        => $this->aux([16000, 34000, 52000, 70000, 88000], 'nettoyer'),
            "Intérieur et tuyaux de raccordement de l'échangeur intermédiaire (intercooler)"
                                                               => $this->aux([16000, 34000, 52000, 70000, 88000], 'nettoyer'),
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
            'Parallélisme des quatre roues'                    => $this->aux($unSurDeux, 'inspecter'),
            'Rotule et soufflet de protection'                 => $this->aux($unSurDeux, 'inspecter'),
            'Élément de filtre à air'                          => $this->alterne('nettoyer', 'remplacer'),
            'Radiateur (aspect extérieur)'                     => $this->tous('inspecter'),
            'Échangeur intermédiaire - intercooler (aspect extérieur)' => $this->tous('inspecter'),
            'Filtre de climatisation'                          => $this->alterne('nettoyer', 'remplacer'),
            'Liquide de refroidissement moteur'                => $this->sauf($tous24Mois, 'remplacer', 'inspecter'),
            'Liquide de frein'                                 => $this->sauf($tous24Mois, 'remplacer', 'inspecter'),
            'Liquide de direction assistée'                    => $this->sauf($tous24Mois, 'remplacer', 'inspecter'),
            'Batterie'                                         => $this->tous('inspecter'),
            'Serrure de porte'                                 => $this->tous('lubrifier'),
            'Câble de la trappe à carburant'                   => $this->tous('lubrifier'),
            'Fuites sur le véhicule (huile / eau / électricité / air)' => $this->tous('inspecter'),
            'Éclairage du véhicule'                            => $this->tous('inspecter'),
        ]);

        // Hors calendrier (au-delà du dernier palier), gardée pour mémoire
        $this->inserer('WINGLE5', 'Huile de boîte de transfert — remplacement à 148 000 km, puis tous les 150 000 km', 'remplacer', 148000, null);
    }

    // ── Tank 400 PHEV (10 000 km / 12 mois) ────────────────────────────

    private function tank400(): void
    {
        $paliers = $this->grille(
            [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120],
            [12, 24, 36, 48, 60, 72, 84, 96, 108, 120, 132, 144]
        );

        $this->reecrire('TANK400-PHEV', $paliers, $this->baremePhev($paliers, [
            'Parallélisme des quatre roues' => $this->aux([10000, 20000], 'inspecter'),
            'Permutation des pneus'         => $this->tous('inspecter'),
            // Tank 400 : 72 mois ou 150 000 km
            'Huile de transmission'         => $this->echeance($paliers, 150000, 72, 'remplacer'),
            'Filtre de pression'            => $this->echeance($paliers, 150000, 72, 'remplacer'),
        ], avecAutoApprentissage: false));
    }

    // ── Tank 700 PHEV (5 000 km puis tous les 10 000 km / 12 mois) ─────

    private function tank700(): void
    {
        $paliers = $this->grille(
            [5, 15, 25, 35, 45, 55, 65, 75, 85, 95, 105, 115],
            [6, 18, 30, 42, 54, 66, 78, 90, 102, 114, 126, 138]
        );

        $this->reecrire('TANK700-PHEV', $paliers, $this->baremePhev($paliers, [
            'Parallélisme des quatre roues' => $this->aux([5000, 15000], 'inspecter'),
            // I R I R… : permutation faite un palier sur deux, contrôlée sinon
            'Permutation des pneus'         => $this->sauf([15000, 35000, 55000, 75000, 95000, 115000], 'remplacer', 'inspecter'),
            // Tank 700 : 48 mois ou 80 000 km
            'Huile de transmission'         => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Filtre de pression'            => $this->echeance($paliers, 80000, 48, 'remplacer'),
        ], avecAutoApprentissage: true));
    }

    /**
     * Lignes communes aux tableaux D03 PHEV (Tank 400 / Tank 700) ; $specifiques
     * contient les lignes qui diffèrent d'un modèle à l'autre.
     */
    private function baremePhev(array $paliers, array $specifiques, bool $avecAutoApprentissage): array
    {
        $bareme = [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Rondelle du bouchon de vidange du carter d'huile" => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->echeance($paliers, 20000, null, 'nettoyer'),
            "Bougies d'allumage"                               => $this->echeance($paliers, 70000, null, 'remplacer'),
            'Courroie striée'                                  => $this->echeance($paliers, 20000, 24, 'inspecter'),
            'Huile de boîte de transfert'                      => $this->echeance($paliers, 100000, null, 'remplacer'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Rotule et soufflet de protection'                 => $this->tous('inspecter'),
            'Élément de filtre à air'                          => $this->fusion(
                $this->echeance($paliers, 15000, 12, 'nettoyer'),
                $this->echeance($paliers, 30000, 24, 'remplacer'),
            ),
            'Filtre de climatisation'                          => $this->tous('nettoyer'),
            'Filtre du réservoir à charbon actif'              => $this->echeance($paliers, 40000, null, 'nettoyer'),
            'Huile du réducteur principal avant'               => $this->echeance($paliers, 100000, 48, 'remplacer', 50000, 48),
            'Huile du réducteur principal arrière'             => $this->echeance($paliers, 100000, 48, 'remplacer', 50000, 48),
            'Liquide de refroidissement moteur'                => $this->fusion(
                $this->echeance($paliers, 20000, 12, 'inspecter'),
                $this->echeance($paliers, 80000, 48, 'remplacer'),
            ),
            'Liquide de refroidissement (batterie de traction / moteur électrique)' => $this->fusion(
                $this->echeance($paliers, 20000, 12, 'inspecter'),
                $this->echeance($paliers, 100000, 60, 'remplacer'),
            ),
            'Liquide de frein'                                 => $this->fusion(
                $this->echeance($paliers, 20000, 12, 'inspecter'),
                $this->echeance($paliers, 80000, 36, 'remplacer'),
            ),
            'Niveau du réservoir de trop-plein haute température' => $this->tous('inspecter'),
            'Niveau du réservoir de trop-plein basse température' => $this->tous('inspecter'),
            'Radiateur (aspect extérieur)'                     => $this->tous('inspecter'),
            'Batterie'                                         => $this->tous('inspecter'),
            'Toit ouvrant'                                     => fn (int $i) => $i < 2 ? 'lubrifier' : 'inspecter',
            "Tuyau d'évacuation du toit ouvrant"               => $this->tous('inspecter'),
            'Poignée de porte'                                 => $this->tous('inspecter'),
            'État de la carrosserie'                           => $this->echeance($paliers, null, 24, 'inspecter', null, 48),
            'Boîtier de la batterie de traction (bloc-batterie B)' => $this->tous('inspecter'),
            'Connecteurs haute/basse tension de la batterie de traction (bloc-batterie B)' => $this->tous('inspecter'),
            'Paramètres d\'état de la batterie de traction (SOC / température / tension des cellules / isolement)' => $this->tous('inspecter'),
            'Système de faisceau haute tension'                => $this->tous('inspecter'),
            'Prise de charge'                                  => $this->tous('inspecter'),
            "Tuyau d'évacuation de la prise de charge"         => $this->tous('inspecter'),
            'Système du groupe motopropulseur électrique'      => $this->tous('inspecter'),
            "Système d'alimentation haute tension"             => $this->tous('inspecter'),
        ];
        if ($avecAutoApprentissage) {
            $bareme['Auto-apprentissage du siège'] = $this->tous('inspecter');
        }

        return array_merge($bareme, $specifiques);
    }

    // ── Corrections ponctuelles des autres barèmes ──────────────────────

    /** Permutation des pneus : les cases R encore en base sont à faire. */
    private function permutationsAFaire(): void
    {
        $exclus = DB::table('types_moteur')->whereIn('code', ['TANK400-PHEV', 'TANK700-PHEV', 'WINGLE5'])->pluck('id');

        DB::table('entretien_taches')
            ->where('designation', 'Permutation des pneus')
            ->whereNotIn('type_moteur_id', $exclus)
            ->update(['action' => 'remplacer']);
    }

    private function huileDifferentiel(): void
    {
        $cas = [
            'GW4D20'  => [[34000, 36], [70000, 72]],
            'GW4C20'  => [[42500, 36], [87500, 72]],
            'WINGLE7' => [[34000, 36], [70000, 72]],
        ];
        $designation = 'Huile de différentiel — remplacement à 50 000 km / 36 mois, puis tous les 100 000 km / 36 mois';

        foreach ($cas as $code => $echeances) {
            $id = DB::table('types_moteur')->where('code', $code)->value('id');
            if (! $id) {
                continue;
            }
            DB::table('entretien_taches')
                ->where('type_moteur_id', $id)
                ->where('designation', 'like', 'Huile de différentiel%')
                ->delete();
            foreach ($echeances as [$km, $mois]) {
                $this->inserer($code, $designation, 'remplacer', $km, $mois);
            }
        }
    }

    // ── Outils ──────────────────────────────────────────────────────────

    /** @return array<int, array{0:int, 1:int}> paliers [km, mois] */
    private function grille(array $milliersKm, array $mois): array
    {
        return array_map(fn ($k, $m) => [(int) round($k * 1000), $m], $milliersKm, $mois);
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

    private function alterne(string $pair, string $impair): callable
    {
        return fn (int $i, int $km) => $i % 2 === 0 ? $pair : $impair;
    }

    /** La seconde règle l'emporte là où elle s'applique. */
    private function fusion(callable $base, callable $prioritaire): callable
    {
        return fn (int $i, int $km) => $prioritaire($i, $km) ?? $base($i, $km);
    }

    /**
     * « Au maximum tous les $intervalleKm km ou $intervalleMois mois » (le
     * premier atteint), avec une première échéance éventuellement différente :
     * l'opération est placée au dernier palier qui ne dépasse aucune des deux
     * limites depuis la fois précédente (ou depuis la mise en circulation).
     */
    private function echeance(array $paliers, ?int $intervalleKm, ?int $intervalleMois, string $action, ?int $premierKm = null, ?int $premierMois = null): callable
    {
        $n = count($paliers);
        // Palier fictif après le dernier, au même pas, pour savoir si le dernier palier est une échéance
        $suite = [...$paliers, [2 * $paliers[$n - 1][0] - $paliers[$n - 2][0], 2 * $paliers[$n - 1][1] - $paliers[$n - 2][1]]];

        $depuis = [0, 0];
        $limKm = $premierKm ?? $intervalleKm;
        $limMois = $premierMois ?? $intervalleMois;
        $depasse = function (array $p) use (&$depuis, &$limKm, &$limMois) {
            return ($limKm !== null && $p[0] - $depuis[0] > $limKm)
                || ($limMois !== null && $p[1] - $depuis[1] > $limMois);
        };

        $retenus = [];
        for ($i = 0; $i < $n; $i++) {
            if ($depasse($suite[$i]) || $depasse($suite[$i + 1])) {
                $retenus[] = $paliers[$i][0];
                $depuis = $paliers[$i];
                $limKm = $intervalleKm;
                $limMois = $intervalleMois;
            }
        }

        return fn (int $i, int $km) => in_array($km, $retenus, true) ? $action : null;
    }

    /**
     * Remplace tout le barème d'un moteur : $lignes = [désignation => fn(index, km) => action|null].
     */
    private function reecrire(string $code, array $paliers, array $lignes): void
    {
        $id = DB::table('types_moteur')->where('code', $code)->value('id');
        if (! $id) {
            return;
        }
        DB::table('entretien_taches')->where('type_moteur_id', $id)->delete();

        $rows = [];
        foreach ($lignes as $designation => $regle) {
            foreach ($paliers as $i => [$km, $mois]) {
                $action = $regle($i, $km);
                if ($action === null) {
                    continue;
                }
                $rows[] = $this->ligne($id, $designation, $action, $km, $mois);
            }
        }
        foreach (array_chunk($rows, 200) as $lot) {
            DB::table('entretien_taches')->insert($lot);
        }
    }

    private function inserer(string $code, string $designation, string $action, int $km, ?int $mois): void
    {
        $id = DB::table('types_moteur')->where('code', $code)->value('id');
        if ($id) {
            DB::table('entretien_taches')->insert($this->ligne($id, $designation, $action, $km, $mois));
        }
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
