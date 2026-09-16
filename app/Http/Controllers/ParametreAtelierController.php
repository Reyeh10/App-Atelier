<?php

namespace App\Http\Controllers;

use App\Models\HoraireAtelier;
use App\Models\MarqueGarantie;
use App\Models\ParametreAtelier;
use App\Models\PauseAtelier;
use App\Models\ServiceRapide;
use Illuminate\Http\Request;

class ParametreAtelierController extends Controller
{
    // Ordre d'affichage : semaine djiboutienne Sam → Ven
    private const ORDRE_JOURS = [6, 0, 1, 2, 3, 4, 5];

    public function index()
    {
        $horaires        = HoraireAtelier::all()->keyBy('jour');
        $pauses          = PauseAtelier::orderBy('heure_debut')->get()->groupBy('jour');
        $parametreAtelier = ParametreAtelier::get();
        $marquesGarantie  = MarqueGarantie::orderBy('nom')->get();
        $servicesRapides  = ServiceRapide::orderBy('nom')->get();

        return view('parametres.index', [
            'horaires'         => $horaires,
            'pauses'           => $pauses,
            'ordreJours'       => self::ORDRE_JOURS,
            'parametreAtelier' => $parametreAtelier,
            'marquesGarantie'  => $marquesGarantie,
            'servicesRapides'  => $servicesRapides,
        ]);
    }

    /**
     * Met à jour la capacité Service Rapide : nombre de véhicules pouvant
     * être pris en charge EN MÊME TEMPS (postes disponibles), utilisée pour
     * le planning des réservations et le contrôle "Sans RDV" à la réception.
     */
    public function updateCapacite(Request $request)
    {
        $data = $request->validate([
            'capacite_service_rapide_simultanee' => ['nullable', 'integer', 'min:1'],
        ], [
            'capacite_service_rapide_simultanee.integer' => 'La capacité doit être un nombre entier.',
            'capacite_service_rapide_simultanee.min'     => 'La capacité doit être d\'au moins 1 véhicule.',
        ]);

        ParametreAtelier::get()->update([
            'capacite_service_rapide_simultanee' => $data['capacite_service_rapide_simultanee'] ?: null,
        ]);

        return back()->with('success', 'Capacité Service Rapide mise à jour.');
    }

    /**
     * Met à jour les tarifs fixes de main d'œuvre Service Rapide (chaque
     * service du catalogue + l'entretien périodique) — utilisés pour générer
     * automatiquement le devis à la réception (cf. DossierReceptionController).
     */
    public function updateTarifs(Request $request)
    {
        $data = $request->validate([
            'tarifs'   => ['required', 'array'],
            'tarifs.*' => ['nullable', 'numeric', 'min:0'],
        ], [
            'tarifs.*.numeric' => 'Le tarif doit être un nombre.',
            'tarifs.*.min'     => 'Le tarif ne peut pas être négatif.',
        ]);

        ParametreAtelier::get()->update(['tarifs_service_rapide' => $data['tarifs']]);

        return back()->with('success', 'Tarifs Service Rapide mis à jour.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'jours'                    => ['required', 'array'],
            'jours.*.actif'            => ['nullable'],
            'jours.*.heure_debut'      => ['nullable', 'date_format:H:i'],
            'jours.*.heure_fin'        => ['nullable', 'date_format:H:i'],
        ], [
            'jours.*.heure_debut.date_format' => 'Format attendu : HH:MM.',
            'jours.*.heure_fin.date_format'   => 'Format attendu : HH:MM.',
        ]);

        foreach ($data['jours'] as $jour => $values) {
            $actif     = isset($values['actif']);
            $heureDebut = $actif ? ($values['heure_debut'] ?? null) : null;
            $heureFin   = $actif ? ($values['heure_fin']   ?? null) : null;

            // Vérification heure_fin > heure_debut si les deux sont renseignés
            if ($heureDebut && $heureFin && $heureFin <= $heureDebut) {
                return back()
                    ->withInput()
                    ->with('error', 'L\'heure de fin doit être après l\'heure de début pour chaque jour.');
            }

            HoraireAtelier::where('jour', (int) $jour)->update([
                'actif'       => $actif,
                'heure_debut' => $heureDebut,
                'heure_fin'   => $heureFin,
            ]);
        }

