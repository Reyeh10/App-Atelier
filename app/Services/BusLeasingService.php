<?php

namespace App\Services;

use App\Models\BonCommande;
use App\Models\Client;
use App\Models\LigneBonCommande;
use App\Models\Vehicule;
use App\Models\VehiculeLeasing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Bus leasing : bus loués, réparés dans un autre garage (pas d'OR à l'atelier).
 *
 * - Liste des bus : import Excel (Immatriculation, Marque, Modèle, N° de châssis) avec
 *   aperçu — le bus est créé dans les véhicules du client et ajouté au parc bus ;
 * - Bons de commande : un par bus (une ou plusieurs pièces), saisi à l'écran ou par
 *   import Excel (Immatriculation, Désignation, Référence, Quantité) avec aperçu.
 *   Le BC part au magasin comme les autres ; il n'est jamais facturé : ses pièces
 *   entrent dans le récap mensuel « bus » (forfait bus + pièces).
 */
class BusLeasingService
{
    public const COLONNES_PARC = ['Immatriculation', 'Marque', 'Modèle', 'N° de châssis (VIN)', 'Date de mise en circulation'];
    public const COLONNES_BC   = ['Immatriculation', 'Désignation', 'Référence', 'Quantité'];

    /**
     * Lit la première feuille (Excel) ou le CSV et retourne toutes ses lignes
     * non vides, en-tête incluse — ou null si le fichier est vide/illisible.
     */
    public function lire(string $chemin, string $extension): ?array
    {
        if (in_array($extension, ['csv', 'txt'], true)) {
            $handle = fopen($chemin, 'r');
            $premiere = fgets($handle) ?: '';
            rewind($handle);
            $delimiteur = substr_count($premiere, ';') > substr_count($premiere, ',') ? ';' : ',';
            $lignes = [];
            while (($ligne = fgetcsv($handle, 0, $delimiteur)) !== false) {
                $lignes[] = $ligne;
            }
            fclose($handle);
        } else {
            try {
                // Valeurs brutes (non formatées)
                $lignes = \PhpOffice\PhpSpreadsheet\IOFactory::load($chemin)->getSheet(0)->toArray(null, true, false, false);
            } catch (\Throwable $e) {
                return null;
            }
        }

        $lignes = array_values(array_filter($lignes, fn ($l) => count(array_filter((array) $l, fn ($v) => trim((string) $v) !== '')) > 0));

        return empty($lignes) ? null : $lignes;
    }

    // ── Liste des bus ──────────────────────────────────────────────────

