<?php

namespace App\Services;

use App\Models\AbonnementPush;
use App\Models\BonCommande;
use App\Models\Devis;
use App\Models\DossierReception;
use App\Models\EncaissementGlobal;
use App\Models\Facture;
use App\Models\LigneBonCommande;
use App\Models\NotificationInterne;
use App\Models\OrdreReparation;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Notifications à chaque étape du parcours d'un véhicule (cloche + son).
 *
 * - Chaque étape crée une notification interne pour les utilisateurs concernés
 *   (selon leurs permissions — l'administrateur reçoit tout), jamais pour celui
 *   qui vient de faire l'action.
 * - Toute notification interne créée est aussi envoyée en « push » aux appareils
 *   que le destinataire a abonnés : elle arrive avec la sonnerie de l'appareil,
 *   même système fermé. L'envoi se fait après la réponse (l'utilisateur n'attend
 *   pas) et une erreur d'envoi ne bloque jamais l'action.
 * - Les écrans ouverts interrogent la cloche toutes les 20 s : son + fenêtre
 *   dans la page pour les appareils non abonnés.
 */
class NotificationService
{
    /** Notifications créées pendant la requête, envoyées en push après la réponse */
    private static array $aPousser = [];

    // ── Étapes du parcours ──────────────────────────────────────────────

