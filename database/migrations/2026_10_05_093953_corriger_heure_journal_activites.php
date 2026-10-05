<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Journal d'activité : jusqu'ici l'heure était écrite par MySQL (valeur par
 * défaut CURRENT_TIMESTAMP), dans le fuseau du serveur MySQL — en UTC chez
 * l'hébergeur, soit 3 h de retard sur Djibouti. Le modèle Activite écrit
 * désormais l'heure lui-même ; cette migration recale les entrées existantes
 * de l'écart mesuré entre l'heure de l'application et celle de MySQL.
 * Sur un poste où MySQL est déjà à l'heure de Djibouti, l'écart est nul :
 * rien n'est modifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $heureMysql = \Carbon\Carbon::parse(DB::selectOne('SELECT NOW() AS maintenant')->maintenant, config('app.timezone'));
        $ecartMinutes = (int) round(now()->diffInMinutes($heureMysql, false) / 15) * 15 * -1;

        if ($ecartMinutes !== 0) {
            // Seulement les entrées écrites par MySQL : celles déjà écrites par l'application
            // (après la copie des fichiers) sont « en avance » sur l'heure de MySQL
            DB::update('UPDATE activites SET created_at = DATE_ADD(created_at, INTERVAL ? MINUTE) WHERE created_at <= NOW()', [$ecartMinutes]);
        }
    }

    public function down(): void
    {
        // Pas de retour arrière : l'écart d'origine n'est pas conservé.
    }
};
