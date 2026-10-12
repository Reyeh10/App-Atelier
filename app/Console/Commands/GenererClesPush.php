<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Génère les clés des notifications push (VAPID) et les écrit dans le .env.
 * À lancer une seule fois par installation : changer les clés oblige chaque
 * appareil à réactiver les notifications.
 */
class GenererClesPush extends Command
{
    protected $signature = 'push:cles {--forcer : Remplacer les clés existantes (les appareils devront réactiver les notifications)}';

    protected $description = 'Génère les clés des notifications push (VAPID) dans le fichier .env';

    public function handle(): int
    {
        $env = base_path('.env');
        if (! is_file($env) || ! is_writable($env)) {
            $this->error('Fichier .env introuvable ou non modifiable.');
            return self::FAILURE;
        }

        $contenu = file_get_contents($env);
        if (preg_match('/^VAPID_PUBLIC_KEY=\S+/m', $contenu) && ! $this->option('forcer')) {
            $this->info('Les clés existent déjà dans le .env (rien à faire). Utilisez --forcer pour les remplacer.');
            return self::SUCCESS;
        }

        try {
            $cles = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('Création des clés impossible : ' . $e->getMessage());
            if (PHP_OS_FAMILY === 'Windows') {
                $this->line("Sous Windows (XAMPP), définir d'abord la variable OPENSSL_CONF, ex. : OPENSSL_CONF=C:\\xampp\\php\\extras\\ssl\\openssl.cnf");
            }
            return self::FAILURE;
        }

        foreach (['VAPID_PUBLIC_KEY' => $cles['publicKey'], 'VAPID_PRIVATE_KEY' => $cles['privateKey']] as $nom => $valeur) {
            $contenu = preg_match("/^{$nom}=.*$/m", $contenu)
                ? preg_replace("/^{$nom}=.*$/m", "{$nom}={$valeur}", $contenu)
                : rtrim($contenu) . PHP_EOL . "{$nom}={$valeur}" . PHP_EOL;
        }
        file_put_contents($env, $contenu);

        $this->info('Clés des notifications push ajoutées au .env.');
        $this->line('Si la configuration est en cache : php artisan config:clear');

        return self::SUCCESS;
    }
}
