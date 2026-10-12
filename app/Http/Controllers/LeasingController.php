<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Client;
use App\Models\RecapLeasing;
use App\Models\User;
use App\Models\VehiculeLeasing;
use App\Services\RecapLeasingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Parc leasing (véhicules de location d'une société, ex. STCD) :
 * - la liste du parc, gérée comme les techniciens (ajouter / retirer) — ces
 *   véhicules ne sont jamais facturés au passage ;
 * - les récaps : pour une période (au plus un mois), le forfait de chaque véhicule
 *   + les pièces de ses OR. Enregistrés pour être retrouvés, imprimés ou exportés.
 *   Ce ne sont pas des factures (pas de suivi de paiement).
 */
class LeasingController extends Controller
{
    /** Ajouter / retirer des véhicules (parc STCD ou leasing tiers) : l'administrateur seulement */
    private function autoriserAdmin(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        return $user;
    }

    /** Générer, enregistrer, imprimer et exporter les récaps : la caissière (et l'administrateur) */
    private function autoriserRecap(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! ($user->isAdmin() || $user->hasPermission('encaisser_factures'))) {
            abort(403);
        }

        return $user;
    }

    // ── Liste du parc ───────────────────────────────────────────────────

    /** Liste de la page : « stcd » (parc leasing) ou « service » (véhicules de service) — paramètre par défaut de la route */
    private function categorie(): string
    {
        $categorie = request()->route('categorie') ?: 'stcd';
        abort_unless(isset(VehiculeLeasing::PARCS_OR[$categorie]), 404);

        return $categorie;
    }

    public function index(Request $request)
    {
        $this->autoriserAdmin();
        $categorie = $this->categorie();

        $recherche = trim((string) $request->get('recherche'));
        $vehicules = VehiculeLeasing::where('categorie', $categorie)
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r->where('immatriculation_normalisee', 'like', '%' . VehiculeLeasing::normaliser($recherche) . '%')
                ->orWhere('proprietaire', 'like', '%' . $recherche . '%')))
            ->when($categorie === 'service', fn ($q) => $q->orderBy('proprietaire'))
            ->orderBy('id')->get();

        $clients = Client::where('type', '!=', 'particulier')->orderBy('raison_sociale')->orderBy('nom')->get();

        return view('leasing.index', [
            'clients'         => $clients,
            'clientParDefaut' => $clients->first(fn (Client $c) => str_contains(mb_strtoupper($c->nom_complet), 'STCD'))?->id,
            'categorie' => $categorie,
            'p'         => VehiculeLeasing::PARCS_OR[$categorie],
            'vehicules' => $vehicules,
            'total'     => VehiculeLeasing::where('categorie', $categorie)->count(),
            'affectations' => $categorie === 'service' ? VehiculeLeasing::where('categorie', 'service')->distinct()->orderBy('proprietaire')->pluck('proprietaire') : collect(),
        ]);
    }

    /**
     * Ajoute un ou plusieurs véhicules : une immatriculation, ou une liste collée
     * depuis Excel (une par ligne, ou séparées par des espaces / virgules /
     * points-virgules). Les doublons sont ignorés.
     */
    public function store(Request $request)
    {
        $user = $this->autoriserAdmin();

        $data = $request->validate([
            'immatriculations' => ['required', 'string', 'max:20000'],
            'proprietaire'     => ['required', 'string', 'max:100'],
        ], [
            'immatriculations.required' => 'Indiquez au moins une immatriculation.',
            'proprietaire.required'     => $this->categorie() === 'service' ? 'Indiquez à qui le véhicule est affecté (service, directeur ou responsable).' : 'Indiquez le propriétaire (facturation du véhicule).',
        ]);
        $categorie = $this->categorie();
        $p = VehiculeLeasing::PARCS_OR[$categorie];

        $saisies = preg_split('/[\r\n,;\t]+/', $data['immatriculations']);
        $ajoutes = 0; $deja = 0; $ailleurs = [];
        foreach ($saisies as $saisie) {
            $immat = trim($saisie);
            // Cellule « N° » collée avec la liste (ex. « 12 ») : ce n'est pas une immatriculation
            if ($immat === '' || ctype_digit(VehiculeLeasing::normaliser($immat))) {
                continue;
            }
            $cle = VehiculeLeasing::normaliser($immat);
            // Un véhicule n'est que dans une seule liste (parc STCD, service, bus…)
            if ($autre = VehiculeLeasing::where('immatriculation_normalisee', $cle)->value('categorie')) {
                $autre === $categorie ? $deja++ : $ailleurs[] = strtoupper($immat);
                continue;
            }
            VehiculeLeasing::create([
                'categorie'                  => $categorie,
                'immatriculation'            => strtoupper(trim(preg_replace('/\s+/', ' ', $immat))),
                'immatriculation_normalisee' => $cle,
                'proprietaire'               => trim($data['proprietaire']),
                'ajoute_par'                 => $user->id,
            ]);
            $ajoutes++;
        }

        Activite::journaliser('ajouter_' . $categorie, "{$p['libelle']} : {$ajoutes} véhicule(s) ajouté(s)" . ($deja ? ", {$deja} déjà dans la liste" : ''));

        $retour = back()->with('success', "{$ajoutes} véhicule(s) ajouté(s) — {$p['libelle']}" . ($deja ? " — {$deja} déjà dans la liste." : '.'));

        return $ailleurs
            ? $retour->with('error', 'Déjà dans une autre liste (parc leasing, véhicules de service ou bus), non ajouté(s) : ' . implode(', ', $ailleurs) . '.')
            : $retour;
    }

    public function destroy(VehiculeLeasing $vehiculeLeasing)
    {
        $this->autoriserAdmin();
        abort_unless($vehiculeLeasing->categorie === $this->categorie(), 404);
        $p = VehiculeLeasing::PARCS_OR[$this->categorie()];

        $immat = $vehiculeLeasing->immatriculation;
        $vehiculeLeasing->delete();
        Activite::journaliser('retirer_' . $this->categorie(), "{$p['libelle']} : véhicule {$immat} retiré de la liste");

        return back()->with('success', "Véhicule {$immat} retiré — {$p['libelle']}.");
    }

    // ── Import Excel de la liste (crée les véhicules), avec aperçu ──────

    public function modeleParc(\App\Services\BusLeasingService $service)
    {
        $this->autoriserAdmin();
        $classeur = $service->modeleParc($this->categorie());

        return response()->streamDownload(function () use ($classeur) {
            IOFactory::createWriter($classeur, 'Xlsx')->save('php://output');
        }, 'modele-' . \Illuminate\Support\Str::slug(VehiculeLeasing::PARCS_OR[$this->categorie()]['libelle']) . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function apercuParc(Request $request, \App\Services\BusLeasingService $service)
    {
        $this->autoriserAdmin();
        $categorie = $this->categorie();

        $request->validate([
            'client_id'    => ['required', 'exists:clients,id'],
            'proprietaire' => ['nullable', 'string', 'max:100'],
            'fichier'      => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'client_id.required' => 'Choisissez le client au nom duquel les véhicules sont créés.',
            'fichier.required'   => 'Sélectionnez le fichier Excel de la liste des véhicules.',
            'fichier.mimes'      => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
        ]);

        $client  = Client::findOrFail($request->client_id);
        $fichier = $request->file('fichier');
        $lignes  = $service->lire($fichier->getRealPath(), strtolower($fichier->getClientOriginalExtension()));
        if ($lignes === null) {
            return back()->with('error', 'Le fichier est vide ou illisible.');
        }

        $analyse = $service->analyserParc($lignes, $client, $categorie, $request->proprietaire);
        $jeton = null;
        if (! $analyse['erreurs']) {
            $jeton = (string) \Illuminate\Support\Str::uuid();
            session(["liste_{$categorie}_apercu" => [
                'jeton' => $jeton, 'client_id' => $client->id, 'proprietaire' => $request->proprietaire, 'bus' => $analyse['bus'],
            ]]);
        }

        return view('leasing.bus-apercu', [
            'g' => VehiculeLeasing::PARCS_OR[$categorie] + ['liste' => mb_strtolower(VehiculeLeasing::PARCS_OR[$categorie]['libelle'])],
            'mode' => 'parc', 'analyse' => $analyse, 'jeton' => $jeton,
            'client' => $client, 'nomFichier' => $fichier->getClientOriginalName(),
        ]);
    }

    public function importerParc(Request $request, \App\Services\BusLeasingService $service)
    {
        $this->autoriserAdmin();
        $categorie = $this->categorie();
        $p = VehiculeLeasing::PARCS_OR[$categorie];

        $apercu = session("liste_{$categorie}_apercu");
        if (! $apercu || $apercu['jeton'] !== $request->jeton) {
            return redirect()->route($p['route'] . '.index')->with('error', "L'aperçu a expiré ou a déjà été importé. Rechargez le fichier.");
        }
        session()->forget("liste_{$categorie}_apercu");

        $client = Client::findOrFail($apercu['client_id']);
        [$crees, $ajoutes] = $service->importerParc($apercu['bus'], $client, $categorie, $apercu['proprietaire']);

        Activite::journaliser('import_' . $categorie, "{$p['libelle']} — import : {$ajoutes} véhicule(s) ajouté(s), {$crees} créé(s) dans les véhicules ({$client->nom_complet})");

        return redirect()->route($p['route'] . '.index')->with('success', "{$ajoutes} véhicule(s) ajouté(s) à la liste — {$crees} nouveau(x) véhicule(s) créé(s) au nom de {$client->nom_complet}.");
    }

    /** Change le propriétaire (parc STCD) ou l'affectation (véhicule de service) */
    public function changerAffectation(Request $request, VehiculeLeasing $vehiculeLeasing)
    {
        $this->autoriserAdmin();
        abort_unless($vehiculeLeasing->categorie === $this->categorie(), 404);

        $data = $request->validate(['proprietaire' => ['required', 'string', 'max:100']], [
            'proprietaire.required' => 'Indiquez l\'affectation du véhicule.',
        ]);
        $avant = $vehiculeLeasing->proprietaire;
        $vehiculeLeasing->update(['proprietaire' => trim($data['proprietaire'])]);
        Activite::journaliser('affectation_' . $this->categorie(), "{$vehiculeLeasing->immatriculation} : « {$avant} » → « {$vehiculeLeasing->proprietaire} »");

        return back()->with('success', "{$vehiculeLeasing->immatriculation} : affecté à {$vehiculeLeasing->proprietaire}.");
    }

    /** Récaps « Véhicules de service » : pièces de leurs OR (+ forfait), regroupés par affectation */
    public function recapsService()
    {
        $this->autoriserRecap();

        return view('leasing.recaps', [
            'type'        => 'service',
            'recaps'      => RecapLeasing::with('generePar')->where('type', 'service')->orderByDesc('date_debut')->orderByDesc('id')->get(),
            'nbVehicules' => VehiculeLeasing::where('categorie', 'service')->count(),
        ]);
    }

    // ── Récaps ──────────────────────────────────────────────────────────

    public function recaps()
    {
        $this->autoriserRecap();

        return view('leasing.recaps', [
            'type'        => 'stcd',
            'recaps'      => RecapLeasing::with('generePar')->where('type', 'stcd')->orderByDesc('date_debut')->orderByDesc('id')->get(),
            'nbVehicules' => VehiculeLeasing::stcd()->count(),
        ]);
    }

    /** Récaps des bus (leasing ou transport public) : forfait + pièces des BC directs de chaque bus */
    public function recapsBus(Request $request)
    {
        $this->autoriserRecap();
        $type = $request->route('groupe') ?: 'bus';
        abort_unless(isset(VehiculeLeasing::GROUPES_BUS[$type]), 404);

        return view('leasing.recaps', [
            'type'        => $type,
            'recaps'      => RecapLeasing::with('generePar')->where('type', $type)->orderByDesc('date_debut')->orderByDesc('id')->get(),
            'nbVehicules' => VehiculeLeasing::where('categorie', $type)->count(),
        ]);
    }

    /** Récaps « Leasing tiers » : véhicules de particuliers loués à STCD, par propriétaire */
    public function recapsTiers()
    {
        $this->autoriserRecap();

        return view('leasing.recaps', [
            'type'        => 'tiers',
            'recaps'      => RecapLeasing::with('generePar')->where('type', 'tiers')->orderByDesc('date_debut')->orderByDesc('id')->get(),
            'nbVehicules' => \App\Models\Vehicule::where('leasing_tiers', true)->count(),
        ]);
    }

    /**
     * Fiche client : choisit, parmi les véhicules du client, ceux qui sont loués à
     * STCD (leasing tiers) — pas de facture au passage, forfait + pièces au récap.
     */
    public function vehiculesTiers(Request $request, Client $client)
    {
        $this->autoriserAdmin();

        $request->validate(['vehicule_ids' => ['nullable', 'array'], 'vehicule_ids.*' => ['integer']]);
        $choisis = collect($request->input('vehicule_ids', []))->map(fn ($id) => (int) $id);

        $client->vehicules()->whereIn('id', $choisis)->update(['leasing_tiers' => true]);
        $client->vehicules()->whereNotIn('id', $choisis)->update(['leasing_tiers' => false]);

        $immats = $client->vehicules()->where('leasing_tiers', true)->pluck('immatriculation')->implode(', ');
        Activite::journaliser('leasing_tiers', "Leasing tiers — {$client->nom_complet} : " . ($immats ?: 'aucun véhicule'), $client);

        return back()->with('success', $immats ? "Véhicules en leasing tiers : {$immats}." : 'Plus aucun véhicule de ce client en leasing tiers.');
    }

    /** Partie d'un propriétaire dans un récap leasing tiers, à lui remettre */
    public function imprimerClient(RecapLeasing $recap, Client $client)
    {
        $this->autoriserRecap();
        abort_unless($recap->estTiers(), 404);

        $recap->setRelation('lignes', $recap->lignes()->where('client_id', $client->id)->get());

        return view('leasing.recap-print', ['recap' => $recap, 'client' => $client]);
    }

    /** Génère (ou régénère) le récap d'une période : au plus un mois (ex. du 25 au 25) */
    public function generer(Request $request, RecapLeasingService $service)
    {
        $this->autoriserRecap();

        $data = $request->validate([
            'type'       => ['nullable', 'in:stcd,tiers,bus,transport,service'],
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after_or_equal:date_debut'],
        ], [
            'date_fin.after_or_equal' => 'La date de fin doit être après la date de début.',
        ]);

        $debut = Carbon::parse($data['date_debut'])->startOfDay();
        $fin   = Carbon::parse($data['date_fin'])->startOfDay();
        if ($fin->gt($debut->copy()->addMonthNoOverflow())) {
            return back()->withInput()->with('error', 'La période ne peut pas dépasser un mois (ex. du 25 au 25 du mois suivant) : un récap = un mois = un forfait.');
        }
        $type = $data['type'] ?? 'stcd';
        if ($type === 'stcd' && VehiculeLeasing::stcd()->count() === 0) {
            return back()->withInput()->with('error', "La liste du parc leasing est vide : ajoutez d'abord les véhicules.");
        }
        if ($type === 'service' && VehiculeLeasing::where('categorie', 'service')->count() === 0) {
            return back()->withInput()->with('error', "La liste des véhicules de service est vide : ajoutez d'abord les véhicules.");
        }
        if (isset(VehiculeLeasing::GROUPES_BUS[$type]) && VehiculeLeasing::where('categorie', $type)->count() === 0) {
            return back()->withInput()->with('error', 'La liste des ' . VehiculeLeasing::GROUPES_BUS[$type]['liste'] . " est vide : importez d'abord les bus.");
        }
        if ($type === 'tiers' && ! \App\Models\Vehicule::where('leasing_tiers', true)->exists()) {
            return back()->withInput()->with('error', "Aucun véhicule en leasing tiers : cochez d'abord les véhicules sur la fiche de leurs propriétaires.");
        }

        $recap = $service->generer($debut, $fin, $type);
        Activite::journaliser('generer_recap_leasing', "Récap {$recap->type_label} {$recap->periode_label} : {$recap->nb_vehicules} véhicules, " . number_format($recap->total, 0, ',', ' ') . ' FDJ HT', $recap);

        return redirect()->route('leasing.recaps.show', $recap)->with('success', "Récap {$recap->periode_label} généré.");
    }

    public function show(RecapLeasing $recap)
    {
        $this->autoriserRecap();

        return view('leasing.recap', ['recap' => $recap->load('lignes', 'generePar')]);
    }

    public function imprimer(RecapLeasing $recap)
    {
        $this->autoriserRecap();

        return view('leasing.recap-print', ['recap' => $recap->load('lignes')]);
    }

    /** Export Excel, sur le modèle du récap papier (N°, propriétaire, immatriculation, pièces, forfait, total) */
    public function exporter(RecapLeasing $recap)
    {
        $this->autoriserRecap();
        $recap->load('lignes');

        $classeur = new Spreadsheet();
        $feuille  = $classeur->getActiveSheet();
        $feuille->setTitle('Recap leasing');
        $feuille->setCellValue('B1', 'GARAGE « S.T.C.D »');
        $feuille->setCellValue('B2', $recap->titre . ' — ' . $recap->periode_label);
        $feuille->fromArray(['N°', 'PROPRIETAIRE (FACTURATION DU VEHICULE)', 'IMMATRICULATION', 'PIECES DETACHEES', "FRAIS FORFAITAIRE / MAIN D'ŒUVRE", 'TOTAUX'], null, 'A4');
        $ligne = 5;
        $groupes = $recap->lignesGroupees();
        foreach ($groupes as $lignesGroupe) {
            foreach ($lignesGroupe as $l) {
                $feuille->fromArray([$l->position, $l->proprietaire, $l->immatriculation, (float) $l->montant_pieces, (float) $l->forfait, (float) $l->total], null, "A{$ligne}");
                $ligne++;
            }
            if ($recap->estGroupe()) {
                $feuille->fromArray(['', 'Sous-total ' . $lignesGroupe->first()->proprietaire, '', (float) $lignesGroupe->sum('montant_pieces'), (float) $lignesGroupe->sum('forfait'), (float) $lignesGroupe->sum('total')], null, "A{$ligne}");
                $feuille->getStyle("A{$ligne}:F{$ligne}")->getFont()->setBold(true);
                $ligne++;
            }
        }
        $feuille->fromArray(['TOTAL', '', '', (float) $recap->total_pieces, (float) $recap->total_forfaits, (float) $recap->total], null, "A{$ligne}");
        $feuille->getStyle("A4:F4")->getFont()->setBold(true);
        $feuille->getStyle("A{$ligne}:F{$ligne}")->getFont()->setBold(true);
        $feuille->getStyle("D5:F{$ligne}")->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'F') as $col) {
            $feuille->getColumnDimension($col)->setAutoSize(true);
        }

        $nom = 'recap-' . match ($recap->type) { 'tiers' => 'leasing-tiers-', 'bus' => 'bus-leasing-', 'transport' => 'bus-transport-', 'service' => 'vehicules-service-', default => 'leasing-' } . $recap->date_debut->format('Y-m-d') . '-au-' . $recap->date_fin->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($classeur) {
            IOFactory::createWriter($classeur, 'Xlsx')->save('php://output');
        }, $nom, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
