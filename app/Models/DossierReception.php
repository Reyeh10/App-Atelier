<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PhotoOr;

/**
 * Modèle DossierReception.
 *
 * Couvre le parcours Réception → Diagnostic → Devis, avant que l'Ordre de
 * Réparation (OR) n'existe. Le dossier est créé dès l'arrivée du véhicule ;
 * pour Panne/Accident, le type précis (électrique/mécanique/garantie) n'est
 * choisi qu'au diagnostic, après réception (cf. DossierReceptionController::diagnostiquer).
 * L'OR n'est généré qu'une fois le devis accepté (méthode creerOrDepuisDossier()),
 * ce qui déclenche le lancement des travaux — sauf pour la Garantie, qui crée
 * l'OR dès le diagnostic (pas de devis client, workflow d'approbation garantie
 * dédié déjà existant sur l'OR : statut_garantie, changerStatutGarantie()).
 *
 * Cycle de vie des statuts :
 *   nouveau → diagnostic → devis_en_cours → transforme_en_or
 *   (ou en_attente_client / annule à tout moment avant transformation)
 */
class DossierReception extends Model
{
    protected $table = 'dossiers_reception';

    protected $fillable = [
        'numero', 'client_id', 'assurance_id', 'numero_sinistre', 'vehicule_id', 'conseiller_id', 'reservation_id',
        'motif_visite', 'type_panne', 'canal_service', 'service_cle', 'canal_arrivee',
        'type_moteur_id', 'entretien_km_seuil',
        'kilometrage_entree', 'niveau_carburant', 'proprete_interne', 'proprete_externe',
        'etat_exterieur', 'motif_entree', 'accessoires_presents', 'liste_accessoires',
        'equipements', 'dommages_carrosserie', 'signature_client', 'photos', 'fiche_signee',
        'date_entree', 'heure_entree', 'urgence', 'notes_internes',
        'statut', 'or_id',
    ];

    protected $casts = [
        'date_entree'           => 'date',
        'accessoires_presents'  => 'boolean',
        'signature_client'      => 'boolean',
        'equipements'           => 'array',
        'dommages_carrosserie'  => 'array',
        'photos'                => 'array',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function client(): BelongsTo         { return $this->belongsTo(Client::class); }
    public function vehicule(): BelongsTo        { return $this->belongsTo(Vehicule::class); }
    public function conseiller(): BelongsTo      { return $this->belongsTo(User::class, 'conseiller_id'); }
    public function reservation(): BelongsTo     { return $this->belongsTo(Reservation::class); }
    public function ordreReparation(): BelongsTo { return $this->belongsTo(OrdreReparation::class, 'or_id'); }

    /** Devis établis pour ce dossier, avant transformation en OR */
    /** Assurance qui paie (accident) — client de type « assurance » ; le véhicule reste au client */
    public function assurance(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'assurance_id');
    }

    public function devis(): HasMany { return $this->hasMany(Devis::class, 'dossier_id')->orderBy('id'); }

    /** Dernier devis établi pour ce dossier */
    public function dernierDevis(): ?Devis { return $this->devis()->latest('id')->first(); }

    // ── Transformation en OR ──────────────────────────────────────────

