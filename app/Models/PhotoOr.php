<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoOr extends Model
{
    protected $table = 'photos_or';

    protected $fillable = ['or_id', 'moment', 'chemin', 'nom_original', 'taille', 'legende', 'categorie', 'type'];

    /**
     * Catégories du dossier de preuves garantie constructeur (cf. demande
     * garantie — OrdreReparationController::uploadPhotosGarantie()).
     * `null` = photo générique du véhicule (réception/restitution), pas liée
     * à une réclamation garantie précise.
     */
    public const CATEGORIES = [
        'vin'              => 'Photo du VIN',
        'tableau_bord'     => 'Tableau de bord',
        'piece_endommagee' => 'Pièce endommagée',
        'piece_neuve'      => 'Pièce neuve',
        'reference_piece'  => 'Référence de la pièce',
        'video_bruit'      => 'Vidéo du bruit',
    ];

    public function ordreReparation(): BelongsTo
    {
        return $this->belongsTo(OrdreReparation::class, 'or_id');
    }

    public function url(): string
    {
        return asset('storage/' . $this->chemin);
    }

    public function estVideo(): bool
    {
        return $this->type === 'video';
    }

    public function getCategorieLabel(): ?string
    {
        return self::CATEGORIES[$this->categorie] ?? null;
    }
}