    /** Branche les étapes sur les modèles (appelé au démarrage de l'application) */
    public static function ecouter(): void
    {
        // Toute notification interne (étapes ci-dessous, garantie...) part aussi en push
        NotificationInterne::created(fn (NotificationInterne $n) => self::pousserPlusTard($n));

        DossierReception::created(function (DossierReception $dossier) {
            $motif = match ($dossier->motif_visite) {
                'panne'          => 'Panne',
                'accident'       => 'Accident',
                'service_rapide' => 'Service rapide',
                default          => $dossier->motif_visite,
            };
            self::notifier('gerer_devis', 'nouvelle_reception', 'Nouvelle réception',
                "{$dossier->numero} — " . self::vehiculeClient($dossier->vehicule, $dossier->client) . " : {$motif}.",
                null, route('dossiers-reception.show', $dossier));
        });

        Devis::created(function (Devis $devis) {
            $parent = $devis->parent;
            self::notifier('valider_devis', 'nouveau_devis', 'Nouveau devis',
                "Devis {$devis->numero} — " . self::vehiculeClient($parent?->vehicule, $parent?->client) . " : à présenter au client une fois complété.",
                $devis->or_id, route('devis.show', $devis));
        });

        BonCommande::created(function (BonCommande $bc) {
            // Les BC flotte (import Excel, sans devis) arrivent par lots : pas de notification
            if (! $bc->devis_id) {
                return;
            }
            self::notifier('gerer_bons_commande', 'bc_envoye', 'Bon de commande envoyé au magasin',
                "{$bc->numero} — " . self::vehiculeClient($bc->vehicule, $bc->client) . " : en attente de la réponse du magasin.",
                $bc->or_id, route('bons-commande.show', $bc));
        });

        // Réponse du magasin : quand toutes les pièces du BC ont une réponse (disponible
        // ou non) — l'accusé de réception de l'envoi ne compte pas. Une fois par BC.
        LigneBonCommande::updated(function (LigneBonCommande $ligne) {
            if (! $ligne->wasChanged('disponible') || $ligne->disponible === null) {
                return;
            }

            // Pièce en rupture revenue en stock au magasin : le BT va suivre, puis « Tout reçu »
            if ($ligne->disponible === true && $ligne->getOriginal('disponible') === false) {
                $bc = $ligne->bonCommande;
                $or = $bc?->ordreReparation ?? $bc?->devis?->ordreReparation;
                self::notifier('gerer_bons_commande', 'piece_disponible', 'Pièce de nouveau disponible',
                    "{$ligne->designation} ({$bc?->numero}) — " . self::vehiculeClient($bc?->vehicule, $bc?->client) . " : la pièce est arrivée au magasin, le BT va suivre.",
                    $or?->id, $bc ? route('bons-commande.show', $bc) : null);
                return;
            }
            $bc = $ligne->bonCommande;
            if (! ($bc?->devis_id || $bc?->leasing_bus || $bc?->depannage_id) || $bc->lignes()->whereNull('disponible')->exists()) {
                return;
            }
            $lien = route('bons-commande.show', $bc);
            if (NotificationInterne::where('type', 'magasin_repondu')->where('lien', $lien)->exists()) {
                return;
            }
            $lignes = $bc->lignes()->get();
            $dispo  = $lignes->where('disponible', true)->count();
            self::notifier('gerer_bons_commande', 'magasin_repondu', 'Le magasin a répondu',
                "{$bc->numero} — " . self::vehiculeClient($bc->vehicule, $bc->client) . " : {$dispo}/{$lignes->count()} pièce(s) disponible(s).",
                $bc->or_id, $lien);
        });

        BonCommande::updated(function (BonCommande $bc) {
            // Pièces reçues au garage : le véhicule peut passer chez un technicien
            // (un BC créé pendant le devis garde or_id vide : l'OR se retrouve par le devis)
            $or = $bc->ordreReparation ?? $bc->devis?->ordreReparation;
            if ($or && $bc->wasChanged('statut') && $bc->statut === 'recu') {
                self::notifier('affecter_technicien', 'pieces_recues', 'Pièces reçues — technicien à affecter',
                    "{$bc->numero} reçu — " . self::libelleOr($or) . " : un technicien peut être affecté.",
                    $or->id);
            }
        });

        OrdreReparation::updated(function (OrdreReparation $or) {
            if (! $or->wasChanged('statut')) {
                return;
            }

            switch ($or->statut) {
                case 'controle_qualite':
                    self::notifier('valider_qualite', 'controle_qualite', 'Contrôle qualité à faire',
                        self::libelleOr($or) . ' : travaux terminés.', $or->id);
                    break;

                case 'lavage':
                    self::notifier('valider_lavage', 'lavage', 'Lavage à faire',
                        self::libelleOr($or) . ' : contrôle qualité validé.', $or->id);
                    break;

                case 'pret':
                    self::notifier('restituer_vehicule', 'vehicule_pret', 'Véhicule prêt à restituer',
                        self::libelleOr($or) . ' est prêt, le client peut venir le récupérer.', $or->id);

                    // Même règle que la liste « À facturer »
                    if (OrdreReparation::aFacturer()->whereKey($or->id)->exists()) {
                        self::notifier('creer_factures', 'a_facturer', 'Véhicule à facturer',
                            self::libelleOr($or) . ' : la facture peut être établie.',
                            $or->id, route('factures.a-facturer'));
                    }
                    break;
            }
        });

        Facture::updated(function (Facture $facture) {
            if ($facture->wasChanged('statut') && $facture->statut === 'payee') {
                self::notifier(fn (User $u) => $u->isAdmin() || $u->isChefGarage(), 'facture_payee', 'Facture payée',
                    "Facture {$facture->numero} — {$facture->payeur_nom} : " . number_format($facture->totalGeneral(), 0, ',', ' ') . ' FDJ.',
                    $facture->or_id, route('factures.show', $facture));
            }
        });

        EncaissementGlobal::updated(function (EncaissementGlobal $eg) {
            if ($eg->wasChanged('statut') && $eg->statut === 'paye') {
                self::notifier(fn (User $u) => $u->isAdmin() || $u->isChefGarage(), 'encaissement_paye', 'Encaissement global payé',
                    "{$eg->numero} — " . number_format((float) $eg->montant_total, 0, ',', ' ') . ' FDJ : ' . $eg->factures()->count() . ' facture(s) soldée(s).',
                    null, route('encaissements-globaux.show', $eg));
            }
        });
    }

    /** Bon de transfert reçu du magasin pour un BC (appel du magasin, sans utilisateur connecté) */
    public static function bonTransfertRecu(BonCommande $bc, string $numeroBt): void
    {
        self::notifier('gerer_bons_commande', 'bt_recu', 'Bon de transfert reçu',
            "BT {$numeroBt} du magasin pour {$bc->numero} — " . self::vehiculeClient($bc->vehicule, $bc->client) . " : vérifier puis marquer « Tout reçu ».",
            $bc->or_id, route('bons-commande.show', $bc));
    }

    // ── Envoi ───────────────────────────────────────────────────────────

    /**
     * Crée une notification pour chaque utilisateur concerné : une permission
     * (l'administrateur l'a toujours) ou un filtre. Jamais pour l'auteur de l'action.
     */
    public static function notifier(string|Closure $qui, string $type, string $titre, string $corps, ?int $orId = null, ?string $lien = null): void
    {
        try {
            $auteurId = Auth::id();
            $destinataires = User::with('userPermissions')->get()->filter(fn (User $u) => $u->id !== $auteurId
                && ($qui instanceof Closure ? $qui($u) : $u->hasPermission($qui)));

            foreach ($destinataires as $user) {
                NotificationInterne::create([
                    'destinataire_id' => $user->id,
                    'type'            => $type,
                    'titre'           => $titre,
                    'corps'           => $corps,
                    'or_id'           => $orId,
                    'lien'            => $lien,
                ]);
            }
        } catch (\Throwable $e) {
            // Une notification ne doit jamais empêcher l'action elle-même
            Log::warning("Notification « {$type} » non créée : " . $e->getMessage());
        }
    }