    /**
     * Contrôle le fichier d'une liste de véhicules (bus, parc leasing STCD, véhicules
     * de service). Rien n'est enregistré. Colonnes : Immatriculation (obligatoire),
     * Marque, Modèle, N° de châssis, Date de mise en circulation, et « Affecté à »
     * pour les véhicules de service ($proprietaire : valeur par défaut de l'écran).
     *
     * @return array{erreurs: string[], avertissements: string[], bus: array}
     */
    public function analyserParc(array $lignes, Client $client, string $categorie = 'bus', ?string $proprietaire = null): array
    {
        $erreurs = []; $avertissements = []; $bus = [];
        $estBus     = isset(VehiculeLeasing::GROUPES_BUS[$categorie]);
        $estService = $categorie === 'service';

        $colonnes = $this->colonnes(array_shift($lignes));
        if (! isset($colonnes['immatriculation'])) {
            return ['erreurs' => ["Colonne « Immatriculation » introuvable dans la première ligne du fichier. Utilisez le modèle Excel."], 'avertissements' => [], 'bus' => []];
        }

        $vehicules = Vehicule::with('client')->get()->keyBy(fn ($v) => VehiculeLeasing::normaliser($v->immatriculation));
        $parc      = VehiculeLeasing::all()->keyBy('immatriculation_normalisee');
        $vus       = [];

        foreach ($lignes as $i => $ligne) {
            $num = $i + 2;
            $val = fn (string $cle) => isset($colonnes[$cle]) ? trim((string) ($ligne[$colonnes[$cle]] ?? '')) : '';

            $immat = strtoupper(preg_replace('/\s+/', ' ', $val('immatriculation')));
            if ($immat === '' && $val('marque') === '' && $val('modele') === '') {
                continue;
            }
            if ($immat === '') {
                $erreurs[] = "Ligne {$num} : immatriculation manquante.";
                continue;
            }
            $cle = VehiculeLeasing::normaliser($immat);
            if (isset($vus[$cle])) {
                $erreurs[] = "Ligne {$num} : le véhicule {$immat} apparaît deux fois dans le fichier (ligne {$vus[$cle]}).";
                continue;
            }
            $vus[$cle] = $num;

            $dansParc = $parc->get($cle);
            if ($dansParc && $dansParc->categorie !== $categorie) {
                $autre = VehiculeLeasing::GROUPES_BUS[$dansParc->categorie]['libelle'] ?? VehiculeLeasing::PARCS_OR[$dansParc->categorie]['libelle'] ?? $dansParc->categorie;
                $erreurs[] = "Ligne {$num} : {$immat} est déjà dans la liste « {$autre} ».";
                continue;
            }

            $existant = $vehicules->get($cle);
            if ($existant && (int) $existant->client_id !== (int) $client->id) {
                $erreurs[] = "Ligne {$num} : le véhicule {$immat} existe déjà au nom d'un autre client ({$existant->client?->nom_complet}).";
                continue;
            }

            // Date de mise en circulation (facultative) : JJ/MM/AAAA ou date Excel
            $dateSaisie = $val('date');
            $date = null;
            if ($dateSaisie !== '') {
                $date = $this->date($ligne[$colonnes['date']] ?? null);
                if ($date === null) {
                    $erreurs[] = "Ligne {$num} ({$immat}) : date de mise en circulation « {$dateSaisie} » non reconnue (format attendu : JJ/MM/AAAA).";
                    continue;
                }
                if ($date > now()->toDateString()) {
                    $erreurs[] = "Ligne {$num} ({$immat}) : la date de mise en circulation est dans le futur.";
                    continue;
                }
            }

            // Véhicule de service : à qui il est affecté (colonne, sinon la valeur de l'écran)
            $affectation = $estService ? ($val('affectation') ?: trim((string) $proprietaire)) : null;
            if ($estService && $affectation === '') {
                $erreurs[] = "Ligne {$num} ({$immat}) : « Affecté à » manquant (service, directeur ou responsable).";
                continue;
            }

            // Nouveau véhicule (pas un bus) : la marque est obligatoire pour sa fiche
            $marque = $val('marque') ?: ($existant?->marque ?? ($estBus ? 'Bus' : ''));
            if (! $existant && $marque === '') {
                $erreurs[] = "Ligne {$num} ({$immat}) : marque manquante — obligatoire pour créer le véhicule.";
                continue;
            }

            // Déjà dans la liste : on complète seulement la fiche véhicule si elle manque
            $action = match (true) {
                $dansParc && $dansParc->vehicule_id === null && ! $existant => 'completer',
                (bool) $dansParc => 'deja',
                (bool) $existant => 'ajouter',
                default          => 'creer',
            };
            if ($action === 'deja') {
                $avertissements[] = "{$immat} est déjà dans la liste : ignoré.";
            }

            $bus[] = [
                'immatriculation' => $existant?->immatriculation ?? $immat,
                'marque'          => $marque,
                'modele'          => $val('modele') ?: ($existant?->modele ?? ''),
                'vin'             => $val('vin') ?: ($existant?->vin ?? null),
                'date'            => $date,
                'affectation'     => $affectation,
                'vehicule_id'     => $existant?->id,
                'parc_id'         => $dansParc?->id,
                'action'          => $action,
            ];
        }

        if (! $erreurs && ! $bus) {
            $erreurs[] = 'Aucun véhicule dans le fichier.';
        }

        return ['erreurs' => $erreurs, 'avertissements' => $avertissements, 'bus' => $bus];
    }