    /**
     * Crée l'Ordre de Réparation à partir des informations de ce dossier
     * (devis accepté = lancement des travaux). Reprend la même logique
     * que l'ancienne création directe d'OR : numéro auto, statut "ouvert",
     * mise à jour du kilométrage véhicule.
     *
     * N'est appelée qu'une fois par dossier — si un OR existe déjà, on le retourne.
     */
    public function creerOrDepuisDossier(bool $serviceGratuit = false): OrdreReparation
    {
        if ($this->or_id) {
            return $this->ordreReparation;
        }

        [$type, $service] = $this->resoudreTypeEtService();

        $or = OrdreReparation::create([
            'numero'                => OrdreReparation::genererNumero(),
            'client_id'             => $this->client_id,
            // Véhicule du parc leasing : pas de facture, pièces reprises dans le récap
            'parc_leasing'          => VehiculeLeasing::contient($this->vehicule?->immatriculation) || (bool) $this->vehicule?->leasing_tiers,
            'assurance_id'          => $this->assurance_id,
            'numero_sinistre'       => $this->numero_sinistre,
            'vehicule_id'           => $this->vehicule_id,
            'conseiller_id'         => $this->conseiller_id,
            'type'                  => $type,
            'service'               => $service,
            'service_gratuit'       => $serviceGratuit,
            'statut'                => 'ouvert',
            // Les OR de garantie démarrent avec une décision en attente, comme
            // l'ancien formulaire direct — workflow inchangé (changerStatutGarantie()).
            'statut_garantie'       => $type === 'garantie' ? 'en_attente' : null,
            'kilometrage_entree'    => $this->kilometrage_entree,
            'entretien_km_seuil'    => $this->entretien_km_seuil,
            'niveau_carburant'      => $this->niveau_carburant,
            // Colonnes NOT NULL côté OR — repli sur 'bon' si jamais absentes du dossier
            'proprete_interne'      => $this->proprete_interne ?? 'bon',
            'proprete_externe'      => $this->proprete_externe ?? 'bon',
            'etat_exterieur'        => $this->etat_exterieur,
            'motif_entree'          => $this->motif_entree,
            'accessoires_presents'  => $this->accessoires_presents,
            'liste_accessoires'     => $this->liste_accessoires,
            'equipements'           => $this->equipements,
            'dommages_carrosserie'  => $this->dommages_carrosserie,
            'signature_client'      => $this->signature_client,
            'date_entree'           => $this->date_entree,
            'heure_entree'          => $this->heure_entree,
            'urgence'               => $this->urgence,
            'notes_internes'        => $this->notes_internes,
            // Fiche de réception signée déjà uploadée sur le dossier (avant que l'OR
            // n'existe) — reprise telle quelle pour ne pas la faire ré-uploader.
            'fiche_signee'          => $this->fiche_signee,
        ]);

        $or->vehicule->update(array_filter([
            'kilometrage'    => $this->kilometrage_entree,
            'type_moteur_id' => $this->type_moteur_id,
        ], fn ($v) => $v !== null));

        // Reprend les photos prises à la réception (avant que l'OR n'existe) sur l'OR nouvellement créé
        foreach ($this->photos ?? [] as $photo) {
            PhotoOr::create([
                'or_id'        => $or->id,
                'chemin'       => $photo['chemin'],
                'nom_original' => $photo['nom_original'] ?? null,
                'taille'       => $photo['taille'] ?? null,
            ]);
        }

        $this->update(['or_id' => $or->id, 'statut' => 'transforme_en_or']);

        Activite::journaliser('creer_or', "Création de l'OR {$or->numero} depuis le dossier {$this->numero} — {$or->client->nom_complet} / {$or->vehicule->immatriculation}", $or);

        if ($or->type === 'garantie') {
            NotificationInterne::notifierResponsablesGarantie(
                titre: "Nouvelle demande de garantie — {$or->numero}",
                corps: "Véhicule : {$or->vehicule->immatriculation} ({$or->vehicule->marque} {$or->vehicule->modele})\nClient : {$or->client->nom_complet}\nMotif : {$or->motif_entree}",
                orId: $or->id
            );
        }

        return $or;
    }

    /**
     * Déduit le type et le service de l'OR à partir de l'arbre de décision
     * du dossier (motif_visite / type_panne / canal_service).
     */
    private function resoudreTypeEtService(): array
    {
        return match($this->motif_visite) {
            'panne'          => match($this->type_panne) {
                'electrique' => ['normal', 'electricite'],
                'mecanique'  => ['normal', 'mecanique'],
                'garantie'   => ['garantie', null],
                default      => ['normal', null],
            },
            'accident'       => ['sinistre', null],
            'service_rapide' => $this->canal_service === 'entretien_periodique'
                ? ['entretien', 'rapide']
                : ['normal', 'rapide'],
            default => ['normal', null],
        };
    }

    // ── Labels et couleurs pour l'affichage ────────────────────────────

    public function getMotifVisiteLabel(): string
    {
        return match($this->motif_visite) {
            'panne'          => 'Panne',
            'accident'       => 'Accident',
            'service_rapide' => 'Service Rapide',
            default          => $this->motif_visite,
        };
    }

    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'nouveau'           => 'Nouveau',
            'diagnostic'        => 'Diagnostic',
            'devis_en_cours'    => 'Devis en cours',
            'transforme_en_or'  => 'Transformé en OR',
            'en_attente_client' => 'En attente du client',
            'annule'            => 'Annulé',
            default             => $this->statut,
        };
    }

    public function getUrgenceLabel(): string
    {
        return match($this->urgence) {
            'normal'      => 'Normal',
            'urgent'      => 'Urgent',
            'tres_urgent' => 'Très urgent',
            default       => 'Normal',
        };
    }

    public function getStatutColor(): string
    {
        return match($this->statut) {
            'nouveau'           => 'blue',
            'diagnostic'        => 'yellow',
            'devis_en_cours'    => 'orange',
            'transforme_en_or'  => 'green',
            'en_attente_client' => 'gray',
            'annule'            => 'red',
            default             => 'gray',
        };
    }

    /**
     * Génère un numéro de dossier séquentiel au format DR-AAAA-XXXX.
     * La séquence repart à 1 chaque année.
     */
    public static function genererNumero(): string
    {
        $annee   = now()->year;
        $dernier = self::whereYear('created_at', $annee)->max('numero');
        $sequence = $dernier ? (int) substr($dernier, -4) + 1 : 1;
        return sprintf('DR-%d-%04d', $annee, $sequence);
    }
}
