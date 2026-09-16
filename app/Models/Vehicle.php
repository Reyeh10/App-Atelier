<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle Vehicule.
 *
 * Représente la fiche technique d'un véhicule client.
 * Contient les informations d'identification (immatriculation, VIN),
 * les caractéristiques techniques et le suivi administratif (assurance, vignette, garantie).
 *
 * Le kilométrage est mis à jour automatiquement à chaque entrée en atelier.
 */
class Vehicule extends Model
{
    protected $fillable = [
        'client_id', 'immatriculation', 'vin', 'marque', 'modele', 'version', 'categorie',
        'annee', 'couleur', 'motorisation', 'type_moteur_id', 'cylindree', 'puissance_fiscale',
        'kilometrage', 'date_mise_circulation', 'date_expiration_assurance',
        'date_expiration_vignette', 'sous_garantie', 'fin_garantie', 'garantie_sortie_le',
        'garantie_couverture', 'notes',
    ];

    protected $casts = [
        'date_mise_circulation'     => 'date',
        'date_expiration_assurance' => 'date',
        'date_expiration_vignette'  => 'date',
        'fin_garantie'              => 'date',
        'garantie_sortie_le'        => 'date',
        'sous_garantie'             => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    /** Client propriétaire du véhicule */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Tous les ordres de réparation pour ce véhicule (historique complet) */
    public function ordresReparations(): HasMany
    {
        return $this->hasMany(OrdreReparation::class);
    }

    /** Type de moteur catalogué (pour le barème d'entretien automatique) */
    public function typeMoteur(): BelongsTo
    {
        return $this->belongsTo(TypeMoteur::class);
    }

    // ── Attributs calculés ─────────────────────────────────────────────

    /**
     * Désignation courte du véhicule pour l'affichage dans les listes.
     * Exemple : "Toyota Hilux (2021)"
     * Accessible via $vehicule->designation.
     */
    public function getDesignationAttribute(): string
    {
        return "{$this->marque} {$this->modele}" . ($this->annee ? " ({$this->annee})" : '');
    }

    // ── Labels ─────────────────────────────────────────────────────────

    /** Retourne le libellé français du type de motorisation */
    public function getMotorisationLabel(): string
    {
        return match($this->motorisation) {
            'essence'    => 'Essence',
            'diesel'     => 'Diesel',
            'hybride'    => 'Hybride',
            'electrique' => 'Électrique',
            'gpl'        => 'GPL',
            default      => 'Autre',
        };
    }

    // ── Alertes administratives ────────────────────────────────────────

    /**
     * Indique si l'assurance du véhicule est expirée (date dépassée).
     * Retourne false si la date n'est pas renseignée.
     */
    public function isAssuranceExpiree(): bool
    {
        return $this->date_expiration_assurance && $this->date_expiration_assurance->isPast();
    }

    /**
     * Indique si la vignette du véhicule est expirée (date dépassée).
     * Retourne false si la date n'est pas renseignée.
     */
    public function isVignetteExpiree(): bool
    {
        return $this->date_expiration_vignette && $this->date_expiration_vignette->isPast();
    }

    // ── Éligibilité garantie (pannes) ──────────────────────────────────

    // Limite d'âge (années depuis la mise en circulation) par catégorie
    private const LIMITE_AGE_ANS = ['pick-up' => 3, 'suv' => 5];
    // Limite de kilométrage par catégorie
    private const LIMITE_KM = ['pick-up' => 100000, 'suv' => 150000];

    /**
     * Limite de kilométrage garantie applicable à la catégorie de ce véhicule,
     * ou null si sa catégorie n'a pas de limite connue. Exposée séparément
     * (plutôt que de garder LIMITE_KM privée) pour que le formulaire de
     * réception puisse revérifier l'éligibilité en direct pendant la saisie
     * du kilométrage du jour, sans dupliquer les seuils côté JS.
     */
    public function getLimiteKmGarantieAttribute(): ?int
    {
        return self::LIMITE_KM[$this->categorie] ?? null;
    }

    /**
     * Limite d'âge (en années) par catégorie, exposée publiquement pour que
     * le formulaire véhicule puisse en déduire automatiquement la date de fin
     * de garantie à la saisie, sans dupliquer les seuils côté JS.
     */
    public static function limitesAgeGarantieParCategorie(): array
    {
        return self::LIMITE_AGE_ANS;
    }

    /**
     * Un véhicule est éligible au circuit garantie (diagnostic pris en charge
     * par l'équipe garantie, pas le chef de garage) si TOUTES ces conditions
     * sont réunies :
     *   - déclaré sous garantie à sa création (sous_garantie=true) — ce choix
     *     est définitif : un véhicule créé sans garantie ne peut plus jamais
     *     y être éligible, quoi qu'il arrive ensuite ;
     *   - jamais marqué "sorti de garantie" par l'équipe garantie
     *     (garantie_sortie_le null) — décision elle aussi définitive ;
     *   - dans la limite d'âge ET de kilométrage de sa catégorie si elle est
     *     connue : pick-up 3 ans / 100 000 km, SUV 5 ans / 150 000 km
     *     (calculés depuis date_mise_circulation / kilometrage). Dès que
     *     l'une des deux limites est dépassée, le véhicule sort
     *     automatiquement de la garantie. Les autres catégories (ou
     *     catégorie/donnée inconnue) n'ont pas de limite appliquée.
     * Si le véhicule n'est pas éligible, le diagnostic d'une panne revient
     * au chef de garage (parcours normal mécanique/électrique).
     */
    public function estEligibleGarantie(): bool
    {
        return $this->motifSortieGarantie() === null;
    }

    /**
     * Raison pour laquelle ce véhicule n'est plus éligible au circuit
     * garantie, ou null s'il l'est toujours. Utilisé pour afficher un message
     * précis à la réception plutôt qu'un simple "non éligible".
     *
     * @return 'jamais_sous_garantie'|'sortie_manuelle'|'limite_age'|'limite_km'|null
     */
    public function motifSortieGarantie(): ?string
    {
        if (! $this->sous_garantie) return 'jamais_sous_garantie';
        if ($this->garantie_sortie_le) return 'sortie_manuelle';

        if ($this->categorie && isset(self::LIMITE_AGE_ANS[$this->categorie]) && $this->date_mise_circulation) {
            $ans = $this->date_mise_circulation->diffInYears(now());
            if ($ans > self::LIMITE_AGE_ANS[$this->categorie]) return 'limite_age';
        }
        if ($this->categorie && isset(self::LIMITE_KM[$this->categorie]) && $this->kilometrage >= self::LIMITE_KM[$this->categorie]) {
            return 'limite_km';
        }

        return null;
    }

    /** Libellé français de la raison de sortie de garantie (cf. motifSortieGarantie()), ou null si toujours éligible. */
    public function getMotifSortieGarantieLabel(): ?string
    {
        return match($this->motifSortieGarantie()) {
            'sortie_manuelle' => 'signalé définitivement sorti de la garantie le ' . $this->garantie_sortie_le->format('d/m/Y'),
            'limite_age'      => "limite d'âge de sa catégorie dépassée (" . self::LIMITE_AGE_ANS[$this->categorie] . ' ans depuis la mise en circulation)',
            'limite_km'       => 'limite de kilométrage de sa catégorie dépassée (' . number_format(self::LIMITE_KM[$this->categorie], 0, ',', ' ') . ' km)',
            default           => null,
        };
    }
}
