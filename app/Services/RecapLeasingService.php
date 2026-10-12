<?php

namespace App\Services;

use App\Models\BonCommande;
use App\Models\LigneDevis;
use App\Models\OrdreReparation;
use App\Models\ParametreAtelier;
use App\Models\RecapLeasing;
use App\Models\Vehicule;
use App\Models\VehiculeLeasing;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Récap leasing d'une période (au plus un mois, ex. du 25 au 25 du mois suivant) :
 * pour chaque véhicule, le forfait mensuel + les pièces de tous ses OR entrés au
 * garage pendant la période (venu deux fois → ses deux OR s'additionnent ; pas
 * venu → le forfait seul). La main-d'œuvre des OR est couverte par le forfait.
 * Montants HT, sans TVA.
 *
 * Trois récaps séparés :
 * - « stcd »  : la liste du parc STCD (VehiculeLeasing, catégorie stcd) ;
 * - « tiers » : les véhicules de particuliers loués à STCD (vehicules.leasing_tiers),
 *               regroupés par propriétaire ;
 * - « bus » / « transport » : bus leasing et bus du transport public (VehiculeLeasing,
 *               même catégorie), réparés dans un autre garage : forfait du groupe +
 *               pièces de leurs BC directs reçus — un récap par groupe.
 *               Un BC reçu après la fin de la période passe dans le récap suivant.
 *
 * Un OR ou un BC déjà repris dans un autre récap n'est pas compté une seconde fois
 * (les périodes « du 25 au 25 » se touchent le 25).
 */
