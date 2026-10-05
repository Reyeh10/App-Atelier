<?php

namespace App\Http\Controllers;

use App\Models\BonCommande;
use App\Models\BonTransfert;
use Illuminate\Http\Request;
use App\Models\Activite;
use App\Services\FournisseurApiService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Suivi des Bons de Commande pièces (BC).
 *
 * Un bon de commande est généré automatiquement dès la création d'un devis
 * avec des pièces (avant même sa validation), puis transmis en temps réel au
 * système fournisseur (stcd-magasin) qui renvoie la disponibilité et le prix
 * de chaque pièce (voir FournisseurApiService). Tant que le devis n'est pas
 * accepté, le BC n'a pas encore d'OR — il reste rattaché à son dossier de
 * réception (cf. BonCommande::dossier()).
 *
 * app-atelier ne gère plus la commande elle-même (c'est stcd-magasin qui s'en
 * charge) : il ne reste ici qu'un suivi en lecture seule, plus l'action de
 * marquer les pièces comme physiquement reçues au garage.
 *
 * Cycle de vie : en_attente → commande → reçu.
 * Consultation : chef de garage, admin. Marquer reçu : chef de garage, admin.
 */
class BonCommandeController extends Controller
{
    /**
     * Liste tous les bons de commande, du plus récent au plus ancien.
     */
    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->peutVoirBonsCommande()) {
            abort(403);
        }

        $this->relancerEnvoisEnAttente();

        $bons = BonCommande::with([
                'ordreReparation.client', 'ordreReparation.vehicule',
                'dossier.client', 'dossier.vehicule',
                'devis', 'lignes', 'vehiculeDirect', 'clientDirect',
            ])
            ->latest()
            ->paginate(25);

        return view('bons-commande.index', compact('bons'));
    }

    /**
     * Il n'y a pas de tâche planifiée (cron) dans cet environnement : on relance
     * ici, à chaque consultation de la liste, l'envoi des BC dont la première
     * tentative a échoué (ex: stcd-magasin injoignable au moment de la création),
     * plutôt qu'ils ne restent bloqués indéfiniment en "en attente". Limité à
     * quelques BC de plus de 30s pour ne pas ralentir la page si le fournisseur
     * est réellement hors ligne.
     */
    private function relancerEnvoisEnAttente(): void
    {
        $enEchec = BonCommande::whereNull('fournisseur_repondu_at')
            ->where('created_at', '<=', now()->subSeconds(30))
            ->latest()
            ->limit(3)
            ->get();

        if ($enEchec->isEmpty()) return;

        $service = app(FournisseurApiService::class);
        foreach ($enEchec as $bc) {
            $service->envoyerBonCommande($bc);
        }
    }

    /**
     * Affiche la fiche détaillée d'un bon de commande : ses lignes et la
     * disponibilité renvoyée par le fournisseur pour chacune.
     */
    public function show(BonCommande $bonCommande)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->peutVoirBonsCommande()) {
            abort(403);
        }

        $bonCommande->load([
            'ordreReparation.client', 'ordreReparation.vehicule',
            'dossier.client', 'dossier.vehicule',
            'devis', 'lignes', 'vehiculeDirect', 'clientDirect', 'livraisonFlotte.import',
        ]);

        return view('bons-commande.show', compact('bonCommande'));
    }

    /**
     * Marque toutes les pièces du bon de commande comme reçues au garage.
     * Le BC passe au statut "reçu", ce qui débloque l'affectation du mécanicien.
     */
    public function marquerRecu(BonCommande $bonCommande)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->peutGererBonsCommande()) {
            abort(403);
        }

        // Pièces disponibles chez le magasin ET transférées (BT) — même règle que le bouton
        $bonCommande->loadMissing(['lignes', 'bonTransfert']);
        if ($raison = $bonCommande->raisonReceptionImpossible()) {
            return back()->with('error', "Impossible : {$raison}");
        }

        $bonCommande->update(['statut' => 'recu']);
        $bonCommande->lignes()->update(['recu' => true]);

        return back()->with('success', "BC {$bonCommande->numero} — toutes les pièces marquées reçues.");
    }

    /**
     * Bascule le statut de réception d'une ligne individuelle (pièce reçue / non reçue).
     * Si toutes les lignes sont reçues, le BC passe automatiquement à "reçu".
     * Si une ligne est décochée alors que le BC était "reçu", il repasse à "commandé".
     */
    public function marquerLigneRecue(BonCommande $bonCommande, int $ligneId)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->peutGererBonsCommande()) {
            abort(403);
        }

        $ligne = $bonCommande->lignes()->findOrFail($ligneId);

        // Une pièce n'est reçue que disponible chez le magasin ET transférée (BT) ;
        // on peut en revanche toujours la décocher
        if (! $ligne->recu && ($raison = $bonCommande->raisonReceptionImpossible($ligne))) {
            return back()->with('error', "Impossible : {$raison}");
        }

        // Bascule : si la pièce était reçue, elle devient non reçue, et vice-versa
        $ligne->update(['recu' => ! $ligne->recu]);

        // Si toutes les lignes sont maintenant reçues, on ferme le BC
        if ($bonCommande->lignes()->where('recu', false)->doesntExist()) {
            $bonCommande->update(['statut' => 'recu']);
        }
        // Si une ligne est décochée alors que le BC était fermé, on le réouvre ;
        // une première pièce reçue fait passer le BC de « En attente » à « Commandé »
        elseif (in_array($bonCommande->statut, ['recu', 'en_attente'], true) && ($bonCommande->statut === 'recu' || $bonCommande->lignes()->where('recu', true)->exists())) {
            $bonCommande->update(['statut' => 'commande']);
        }

        return back()->with('success', 'Statut de la pièce mis à jour.');
    }

    /**
     * Correction administrateur des lignes d'un bon de commande — même s'il est
     * déjà « Tout reçu ». Les changements sont reportés sur la ligne du devis
     * correspondante (désignation, référence, quantité, prix, disponibilité),
     * sauf si l'OR est déjà facturé (le devis reste alors figé).
     */
    public function corriger(Request $request, BonCommande $bonCommande)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'lignes'                 => ['required', 'array'],
            'lignes.*.designation'   => ['required', 'string', 'max:255'],
            'lignes.*.reference'     => ['nullable', 'string', 'max:100'],
            'lignes.*.quantite'      => ['required', 'numeric', 'min:0.01'],
            'lignes.*.disponible'    => ['nullable', 'in:0,1'],
            'lignes.*.prix_unitaire' => ['nullable', 'numeric', 'min:0'],
            'lignes.*.note'          => ['nullable', 'string', 'max:255'],
        ], [
            'lignes.*.designation.required' => 'La désignation est obligatoire pour chaque pièce.',
            'lignes.*.quantite.min'         => 'La quantité doit être supérieure à zéro.',
        ]);

        $bonCommande->load('lignes.ligneDevis.devis.ordreReparation.facture');
        $devisFige = false;

        foreach ($request->input('lignes') as $id => $valeurs) {
            $ligne = $bonCommande->lignes->firstWhere('id', (int) $id);
            if (! $ligne) continue;

            $disponible = ($valeurs['disponible'] ?? '') === '' ? null : (bool) $valeurs['disponible'];
            $ligne->update([
                'designation'   => $valeurs['designation'],
                'reference'     => $valeurs['reference'] ?? null,
                'quantite'      => $valeurs['quantite'],
                'disponible'    => $disponible,
                'prix_unitaire' => ($valeurs['prix_unitaire'] ?? '') === '' ? null : $valeurs['prix_unitaire'],
                'note'          => $valeurs['note'] ?? null,
                'recu'          => ! empty($valeurs['recu']),
            ]);

            $ligneDevis = $ligne->ligneDevis;
            if (! $ligneDevis) continue;
            if ($ligneDevis->devis->estFige()) {
                $devisFige = true;
                continue;
            }

            $remise = (float) ($ligneDevis->remise ?? 0);
            $ligneDevis->update([
                'designation' => $ligne->designation,
                'reference'   => $ligne->reference,
                'quantite'    => $ligne->quantite,
                'total_ht'    => round((float) $ligne->quantite * (float) $ligneDevis->prix_unitaire * (1 - $remise / 100), 2),
            ]);
            // Prix et disponibilité reportés comme pour une réponse du fournisseur (recalcule le devis)
            $ligne->refresh()->propagerVersDevis();
        }

        // Statut du BC d'après les lignes : tout reçu → « Reçu », sinon rouvert s'il l'était
        $bonCommande->load('lignes');
        if ($bonCommande->lignes->isNotEmpty() && $bonCommande->lignes->every(fn ($l) => $l->recu)) {
            $bonCommande->update(['statut' => 'recu']);
        } elseif ($bonCommande->statut === 'recu') {
            $bonCommande->update(['statut' => 'commande']);
        }

        Activite::journaliser('corriger_bon_commande', "Correction administrateur du bon de commande {$bonCommande->numero}", $bonCommande);

        return back()->with('success', "BC {$bonCommande->numero} corrigé."
            . ($devisFige ? ' Le devis n\'a pas été modifié : l\'OR est déjà facturé.' : ' Le devis a été mis à jour.'));
    }

    /**
     * Rouvre un bon de commande « Tout reçu » (administrateur) : repasse en
     * « Commandé » et toutes ses pièces redeviennent « en attente ».
     */
    public function rouvrir(BonCommande $bonCommande)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $bonCommande->update(['statut' => 'commande']);
        $bonCommande->lignes()->update(['recu' => false]);

        Activite::journaliser('rouvrir_bon_commande', "Réouverture par l'administrateur du bon de commande {$bonCommande->numero}", $bonCommande);

        return back()->with('success', "BC {$bonCommande->numero} rouvert — pièces repassées « en attente ».");
    }

    /**
     * Supprime un bon de commande (administrateur). Les lignes du devis restent ;
     * la feuille de travail concernée n'attend plus ces pièces.
     */
    public function supprimer(BonCommande $bonCommande)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $numero = $bonCommande->numero;
        $devis  = $bonCommande->devis;
        $bonCommande->delete();

        Activite::journaliser('supprimer_bon_commande', "Suppression par l'administrateur du bon de commande {$numero}", $devis);

        return $devis
            ? redirect()->route('devis.show', $devis)->with('success', "BC {$numero} supprimé.")
            : redirect()->route('bons-commande.index')->with('success', "BC {$numero} supprimé.");
    }

    /**
     * Joint à la main le bon de transfert du magasin (numéro + scan ou photo
     * du BT papier) — quand il n'est pas arrivé automatiquement. Un seul BT
     * par bon de commande : il remplace le précédent.
     */
    public function enregistrerBonTransfert(Request $request, BonCommande $bonCommande)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->peutGererBonsCommande()) {
            abort(403);
        }

        $existant = $bonCommande->bonTransfert;
        // Fichier obligatoire s'il n'y en a pas encore, ou si c'est un autre BT (autre numéro)
        $fichierRequis = ! $existant?->fichier_chemin || trim((string) $request->input('numero')) !== $existant->numero;

        $data = $request->validate([
            'numero'         => ['required', 'string', 'max:50'],
            'date_transfert' => ['nullable', 'date'],
            'fichier'        => [$fichierRequis ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ], [
            'numero.required'  => 'Indiquez le numéro du bon de transfert.',
            'fichier.required' => 'Joignez le scan ou la photo du bon de transfert (obligatoire pour un nouveau BT).',
            'fichier.mimes'    => 'Le fichier doit être un PDF, JPG ou PNG.',
            'fichier.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        $existant?->oublierFichierSiAutreNumero($data['numero']);

        $bt = BonTransfert::updateOrCreate(
            ['bon_commande_id' => $bonCommande->id],
            [
                'numero'         => $data['numero'],
                'date_transfert' => $data['date_transfert'] ?? now()->toDateString(),
                'notes'          => $data['notes'] ?? null,
                'source'         => $existant?->source === 'magasin' ? 'magasin' : 'manuel',
                'saisi_par'      => $user->id,
            ]
        );
        $bt->remplacerFichier($request->file('fichier'));

        Activite::journaliser('bon_transfert_saisi', "Bon de transfert {$bt->numero} joint au {$bonCommande->numero}", $bonCommande);

        return back()->with('success', "Bon de transfert {$bt->numero} enregistré.");
    }

    /**
     * Ouvre le bon de transfert : le fichier joint s'il y en a un, sinon une
     * page imprimable construite à partir des données reçues du magasin.
     */
    public function voirBonTransfert(BonCommande $bonCommande)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->peutVoirBonsCommande()) {
            abort(403);
        }

        $bt = $bonCommande->bonTransfert;
        if (! $bt) {
            abort(404);
        }
        if ($bt->fichier_url) {
            return redirect($bt->fichier_url);
        }

        $bonCommande->load(['ordreReparation.vehicule', 'ordreReparation.client', 'dossier.vehicule', 'dossier.client', 'lignes']);

        return view('bons-commande.bon-transfert', ['bc' => $bonCommande, 'bt' => $bt]);
    }
}