    /** Envoi push groupé après la réponse HTTP */
    private static function pousserPlusTard(NotificationInterne $notification): void
    {
        if (! self::pushConfigure()) {
            return;
        }
        if (! self::$aPousser) {
            // Écran : après l'envoi de la page ; commande artisan : à la fin de la commande
            app()->runningInConsole()
                ? app()->terminating(fn () => self::envoyerPush())
                : \Illuminate\Support\defer(fn () => self::envoyerPush());
        }
        self::$aPousser[] = $notification->id;
    }

    /** Envoie en push les notifications en attente (celles encore en base : une transaction annulée n'envoie rien) */
    public static function envoyerPush(): void
    {
        $ids = self::$aPousser;
        self::$aPousser = [];
        if (! $ids) {
            return;
        }

        try {
            $notifications = NotificationInterne::whereIn('id', $ids)->get();
            $abonnements   = AbonnementPush::whereIn('user_id', $notifications->pluck('destinataire_id')->unique())->get()->groupBy('user_id');

            $envois = [];
            foreach ($notifications as $n) {
                foreach ($abonnements->get($n->destinataire_id, collect()) as $abonnement) {
                    $envois[] = [$abonnement, [
                        'title' => $n->titre,
                        'body'  => $n->corps,
                        'url'   => route('notifications.ouvrir', $n),
                        'tag'   => 'notif-' . $n->id,
                    ]];
                }
            }
            self::pousser($envois);
        } catch (\Throwable $e) {
            Log::warning('Envoi push impossible : ' . $e->getMessage());
        }
    }

    /** Notification de test envoyée à un appareil qui vient de s'abonner */
    public static function testerAppareil(AbonnementPush $abonnement): void
    {
        try {
            self::pousser([[$abonnement, [
                'title' => 'Notifications activées',
                'body'  => 'Cet appareil recevra les notifications de l\'atelier, même système fermé.',
                'url'   => route('dashboard'),
                'tag'   => 'notif-test',
            ]]]);
        } catch (\Throwable $e) {
            Log::warning('Push de test impossible : ' . $e->getMessage());
        }
    }

    /** @param array<int, array{0: AbonnementPush, 1: array}> $envois */
    private static function pousser(array $envois): void
    {
        if (! $envois || ! self::pushConfigure()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.webpush.subject'),
                'publicKey'  => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ], ['TTL' => 86400, 'urgency' => 'high'], new \GuzzleHttp\Client(['timeout' => 10]));

        $icone = asset('icone-192.png');
        foreach ($envois as [$abonnement, $contenu]) {
            $webPush->queueNotification(Subscription::create([
                'endpoint'        => $abonnement->endpoint,
                'publicKey'       => $abonnement->p256dh,
                'authToken'       => $abonnement->auth,
                'contentEncoding' => 'aes128gcm',
            ]), json_encode($contenu + ['icon' => $icone]));
        }

        foreach ($webPush->flush() as $rapport) {
            // Appareil désabonné ou navigateur réinstallé : on l'oublie
            if ($rapport->isSubscriptionExpired()) {
                AbonnementPush::where('endpoint_hash', hash('sha256', $rapport->getEndpoint()))->delete();
            } elseif (! $rapport->isSuccess()) {
                Log::warning('Push refusé : ' . $rapport->getReason());
            }
        }
    }

    /** Clés VAPID présentes dans le .env (php artisan push:cles) */
    public static function pushConfigure(): bool
    {
        return (bool) (config('services.webpush.public_key') && config('services.webpush.private_key'));
    }

    // ── Libellés ────────────────────────────────────────────────────────

    private static function libelleOr(?OrdreReparation $or): string
    {
        return $or ? "{$or->numero} — " . self::vehiculeClient($or->vehicule, $or->client) : '';
    }

    private static function vehiculeClient($vehicule, $client): string
    {
        return trim(($vehicule?->immatriculation ?? '') . ($client ? " ({$client->nom_complet})" : '')) ?: '—';
    }
}