class RecapLeasingService
{
    public function generer(CarbonInterface $debut, CarbonInterface $fin, string $type = 'stcd'): RecapLeasing
    {
        $parametres = ParametreAtelier::get();
        $estBus     = isset(VehiculeLeasing::GROUPES_BUS[$type]);
        $forfait    = (int) match (true) {
            $estBus             => $parametres->{VehiculeLeasing::GROUPES_BUS[$type]['forfait']},
            $type === 'service' => $parametres->forfait_service,
            default             => $parametres->forfait_leasing,
        };
        $vehicules  = match (true) {
            $type === 'tiers'   => $this->vehiculesTiers(),
            $estBus             => $this->vehiculesParc($type),
            $type === 'service' => $this->vehiculesParc('service')->sortBy(fn ($v) => mb_strtolower($v['proprietaire'] . ' ' . $v['immatriculation']))->values(),
            default             => $this->vehiculesParc('stcd'),
        };

        // Récap déjà généré pour la même période : il est régénéré (remplacé)
        $existant = RecapLeasing::where('type', $type)->whereDate('date_debut', $debut)->whereDate('date_fin', $fin)->first();

        // OR et BC déjà comptés dans un autre récap
        $autres = RecapLeasing::with('lignes')->when($existant, fn ($q) => $q->whereKeyNot($existant->id))->get();
        $dejaComptes = $autres->flatMap(fn (RecapLeasing $r) => $r->lignes->flatMap(fn ($l) => collect($l->ors ?? [])->pluck('id')))
            ->unique()->all();
        $bcDejaComptes = $autres->flatMap(fn (RecapLeasing $r) => $r->lignes->flatMap(fn ($l) => collect($l->bcs ?? [])->pluck('id')))
            ->unique()->all();

        return DB::transaction(function () use ($type, $vehicules, $debut, $fin, $forfait, $existant, $dejaComptes, $bcDejaComptes) {
            $recap = $existant ?? new RecapLeasing();
            $recap->fill([
                'type'         => $type,
                'date_debut'   => $debut->toDateString(),
                'date_fin'     => $fin->toDateString(),
                'forfait'      => $forfait,
                'nb_vehicules' => $vehicules->count(),
                'genere_par'   => Auth::id(),
            ]);
            $recap->save();
            $recap->lignes()->delete();

            $totalPieces = 0.0;
            foreach ($vehicules->values() as $position => $v) {
                $ors = $v['vehicule_ids']
                    ? OrdreReparation::whereIn('vehicule_id', $v['vehicule_ids'])
                        ->where('statut', '!=', 'annule')
                        ->whereNot(fn ($q) => $q->payeParTiers())   // garantie approuvée / assurance : facturé, hors récap
                        ->whereDate('date_entree', '>=', $debut)
                        ->whereDate('date_entree', '<=', $fin)
                        ->whereNotIn('id', $dejaComptes)
                        ->orderBy('date_entree')->get(['id', 'numero', 'date_entree'])
                    : collect();

                // Pièces des devis acceptés de ces OR (prix du magasin, HT)
                $pieces = $ors->isEmpty() ? 0.0 : round((float) LigneDevis::where('type', 'piece')
                    ->whereHas('devis', fn ($d) => $d->where('statut', 'accepte')->whereIn('or_id', $ors->pluck('id')))
                    ->sum('total_ht'));

                // Bus : pièces de ses BC directs reçus (prix du magasin), faits jusqu'à la fin
                // de la période et pas encore comptés — un BC reçu en retard passe au récap suivant
                $bcs = isset(VehiculeLeasing::GROUPES_BUS[$type]) && $v['vehicule_ids']
                    ? BonCommande::with('lignes.ligneDevis')->where('leasing_bus', true)
                        ->whereIn('vehicule_id', $v['vehicule_ids'])
                        ->where('statut', 'recu')
                        ->whereDate('created_at', '<=', $fin)
                        ->whereNotIn('id', $bcDejaComptes)
                        ->orderBy('id')->get()
                    : collect();
                $pieces += round((float) $bcs->sum(fn (BonCommande $bc) => $bc->lignes->sum(fn ($l) => $l->montantAffiche() ?? 0)));

                $recap->lignes()->create([
                    'position'        => $position + 1,
                    'client_id'       => $v['client_id'],
                    'vehicule_id'     => $v['vehicule_ids'][0] ?? null,
                    'immatriculation' => $v['immatriculation'],
                    'proprietaire'    => $v['proprietaire'],
                    'montant_pieces'  => $pieces,
                    'forfait'         => $forfait,
                    'total'           => $pieces + $forfait,
                    'ors'             => $ors->map(fn ($o) => ['id' => $o->id, 'numero' => $o->numero])->values()->all(),
                    'bcs'             => $bcs->map(fn ($b) => ['id' => $b->id, 'numero' => $b->numero])->values()->all(),
                ]);
                $totalPieces += $pieces;
            }

            $nb = $vehicules->count();
            $recap->update([
                'total_pieces'   => $totalPieces,
                'total_forfaits' => $nb * $forfait,
                'total'          => $totalPieces + $nb * $forfait,
            ]);

            return $recap->fresh('lignes');
        });
    }

    /** Parc STCD ou bus : la liste des immatriculations, rapprochées des véhicules de l'atelier */
    private function vehiculesParc(string $categorie): Collection
    {
        $ids = Vehicule::get(['id', 'immatriculation'])
            ->groupBy(fn (Vehicule $v) => VehiculeLeasing::normaliser($v->immatriculation))
            ->map(fn ($groupe) => $groupe->pluck('id')->all());

        return VehiculeLeasing::where('categorie', $categorie)->orderBy('id')->get()->map(fn (VehiculeLeasing $vl) => [
            'immatriculation' => $vl->immatriculation,
            'proprietaire'    => $vl->proprietaire,
            'client_id'       => null,
            'vehicule_ids'    => $ids[$vl->immatriculation_normalisee] ?? [],
        ]);
    }

    /** Leasing tiers : les véhicules cochés, regroupés par propriétaire */
    private function vehiculesTiers(): Collection
    {
        return Vehicule::with('client')->where('leasing_tiers', true)->get()
            ->sortBy(fn (Vehicule $v) => mb_strtolower(($v->client?->nom_complet ?? '') . ' ' . $v->immatriculation))
            ->map(fn (Vehicule $v) => [
                'immatriculation' => $v->immatriculation,
                'proprietaire'    => $v->client?->nom_complet ?? '—',
                'client_id'       => $v->client_id,
                'vehicule_ids'    => [$v->id],
            ])->values();
    }
}
