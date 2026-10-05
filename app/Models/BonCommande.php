<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle BonCommande.
 *
 * Un bon de commande pièces (BC) est généré automatiquement dès la création
 * d'un devis contenant des pièces détachées (avant même sa validation), puis
 * transmis en temps réel au système fournisseur (stcd-magasin) qui renvoie
 * la disponibilité et le prix de vente de chaque pièce. Le BC n'a pas encore
 * d'OR tant que le devis n'est pas accepté — il reste rattaché à son dossier
 * de réception (`or_id`/`dossier_id` : un seul des deux est renseigné, comme
 * pour Devis — cf. vehicule()/client() ci-dessous pour lire l'un ou l'autre
 * sans s'en soucier).
 *
 * Cycle de vie : en_attente → commande → recu.
 * Chaque ligne du BC peut être cochée individuellement à la réception.
 *
 * Format du numéro : BC-AAAA-XXXX (ex: BC-2026-0001).
 */
class BonCommande extends Model
{
    protected $table = 'bons_commande';

    protected $fillable = ['numero', 'devis_id', 'or_id', 'dossier_id', 'client_id', 'vehicule_id', 'statut', 'notes', 'fournisseur_repondu_at'];

    protected $casts = [
        'fournisseur_repondu_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    /** Devis d'origine (celui qui a généré ce bon de commande) */
    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }

    /** OR associé à ce bon de commande (null si envoyé avant acceptation du devis) */
    public function ordreReparation(): BelongsTo
    {
        return $this->belongsTo(OrdreReparation::class, 'or_id');
    }

    /** Dossier de réception d'origine (avant qu'un OR n'existe) */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierReception::class, 'dossier_id');
    }

    /**
     * Lignes de pièces à commander. Ordre stable (par id de création) — la
     * position dans cette liste sert de clé de correspondance avec
     * stcd-magasin (index dans le payload JSON), donc jamais réordonnée.
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommande::class)->orderBy('id');
    }

    /** Bon de transfert du magasin (un seul par bon de commande) */
    public function bonTransfert(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BonTransfert::class);
    }

    /** Véhicule porté directement par le BC (BC flotte, sans devis) */
    public function vehiculeDirect(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id');
    }

    /** Client porté directement par le BC (BC flotte, sans devis) */
    public function clientDirect(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** Livraison flotte d'origine (BC créé par un import Excel flotte) */
    public function livraisonFlotte(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LivraisonFlotte::class);
    }

    /** Véhicule concerné, que le BC soit déjà rattaché à un OR ou encore à un dossier */
    public function getVehiculeAttribute(): ?Vehicule
    {
        return $this->ordreReparation?->vehicule ?? $this->dossier?->vehicule ?? $this->devis?->vehicule ?? $this->vehiculeDirect;
    }

    /** Client concerné, que le BC soit déjà rattaché à un OR ou encore à un dossier */
    public function getClientAttribute(): ?Client
    {
        return $this->ordreReparation?->client ?? $this->dossier?->client ?? $this->devis?->client ?? $this->clientDirect;
    }

    // ── Numérotation ───────────────────────────────────────────────────

    /**
     * Génère un numéro de BC séquentiel au format BC-AAAA-XXXX.
     * La séquence repart à 1 chaque année.
     */
    public static function genererNumero(): string
    {
        $annee   = now()->year;
        $dernier = self::whereYear('created_at', $annee)->max('numero');
        $seq     = $dernier ? (int) substr($dernier, -4) + 1 : 1;
        return sprintf('BC-%d-%04d', $annee, $seq);
    }

    // ── Labels et couleurs ─────────────────────────────────────────────

    /** Retourne le libellé français du statut du BC */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'en_attente' => 'En attente',
            'commande'   => 'Commandé',
            'recu'       => 'Reçu',
            'annule'     => 'Annulé',
            default      => $this->statut,
        };
    }

    /** Retourne la couleur Tailwind pour le badge de statut */
    public function getStatutColor(): string
    {
        return match($this->statut) {
            'en_attente' => 'yellow',
            'commande'   => 'blue',
            'recu'       => 'green',
            'annule'     => 'red',
            default      => 'gray',
        };
    }

    /**
     * Le fournisseur (stcd-magasin) a-t-il statué sur la disponibilité de
     * toutes les lignes ? Tant que ce n'est pas le cas, le garage ne doit pas
     * pouvoir marquer les pièces comme reçues.
     */
    public function estValideParFournisseur(): bool
    {
        return $this->lignes->isNotEmpty()
            && $this->lignes->every(fn (LigneBonCommande $ligne) => ! is_null($ligne->disponible));
    }

    /**
     * Pourquoi les pièces (toutes, ou une seule ligne) ne peuvent pas encore être
     * marquées reçues au garage — null si c'est possible. Pas de réception sans
     * bon de transfert (BT du magasin, ou BT papier joint à la main) : la réponse
     * automatique du magasin (référence reconnue à la création du devis) ne
     * suffit pas, les pièces n'ont pas encore quitté le magasin.
     */
    public function raisonReceptionImpossible(?LigneBonCommande $ligne = null): ?string
    {
        if ($this->statut === 'annule') {
            return "Le bon de commande {$this->numero} est annulé : aucune pièce à recevoir.";
        }

        $lignes = $ligne ? collect([$ligne]) : $this->lignes;
        if ($lignes->isEmpty()) {
            return "Le bon de commande {$this->numero} ne contient aucune pièce.";
        }
        if ($enAttente = $lignes->first(fn (LigneBonCommande $l) => is_null($l->disponible))) {
            return "Le fournisseur (stcd-magasin) n'a pas encore validé la disponibilité de « {$enAttente->designation} ».";
        }
        if (! $this->bonTransfert) {
            return "Le magasin n'a pas encore fait le bon de transfert (BT) : les pièces n'ont pas quitté le magasin. Si vous avez reçu le BT sur papier, joignez-le d'abord.";
        }
        $nonCouverte = $this->ligneNonCouverteParBt();
        if ($nonCouverte && (! $ligne || $nonCouverte->is($ligne))) {
            return "Le bon de transfert {$this->bonTransfert->numero} ne couvre pas « {$nonCouverte->designation} » ("
                . rtrim(rtrim(number_format($nonCouverte->quantite, 2, ',', ' '), '0'), ',')
                . " demandé). Attendez le BT mis à jour par le magasin.";
        }

        return null;
    }

    /**
     * Pièce du BC dont la quantité n'est pas couverte par le bon de transfert du
     * magasin (ex : devis passé de 1 à 2 plaquettes après le BT) — null si le BT
     * couvre tout, ou s'il n'y a pas de BT (réception sans BT, comme avant).
     * Les lignes du BT sont repérées par leur position (index) dans le BC ; une
     * ligne de BT sans quantité (BT saisi à la main) est considérée comme couverte.
     */
    public function ligneNonCouverteParBt(): ?LigneBonCommande
    {
        $bt = $this->bonTransfert;
        if (! $bt || empty($bt->lignes)) {
            return null;
        }

        $lignesBt = collect($bt->lignes);
        foreach ($this->lignes->values() as $index => $ligne) {
            $ligneBt = $lignesBt->first(fn ($l) => isset($l['index']) && is_numeric($l['index']) && (int) $l['index'] === $index);
            if (! $ligneBt) {
                return $ligne;
            }
            if (isset($ligneBt['quantite']) && is_numeric($ligneBt['quantite']) && (float) $ligneBt['quantite'] < (float) $ligne->quantite) {
                return $ligne;
            }
        }

        return null;
    }
}
