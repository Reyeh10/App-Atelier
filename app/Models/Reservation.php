<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modèle Reservation — prise de RDV pour le Service Rapide.
 *
 * Une réservation est prise pour une date (pas d'heure — la capacité se
 * gère au jour, selon le paramètre `capacite_service_rapide_jour`).
 * Le jour venu, le réceptionniste "honore" la réservation, ce qui crée
 * un DossierReception préfilli avec le client/véhicule/canal_service.
 */
class Reservation extends Model
{
    protected $fillable = [
        'numero', 'client_id', 'vehicule_id', 'conseiller_id',
        'canal_service', 'service_cle', 'tache', 'date_rdv', 'heure_rdv', 'duree_estimee', 'statut', 'notes',
    ];

    protected $casts = [
        'date_rdv'      => 'date',
        'duree_estimee' => 'decimal:2',
    ];

    /** Horodatage de fin du créneau (heure_rdv + duree_estimee), pour les calculs de chevauchement */
    public function getHeureFinAttribute(): ?string
    {
        if (!$this->heure_rdv || !$this->duree_estimee) return null;
        return \Illuminate\Support\Carbon::parse($this->heure_rdv)
            ->addMinutes((float) $this->duree_estimee * 60)
            ->format('H:i');
    }

    // ── Relations ──────────────────────────────────────────────────────

    public function client(): BelongsTo     { return $this->belongsTo(Client::class); }
    public function vehicule(): BelongsTo    { return $this->belongsTo(Vehicule::class); }
    public function conseiller(): BelongsTo { return $this->belongsTo(User::class, 'conseiller_id'); }
    public function dossier(): HasOne       { return $this->hasOne(DossierReception::class, 'reservation_id'); }

    /** Devis établi à l'avance pour cette réservation (le plus récent, hors devis refusés) */
    public function devis(): HasOne
    {
        return $this->hasOne(Devis::class, 'reservation_id')->ofMany(['id' => 'max'], fn ($q) => $q->where('statut', '!=', 'refuse'));
    }

    // ── Labels et couleurs ─────────────────────────────────────────────

    public function getCanalServiceLabel(): string
    {
        return match($this->canal_service) {
            'entretien_periodique' => 'Entretien périodique',
            'autre'                => 'Autre (filtres, pneus, lavage...)',
            default                => $this->canal_service,
        };
    }

    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'planifie' => 'Planifié',
            'honore'   => 'Honoré',
            'annule'   => 'Annulé',
            'no_show'  => 'Absent (no-show)',
            default    => $this->statut,
        };
    }

    public function getStatutColor(): string
    {
        return match($this->statut) {
            'planifie' => 'blue',
            'honore'   => 'green',
            'annule'   => 'red',
            'no_show'  => 'orange',
            default    => 'gray',
        };
    }

    /**
     * Génère un numéro de réservation séquentiel au format RDV-AAAA-XXXX.
     * La séquence repart à 1 chaque année.
     */
    public static function genererNumero(): string
    {
        $annee   = now()->year;
        $dernier = self::whereYear('created_at', $annee)->max('numero');
        $sequence = $dernier ? (int) substr($dernier, -4) + 1 : 1;
        return sprintf('RDV-%d-%04d', $annee, $sequence);
    }
}
