<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deux nouveaux types de moteur avec leur barème d'entretien constructeur
 * (tableaux fournis par l'utilisateur le 11/10/2026 : « maintenance schedule H7 »
 * et « maintenance schedule Jolion »), même méthode que 2026_09_28_000005 :
 *
 *  - les cases du tableau (R / I / C / L) sont reprises telles quelles ;
 *  - les consignes en texte (« au maximum tous les X km ou Y mois ») sont
 *    déroulées sur tout le calendrier, au dernier palier qui ne dépasse aucune
 *    des deux limites depuis la fois précédente ;
 *  - permutation des pneus : R = à faire (main-d'œuvre facturée), I = contrôle.
 *
 * Haval H7       : 12 paliers, 5 000 km / 6 mois puis tous les 7 500 km / 6 mois.
 * Haval Jolion ICE (essence, non hybride) : 14 paliers, même grille. Boîte
 * automatique (AT) seulement (précisé par l'utilisateur le 11/10/2026) : la ligne
 * « huile de boîte manuelle (MT) » du tableau n'est pas reprise.
 */
return new class extends Migration
{
    private const HAVAL_12 = [[5, 12.5, 20, 27.5, 35, 42.5, 50, 57.5, 65, 72.5, 80, 87.5], [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72]];
    private const HAVAL_14 = [[5, 12.5, 20, 27.5, 35, 42.5, 50, 57.5, 65, 72.5, 80, 87.5, 95, 102.5], [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84]];

    public function up(): void
    {
        DB::transaction(function () {
            $this->h7();
            $this->jolionIce();
        });
    }

    public function down(): void
    {
        $ids = DB::table('types_moteur')->whereIn('code', ['H7', 'JOLION-ICE'])->pluck('id');
        DB::table('entretien_taches')->whereIn('type_moteur_id', $ids)->delete();
        DB::table('types_moteur')->whereIn('id', $ids)->delete();
    }

    // ── Haval H7 (12 paliers) ───────────────────────────────────────────

    private function h7(): void
    {
        $paliers = $this->grille(...self::HAVAL_12);
        // Permutation des pneus : I I R I R I R I R I R I
        $permutation = [20000, 35000, 50000, 65000, 80000];

        $this->creer('H7', 'Haval', 'H7', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Rondelle du bouchon de vidange du carter d'huile" => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->echeance($paliers, 20000, null, 'nettoyer'),
            "Bougies d'allumage"                               => $this->echeance($paliers, 70000, null, 'remplacer'),
            "Courroie d'alternateur / pompe à eau"             => $this->echeance($paliers, 20000, 24, 'inspecter'),
            'Huile de transmission'                            => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Filtre de pression'                               => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Huile de boîte de transfert'                      => $this->echeance($paliers, 100000, 48, 'remplacer', 50000, 48),
            'Huile du réducteur principal arrière'             => $this->echeance($paliers, 100000, 48, 'remplacer', 50000, 48),
            'Lubrifiant du gestionnaire de couple intelligent' => $this->echeance($paliers, 60000, null, 'remplacer'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Permutation des pneus'                            => $this->sauf($permutation, 'remplacer', 'inspecter'),
            'Rotule et soufflet de protection'                 => $this->tous('inspecter'),
            'Élément de filtre à air'                          => $this->fusion(
                $this->echeance($paliers, 10000, 12, 'nettoyer'),
                $this->echeance($paliers, 20000, 24, 'remplacer'),
            ),
            // Contrôlé et nettoyé à chaque entretien, remplacé si nécessaire
            'Filtre de climatisation'                          => $this->tous('nettoyer'),
            'Filtre du réservoir à charbon actif (canister)'   => $this->echeance($paliers, 40000, null, 'nettoyer'),
            'Liquide de refroidissement moteur'                => $this->fusion(
                $this->echeance($paliers, 20000, 12, 'inspecter'),
                $this->echeance($paliers, 80000, 48, 'remplacer'),
            ),
            'Liquide de frein'                                 => $this->fusion(
                $this->echeance($paliers, 20000, 12, 'inspecter'),
                $this->echeance($paliers, 80000, 36, 'remplacer'),
            ),
            'Niveau du réservoir de trop-plein haute température' => $this->tous('inspecter'),
            'Radiateur (aspect extérieur)'                     => $this->tous('inspecter'),
            'Échangeur - intercooler (aspect extérieur)'       => $this->tous('inspecter'),
            'Batterie'                                         => $this->tous('inspecter'),
            'Toit ouvrant panoramique'                         => fn (int $i) => $i === 0 ? 'lubrifier' : 'inspecter',
            "Tuyau d'évacuation du toit ouvrant"               => $this->tous('inspecter'),
            'Fuites (huile / eau / électricité / air)'         => $this->tous('inspecter'),
            'Éclairage'                                        => $this->tous('inspecter'),
            'Poignée de porte'                                 => $this->tous('inspecter'),
            // Première fois au plus tard à 48 mois, puis tous les 24 mois
            'État de la carrosserie'                           => $this->echeance($paliers, null, 24, 'inspecter', null, 48),
        ]);
    }

    // ── Haval Jolion ICE (14 paliers) ───────────────────────────────────

    private function jolionIce(): void
    {
        $paliers = $this->grille(...self::HAVAL_14);
        $tous18Mois = [20000, 42500, 65000, 87500];
        $tous24Mois = [12500, 27500, 42500, 57500, 72500, 87500, 102500];

        $this->creer('JOLION-ICE', 'Haval', 'Jolion ICE', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Joint / bouchon de vidange du carter d'huile"     => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->aux($tous18Mois, 'nettoyer'),
            "Intérieur de l'échangeur et tuyauterie"           => $this->aux($tous18Mois, 'nettoyer'),
            "Bougies d'allumage"                               => $this->aux($tous18Mois, 'remplacer'),
            "Courroie d'alternateur / pompe à eau"             => $this->echeance($paliers, 100000, null, 'remplacer'),
            // Boîte automatique : huile + cartouche et boîtier du filtre de pression ensemble
            'Huile de boîte automatique'                       => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Cartouche et boîtier du filtre de pression'       => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Permutation des pneus'                            => $this->aux($tous24Mois, 'remplacer'),
            'Parallélisme des quatre roues'                    => $this->aux($tous24Mois, 'inspecter'),
            'Rotule et soufflet de protection'                 => $this->aux($tous24Mois, 'inspecter'),
            'Élément du filtre à air'                          => $this->alterne('nettoyer', 'remplacer'),
            'Radiateur (aspect visuel)'                        => $this->tous('inspecter'),
            'Échangeur (aspect visuel)'                        => $this->tous('inspecter'),
            'Filtre du réservoir à charbon actif (canister)'   => $this->fusion($this->aux([20000, 65000], 'nettoyer'), $this->aux([42500, 87500], 'remplacer')),
            'Filtre de climatisation'                          => $this->alterne('nettoyer', 'remplacer'),
            'Liquide de refroidissement moteur'                => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Liquide de frein'                                 => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Toit ouvrant'                                     => fn (int $i) => $i === 0 ? 'lubrifier' : 'inspecter',
            "Tuyau d'évacuation du toit ouvrant"               => fn (int $i) => $i === 0 ? null : 'inspecter',
            'Batterie'                                         => $this->tous('inspecter'),
            'Fuites'                                           => $this->tous('inspecter'),
            'Éclairage'                                        => $this->tous('inspecter'),
        ]);
    }

    // ── Outils (mêmes règles que 2026_09_28_000005) ─────────────────────

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
     * « Au maximum tous les $intervalleKm km ou $intervalleMois mois » (le premier
     * atteint), avec une première échéance éventuellement différente : l'opération
     * est placée au dernier palier qui ne dépasse aucune des deux limites depuis
     * la fois précédente (ou depuis la mise en circulation).
     */
    private function echeance(array $paliers, ?int $intervalleKm, ?int $intervalleMois, string $action, ?int $premierKm = null, ?int $premierMois = null): callable
    {
        $n = count($paliers);
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

    /** Crée le type de moteur et son barème : $lignes = [désignation => fn(index, km) => action|null] */
    private function creer(string $code, string $marque, string $modele, array $paliers, array $lignes): void
    {
        if (DB::table('types_moteur')->where('code', $code)->exists()) {
            return;
        }
        $id = DB::table('types_moteur')->insertGetId([
            'code' => $code, 'marque' => $marque, 'modele' => $modele, 'libelle' => $modele,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = [];
        foreach ($lignes as $designation => $regle) {
            foreach ($paliers as $i => [$km, $mois]) {
                $action = $regle($i, $km);
                if ($action === null) {
                    continue;
                }
                $rows[] = [
                    'type_moteur_id' => $id, 'designation' => $designation, 'action' => $action,
                    'km_seuil' => $km, 'mois_seuil' => $mois, 'ordre' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($rows, 200) as $lot) {
            DB::table('entretien_taches')->insert($lot);
        }
    }
};
