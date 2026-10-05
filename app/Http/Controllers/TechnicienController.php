<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Technicien;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur des Techniciens (mécaniciens).
 *
 * Simples fiches (comme les clients) — pas de compte de connexion. Utilisées
 * pour affecter les OR et suivre la performance (cf. RapportController).
 * Un technicien qui quitte le garage est désactivé, pas supprimé, pour
 * garder son nom sur les anciens OR déjà affectés.
 */
class TechnicienController extends Controller
{
    private const SERVICES = ['rapide', 'mecanique', 'electricite', 'carrosserie', 'peinture'];

    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_techniciens')) {
            abort(403);
        }

        $query = Technicien::withCount('ordresReparations')->orderBy('nom');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        if ($request->get('statut') === 'inactif') {
            $query->where('actif', false);
        } elseif ($request->get('statut') !== 'tous') {
            $query->where('actif', true);
        }

        $techniciens = $query->paginate(20)->withQueryString();

        return view('techniciens.index', compact('techniciens'));
    }

    public function create()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }
        return view('techniciens.create');
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }

        $data = $this->validerDonnees($request);
        $technicien = Technicien::create($data + ['actif' => true]);

        Activite::journaliser('creer_technicien', "Création du technicien {$technicien->name}", $technicien);

        return redirect()->route('techniciens.index')->with('success', "Technicien {$technicien->name} créé.");
    }

    public function edit(Technicien $technicien)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }
        return view('techniciens.edit', compact('technicien'));
    }

    public function update(Request $request, Technicien $technicien)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }

        $data = $this->validerDonnees($request);
        $technicien->update($data);

        Activite::journaliser('modifier_technicien', "Modification du technicien {$technicien->name}", $technicien);

        return redirect()->route('techniciens.index')->with('success', "Technicien {$technicien->name} mis à jour.");
    }

    /**
     * Active/désactive un technicien. Désactivé, il n'apparaît plus dans la
     * liste pour affecter de nouveaux OR, mais reste visible sur les anciens.
     */
    public function toggleActif(Technicien $technicien)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }

        $technicien->update(['actif' => ! $technicien->actif]);
        $etat = $technicien->actif ? 'réactivé' : 'désactivé';
        Activite::journaliser('modifier_technicien', "Technicien {$technicien->name} {$etat}", $technicien);

        return back()->with('success', "Technicien {$technicien->name} {$etat}.");
    }

    /**
     * Supprime définitivement une fiche technicien — seulement si aucun OR
     * n'y est lié (sinon : désactiver plutôt, pour garder l'historique).
     */
    public function destroy(Technicien $technicien)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }

        if ($technicien->ordresReparations()->exists()) {
            return back()->with('error', "Impossible de supprimer {$technicien->name} : des OR lui sont liés — désactivez-le plutôt.");
        }

        $nom = $technicien->name;
        $technicien->delete();
        Activite::journaliser('supprimer_technicien', "Suppression du technicien {$nom}");

        return back()->with('success', "Technicien {$nom} supprimé.");
    }

    /**
     * Formulaire d'import CSV/Excel d'une liste de techniciens.
     */
    public function importForm()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }
        return view('techniciens.import');
    }

    /**
     * Importe une liste de techniciens depuis un fichier CSV ou Excel (.xlsx/.xls)
     * — colonnes attendues : Prénom, Nom, Téléphone, Service (avec en-tête,
     * insensible à la casse/accents). Pour le CSV, délimiteur , ou ; auto-détecté.
     * Rien n'est perdu silencieusement : une ligne au service non reconnu est
     * quand même importée (service laissé vide) et signalée dans le résumé.
     */
    public function import(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('gerer_techniciens')) {
            abort(403);
        }

        $request->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:2048'],
        ], [
            'fichier.required' => 'Veuillez sélectionner un fichier à importer.',
            'fichier.mimes'    => 'Le fichier doit être au format CSV ou Excel (.xlsx, .xls).',
        ]);

        $fichier = $request->file('fichier');
        $extension = strtolower($fichier->getClientOriginalExtension());

        $lignes = $extension === 'csv' || $extension === 'txt'
            ? $this->lireCsv($fichier->getRealPath())
            : $this->lireExcel($fichier->getRealPath());

        if ($lignes === null) {
            return back()->with('error', 'Le fichier est vide ou illisible.');
        }

        $entete = array_shift($lignes);

        // Repère chaque colonne par son en-tête (insensible casse/accents), quel que soit l'ordre
        $normaliser = fn (string $s) => strtolower(trim(str_replace(['é', 'è', 'ê'], 'e', (string) $s)));
        $index = [];
        foreach ($entete as $i => $col) {
            $index[$normaliser((string) $col)] = $i;
        }
        $colPrenom = $index['prenom'] ?? null;
        $colNom    = $index['nom'] ?? null;
        $colTel    = $index['telephone'] ?? $index['tel'] ?? null;
        $colSvc    = $index['service'] ?? null;

        if ($colNom === null) {
            return back()->with('error', 'Colonne "Nom" introuvable dans le fichier — vérifiez les en-têtes (Prénom, Nom, Téléphone, Service).');
        }

        $servicesParLabel = [
            'service rapide' => 'rapide', 'rapide' => 'rapide',
            'mecanique' => 'mecanique',
            'electricite' => 'electricite',
            'carrosserie' => 'carrosserie',
            'peinture' => 'peinture',
        ];

        $crees = 0;
        $ignores = [];
        $numLigne = 1;
        foreach ($lignes as $ligne) {
            $numLigne++;
            $nom = trim((string) ($ligne[$colNom] ?? ''));
            if ($nom === '') {
                $ignores[] = "Ligne {$numLigne} : nom manquant";
                continue;
            }

            $service = null;
            $serviceBrut = $colSvc !== null ? trim((string) ($ligne[$colSvc] ?? '')) : '';
            if ($serviceBrut !== '') {
                $service = $servicesParLabel[$normaliser($serviceBrut)] ?? null;
                if ($service === null) {
                    $ignores[] = "Ligne {$numLigne} : service « {$serviceBrut} » non reconnu — importé sans service";
                }
            }

            Technicien::create([
                'nom'       => $nom,
                'prenom'    => $colPrenom !== null ? (trim((string) ($ligne[$colPrenom] ?? '')) ?: null) : null,
                'telephone' => $colTel !== null ? (trim((string) ($ligne[$colTel] ?? '')) ?: null) : null,
                'service'   => $service,
                'actif'     => true,
            ]);
            $crees++;
        }

        Activite::journaliser('importer_techniciens', "Import de {$crees} technicien(s) depuis un fichier");

        $message = "{$crees} technicien(s) importé(s).";
        if (! empty($ignores)) {
            $message .= ' Avertissements : ' . implode(' | ', $ignores);
        }

        return redirect()->route('techniciens.index')->with('success', $message);
    }

    /**
     * Lit un fichier CSV et retourne toutes ses lignes (en-tête incluse) sous
     * forme de tableau de tableaux, ou null si le fichier est vide/illisible.
     */
    private function lireCsv(string $chemin): ?array
    {
        $handle = fopen($chemin, 'r');

        // Détection du délimiteur (';' fréquent dans les exports Excel français) sur la 1re ligne
        $premiereLigne = fgets($handle) ?: '';
        rewind($handle);
        $delimiteur = substr_count($premiereLigne, ';') > substr_count($premiereLigne, ',') ? ';' : ',';

        $lignes = [];
        while (($ligne = fgetcsv($handle, 0, $delimiteur)) !== false) {
            $lignes[] = $ligne;
        }
        fclose($handle);

        return empty($lignes) ? null : $lignes;
    }

    /**
     * Lit la première feuille d'un fichier Excel (.xlsx/.xls) et retourne
     * toutes ses lignes (en-tête incluse), ou null si le fichier est vide/illisible.
     */
    private function lireExcel(string $chemin): ?array
    {
        try {
            $lignes = IOFactory::load($chemin)->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return null;
        }

        // Retire les lignes entièrement vides (fréquent en fin de feuille Excel)
        $lignes = array_values(array_filter($lignes, fn ($ligne) => count(array_filter($ligne, fn ($v) => trim((string) $v) !== '')) > 0));

        return empty($lignes) ? null : $lignes;
    }

    private function validerDonnees(Request $request): array
    {
        return $request->validate([
            'nom'       => ['required', 'string', 'max:255'],
            'prenom'    => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'service'   => ['nullable', 'in:' . implode(',', self::SERVICES)],
        ], [
            'nom.required'  => 'Le nom est obligatoire.',
            'service.in'    => 'Le service sélectionné est invalide.',
        ]);
    }
}