    /**
     * Crée les véhicules manquants au nom du client et les ajoute à la liste.
     * $proprietaire : libellé de la liste (parc STCD) ; bus : le nom du client ;
     * véhicules de service : l'affectation de chaque ligne.
     */
    public function importerParc(array $bus, Client $client, string $categorie = 'bus', ?string $proprietaire = null): array
    {
        $crees = 0; $ajoutes = 0;
        $estBus = isset(VehiculeLeasing::GROUPES_BUS[$categorie]);

        DB::transaction(function () use ($bus, $client, $categorie, $proprietaire, $estBus, &$crees, &$ajoutes) {
            foreach ($bus as $b) {
                if ($b['action'] === 'deja') {
                    continue;
                }
                $vehicule = $b['vehicule_id'] ? Vehicule::find($b['vehicule_id']) : null;
                if (! $vehicule) {
                    $vehicule = Vehicule::create([
                        'client_id'             => $client->id,
                        'immatriculation'       => $b['immatriculation'],
                        'marque'                => $b['marque'],
                        'modele'                => $b['modele'],
                        'vin'                   => $b['vin'],
                        'date_mise_circulation' => $b['date'] ?? null,
                        'categorie'             => $estBus ? 'bus' : null,
                    ]);
                    $crees++;
                } elseif (! empty($b['date']) && ! $vehicule->date_mise_circulation) {
                    $vehicule->update(['date_mise_circulation' => $b['date']]);
                }

                if ($b['action'] === 'completer') {
                    VehiculeLeasing::whereKey($b['parc_id'])->update(['vehicule_id' => $vehicule->id]);
                    continue;
                }
                VehiculeLeasing::create([
                    'categorie'                  => $categorie,
                    'vehicule_id'                => $vehicule->id,
                    'immatriculation'            => $vehicule->immatriculation,
                    'immatriculation_normalisee' => VehiculeLeasing::normaliser($vehicule->immatriculation),
                    'proprietaire'               => $b['affectation'] ?? ($estBus ? $client->nom_complet : (trim((string) $proprietaire) ?: $client->nom_complet)),
                    'ajoute_par'                 => Auth::id(),
                ]);
                $ajoutes++;
            }
        });

        return [$crees, $ajoutes];
    }

    // ── Bons de commande ───────────────────────────────────────────────

    /**
     * Contrôle le fichier des pièces et regroupe par bus (un BC par bus). Rien n'est enregistré.
     *
     * @return array{erreurs: string[], groupes: array}
     */
    public function analyserBc(array $lignes, string $categorie = 'bus'): array
    {
        $erreurs = []; $groupes = [];

        $colonnes = $this->colonnes(array_shift($lignes));
        foreach (['immatriculation' => 'Immatriculation', 'designation' => 'Désignation', 'quantite' => 'Quantité'] as $cle => $libelle) {
            if (! isset($colonnes[$cle])) {
                $erreurs[] = "Colonne « {$libelle} » introuvable dans la première ligne du fichier. Utilisez le modèle Excel.";
            }
        }
        if ($erreurs) {
            return ['erreurs' => $erreurs, 'groupes' => []];
        }

        $parc = VehiculeLeasing::where('categorie', $categorie)->with('fiche')->get()->keyBy('immatriculation_normalisee');
        $liste = VehiculeLeasing::GROUPES_BUS[$categorie]['liste'] ?? 'bus';

        foreach ($lignes as $i => $ligne) {
            $num = $i + 2;
            $val = fn (string $cle) => isset($colonnes[$cle]) ? trim((string) ($ligne[$colonnes[$cle]] ?? '')) : '';

            $immat = $val('immatriculation'); $designation = $val('designation');
            if ($immat === '' && $designation === '') {
                continue;
            }
            if ($immat === '') {
                $erreurs[] = "Ligne {$num} : immatriculation manquante.";
                continue;
            }
            $bus = $parc->get(VehiculeLeasing::normaliser($immat));
            if (! $bus) {
                $erreurs[] = "Ligne {$num} : le bus {$immat} n'est pas dans la liste des {$liste}.";
                continue;
            }
            if (! $bus->fiche) {
                $erreurs[] = "Ligne {$num} : le bus {$immat} n'a pas de fiche véhicule — réimportez-le dans la liste des bus.";
                continue;
            }
            if ($designation === '') {
                $erreurs[] = "Ligne {$num} ({$immat}) : désignation de la pièce manquante.";
                continue;
            }
            $quantite = $this->nombre($val('quantite'));
            if ($quantite === null || $quantite <= 0) {
                $erreurs[] = "Ligne {$num} ({$immat}) : quantité invalide pour « {$designation} ».";
                continue;
            }

            $groupes[$bus->id] ??= ['bus_id' => $bus->id, 'immatriculation' => $bus->immatriculation, 'lignes' => []];
            $groupes[$bus->id]['lignes'][] = [
                'designation' => $designation,
                'reference'   => $val('reference') ?: null,
                'quantite'    => $quantite,
            ];
        }

        if (! $erreurs && ! $groupes) {
            $erreurs[] = 'Aucune pièce dans le fichier.';
        }

        return ['erreurs' => $erreurs, 'groupes' => array_values($groupes)];
    }

