<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\PhotoOr;
use App\Models\BonCommande;

/**
 * Modèle OrdreReparation.
 *
 * L'OR est le document central de l'atelier. Il est créé à l'entrée du véhicule
 * et suit toute la vie du dossier jusqu'à la restitution au client.
 *
 * Cycle de vie des statuts :
 *   ouvert → diagnostic → devis_envoye → devis_accepte → en_cours
 *   → controle_qualite → lavage → pret → facture → livre
 *   (annule possible à tout moment)
 */
class OrdreReparation extends Model
{
    protected $table = 'ordres_reparations';

    protected $fillable = [
        // Identification et affectation
        'numero', 'client_id', 'vehicule_id', 'conseiller_id', 'technicien_id',
        'service', 'service_gratuit', 'date_affectation', 'chef_id',
        'controle_qualite_technicien_id',
        // Statut et type
        'type', 'statut', 'statut_garantie', 'motif_refus_garantie', 'motif_approbation_garantie',
        // État du véhicule à l'entrée
        'kilometrage_entree', 'entretien_km_seuil', 'niveau_carburant', 'proprete_interne', 'proprete_externe',
        'etat_exterieur', 'motif_entree', 'accessoires_presents', 'liste_accessoires',
        'equipements', 'dommages_carrosserie', 'signature_client',
        // Dates et planning
        'date_entree', 'heure_entree', 'date_sortie_prevue', 'date_sortie_reelle',
        'urgence', 'notes_internes', 'fiche_signee',
        // Pointage des travaux
        'heure_debut_travaux', 'heure_fin_travaux', 'duree_estimee',
        // État du véhicule à la sortie (restitution)
        'kilometrage_sortie', 'niveau_carburant_sortie',
        'proprete_interne_sortie', 'proprete_externe_sortie',
        'equipements_sortie', 'dommages_carrosserie_sortie', 'notes_restitution', 'signature_restitution',
        'fiche_signee_restitution', 'restitue_par_id',
    ];

    protected $casts = [
        'date_entree'          => 'date',
        'date_sortie_prevue'   => 'date',
        'date_sortie_reelle'   => 'date',
        'date_affectation'     => 'datetime',
        'heure_debut_travaux'  => 'datetime',
        'heure_fin_travaux'    => 'datetime',
        'accessoires_presents' => 'boolean',
        'signature_client'     => 'boolean',
        'service_gratuit'      => 'boolean',
        'equipements'          => 'array',   // Stocké en JSON, retourné comme tableau PHP
        'dommages_carrosserie' => 'array',   // Idem — zones de carrosserie endommagées
        'equipements_sortie'   => 'array',   // Équipements vérifiés à la restitution
        'dommages_carrosserie_sortie' => 'array', // Zones endommagées constatées à la restitution
    ];

    // ── Relations ──────────────────────────────────────────────────────

    /** Client propriétaire du véhicule */
    public function client(): BelongsTo     { return $this->belongsTo(Client::class); }

    /** Véhicule concerné par cet OR */
    public function vehicule(): BelongsTo   { return $this->belongsTo(Vehicule::class); }

    /** Réceptionniste qui a créé l'OR */
    public function conseiller(): BelongsTo { return $this->belongsTo(User::class, 'conseiller_id'); }

    /** Technicien affecté aux travaux (fiche seule, pas de compte de connexion — cf. Technicien) */
    public function technicien(): BelongsTo { return $this->belongsTo(Technicien::class, 'technicien_id'); }

    /** Technicien ayant réalisé le contrôle qualité (repris sur la feuille de travail imprimée) */
    public function controleQualitePar(): BelongsTo { return $this->belongsTo(Technicien::class, 'controle_qualite_technicien_id'); }

    /** Chef de garage qui a validé l'affectation */
    public function chef(): BelongsTo       { return $this->belongsTo(User::class, 'chef_id'); }

    /** Réceptionniste qui a effectué la restitution du véhicule */
    public function restitueePar(): BelongsTo { return $this->belongsTo(User::class, 'restitue_par_id'); }

    /** Photos de réception originales (ancien système, table photos_reception) */
    public function photos(): HasMany       { return $this->hasMany(PhotoReception::class, 'or_id'); }

