<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Véhicule du parc leasing — liste gérée comme les techniciens, jamais facturé :
 * chaque mois, le récap reprend son forfait + ses pièces (cf. RecapLeasing).
 * Catégories :
 * - « stcd »      : parc STCD (véhicules légers), pièces de leurs OR ;
 * - « bus »       : bus leasing, réparés dans un autre garage, pièces de leurs BC directs ;
 * - « transport » : bus du transport public — même fonctionnement que « bus »,
 *                   liste et récap à part ;
 * - « service »   : véhicules de service, affectés à un service ou à un directeur /
 *                   responsable (l'affectation est dans « proprietaire ») — même logique
 *                   que le parc STCD (pièces de leurs OR), récap regroupé par affectation.
 */
class VehiculeLeasing extends Model
{
    protected $table = 'vehicules_leasing';

    protected $fillable = ['categorie', 'vehicule_id', 'immatriculation', 'immatriculation_normalisee', 'proprietaire', 'ajoute_par'];

    /** Groupes de bus (BC directs, pas d'OR, récap mensuel) : libellés, routes et réglage du forfait */
    public const GROUPES_BUS = [
        'bus' => [
            'libelle' => 'Bus leasing',
            'liste'   => 'bus en leasing',
            'route'   => 'leasing-bus',
            'forfait' => 'forfait_bus_leasing',
            'titre'   => 'Leasing bus',
        ],
        'transport' => [
            'libelle' => 'Bus transport public',
            'liste'   => 'bus du transport public',
            'route'   => 'bus-transport',
            'forfait' => 'forfait_bus_transport',
            'titre'   => 'Transport public — bus',
        ],
    ];

    /** Listes gérées comme le parc STCD (pièces des OR) : libellés et noms de routes */
    public const PARCS_OR = [
        'stcd' => [
            'libelle'     => 'Parc leasing',
            'sous_titre'  => 'Véhicules de location : pas de facture au passage, forfait + pièces dans le récap de chaque mois',
            'route'       => 'leasing',
            'champ'       => 'Propriétaire (facturation du véhicule)',
            'defaut'      => 'STCD',
            'or'          => 'parc leasing STCD',
        ],
        'service' => [
            'libelle'     => 'Véhicules de service',
            'sous_titre'  => 'Véhicules affectés à un service ou à un directeur / responsable : pas de facture au passage, pièces (+ forfait) dans le récap de chaque mois',
            'route'       => 'vehicules-service',
            'champ'       => 'Affecté à (service, directeur ou responsable)',
            'defaut'      => '',
            'or'          => 'véhicule de service',
        ],
    ];

    /** Catégorie de la liste où se trouve le véhicule (null s'il n'est dans aucune) */
    public static function categorieDe(?string $immatriculation): ?string
    {
        $cle = self::normaliser($immatriculation);

        return $cle === '' ? null : self::where('immatriculation_normalisee', $cle)->value('categorie');
    }

    public function scopeStcd($query)
    {
        return $query->where('categorie', 'stcd');
    }

    public function scopeBus($query)
    {
        return $query->where('categorie', 'bus');
    }

    /** Le bus (immatriculation) est-il dans une liste de bus (leasing ou transport public) ? — jamais facturé */
    public static function estBus(?string $immatriculation): bool
    {
        $cle = self::normaliser($immatriculation);

        return $cle !== '' && self::whereIn('categorie', array_keys(self::GROUPES_BUS))->where('immatriculation_normalisee', $cle)->exists();
    }

    /** Fiche du bus dans l'atelier (créée à l'import de la liste des bus) */
    public function fiche(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id');
    }

    /** « 45 D 81 », « 45-D-81 » → « 45D81 » (même règle que l'import flotte) */
    public static function normaliser(?string $immatriculation): string
    {
        return strtoupper(preg_replace('/[\s\-\.]+/', '', (string) $immatriculation));
    }

    /** Le véhicule (immatriculation) fait-il partie du parc leasing ? */
    public static function contient(?string $immatriculation): bool
    {
        $cle = self::normaliser($immatriculation);

        return $cle !== '' && self::where('immatriculation_normalisee', $cle)->exists();
    }

    /** Fiche véhicule correspondante dans l'atelier (null si le véhicule n'y est pas encore passé) */
    public function vehicule(): ?Vehicule
    {
        return Vehicule::get(['id', 'immatriculation', 'marque', 'modele'])
            ->first(fn (Vehicule $v) => self::normaliser($v->immatriculation) === $this->immatriculation_normalisee);
    }

    public function ajoutePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajoute_par');
    }
}
