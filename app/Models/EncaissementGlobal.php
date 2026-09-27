<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EncaissementGlobal extends Model
{
    protected $table = 'encaissements_globaux';

    protected $fillable = [
        'numero', 'client_id', 'marque_garantie_id', 'montant_total', 'statut',
        'mode_paiement', 'date_emission', 'date_paiement',
        'notes', 'created_by_id',
    ];

    protected $casts = [
        'date_emission'  => 'date',
        'date_paiement'  => 'date',
        'montant_total'  => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Compte garantie constructeur regroupé (renseigné uniquement si ce n'est pas un client) */
    public function marqueGarantie(): BelongsTo
    {
        return $this->belongsTo(MarqueGarantie::class);
    }

    /** Nom de l'entité qui règle réellement cet encaissement (marque garantie, sinon le client) */
    public function getPayeurNomAttribute(): string
    {
        return $this->marque_garantie_id
            ? $this->marqueGarantie->nom . ' (garantie constructeur)'
            : $this->client->nom_complet;
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class, 'encaissement_global_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $seq   = self::whereYear('created_at', $annee)->count() + 1;
        return sprintf('EG-%d/%04d', $annee, $seq);
    }

    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'emis' => 'Émis',
            'paye' => 'Payé',
            default => $this->statut,
        };
    }

    public function getStatutColor(): string
    {
        return match($this->statut) {
            'emis' => 'blue',
            'paye' => 'green',
            default => 'gray',
        };
    }

    public function getModePaiementLabel(): string
    {
        return match($this->mode_paiement) {
            'especes'  => 'Espèces',
            'cheque'   => 'Chèque',
            'carte'    => 'Carte bancaire',
            'virement' => 'Virement',
            'compte'   => 'Compte société',
            default    => $this->mode_paiement,
        };
    }
}