    /** Photos du véhicule prises lors de la réception (nouveau système, table photos_or) */
    public function photosOr(): HasMany     { return $this->hasMany(PhotoOr::class, 'or_id'); }

    /** Dernier devis établi pour cet OR */
    public function devis(): HasOne         { return $this->hasOne(Devis::class, 'or_id')->latestOfMany(); }

    /** Tous les devis établis pour cet OR (un OR peut avoir plusieurs devis successifs) */
    public function allDevis(): HasMany     { return $this->hasMany(Devis::class, 'or_id')->orderBy('id'); }

    /** Tous les bons de commande liés à cet OR */
    public function bonsCommande(): HasMany { return $this->hasMany(BonCommande::class, 'or_id'); }

    /** Facture en vigueur de cet OR (une facture annulée par avoir n'en est plus une) */
    public function facture(): HasOne       { return $this->hasOne(Facture::class, 'or_id')->where('statut', '!=', 'annulee'); }

    /** Toutes les factures de l'OR, y compris celles annulées par avoir */
    public function factures(): HasMany     { return $this->hasMany(Facture::class, 'or_id')->orderBy('id'); }

    /** Avoirs émis sur les factures de cet OR */
    public function avoirs(): HasMany       { return $this->hasMany(Avoir::class, 'or_id')->orderBy('id'); }

    /** Dossier de réception dont est issu cet OR (réception → diagnostic → devis) */
    public function dossier(): HasOne       { return $this->hasOne(DossierReception::class, 'or_id'); }

    /**
     * Portée : OR à facturer par la caisse (menu « À facturer ») — véhicule prêt
     * sans facture, ou déjà restitué mais dont la facture a été annulée par
     * avoir (il reste à le refacturer). Un service gratuit n'est jamais facturé.
     */
    public function scopeAFacturer($query)
    {
        return $query->where('service_gratuit', false)
            ->whereDoesntHave('facture')
            ->where(function ($q) {
                $q->where('statut', 'pret')
                  ->orWhere(fn ($q2) => $q2->where('statut', 'livre')->whereHas('factures', fn ($f) => $f->where('statut', 'annulee')));
            });
    }

    /**
     * Même règle que la liste « À facturer » (scopeAFacturer), plus : les pièces de
     * tous les devis acceptés sont reçues au garage (un BC rouvert ou modifié après
     * le BT doit d'abord être reçu de nouveau).
     */
    public function peutEtreFacture(): bool
    {
        return $this->raisonNonFacturable() === null;
    }

    /** Pourquoi l'OR ne peut pas encore être facturé (null s'il peut l'être) */
    public function raisonNonFacturable(): ?string
    {
        if ($this->service_gratuit) {
            return "L'OR {$this->numero} est un service gratuit : il n'est pas facturé.";
        }
        if ($this->facture) {
            return "L'OR {$this->numero} a déjà la facture {$this->facture->numero}.";
        }
        $refacturation = $this->statut === 'livre' && $this->factures()->where('statut', 'annulee')->exists();
        if ($this->statut !== 'pret' && ! $refacturation) {
            return "Impossible de facturer : l'OR {$this->numero} n'est pas prêt (statut « {$this->getStatutLabel()} »). Il doit passer le contrôle qualité et le lavage.";
        }
        foreach ($this->devisAcceptes() as $devis) {
            $bc = $devis->bonCommande;
            if ($bc && $bc->lignes()->exists() && $bc->statut !== 'recu') {
                return "Impossible de facturer : les pièces du bon de commande {$bc->numero} ne sont pas encore reçues au garage.";
            }
        }

        return null;
    }

    /**
     * Le véhicule peut-il être rendu au client ? Même règle que le bouton
     * « Restituer » : facture payée ou crédit accordé (compte client, garantie
     * constructeur), ou service gratuit terminé.
     */
    public function peutEtreRestitue(): bool
    {
        if ($this->statut === 'facture') {
            return (bool) $this->facture?->peutEtreRestitue();
        }

        return $this->service_gratuit && $this->statut === 'pret';
    }

