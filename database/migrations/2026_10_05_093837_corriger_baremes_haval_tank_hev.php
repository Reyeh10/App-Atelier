<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Réécrit les barèmes Tank 300 HEV, Tank 500 HEV, H6 HEV, Dargo, H9 diesel,
 * Jolion HEV et Jolion Pro HEV d'après les tableaux constructeur fournis par
 * l'utilisateur le 28/09/2026 (même méthode que 2026_09_28_000004) :
 *
 *  - les cases du tableau sont reprises telles quelles ;
 *  - les consignes en texte (« au maximum tous les X km ou Y mois ») sont
 *    déroulées sur tout le calendrier, au dernier palier qui ne dépasse aucune
 *    des deux limites depuis la fois précédente ;
 *  - permutation des pneus : R = à faire (main-d'œuvre facturée), I = contrôle.
 *
 * Tank 300 / 500, liquide de frein : « puis tous les 450 000 km ou 24 mois »
 * (sans doute 45 000 km) — les 24 mois arrivent toujours avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->tank('TANK300-HEV', true);
            $this->tank('TANK500-HEV', false);
            $this->h6();
            $this->dargo();
            $this->h9();
            $this->jolion();
            $this->jolionPro();
        });
    }

    public function down(): void
    {
        // Correction de données d'après les tableaux constructeur : pas de retour arrière.
    }

    private const HAVAL_14 = [[5, 12.5, 20, 27.5, 35, 42.5, 50, 57.5, 65, 72.5, 80, 87.5, 95, 102.5], [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84]];

    // ── Tank 300 HEV / Tank 500 HEV (10 000 km / 12 mois) ─────────────

    private function tank(string $code, bool $tank300): void
    {
        $paliers = $this->grille(
            [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150],
            [12, 24, 36, 48, 60, 72, 84, 96, 108, 120, 132, 144, 156, 168, 180]
        );
        $pairs = [20000, 40000, 60000, 80000, 100000, 120000, 140000];

        $bareme = [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Rondelle du bouchon de vidange du carter d'huile" => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            // 12 500 km ou 12 mois, puis 15 000 km ou 12 mois : chaque palier (12 mois)
            'Filtre à carburant'                               => $this->echeance($paliers, 15000, 12, 'remplacer', 12500, 12),
            'Papillon des gaz'                                 => $this->aux($pairs, 'nettoyer'),
            "Intérieur et tuyauterie de l'échangeur (intercooler)" => $this->aux($pairs, 'inspecter'),
            "Bougies d'allumage"                               => $this->aux([30000, 60000, 90000, 120000, 150000], 'remplacer'),
            'Huile de boîte de transfert'                      => $this->echeance($paliers, 50000, null, 'remplacer'),
            'Huile de différentiel'                            => $this->echeance($paliers, 100000, 36, 'remplacer', 50000, 36),
            'Huile de boîte automatique 9HAT'                  => $this->echeance($paliers, 60000, 36, 'remplacer'),
            'Filtre de pression'                               => $this->echeance($paliers, 60000, 36, 'remplacer'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Permutation des pneus'                            => $this->tous('remplacer'),
            'Parallélisme des quatre roues'                    => $this->tous('inspecter'),
            'Rotule et soufflet de protection'                 => $this->aux($pairs, 'inspecter'),
            'Élément de filtre à air'                          => $this->alterne('inspecter', 'remplacer'),
            'Filtre de climatisation'                          => $this->aux($pairs, 'remplacer'),
            'Radiateur (aspect extérieur)'                     => $this->tous('inspecter'),
            'Échangeur - intercooler (aspect extérieur)'       => $this->tous('inspecter'),
            'Filtre du réservoir à charbon actif'              => $this->aux($pairs, 'nettoyer'),
            'Liquide de refroidissement moteur'                => $this->echeance($paliers, 60000, 48, 'remplacer'),
            'Liquide de frein'                                 => $this->echeance($paliers, 45000, 24, 'remplacer', 45000, 36),
            'Boîtier de la batterie de traction'               => $this->tous('inspecter'),
            'Connecteurs haute/basse tension de la batterie de traction' => $this->tous('inspecter'),
            'Paramètre SOH de la batterie de traction'         => $this->tous('inspecter'),
            'Boulons entre batterie de traction et châssis'    => $this->tous('inspecter'),
            'Batterie et connexions'                           => $this->tous('inspecter'),
            'Fuites (huile / eau / électricité / air)'         => $this->tous('inspecter'),
            "Tuyau d'évacuation du toit ouvrant"               => $this->tous('inspecter'),
            'Éclairage'                                        => $this->tous('inspecter'),
            'État de la carrosserie'                           => $this->echeance($paliers, null, 24, 'inspecter', null, 48),
        ];

        if ($tank300) {
            $bareme["Courroie d'alternateur / pompe à eau"]  = $this->echeance($paliers, 100000, null, 'remplacer');
            $bareme['Liquide de refroidissement de la batterie de traction'] = $this->aux([40000, 80000, 120000], 'remplacer');
            $bareme['Frein de stationnement']                = $this->tous('inspecter');
        } else {
            $bareme['Liquide de refroidissement (batterie de traction / groupe électrique)'] = $this->echeance($paliers, 80000, 48, 'remplacer');
        }

        $this->reecrire($code, $paliers, $bareme);
    }

    // ── H6 HEV (B01-4B15D) ─────────────────────────────────────────────

    private function h6(): void
    {
        $paliers = $this->grille(
            [5, 12.5, 20, 27.5, 35, 42.5, 50, 57.5, 65, 72.5, 80, 87.5, 95, 103, 110, 118, 125, 133, 140, 148, 155],
            [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84, 90, 96, 102, 108, 114, 120, 126]
        );
        $tous12Mois = [12500, 27500, 42500, 57500, 72500, 87500, 103000, 118000, 133000, 148000];
        // Ligne du tableau telle quelle (C C à 80 et 87,5 000 km, puis l'alternance reprend)
        $clim = 'CRCRCRCRCRCCRCRCRCRCR';

        $this->reecrire('H6-HEV', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Joint / bouchon de vidange du carter d'huile"     => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->aux($tous12Mois, 'nettoyer'),
            "Intérieur de l'échangeur et tuyauterie"           => $this->aux($tous12Mois, 'nettoyer'),
            "Bougies d'allumage"                               => $this->aux([42500, 87500, 133000], 'remplacer'),
            "Courroie d'alternateur / pompe à eau"             => $this->echeance($paliers, 100000, null, 'remplacer'),
            'Huile de transmission DHT'                        => $this->echeance($paliers, 60000, 36, 'remplacer'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Permutation des pneus'                            => $this->tous('remplacer'),
            'Parallélisme des quatre roues'                    => $this->tous('inspecter'),
            'Rotule et soufflet de protection'                 => $this->tous('inspecter'),
            'Élément du filtre à air'                          => $this->tous('remplacer'),
            'Radiateur (aspect visuel)'                        => $this->tous('inspecter'),
            'Échangeur (aspect visuel)'                        => $this->tous('inspecter'),
            'Filtre de climatisation'                          => fn (int $i) => $clim[$i] === 'R' ? 'remplacer' : 'nettoyer',
            'Liquide de refroidissement moteur'                => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Liquide de frein'                                 => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Liquide de refroidissement (batterie / moteur électrique)' => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Batterie'                                         => $this->tous('inspecter'),
            'Toit ouvrant'                                     => fn (int $i) => in_array($i, [0, 11], true) ? 'lubrifier' : 'inspecter',
            'Boulons entre batterie de traction et châssis'    => $this->tous('inspecter'),
            'Boîtier de la batterie de traction'               => $this->tous('inspecter'),
            'Connecteurs haute/basse tension de la batterie de traction' => $this->tous('inspecter'),
            'Paramètre SOH de la batterie de traction'         => $this->tous('inspecter'),
            'Fuites (huile / eau / électricité / air)'         => $this->tous('inspecter'),
            'Éclairage'                                        => $this->tous('inspecter'),
            'État de la carrosserie'                           => $this->tous('inspecter'),
        ]);
    }

    // ── Dargo ───────────────────────────────────────────────────────────

    private function dargo(): void
    {
        $paliers = $this->grille(...self::HAVAL_14);
        $tous24Mois = [12500, 27500, 42500, 57500, 72500, 87500, 102500];
        $tous18Mois = [20000, 42500, 65000, 87500];

        $this->reecrire('DARGO', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Joint / bouchon de vidange du carter d'huile"     => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->aux($tous18Mois, 'nettoyer'),
            "Intérieur de l'échangeur et tuyauterie"           => $this->aux($tous18Mois, 'nettoyer'),
            "Bougies d'allumage"                               => $this->aux([42500, 87500], 'remplacer'),
            "Courroie d'alternateur / pompe à eau"             => $this->echeance($paliers, 100000, null, 'remplacer'),
            'Huile de boîte automatique'                       => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Cartouche et boîtier du filtre de pression'       => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Huile de boîte de transfert'                      => $this->echeance($paliers, 100000, 36, 'remplacer', 50000, 36),
            'Huile du réducteur principal arrière'             => $this->echeance($paliers, 100000, 36, 'remplacer', 50000, 36),
            'Lubrifiant du gestionnaire de couple'             => $this->echeance($paliers, 60000, null, 'remplacer'),
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
            'Toit ouvrant'                                     => fn (int $i) => $i === 0 ? 'lubrifier' : 'inspecter',
            "Tuyau d'évacuation du toit ouvrant"               => $this->tous('inspecter'),
            'Filtre de climatisation'                          => $this->alterne('nettoyer', 'remplacer'),
            'Liquide de refroidissement moteur'                => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Liquide de frein'                                 => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Batterie'                                         => $this->tous('inspecter'),
            'Fuites'                                           => $this->tous('inspecter'),
            'Éclairage'                                        => $this->tous('inspecter'),
        ]);
    }

    // ── H9 diesel (5 000 km / 6 mois) ──────────────────────────────────

    private function h9(): void
    {
        $paliers = $this->grille(
            [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95],
            [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84, 90, 96, 102, 108, 114]
        );
        $tous18Mois = [15000, 30000, 45000, 60000, 75000, 90000];
        $tous12Mois = [10000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000];

        $this->reecrire('H9-DIESEL', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Rondelle du bouchon de vidange du carter d'huile" => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->echeance($paliers, 20000, null, 'nettoyer'),
            'Galet tendeur, galet de renvoi et poulies'        => $this->aux($tous18Mois, 'inspecter'),
            'Vanne EGR haute pression et refroidisseur'        => $this->aux($tous12Mois, 'nettoyer'),
            'Vanne EGR basse pression et refroidisseur'        => $this->aux($tous12Mois, 'nettoyer'),
            'Courroie de distribution'                         => $this->fusion(
                $this->echeance($paliers, 20000, null, 'inspecter'),
                $this->echeance($paliers, 80000, null, 'remplacer'),
            ),
            "Courroie d'alternateur / ventilateur"             => $this->echeance($paliers, 20000, 24, 'inspecter'),
            'Huile de transmission'                            => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Filtre de pression'                               => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Filtre à carburant'                               => $this->aux([5000, 20000, 35000, 50000, 65000, 80000, 95000], 'remplacer'),
            'Huile de boîte de transfert'                      => $this->echeance($paliers, 100000, null, 'remplacer'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Permutation des pneus'                            => $this->sauf($tous18Mois, 'remplacer', 'inspecter'),
            'Rotule et soufflet de protection'                 => $this->tous('inspecter'),
            'Élément de filtre à air'                          => $this->tous('remplacer'),
            'Filtre de climatisation'                          => $this->tous('nettoyer'),
            'Huile du réducteur principal avant'               => $this->echeance($paliers, 100000, 48, 'remplacer', 50000, 48),
            'Huile du réducteur principal arrière'             => $this->echeance($paliers, 100000, 48, 'remplacer', 50000, 48),
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
            'État de la carrosserie'                           => $this->echeance($paliers, null, 24, 'inspecter', null, 48),
        ]);
    }

    // ── Jolion HEV ──────────────────────────────────────────────────────

    private function jolion(): void
    {
        $paliers = $this->grille(...self::HAVAL_14);
        $tous24Mois = [12500, 27500, 42500, 57500, 72500, 87500, 102500];
        $tous18Mois = [20000, 42500, 65000, 87500];

        $this->reecrire('JOLION-HEV', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Joint / bouchon de vidange du carter d'huile"     => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->aux($tous18Mois, 'nettoyer'),
            "Bougies d'allumage"                               => $this->aux($tous18Mois, 'remplacer'),
            'Huile de transmission DHT'                        => $this->echeance($paliers, 60000, 36, 'remplacer'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            'Permutation des pneus'                            => $this->aux($tous24Mois, 'remplacer'),
            'Rotule et soufflet de protection'                 => $this->aux($tous24Mois, 'inspecter'),
            'Élément du filtre à air'                          => $this->alterne('nettoyer', 'remplacer'),
            'Radiateur (aspect visuel)'                        => $this->tous('inspecter'),
            'Filtre du réservoir à charbon actif (canister)'   => $this->fusion($this->aux([20000, 65000], 'nettoyer'), $this->aux([42500, 87500], 'remplacer')),
            'Filtre de climatisation'                          => $this->alterne('nettoyer', 'remplacer'),
            'Liquide de refroidissement moteur'                => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Liquide de frein'                                 => $this->echeance($paliers, 40000, 24, 'remplacer'),
            'Toit ouvrant'                                     => fn (int $i) => $i === 0 ? 'lubrifier' : 'inspecter',
            "Tuyau d'évacuation du toit ouvrant"               => fn (int $i) => $i === 0 ? null : 'inspecter',
            'Batterie'                                         => $this->tous('inspecter'),
            'Fuites'                                           => $this->tous('inspecter'),
            'Éclairage'                                        => $this->tous('inspecter'),
            'Boulons entre batterie de traction et châssis'    => $this->tous('inspecter'),
            'Boîtier de la batterie de traction'               => $this->tous('inspecter'),
            'Connecteurs haute/basse tension de la batterie de traction' => $this->tous('inspecter'),
            'Paramètre SOH de la batterie de traction'         => $this->tous('inspecter'),
            'Liquide de refroidissement du moteur électrique'  => $this->echeance($paliers, 80000, 48, 'remplacer'),
        ]);
    }

    // ── Jolion Pro HEV ──────────────────────────────────────────────────

    private function jolionPro(): void
    {
        $paliers = $this->grille(
            [5, 12.5, 20, 27.5, 35, 42.5, 50, 57.5, 65, 72.5, 80, 87.5],
            [6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72]
        );
        $tous24Mois = [12500, 27500, 42500, 57500, 72500, 87500];

        $this->reecrire('JOLION-PRO-HEV', $paliers, [
            'Huile moteur'                                     => $this->tous('remplacer'),
            "Rondelle du bouchon de vidange du carter d'huile" => $this->tous('remplacer'),
            'Filtre à huile moteur'                            => $this->tous('remplacer'),
            'Papillon des gaz'                                 => $this->echeance($paliers, 20000, null, 'nettoyer'),
            "Bougies d'allumage"                               => $this->echeance($paliers, 20000, null, 'remplacer'),
            'Huile de transmission'                            => $this->echeance($paliers, 80000, 48, 'remplacer'),
            'Boulons et écrous importants'                     => $this->tous('inspecter'),
            'Frein à disque'                                   => $this->tous('inspecter'),
            'Pression et usure des pneus'                      => $this->tous('inspecter'),
            // I R I R… : permutation faite tous les 12 mois, contrôlée sinon
            'Permutation des pneus'                            => $this->sauf($tous24Mois, 'remplacer', 'inspecter'),
            'Rotule et soufflet de protection'                 => $this->tous('inspecter'),
            'Élément de filtre à air'                          => $this->fusion(
                $this->echeance($paliers, 10000, 12, 'nettoyer'),
                $this->echeance($paliers, 20000, 24, 'remplacer'),
            ),
            'Filtre de climatisation'                          => $this->tous('nettoyer'),
            'Filtre du réservoir à charbon actif'              => $this->echeance($paliers, 40000, null, 'nettoyer'),
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
            'Toit ouvrant panoramique'                         => fn (int $i) => $i === 0 ? 'lubrifier' : 'inspecter',
            "Tuyau d'évacuation du toit ouvrant"               => $this->tous('inspecter'),
            'Fuites (huile / eau / électricité / air)'         => $this->tous('inspecter'),
            'Éclairage'                                        => $this->tous('inspecter'),
            'Aspect de la batterie de traction'                => $this->aux($tous24Mois, 'inspecter'),
            'Système du groupe motopropulseur électrique'      => $this->tous('inspecter'),
            'Système de faisceau haute tension'                => $this->tous('inspecter'),
            'Poignée de porte'                                 => $this->tous('inspecter'),
            'État de la carrosserie'                           => $this->echeance($paliers, null, 24, 'inspecter', null, 48),
        ]);
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