    /** Crée le BC d'un bus (une ou plusieurs pièces) et l'envoie au magasin */
    public function creerBc(VehiculeLeasing $bus, array $lignes): BonCommande
    {
        $bus->loadMissing('fiche');

        $bc = DB::transaction(function () use ($bus, $lignes) {
            $bc = BonCommande::create([
                'numero'      => BonCommande::genererNumero(),
                'devis_id'    => null,
                'or_id'       => null,
                'dossier_id'  => null,
                'client_id'   => $bus->fiche->client_id,
                'vehicule_id' => $bus->fiche->id,
                'leasing_bus' => true,
                'statut'      => 'en_attente',
                'notes'       => (VehiculeLeasing::GROUPES_BUS[$bus->categorie]['libelle'] ?? 'Bus') . " {$bus->immatriculation}",
            ]);
            foreach ($lignes as $ligne) {
                LigneBonCommande::create([
                    'bon_commande_id' => $bc->id,
                    'designation'     => $ligne['designation'],
                    'reference'       => ($ligne['reference'] ?? null) ?: null,
                    'quantite'        => $ligne['quantite'],
                ]);
            }

            return $bc;
        });

        app(FournisseurApiService::class)->envoyerBonCommande($bc);

        return $bc;
    }

    // ── Modèles Excel ──────────────────────────────────────────────────

    public function modeleParc(string $categorie = 'bus'): Spreadsheet
    {
        if ($categorie === 'service') {
            return $this->modele('Véhicules', [...self::COLONNES_PARC, 'Affecté à'], [
                ['45 D 81', 'Toyota', 'Land Cruiser', '', '15/03/2022', 'Directeur général'],
                ['46 D 12', 'Toyota', 'Hilux', '', '', 'Service technique'],
            ], [
                'Véhicules de service — une ligne par véhicule.',
                'Obligatoire : Immatriculation, et Marque pour un véhicule qui n\'existe pas encore.',
                '« Affecté à » : service, directeur ou responsable (vide = la valeur saisie à l\'écran).',
                'Date de mise en circulation : JJ/MM/AAAA, facultative.',
                'Supprimez les lignes d\'exemple avant d\'importer.',
            ]);
        }
        if ($categorie === 'stcd') {
            return $this->modele('Véhicules', self::COLONNES_PARC, [
                ['45 D 81', 'Toyota', 'Hilux', 'MR0FR22G000000001', '15/03/2022'],
                ['46 D 12', 'Suzuki', 'Swift', '', ''],
            ], [
                'Parc leasing — une ligne par véhicule.',
                'Obligatoire : Immatriculation, et Marque pour un véhicule qui n\'existe pas encore.',
                'Date de mise en circulation : JJ/MM/AAAA, facultative.',
                'Supprimez les lignes d\'exemple avant d\'importer.',
            ]);
        }

        return $this->modele('Bus', self::COLONNES_PARC, [
            ['123 A 45', 'King Long', 'XMQ6127', 'LKLR1KSF0EB000001', '01/06/2020'],
            ['456 B 78', 'Higer', 'KLQ6129', '', ''],
        ], [
            'Liste des bus — une ligne par bus.',
            'Obligatoire : Immatriculation. Marque, Modèle, N° de châssis et Date de mise en circulation (JJ/MM/AAAA) sont facultatifs.',
            'Un bus qui n\'existe pas encore dans les véhicules est créé au nom du client choisi à l\'écran.',
            'Supprimez les lignes d\'exemple avant d\'importer.',
        ]);
    }

