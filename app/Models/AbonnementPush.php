<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Appareil d'un utilisateur abonné aux notifications « push » (sonnerie de l'appareil) */
class AbonnementPush extends Model
{
    protected $table = 'abonnements_push';

    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'appareil'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