        return back()->with('success', 'Horaires de l\'atelier enregistrés.');
    }

    public function storePause(Request $request)
    {
        $request->validate([
            'jour'        => ['required', 'integer', 'between:0,6'],
            'nom'         => ['required', 'string', 'max:100'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin'   => ['required', 'date_format:H:i', 'after:heure_debut'],
        ], [
            'jour.required'           => 'Sélectionnez le jour.',
            'nom.required'            => 'Le nom de la pause est obligatoire.',
            'heure_debut.date_format' => 'Format attendu : HH:MM.',
            'heure_fin.date_format'   => 'Format attendu : HH:MM.',
            'heure_fin.after'         => 'L\'heure de fin doit être après l\'heure de début.',
        ]);

        PauseAtelier::create([
            'jour'        => (int) $request->jour,
            'nom'         => $request->nom,
            'heure_debut' => $request->heure_debut,
            'heure_fin'   => $request->heure_fin,
            'actif'       => true,
        ]);

        return back()->with('success', 'Pause ajoutée.');
    }

    public function togglePause(PauseAtelier $pause)
    {
        $pause->update(['actif' => !$pause->actif]);
        $label = $pause->actif ? 'activée' : 'désactivée';
        return back()->with('success', 'Pause « ' . $pause->nom . ' » ' . $label . '.');
    }

    public function destroyPause(PauseAtelier $pause)
    {
        $nom = $pause->nom;
        $pause->delete();
        return back()->with('success', 'Pause « ' . $nom . ' » supprimée.');
    }

    /**
     * Crée un nouveau compte de facturation garantie constructeur (ex: GWM).
     * Le nom doit correspondre exactement à la marque saisie sur les véhicules
     * (comparaison insensible à la casse/espaces au moment de facturer).
     */
    public function storeMarqueGarantie(Request $request)
    {
        $data = $request->validate([
            'nom'            => ['required', 'string', 'max:100', 'unique:marques_garantie,nom'],
            'plafond_credit' => ['required', 'numeric', 'min:0'],
        ], [
            'nom.required'            => 'Le nom de la marque est obligatoire.',
            'nom.unique'              => 'Cette marque a déjà un compte garantie.',
            'plafond_credit.required' => 'Le plafond de crédit est obligatoire.',
            'plafond_credit.numeric'  => 'Le plafond doit être un nombre.',
            'plafond_credit.min'      => 'Le plafond ne peut pas être négatif.',
        ]);

        MarqueGarantie::create($data + ['actif' => true]);

        return back()->with('success', "Compte garantie « {$data['nom']} » créé.");
    }

    public function updateMarqueGarantie(Request $request, MarqueGarantie $marqueGarantie)
    {
        $data = $request->validate([
            'plafond_credit' => ['required', 'numeric', 'min:0'],
        ], [
            'plafond_credit.required' => 'Le plafond de crédit est obligatoire.',
            'plafond_credit.numeric'  => 'Le plafond doit être un nombre.',
            'plafond_credit.min'      => 'Le plafond ne peut pas être négatif.',
        ]);

        $marqueGarantie->update($data);

        return back()->with('success', "Plafond « {$marqueGarantie->nom} » mis à jour.");
    }

    public function toggleMarqueGarantie(MarqueGarantie $marqueGarantie)
    {
        $marqueGarantie->update(['actif' => ! $marqueGarantie->actif]);
        $etat = $marqueGarantie->actif ? 'activé' : 'désactivé';
        return back()->with('success', "Compte « {$marqueGarantie->nom} » {$etat}.");
    }

    public function destroyMarqueGarantie(MarqueGarantie $marqueGarantie)
    {
        if ($marqueGarantie->factures()->exists()) {
            return back()->with('error', "Impossible de supprimer « {$marqueGarantie->nom} » : des factures y sont déjà rattachées — désactivez-le plutôt.");
        }

        $nom = $marqueGarantie->nom;
        $marqueGarantie->delete();

        return back()->with('success', "Compte garantie « {$nom} » supprimé.");
    }

    /**
     * Crée un nouveau service du catalogue Service Rapide (ex: "Lavage moteur"),
     * avec son propre tarif de main d'œuvre et sa propre capacité (postes
     * simultanés) — remplace l'ancien catalogue figé config/services_rapides.php.
     */
    public function storeServiceRapide(Request $request)
    {
        $data = $request->validate([
            'nom'                 => ['required', 'string', 'max:150', 'unique:services_rapides,nom'],
            'duree_min'           => ['required', 'integer', 'min:1'],
            'duree_max'           => ['required', 'integer', 'min:1', 'gte:duree_min'],
            'tarif'               => ['required', 'numeric', 'min:0'],
            'capacite_simultanee' => ['nullable', 'integer', 'min:1'],
        ], [
            'nom.required'       => 'Le nom du service est obligatoire.',
            'nom.unique'         => 'Un service porte déjà ce nom.',
            'duree_min.required' => 'La durée minimum est obligatoire.',
            'duree_max.required' => 'La durée maximum est obligatoire.',
            'duree_max.gte'      => 'La durée maximum doit être supérieure ou égale à la durée minimum.',
            'tarif.required'     => 'Le tarif est obligatoire.',
            'tarif.numeric'      => 'Le tarif doit être un nombre.',
            'tarif.min'          => 'Le tarif ne peut pas être négatif.',
        ]);

        ServiceRapide::create($data + ['actif' => true]);

        return back()->with('success', "Service « {$data['nom']} » ajouté au catalogue.");
    }

    public function updateServiceRapide(Request $request, ServiceRapide $serviceRapide)
    {
        $data = $request->validate([
            'nom'                 => ['required', 'string', 'max:150', 'unique:services_rapides,nom,' . $serviceRapide->id],
            'duree_min'           => ['required', 'integer', 'min:1'],
            'duree_max'           => ['required', 'integer', 'min:1', 'gte:duree_min'],
            'tarif'               => ['required', 'numeric', 'min:0'],
            'capacite_simultanee' => ['nullable', 'integer', 'min:1'],
        ], [
            'nom.required'       => 'Le nom du service est obligatoire.',
            'nom.unique'         => 'Un service porte déjà ce nom.',
            'duree_min.required' => 'La durée minimum est obligatoire.',
            'duree_max.required' => 'La durée maximum est obligatoire.',
            'duree_max.gte'      => 'La durée maximum doit être supérieure ou égale à la durée minimum.',
            'tarif.required'     => 'Le tarif est obligatoire.',
            'tarif.numeric'      => 'Le tarif doit être un nombre.',
            'tarif.min'          => 'Le tarif ne peut pas être négatif.',
        ]);

        $serviceRapide->update($data);

        return back()->with('success', "Service « {$serviceRapide->nom} » mis à jour.");
    }

    public function toggleServiceRapide(ServiceRapide $serviceRapide)
    {
        $serviceRapide->update(['actif' => ! $serviceRapide->actif]);
        $etat = $serviceRapide->actif ? 'activé' : 'désactivé';
        return back()->with('success', "Service « {$serviceRapide->nom} » {$etat}.");
    }

    public function destroyServiceRapide(ServiceRapide $serviceRapide)
    {
        if ($serviceRapide->estUtilise()) {
            return back()->with('error', "Impossible de supprimer « {$serviceRapide->nom} » : des réservations ou dossiers de réception y sont déjà rattachés — désactivez-le plutôt.");
        }

        $nom = $serviceRapide->nom;
        $serviceRapide->delete();

        return back()->with('success', "Service « {$nom} » supprimé du catalogue.");
    }
}