    public function modeleBc(): Spreadsheet
    {
        return $this->modele('Pièces', self::COLONNES_BC, [
            ['123 A 45', 'Filtre à huile', '1109010', 1],
            ['123 A 45', 'Plaquettes de frein AV', '', 2],
            ['456 B 78', 'Courroie', '', 1],
        ], [
            'Pièces pour les bus en leasing — une ligne par pièce.',
            'Un bus avec 3 pièces = 3 lignes, avec son immatriculation répétée : un bon de commande est créé par bus.',
            'Obligatoire : Immatriculation (bus de la liste des bus en leasing), Désignation, Quantité. Référence : facultative.',
            'Supprimez les lignes d\'exemple avant d\'importer.',
        ]);
    }

    private function modele(string $titre, array $colonnes, array $exemples, array $aide): Spreadsheet
    {
        $classeur = new Spreadsheet();
        $feuille  = $classeur->getActiveSheet();
        $feuille->setTitle($titre);
        $feuille->fromArray($colonnes, null, 'A1');
        $feuille->fromArray($exemples, null, 'A2');

        $derniere = chr(ord('A') + count($colonnes) - 1);
        $entete = $feuille->getStyle("A1:{$derniere}1");
        $entete->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $entete->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F97316');
        foreach (range('A', $derniere) as $col) {
            $feuille->getColumnDimension($col)->setAutoSize(true);
            $feuille->getStyle("{$col}:{$col}")->getNumberFormat()->setFormatCode('@');
        }
        $feuille->freezePane('A2');

        $feuilleAide = $classeur->createSheet();
        $feuilleAide->setTitle('Aide');
        $feuilleAide->fromArray(array_map(fn ($l) => ['• ' . $l], $aide), null, 'A1');
        $feuilleAide->getColumnDimension('A')->setWidth(110);
        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }

    // ── Outils ─────────────────────────────────────────────────────────

    /** Position de chaque colonne connue, d'après l'en-tête (casse, accents et ponctuation ignorés) */
    private function colonnes(array $entete): array
    {
        $prefixes = [
            'immatriculation' => ['immat', 'plaque', 'matricule', 'bus', 'vehicule'],
            'designation'     => ['designation', 'libelle', 'description', 'piece'],
            'quantite'        => ['quantite', 'qte', 'qty'],
            'reference'       => ['reference', 'ref'],
            'marque'          => ['marque'],
            'modele'          => ['modele', 'model'],
            'vin'             => ['vin', 'ndechassis', 'nodechassis', 'numerodechassis', 'chassis'],
            'date'            => ['datedemise', 'datemise', 'misenc', 'miseencirculation', 'dmc', 'date'],
            'affectation'     => ['affect', 'service', 'direction', 'responsable'],
        ];

        $colonnes = [];
        foreach ($entete as $index => $titre) {
            $cle = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $titre)));
            if ($cle === '') {
                continue;
            }
            foreach ($prefixes as $champ => $debuts) {
                if (isset($colonnes[$champ])) {
                    continue;
                }
                foreach ($debuts as $debut) {
                    if (str_starts_with($cle, $debut)) {
                        $colonnes[$champ] = $index;
                        continue 3;
                    }
                }
            }
        }

        return $colonnes;
    }

    /** Date JJ/MM/AAAA, AAAA-MM-JJ ou numéro de série Excel → « AAAA-MM-JJ » (null si illisible) */
    private function date($valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        try {
            if (is_numeric($valeur)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valeur)->format('Y-m-d');
            }
            foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/y'] as $format) {
                $d = \DateTime::createFromFormat('!' . $format, trim((string) $valeur));
                if ($d && $d->format($format) === trim((string) $valeur)) {
                    return $d->format('Y-m-d');
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function nombre(string $valeur): ?float
    {
        $v = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $valeur);

        return is_numeric($v) ? (float) $v : null;
    }
}
