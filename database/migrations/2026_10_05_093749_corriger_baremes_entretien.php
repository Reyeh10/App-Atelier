<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrections des barèmes d'entretien constructeur (données) :
 *
 *  1. Désignations en anglais traduites en français. Indispensable pour le
 *     H6 HEV : son huile moteur s'appelait « Engine oil », or les paliers
 *     d'entretien sont repérés par la ligne « Huile moteur » — aucun palier
 *     n'était trouvé et le devis / la feuille de travail restaient vides.
 *     Seul le nom de base est traduit ; la remarque après « — » est gardée.
 *  2. « Permutation des pneus » était enregistrée comme pièce à remplacer (elle
 *     arrivait dans le devis comme une pièce à 0 FDJ) : c'est une opération à
 *     faire, elle passe dans les contrôles de la feuille de travail.
 *  3. Doublons créés par la traduction (même moteur, palier, désignation et
 *     action) supprimés.
 */
return new class extends Migration
{
    /** Nom de base anglais (avant « — ») => nom français */
    private const TRADUCTIONS = [
        '9HAT Oil'                                                  => 'Huile de boîte automatique 9HAT',
        'Air conditioner element'                                   => 'Filtre de climatisation',
        'Air filter element'                                        => 'Élément du filtre à air',
        'Appearance of power battery pack'                          => 'Aspect de la batterie de traction',
        'Ball joint and dust cover'                                 => 'Rotule et soufflet de protection',
        'Battary and connection'                                    => 'Batterie et connexions',
        'Battery and connection'                                    => 'Batterie et connexions',
        'Belt of generator and water pump'                          => "Courroie d'alternateur / pompe à eau",
        'Bolts between power battery pack and chassis'              => 'Boulons entre batterie de traction et châssis',
        'Bots between power battary pack and chassis'               => 'Boulons entre batterie de traction et châssis',
        'Power battery and lower body connection bolt torque'       => 'Boulons entre batterie de traction et châssis',
        'Brake fluid'                                               => 'Liquide de frein',
        'Carbon canister filter'                                    => 'Filtre du réservoir à charbon actif (canister)',
        'Coolant (batterie/moteur électrique)'                      => 'Liquide de refroidissement (batterie / moteur électrique)',
        'Coolant (moteur)'                                          => 'Liquide de refroidissement moteur',
        'DHT oil'                                                   => 'Huile de transmission DHT',
        'Differential gear oil'                                     => 'Huile de différentiel',
        'Direct hydraulic transmission oil'                         => 'Huile de transmission DHT',
        'Disc brake'                                                => 'Frein à disque',
        'Door handle'                                               => 'Poignée de porte',
        'Drive motor coolant'                                       => 'Liquide de refroidissement du moteur électrique',
        'Electric drive system assembly'                            => 'Système du groupe motopropulseur électrique',
        'Engine coolant'                                            => 'Liquide de refroidissement moteur',
        'Engine oil'                                                => 'Huile moteur',
        'Engine oil filter'                                         => 'Filtre à huile moteur',
        'Four-wheel alignment'                                      => 'Parallélisme des quatre roues',
        'Front reducer oil'                                         => 'Huile du réducteur principal avant',
        'Fuel filter'                                               => 'Filtre à carburant',
        'Fuel filter (filtre à carburant)'                          => 'Filtre à carburant',
        'Full car four leaks (oil/water/electricity/air)'           => 'Fuites sur le véhicule (huile / eau / électricité / air)',
        'Generator/silicone fan belt'                               => "Courroie d'alternateur / ventilateur",
        'Generator/water pump belt'                                 => "Courroie d'alternateur / pompe à eau",
        'High and low voltage connectors of Power battery pack'     => 'Connecteurs haute/basse tension de la batterie de traction',
        'Power battary pack high/low voltage connector'             => 'Connecteurs haute/basse tension de la batterie de traction',
        'Power battery pack high/low voltage connector'             => 'Connecteurs haute/basse tension de la batterie de traction',
        'High pressure EGR valve and cooler'                        => 'Vanne EGR haute pression et refroidisseur',
        'Low pressure EGR valve and cooler'                         => 'Vanne EGR basse pression et refroidisseur',
        'High temperature overflow tank level'                      => 'Niveau du réservoir de trop-plein haute température',
        'Low temperature overflow tank level'                       => 'Niveau du réservoir de trop-plein basse température',
        'High-voltage wiring harness'                               => 'Système de faisceau haute tension',
        'Important bolts and nuts'                                  => 'Boulons et écrous importants',
        'Intercooler (aspect visuel)'                               => 'Échangeur (aspect visuel)',
        'Interior and connection pipes of intercooler'              => "Intérieur de l'échangeur et tuyauterie",
        'Internal of intercooler and pipeline'                      => "Intérieur de l'échangeur et tuyauterie",
        'Leakage'                                                   => 'Fuites (huile/eau/électricité/air)',
        'Leakage (oil/water/electricity/air)'                       => 'Fuites (huile/eau/électricité/air)',
        'Lighting'                                                  => 'Éclairage',
        'Lights'                                                    => 'Éclairage',
        'Motor battery coolant'                                     => 'Liquide de refroidissement (batterie / moteur électrique)',
        'Parking brake operation'                                   => 'Frein de stationnement',
        'Power battary pack'                                        => 'Boîtier de la batterie de traction',
        'Power battery housing'                                     => 'Boîtier de la batterie de traction',
        'Power battery pack housing'                                => 'Boîtier de la batterie de traction',
        'Power battary pack SOH paremeter'                          => 'Paramètre SOH de la batterie de traction',
        'Power battery pack SOH parameter'                          => 'Paramètre SOH de la batterie de traction',
        'SOH parameters of power battery'                           => 'Paramètre SOH de la batterie de traction',
        'Power battery pack / electric drive system coolant'        => 'Liquide de refroidissement (batterie de traction / groupe électrique)',
        'Power battery pack coolant'                                => 'Liquide de refroidissement de la batterie de traction',
        'Press filter'                                              => 'Filtre de pression',
        'Radiator (aspect visuel)'                                  => 'Radiateur (aspect visuel)',
        'Rear reducer oil'                                          => 'Huile du réducteur principal arrière',
        'Sunroof'                                                   => 'Toit ouvrant',
        'Sunroof drain pipe'                                        => "Tuyau d'évacuation du toit ouvrant",
        'Sunroof drainage pipe'                                     => "Tuyau d'évacuation du toit ouvrant",
        'Tension wheel / transition wheel / belt wheel'             => 'Galet tendeur, galet de renvoi et poulies',
        'Throttle valve (papillon des gaz)'                         => 'Papillon des gaz',
        'Timing belt (courroie de distribution)'                    => 'Courroie de distribution',
        'Torque manager lubricants'                                 => 'Lubrifiant du gestionnaire de couple',
        'Transfer case oil'                                         => 'Huile de boîte de transfert',
        'Transmission oil'                                          => 'Huile de transmission',
        'Transmission oil (AT)'                                     => 'Huile de boîte automatique',
        'Tyre pressure and wear'                                    => 'Pression et usure des pneus',
        'Vehicle body condition'                                    => 'État de la carrosserie',
        'Washer-Drain plug of oil pan'                              => "Joint / bouchon de vidange du carter d'huile",
        'Wheel transposition (permutation pneus)'                   => 'Permutation des pneus',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            // 1. Traduction du nom de base (la remarque après « — » est conservée)
            foreach (DB::table('entretien_taches')->get(['id', 'designation']) as $tache) {
                [$base, $remarque] = array_pad(explode(' — ', $tache->designation, 2), 2, null);
                $base = trim($base);
                if (! isset(self::TRADUCTIONS[$base])) {
                    continue;
                }
                $nouveau = self::TRADUCTIONS[$base] . ($remarque !== null ? ' — ' . $remarque : '');
                DB::table('entretien_taches')->where('id', $tache->id)->update(['designation' => $nouveau]);
            }

            // 2. La permutation des pneus est une opération, pas une pièce
            DB::table('entretien_taches')
                ->where('designation', 'Permutation des pneus')
                ->where('action', 'remplacer')
                ->update(['action' => 'inspecter']);

            // 3. Doublons éventuels créés par la traduction
            $doublons = DB::table('entretien_taches')
                ->select('type_moteur_id', 'designation', 'km_seuil', 'action', DB::raw('MIN(id) as garder'))
                ->groupBy('type_moteur_id', 'designation', 'km_seuil', 'action')
                ->havingRaw('COUNT(*) > 1')
                ->get();
            foreach ($doublons as $d) {
                DB::table('entretien_taches')
                    ->where('type_moteur_id', $d->type_moteur_id)
                    ->where('designation', $d->designation)
                    ->where('km_seuil', $d->km_seuil)
                    ->where('action', $d->action)
                    ->where('id', '!=', $d->garder)
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // Correction de données : pas de retour arrière (les anciennes désignations
        // anglaises bloquaient la résolution des paliers du H6 HEV).
    }
};
