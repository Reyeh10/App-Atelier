<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Import Excel des pièces livrées à une flotte (ex : société de bus qui a son
 * propre atelier). Le fichier est découpé en livraisons — une par bus et par
 * date — qui ont chacune leur BC magasin et leur facture.
 *
 * Format du numéro : FLT-AAAA-XXXX (ex: FLT-2026-0001).
 */
class ImportFlotte extends Model
{
    protected $table = 'imports_flotte';

    protected $fillable = ['numero', 'client_id', 'fichier_nom_original', 'fichier_chemin', 'notes', 'cree_par'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(LivraisonFlotte::class)->orderBy('date_livraison')->orderBy('id');
    }

    /** URL du fichier Excel importé, ou null */
    public function getFichierUrlAttribute(): ?string
    {
        return $this->fichier_chemin ? asset('storage/' . $this->fichier_chemin) : null;
    }

    public static function genererNumero(): string
    {
        $annee   = now()->year;
        $dernier = self::whereYear('created_at', $annee)->max('numero');
        $seq     = $dernier ? (int) substr($dernier, -4) + 1 : 1;
        return sprintf('FLT-%d-%04d', $annee, $seq);
    }
}