    /** Pourquoi le véhicule ne peut pas encore être restitué (message pour l'utilisateur) */
    public function raisonNonRestituable(): string
    {
        if ($this->statut === 'livre') {
            return "Le véhicule de l'OR {$this->numero} a déjà été restitué.";
        }
        if ($this->statut === 'annule') {
            return "L'OR {$this->numero} est annulé.";
        }
        if ($this->statut === 'facture' && $this->facture) {
            return "Restitution impossible : la facture {$this->facture->numero} n'est pas payée (reste "
                . number_format($this->facture->getMontantRestant(), 0, ',', ' ')
                . " FDJ) et aucun crédit n'est accordé.";
        }
        if ($this->statut === 'pret') {
            return "Restitution impossible : le véhicule doit d'abord être facturé.";
        }

        return "Restitution impossible : les travaux ne sont pas terminés (statut « {$this->getStatutLabel()} »).";
    }

    // ── Étapes des travaux : mêmes règles pour les boutons et le serveur ──

    /**
     * Le véhicule a quitté la phase travaux (contrôle qualité, lavage, prêt,
     * facturé, livré ou annulé) : plus d'affectation ni de pointage possible,
     * sinon un OR déjà facturé pourrait redescendre à « prêt ».
     */
    public function travauxClos(): bool
    {
        return in_array($this->statut, ['controle_qualite', 'lavage', 'pret', 'facture', 'livre', 'annule'], true);
    }

    /** Affectation (ou réaffectation) du technicien de la feuille 1 */
    public function peutEtreAffecte(): bool
    {
        return ! $this->travauxClos()
            && ! ($this->type === 'garantie' && $this->statut_garantie !== 'approuve');
    }

    /** Démarrage des travaux de la feuille 1 : technicien affecté, pièces reçues, pas encore démarré */
    public function peutDemarrerTravaux(): bool
    {
        return $this->isAffecte()
            && ! $this->heure_debut_travaux
            && ! $this->travauxClos()
            && ! $this->estAvantAcceptationDevis()
            && ! $this->bcBloquantFeuille1();
    }

    /** Fin des travaux de la feuille 1 : démarrés, pas encore terminés, véhicule en cours */
    public function peutTerminerTravaux(): bool
    {
        return $this->heure_debut_travaux
            && ! $this->heure_fin_travaux
            && $this->statut === 'en_cours';
    }

    /** Pourquoi une étape des travaux est refusée (message pour l'utilisateur) */
    public function raisonEtapeRefusee(string $etape): string
    {
        if ($this->travauxClos()) {
            return "Impossible de {$etape} : l'OR {$this->numero} n'est plus en travaux (statut « {$this->getStatutLabel()} »).";
        }
        if ($this->type === 'garantie' && $this->statut_garantie !== 'approuve') {
            return "Impossible de {$etape} : la garantie de l'OR {$this->numero} n'est pas encore approuvée.";
        }
        if (! $this->isAffecte()) {
            return "Impossible de {$etape} : aucun technicien n'est affecté à l'OR {$this->numero}.";
        }
        if ($bc = $this->bcBloquantFeuille1()) {
            return "Impossible de {$etape} : le bon de commande {$bc->numero} n'est pas encore marqué « Tout reçu ».";
        }
        if ($this->heure_fin_travaux) {
            return "Impossible de {$etape} : les travaux de l'OR {$this->numero} sont déjà terminés.";
        }
        if ($this->heure_debut_travaux) {
            return "Impossible de {$etape} : les travaux de l'OR {$this->numero} sont déjà démarrés.";
        }

        return "Impossible de {$etape} : les travaux de l'OR {$this->numero} n'ont pas démarré (statut « {$this->getStatutLabel()} »).";
    }

