<?php

namespace App\Services;

use App\Models\BonCommande;
use App\Models\Client;
use App\Models\ImportFlotte;
use App\Models\LigneBonCommande;
use App\Models\LivraisonFlotte;
use App\Models\Vehicule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Import Excel des pièces livrées à une flotte (ex : société de bus avec son
 * propre atelier) : une ligne du fichier = une pièce (ou une main-d'œuvre),
 * avec l'immatriculation du bus. Les lignes sont regroupées par bus et par
 * date : chaque groupe devient une livraison, avec son BC envoyé au magasin
 * (pièces seulement) puis sa propre facture.
 */
class ImportFlotteService
{
    /** Colonnes du modèle Excel, dans l'ordre */
    public const COLONNES = ['Date', 'Immatriculation', 'Type', 'Référence', 'Désignation', 'Quantité', 'Prix unitaire', 'Remise %', 'Kilométrage'];

    // ── Lecture du fichier ─────────────────────────────────────────────

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
                // Valeurs brutes (non formatées) : dates en numéro de série Excel, prix sans séparateur
                $lignes = IOFactory::load($chemin)->getSheet(0)->toArray(null, true, false, false);
            } catch (\Throwable $e) {
                return null;
            }
        }

        $lignes = array_values(array_filter($lignes, fn ($l) => count(array_filter((array) $l, fn ($v) => trim((string) $v) !== '')) > 0));

        return empty($lignes) ? null : $lignes;
    }

    // ── Analyse ────────────────────────────────────────────────────────

    /**
     * Contrôle chaque ligne et regroupe par bus + date. Rien n'est enregistré.
     *
     * @return array{erreurs: string[], avertissements: string[], groupes: array}
     */
    public function analyser(array $lignes, Client $client, string $dateParDefaut): array
    {
        $erreurs = [];
        $avertissements = [];

        $colonnes = $this->colonnes(array_shift($lignes));
        foreach (['immatriculation' => 'Immatriculation', 'designation' => 'Désignation', 'quantite' => 'Quantité'] as $cle => $libelle) {
            if (! isset($colonnes[$cle])) {
                $erreurs[] = "Colonne « {$libelle} » introuvable dans la première ligne du fichier. Utilisez le modèle Excel.";
            }
        }
        if ($erreurs) {
            return ['erreurs' => $erreurs, 'avertissements' => [], 'groupes' => []];
        }

        // Bus du client, retrouvés par immatriculation normalisée (sans espaces ni tirets)
        $vehiculesClient = $client->vehicules()->get()->keyBy(fn ($v) => $this->normaliserImmat($v->immatriculation));

        $groupes = [];
        $tousVehicules = null;
        foreach ($lignes as $i => $ligne) {
            $numLigne = $i + 2; // ligne 1 = en-tête
            $val = fn (string $cle) => isset($colonnes[$cle]) ? trim((string) ($ligne[$colonnes[$cle]] ?? '')) : '';

            $immat       = $val('immatriculation');
            $designation = $val('designation');
            if ($immat === '' && $designation === '') {
                continue;
            }

            if ($immat === '') {
                $erreurs[] = "Ligne {$numLigne} : immatriculation manquante.";
                continue;
            }
            $vehicule = $vehiculesClient->get($this->normaliserImmat($immat));
            if (! $vehicule) {
                $tousVehicules ??= Vehicule::with('client')->get()->keyBy(fn ($v) => $this->normaliserImmat($v->immatriculation));
                $autre = $tousVehicules->get($this->normaliserImmat($immat));
                $erreurs[] = $autre
                    ? "Ligne {$numLigne} : le véhicule {$immat} appartient à un autre client ({$autre->client?->nom_complet})."
                    : "Ligne {$numLigne} : bus {$immat} inconnu — créez-le d'abord dans Véhicules pour {$client->nom_complet}.";
                continue;
            }

            if ($designation === '') {
                $erreurs[] = "Ligne {$numLigne} ({$immat}) : désignation manquante.";
                continue;
            }

            $typeSaisi = $val('type');
            $type = $this->type($typeSaisi);
            if ($type === null) {
                $erreurs[] = "Ligne {$numLigne} ({$immat}) : type « {$typeSaisi} » non reconnu — mettez « Pièce » ou « Main d'œuvre ».";
                continue;
            }

            $quantite = $this->nombre($val('quantite'));
            if ($quantite === null || $quantite <= 0) {
                $erreurs[] = "Ligne {$numLigne} ({$immat}) : quantité invalide pour « {$designation} ».";
                continue;
            }

            $prix = $val('prix') === '' ? null : $this->nombre($val('prix'));
            if ($val('prix') !== '' && ($prix === null || $prix < 0)) {
                $erreurs[] = "Ligne {$numLigne} ({$immat}) : prix unitaire invalide pour « {$designation} ».";
                continue;
            }

            $remise = $val('remise') === '' ? 0.0 : $this->nombre(str_replace('%', '', $val('remise')));
            if ($remise === null || $remise < 0 || $remise > 100) {
                $erreurs[] = "Ligne {$numLigne} ({$immat}) : remise invalide (entre 0 et 100).";
                continue;
            }

            $dateSaisie = $val('date');
            $date = $dateSaisie === '' ? $dateParDefaut : $this->date($ligne[$colonnes['date']] ?? null);
            if ($date === null) {
                $erreurs[] = "Ligne {$numLigne} ({$immat}) : date « {$dateSaisie} » non reconnue (format attendu : JJ/MM/AAAA).";
                continue;
            }

            $km = $val('kilometrage') === '' ? null : $this->nombre($val('kilometrage'));

            $cle = $vehicule->id . '|' . $date;
            $groupes[$cle] ??= [
                'vehicule_id'     => $vehicule->id,
                'immatriculation' => $vehicule->immatriculation,
                'designation_vehicule' => trim($vehicule->marque . ' ' . $vehicule->modele),
                'date'            => $date,
                'kilometrage'     => null,
                'lignes'          => [],
            ];
            if ($km !== null) {
                $groupes[$cle]['kilometrage'] = max((int) $km, (int) $groupes[$cle]['kilometrage']);
            }
            $groupes[$cle]['lignes'][] = [
                'type'          => $type,
                'reference'     => $type === 'piece' ? ($val('reference') ?: null) : null,
                'designation'   => $designation,
                'quantite'      => $quantite,
                'prix_unitaire' => $prix,
                'remise'        => $remise,
            ];
        }

        if (! $erreurs && ! $groupes) {
            $erreurs[] = 'Aucune ligne à importer dans le fichier.';
        }

        // Même bus, même date déjà importés : probablement le même fichier une deuxième fois
        foreach ($groupes as $groupe) {
            $existe = LivraisonFlotte::where('vehicule_id', $groupe['vehicule_id'])
                ->whereDate('date_livraison', $groupe['date'])
                ->with('import')
                ->first();
            if ($existe) {
                $avertissements[] = "Le bus {$groupe['immatriculation']} a déjà une livraison le " . Carbon::parse($groupe['date'])->format('d/m/Y') . " (import {$existe->import?->numero}). Vérifiez que ce fichier n'a pas déjà été importé.";
            }
            if (collect($groupe['lignes'])->contains(fn ($l) => $l['type'] === 'main_oeuvre' && $l['prix_unitaire'] === null)) {
                $avertissements[] = "Bus {$groupe['immatriculation']} : main-d'œuvre sans prix — il faudra le saisir au moment de facturer.";
            }
        }

        usort($groupes, fn ($a, $b) => [$a['date'], $a['immatriculation']] <=> [$b['date'], $b['immatriculation']]);

        return ['erreurs' => $erreurs, 'avertissements' => array_values(array_unique($avertissements)), 'groupes' => array_values($groupes)];
    }

    // ── Création ───────────────────────────────────────────────────────

    /**
     * Enregistre l'import : une livraison par groupe (bus + date) et, s'il y a
     * des pièces, son BC — puis envoie les BC au magasin (hors transaction).
     */
    public function creer(Client $client, array $groupes, ?string $fichierChemin, ?string $fichierNom, ?string $notes): ImportFlotte
    {
        $import = DB::transaction(function () use ($client, $groupes, $fichierChemin, $fichierNom, $notes) {
            $import = ImportFlotte::create([
                'numero'               => ImportFlotte::genererNumero(),
                'client_id'            => $client->id,
                'fichier_nom_original' => $fichierNom,
                'fichier_chemin'       => $fichierChemin,
                'notes'                => $notes,
                'cree_par'             => Auth::id(),
            ]);

            foreach ($groupes as $groupe) {
                $pieces = array_filter($groupe['lignes'], fn ($l) => $l['type'] === 'piece');

                $bc = null;
                if ($pieces) {
                    $bc = BonCommande::create([
                        'numero'      => BonCommande::genererNumero(),
                        'devis_id'    => null,
                        'or_id'       => null,
                        'dossier_id'  => null,
                        'client_id'   => $client->id,
                        'vehicule_id' => $groupe['vehicule_id'],
                        'statut'      => 'en_attente',
                        'notes'       => "Livraison flotte {$import->numero}",
                    ]);
                }

                $livraison = LivraisonFlotte::create([
                    'import_flotte_id' => $import->id,
                    'client_id'        => $client->id,
                    'vehicule_id'      => $groupe['vehicule_id'],
                    'date_livraison'   => $groupe['date'],
                    'kilometrage'      => $groupe['kilometrage'],
                    'bon_commande_id'  => $bc?->id,
                ]);

                foreach ($groupe['lignes'] as $ligne) {
                    $ligneBc = null;
                    if ($bc && $ligne['type'] === 'piece') {
                        $ligneBc = LigneBonCommande::create([
                            'bon_commande_id' => $bc->id,
                            'designation'     => $ligne['designation'],
                            'reference'       => $ligne['reference'],
                            'quantite'        => $ligne['quantite'],
                        ]);
                    }
                    $livraison->lignes()->create(array_merge($ligne, ['ligne_bon_commande_id' => $ligneBc?->id]));
                }

                // Kilométrage relevé : on le reporte sur la fiche du bus s'il est plus récent
                if ($groupe['kilometrage']) {
                    Vehicule::whereKey($groupe['vehicule_id'])
                        ->where(fn ($q) => $q->whereNull('kilometrage')->orWhere('kilometrage', '<', $groupe['kilometrage']))
                        ->update(['kilometrage' => $groupe['kilometrage']]);
                }
            }

            return $import;
        });

        // Envoi au magasin. Si le magasin ne répond pas, on n'insiste pas pour les
        // BC suivants (la page Suivi des pièces relance les envois en attente).
        $service = app(FournisseurApiService::class);
        foreach ($import->livraisons()->with('bonCommande')->get() as $livraison) {
            if (! $livraison->bonCommande) {
                continue;
            }
            $service->envoyerBonCommande($livraison->bonCommande);
            if (! $livraison->bonCommande->fresh()->fournisseur_repondu_at) {
                break;
            }
        }

        return $import;
    }

    // ── Modèle Excel ───────────────────────────────────────────────────

    /** Classeur modèle à remplir : en-têtes, deux bus d'exemple et une feuille d'aide */
    public function modele(): Spreadsheet
    {
        $classeur = new Spreadsheet();
        $feuille  = $classeur->getActiveSheet();
        $feuille->setTitle('Livraison');

        $feuille->fromArray(self::COLONNES, null, 'A1');
        $date = now()->format('d/m/Y');
        $feuille->fromArray([
            [$date, '123 A 45', 'Pièce', '1109010', 'Filtre à huile', 1, 3500, '', 184500],
            [$date, '123 A 45', 'Pièce', '3501110', 'Plaquettes de frein AV', 2, 9000, '', ''],
            [$date, '123 A 45', "Main d'œuvre", '', 'Montage plaquettes', 1, 5000, '', ''],
            [$date, '456 B 78', 'Pièce', '1109010', 'Filtre à huile', 1, 3500, '', 97200],
            [$date, '456 B 78', 'Pièce', '1701200', 'Courroie', 1, 12000, '', ''],
        ], null, 'A2');

        $entete = $feuille->getStyle('A1:I1');
        $entete->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $entete->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F97316');
        foreach (range('A', 'I') as $col) {
            $feuille->getColumnDimension($col)->setAutoSize(true);
        }
        $feuille->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $feuille->getStyle('B:B')->getNumberFormat()->setFormatCode('@');
        $feuille->getStyle('D:D')->getNumberFormat()->setFormatCode('@');
        $feuille->freezePane('A2');

        // Liste déroulante pour le type
        for ($ligne = 2; $ligne <= 500; $ligne++) {
            $validation = $feuille->getCell("C{$ligne}")->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setFormula1('"Pièce,Main d\'œuvre"');
        }

        $aide = $classeur->createSheet();
        $aide->setTitle('Aide');
        $aide->fromArray([
            ['Comment remplir la feuille « Livraison »'],
            [''],
            ['• Une ligne = une pièce (ou une main-d\'œuvre). Un bus avec 5 pièces = 5 lignes.'],
            ['• Répétez l\'immatriculation du bus sur chaque ligne : c\'est elle qui range la pièce dans la facture du bus.'],
            ['• Une facture est créée par bus et par date de livraison.'],
            ['• Obligatoire : Immatriculation, Désignation, Quantité. Le bus doit déjà exister dans le système.'],
            ['• Date : JJ/MM/AAAA. Vide = la date choisie à l\'écran d\'import.'],
            ['• Type : « Pièce » ou « Main d\'œuvre ». Vide = Pièce.'],
            ['• Prix unitaire en FDJ, hors taxe. Vide = le prix renvoyé par le magasin.'],
            ['• Les pièces partent au magasin (un bon de commande par bus) ; la main-d\'œuvre va directement sur la facture.'],
            ['• Si aucune main-d\'œuvre n\'est indiquée pour un bus, la main-d\'œuvre flotte (Réglages atelier, 5 000 FDJ par défaut) est ajoutée automatiquement à sa facture.'],
            ['• Supprimez les lignes d\'exemple avant d\'importer.'],
        ], null, 'A1');
        $aide->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $aide->getColumnDimension('A')->setWidth(110);

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
            'prix'            => ['prix', 'pu'],
            'remise'          => ['remise'],
            'reference'       => ['reference', 'ref'],
            'kilometrage'     => ['kilometrage', 'km'],
            'date'            => ['date'],
            'type'            => ['type'],
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

    private function normaliserImmat(?string $immat): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii((string) $immat)));
    }

    private function type(string $valeur): ?string
    {
        $v = preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($valeur)));

        return match (true) {
            $v === '', str_starts_with($v, 'piece'), $v === 'p', $v === 'pc' => 'piece',
            str_starts_with($v, 'main'), $v === 'mo' => 'main_oeuvre',
            default => null,
        };
    }

    /** « 3 500 », « 3500,50 », 3500 → nombre ; null si illisible */
    private function nombre(string $valeur): ?float
    {
        $v = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $valeur);
        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? (float) $v : null;
    }

    /** Date Excel (numéro de série) ou texte JJ/MM/AAAA, AAAA-MM-JJ... → AAAA-MM-JJ */
    private function date(mixed $valeur): ?string
    {
        if (is_numeric($valeur)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valeur))->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }

        $texte = trim((string) $valeur);
        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'd/m/y', 'j/n/y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $texte);
                if ($date && $date->format($format) === $texte) {
                    return $date->toDateString();
                }
            } catch (\Throwable $e) {
                // format suivant
            }
        }

        return null;
    }
}
