<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\BonCommande;
use App\Models\Client;
use App\Models\User;
use App\Models\VehiculeLeasing;
use App\Services\BusLeasingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Bus réparés dans un autre garage (pas d'OR à l'atelier), en deux groupes séparés
 * (même fonctionnement, liste et récap à part) — cf. VehiculeLeasing::GROUPES_BUS :
 * « bus » (bus leasing, /leasing-bus) et « transport » (bus transport public, /bus-transport).
 *
 * - liste des bus (import Excel avec aperçu) : l'administrateur ;
 * - bons de commande directs, un par bus (à l'écran ou par import Excel avec
 *   aperçu) : ceux qui gèrent les bons de commande (et l'administrateur) ;
 * - jamais de facture : forfait + pièces dans le récap mensuel du groupe (LeasingController).
 */
class BusLeasingController extends Controller
{
    /** Groupe de la page (paramètre par défaut de la route) : « bus » ou « transport » */
    private function groupe(): string
    {
        $groupe = request()->route('groupe') ?: 'bus';
        abort_unless(isset(VehiculeLeasing::GROUPES_BUS[$groupe]), 404);

        return $groupe;
    }

    /** Libellés et nom de route du groupe */
    private function g(): array
    {
        return VehiculeLeasing::GROUPES_BUS[$this->groupe()];
    }

    private function cleSession(string $quoi): string
    {
        return "bus_{$this->groupe()}_apercu_{$quoi}";
    }

    /** Liste des bus : l'administrateur seulement */
    private function autoriserAdmin(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        return $user;
    }

    /** Bons de commande des bus : ceux qui gèrent les bons de commande (et l'administrateur) */
    private function autoriserBc(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->peutGererBonsCommande()) {
            abort(403);
        }

        return $user;
    }

    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! ($user->isAdmin() || $user->peutGererBonsCommande())) {
            abort(403);
        }

        $groupe    = $this->groupe();
        $recherche = trim((string) $request->get('recherche'));
        $bus = VehiculeLeasing::where('categorie', $groupe)->with('fiche')
            ->when($recherche !== '', fn ($q) => $q->where('immatriculation_normalisee', 'like', '%' . VehiculeLeasing::normaliser($recherche) . '%'))
            ->orderBy('immatriculation')->get();

        // Derniers BC des bus de ce groupe
        $vehiculeIds = VehiculeLeasing::where('categorie', $groupe)->whereNotNull('vehicule_id')->pluck('vehicule_id');
        $bcs = BonCommande::with(['vehiculeDirect', 'lignes', 'bonTransfert'])->where('leasing_bus', true)
            ->whereIn('vehicule_id', $vehiculeIds)
            ->latest('id')->limit(30)->get();

        $clients = Client::where('type', '!=', 'particulier')->orderBy('raison_sociale')->orderBy('nom')->get();

        return view('leasing.bus', [
            'g'        => $this->g(),
            'bus'      => $bus,
            'total'    => VehiculeLeasing::where('categorie', $groupe)->count(),
            'bcs'      => $bcs,
            'clients'  => $clients,
            // Propriétaire proposé : le client STCD
            'clientParDefaut' => $clients->first(fn (Client $c) => str_contains(mb_strtoupper($c->nom_complet), 'STCD'))?->id,
            'estAdmin' => $user->isAdmin(),
            'forfait'  => (int) \App\Models\ParametreAtelier::get()->{$this->g()['forfait']},
        ]);
    }

    // ── Liste des bus (import Excel avec aperçu) ────────────────────────

    public function modeleParc(BusLeasingService $service)
    {
        return $this->telecharger($service->modeleParc($this->groupe()), 'modele-liste-' . Str::slug($this->g()['libelle']) . '.xlsx');
    }

    public function apercuParc(Request $request, BusLeasingService $service)
    {
        $this->autoriserAdmin();

        $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'fichier'   => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'client_id.required' => 'Choisissez le client propriétaire des bus.',
            'fichier.required'   => 'Sélectionnez le fichier Excel de la liste des bus.',
            'fichier.mimes'      => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
        ]);

        $client  = Client::findOrFail($request->client_id);
        $fichier = $request->file('fichier');
        $lignes  = $service->lire($fichier->getRealPath(), strtolower($fichier->getClientOriginalExtension()));
        if ($lignes === null) {
            return back()->with('error', 'Le fichier est vide ou illisible.');
        }

        $analyse = $service->analyserParc($lignes, $client, $this->groupe());
        $jeton = null;
        if (! $analyse['erreurs']) {
            $jeton = (string) Str::uuid();
            session([$this->cleSession('parc') => ['jeton' => $jeton, 'client_id' => $client->id, 'bus' => $analyse['bus']]]);
        }

        return view('leasing.bus-apercu', [
            'g' => $this->g(), 'mode' => 'parc', 'analyse' => $analyse, 'jeton' => $jeton,
            'client' => $client, 'nomFichier' => $fichier->getClientOriginalName(),
        ]);
    }

    public function importerParc(Request $request, BusLeasingService $service)
    {
        $this->autoriserAdmin();
        $g = $this->g();

        $apercu = session($this->cleSession('parc'));
        if (! $apercu || $apercu['jeton'] !== $request->jeton) {
            return redirect()->route($g['route'] . '.index')->with('error', "L'aperçu a expiré ou a déjà été importé. Rechargez le fichier.");
        }
        session()->forget($this->cleSession('parc'));

        $client = Client::findOrFail($apercu['client_id']);
        [$crees, $ajoutes] = $service->importerParc($apercu['bus'], $client, $this->groupe());

        Activite::journaliser('import_' . $this->groupe() . '_bus', "{$g['libelle']} — {$client->nom_complet} : {$ajoutes} bus ajouté(s) à la liste, dont {$crees} créé(s) dans les véhicules");

        return redirect()->route($g['route'] . '.index')->with('success', "{$ajoutes} bus ajouté(s) à la liste des {$g['liste']} ({$crees} nouveau(x) véhicule(s) créé(s)).");
    }

    public function destroy(VehiculeLeasing $vehiculeLeasing)
    {
        $this->autoriserAdmin();
        abort_unless($vehiculeLeasing->categorie === $this->groupe(), 404);
        $g = $this->g();

        $immat = $vehiculeLeasing->immatriculation;
        $vehiculeLeasing->delete();
        Activite::journaliser('retirer_' . $this->groupe() . '_bus', "{$g['libelle']} : {$immat} retiré de la liste");

        return back()->with('success', "Bus {$immat} retiré de la liste des {$g['liste']}.");
    }

    // ── Bons de commande des bus ───────────────────────────────────────

    public function creerBc(Request $request)
    {
        $this->autoriserBc();

        return view('leasing.bus-bc', [
            'g'     => $this->g(),
            'bus'   => VehiculeLeasing::where('categorie', $this->groupe())->with('fiche')->whereNotNull('vehicule_id')->orderBy('immatriculation')->get(),
            'busId' => (int) $request->get('bus') ?: null,
        ]);
    }

    /** Un BC pour un bus (une ou plusieurs pièces), saisi à l'écran */
    public function enregistrerBc(Request $request, BusLeasingService $service)
    {
        $this->autoriserBc();
        $g = $this->g();

        $data = $request->validate([
            'bus_id'                => ['required', 'exists:vehicules_leasing,id'],
            'lignes'                => ['required', 'array', 'min:1'],
            'lignes.*.designation'  => ['required', 'string', 'max:255'],
            'lignes.*.reference'    => ['nullable', 'string', 'max:100'],
            'lignes.*.quantite'     => ['required', 'numeric', 'gt:0'],
        ], [
            'bus_id.required'               => 'Choisissez le bus.',
            'lignes.required'               => 'Ajoutez au moins une pièce.',
            'lignes.*.designation.required' => 'La désignation de chaque pièce est obligatoire.',
            'lignes.*.quantite.required'    => 'La quantité de chaque pièce est obligatoire.',
            'lignes.*.quantite.gt'          => 'La quantité doit être supérieure à zéro.',
        ]);

        $bus = VehiculeLeasing::where('categorie', $this->groupe())->with('fiche')->findOrFail($data['bus_id']);
        if (! $bus->fiche) {
            return back()->withInput()->with('error', "Le bus {$bus->immatriculation} n'a pas de fiche véhicule : réimportez-le dans la liste des bus.");
        }

        $bc = $service->creerBc($bus, array_values($data['lignes']));
        Activite::journaliser('creer_bc_' . $this->groupe() . '_bus', "BC {$bc->numero} — {$g['libelle']} {$bus->immatriculation} : " . count($data['lignes']) . ' pièce(s)', $bc);

        return redirect()->route('bons-commande.show', $bc)->with('success', "Bon de commande {$bc->numero} créé pour le bus {$bus->immatriculation} et envoyé au magasin.");
    }

    public function modeleBc(BusLeasingService $service)
    {
        return $this->telecharger($service->modeleBc(), 'modele-pieces-' . Str::slug($this->g()['libelle']) . '.xlsx');
    }

    public function apercuBc(Request $request, BusLeasingService $service)
    {
        $this->autoriserBc();

        $request->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'fichier.required' => 'Sélectionnez le fichier Excel des pièces.',
            'fichier.mimes'    => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
        ]);

        $fichier = $request->file('fichier');
        $lignes  = $service->lire($fichier->getRealPath(), strtolower($fichier->getClientOriginalExtension()));
        if ($lignes === null) {
            return back()->with('error', 'Le fichier est vide ou illisible.');
        }

        $analyse = $service->analyserBc($lignes, $this->groupe());
        $jeton = null;
        if (! $analyse['erreurs']) {
            $jeton = (string) Str::uuid();
            session([$this->cleSession('bc') => ['jeton' => $jeton, 'groupes' => $analyse['groupes']]]);
        }

        return view('leasing.bus-apercu', [
            'g' => $this->g(), 'mode' => 'bc', 'analyse' => $analyse, 'jeton' => $jeton,
            'client' => null, 'nomFichier' => $fichier->getClientOriginalName(),
        ]);
    }

    /** Confirme l'aperçu : un BC par bus, envoyés au magasin */
    public function importerBc(Request $request, BusLeasingService $service)
    {
        $this->autoriserBc();
        $g = $this->g();

        $apercu = session($this->cleSession('bc'));
        if (! $apercu || $apercu['jeton'] !== $request->jeton) {
            return redirect()->route($g['route'] . '.bc.create')->with('error', "L'aperçu a expiré ou a déjà été importé. Rechargez le fichier.");
        }
        session()->forget($this->cleSession('bc'));

        $numeros = [];
        foreach ($apercu['groupes'] as $groupe) {
            $bus = VehiculeLeasing::where('categorie', $this->groupe())->with('fiche')->find($groupe['bus_id']);
            if (! $bus?->fiche) {
                continue;
            }
            $numeros[] = $service->creerBc($bus, $groupe['lignes'])->numero;
        }

        Activite::journaliser('import_bc_' . $this->groupe() . '_bus', "{$g['libelle']} : " . count($numeros) . ' bon(s) de commande importé(s) — ' . implode(', ', $numeros));

        return redirect()->route($g['route'] . '.index')->with('success', count($numeros) . ' bon(s) de commande créé(s) et envoyé(s) au magasin : ' . implode(', ', $numeros) . '.');
    }

    private function telecharger($classeur, string $nom)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! ($user->isAdmin() || $user->peutGererBonsCommande())) {
            abort(403);
        }

        return response()->streamDownload(function () use ($classeur) {
            IOFactory::createWriter($classeur, 'Xlsx')->save('php://output');
        }, $nom, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