    /**
     * Portée : OR prêts à être physiquement restitués au client — soit facturés
     * avec une facture réglée (payée ou à crédit), soit un service gratuit déjà
     * au statut "pret" (pas de facture à attendre). Utilisée à la fois pour le
     * filtre "Prêts à restituer" et le badge de compteur du menu, pour que les
     * deux restent toujours cohérents entre eux.
     */
    public function scopePretsARestituer($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('statut', 'facture')
                   ->whereHas('facture', fn ($q3) => $q3->where('statut', 'payee')->orWhere('credit_accorde', true));
            })->orWhere(function ($q2) {
                $q2->where('service_gratuit', true)->where('statut', 'pret');
            });
        });
    }

    // ── Calculs de temps et performance ────────────────────────────────

    /**
     * Calcule la durée brute des travaux en heures (temps total écoulé, pauses incluses).
     * Retourne null si l'une des deux heures n'est pas enregistrée.
     */
    public function getDureeReelleHeures(): ?float
    {
        if (!$this->heure_debut_travaux || !$this->heure_fin_travaux) return null;
        return round($this->heure_debut_travaux->diffInMinutes($this->heure_fin_travaux) / 60, 2);
    }

    /**
     * Calcule la durée nette des travaux en heures en excluant les pauses configurées.
     * Si aucune pause n'est configurée, retourne la même valeur que getDureeReelleHeures().
     */
    public function getDureeNetteHeures(): ?float
    {
        if (!$this->heure_debut_travaux || !$this->heure_fin_travaux) return null;
        return \App\Services\HoraireService::calculerDureeNette(
            $this->heure_debut_travaux,
            $this->heure_fin_travaux
        );
    }

    /**
     * Calcule le taux de performance du mécanicien en pourcentage basé sur la durée nette.
     * 100% = durée nette = durée estimée. >100% = plus rapide que prévu.
     * Retourne null si les données nécessaires sont manquantes.
     */
    public function getPerformance(): ?int
    {
        $nette = $this->getDureeNetteHeures();
        if (!$nette || !$this->duree_estimee) return null;
        return (int) round($this->duree_estimee / $nette * 100);
    }

    /**
     * Formate un nombre d'heures en format lisible (ex: 1.5 → "1h30min").
     */
    public function formatDuree(float $heures): string
    {
        $h = (int) $heures;
        $m = (int) round(($heures - $h) * 60);
        return $h > 0 ? "{$h}h" . ($m > 0 ? "{$m}min" : '') : "{$m}min";
    }

    // ── Labels et couleurs pour l'affichage ────────────────────────────

    /** Retourne le libellé français du statut de l'OR */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'ouvert'           => 'Ouvert',
            'diagnostic'       => 'Diagnostic',
            'devis_envoye'     => 'Devis envoyé',
            'devis_accepte'    => 'Devis accepté',
            'en_cours'         => 'En cours',
            'controle_qualite' => 'Contrôle qualité',
            'lavage'           => 'Lavage',
            'pret'             => 'Prêt',
            'facture'          => 'Facturé',
            'livre'            => 'Livré',
            'annule'           => 'Annulé',
            default            => $this->statut,
        };
    }

    /** Retourne la couleur Tailwind associée au statut (pour les badges) */
    public function getStatutColor(): string
    {
        return match($this->statut) {
            'ouvert'           => 'blue',
            'diagnostic'       => 'yellow',
            'devis_envoye'     => 'orange',
            'devis_accepte'    => 'indigo',
            'en_cours'         => 'purple',
            'controle_qualite' => 'pink',
            'lavage'           => 'cyan',
            'pret'             => 'teal',
            'facture'          => 'green',
            'livre'            => 'gray',
            'annule'           => 'red',
            default            => 'gray',
        };
    }

    /** Retourne le libellé du type d'OR (normal, garantie, sinistre, entretien) */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'normal'    => 'Normal',
            'garantie'  => 'Garantie',
            'sinistre'  => 'Sinistre',
            'entretien' => 'Entretien',
            default     => $this->type,
        };
    }

    /** Retourne le libellé du service atelier (mécanique, carrosserie, etc.) */
    public function getServiceLabel(): string
    {
        return match($this->service) {
            'rapide'      => 'Service Rapide',
            'mecanique'   => 'Mécanique',
            'electricite' => 'Électricité',
            'carrosserie' => 'Carrosserie',
            'peinture'    => 'Peinture',
            default       => 'Non défini',
        };
    }

    /** Retourne la couleur Tailwind associée au service pour les badges */
    public function getServiceColor(): string
    {
        return match($this->service) {
            'rapide'      => 'blue',
            'mecanique'   => 'orange',
            'electricite' => 'yellow',
            'carrosserie' => 'purple',
            'peinture'    => 'pink',
            default       => 'gray',
        };
    }

    /** Indique si l'OR a un mécanicien et un service affectés */
    public function isAffecte(): bool
    {
        return $this->technicien_id !== null && $this->service !== null;
    }

    /**
     * Catégories du dossier de preuves garantie (cf. PhotoOr::CATEGORIES) qui n'ont
     * encore aucune photo — la vidéo du bruit ('video_bruit') est facultative et
     * n'est jamais comptée comme manquante. Utilisé pour bloquer la facturation
     * d'un OR garantie approuvé tant que le dossier n'est pas complet
     * (cf. FactureController::store()).
     */
    public function categoriesGarantieManquantes(): array
    {
        $presentes = $this->photosOr->pluck('categorie')->filter()->unique();

        return collect(PhotoOr::CATEGORIES)
            ->except('video_bruit')
            ->reject(fn ($label, $cle) => $presentes->contains($cle))
            ->all();
    }

    /** Indique si le dossier de preuves garantie (hors vidéo, facultative) est complet */
    public function documentsGarantieComplets(): bool
    {
        return empty($this->categoriesGarantieManquantes());
    }

    // ── Feuilles de travail ────────────────────────────────────────────
    //
    // Feuille 1 = premier devis accepté : elle utilise l'affectation et le
    // pointage de l'OR lui-même (technicien_id, heure_debut_travaux...), comme
    // avant — rapports et tableau de bord restent inchangés.
    // Feuilles suivantes = chaque devis complémentaire accepté, avec son propre
    // technicien et son propre pointage, stockés sur le devis.

    /** Devis acceptés de l'OR, dans l'ordre de création */
    public function devisAcceptes(): \Illuminate\Support\Collection
    {
        return $this->allDevis->where('statut', 'accepte')->sortBy('id')->values();
    }

    /** Devis de la feuille 1 (premier devis accepté), ou null s'il n'y en a pas encore */
    public function devisPrincipal(): ?Devis
    {
        return $this->devisAcceptes()->first();
    }

    /** Devis complémentaires acceptés, chacun avec sa propre feuille de travail */
    public function devisComplementaires(): \Illuminate\Support\Collection
    {
        return $this->devisAcceptes()->slice(1)->values();
    }

    /** Toutes les feuilles complémentaires sont-elles terminées ? */
    public function feuillesComplementairesTerminees(): bool
    {
        return $this->devisComplementaires()->every(fn (Devis $d) => $d->heure_fin_travaux !== null);
    }

    /**
     * Bon de commande qui bloque encore l'affectation de la feuille 1 : celui du
     * premier devis accepté uniquement (les BC des devis complémentaires ne
     * bloquent que leur propre feuille). Sans devis accepté, n'importe quel BC
     * de l'OR encore en attente bloque, comme avant.
     */
    public function bcBloquantFeuille1(): ?BonCommande
    {
        $principal = $this->devisPrincipal();
        if ($principal) {
            return $principal->attendPieces() ? $principal->bonCommande : null;
        }

        return $this->bonsCommande()->whereIn('statut', ['en_attente', 'commande'])->whereHas('lignes')->first();
    }

    /** Aucun devis n'est encore accepté : le véhicule en est au diagnostic / devis */
    public function estAvantAcceptationDevis(): bool
    {
        return in_array($this->statut, ['ouvert', 'diagnostic', 'devis_envoye'], true);
    }

    /** Retourne le libellé du niveau d'urgence */
    public function getUrgenceLabel(): string
    {
        return match($this->urgence) {
            'normal'      => 'Normal',
            'urgent'      => 'Urgent',
            'tres_urgent' => 'Très urgent',
            default       => 'Normal',
        };
    }

    /**
     * Génère un numéro d'OR unique au format OR-AAAA-XXXX.
     * Exemple : OR-2026-0001, OR-2026-0002...
     * La séquence repart à 1 chaque année.
     */
    public static function genererNumero(): string
    {
        $annee   = now()->year;
        $dernier = self::whereYear('created_at', $annee)->max('numero');
        // Extrait les 4 derniers chiffres du dernier numéro et incrémente
        $sequence = $dernier ? (int) substr($dernier, -4) + 1 : 1;
        return sprintf('OR-%d-%04d', $annee, $sequence);
    }
}
