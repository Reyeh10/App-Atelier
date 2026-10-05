<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Bon de transfert (BT) établi par le magasin (stcd-magasin) pour livrer au
 * garage les pièces d'un bon de commande. Un seul BT par bon de commande.
 *
 * Arrive automatiquement par l'API (source "magasin" : numéro, date, dépôt,
 * lignes, PDF éventuel) ou est joint à la main depuis le bon de commande
 * (source "manuel" : numéro + scan ou photo du BT papier).
 */
class BonTransfert extends Model
{
    protected $table = 'bons_transfert';

    protected $fillable = [
        'bon_commande_id', 'numero', 'date_transfert', 'depot', 'lignes',
        'fichier_chemin', 'fichier_nom_original', 'source', 'notes', 'saisi_par',
    ];

    protected $casts = [
        'date_transfert' => 'date',
        'lignes'         => 'array',
    ];

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    /** URL du fichier joint (PDF, scan ou photo), ou null */
    public function getFichierUrlAttribute(): ?string
    {
        return $this->fichier_chemin ? asset('storage/' . $this->fichier_chemin) : null;
    }

    public function getSourceLabel(): string
    {
        return $this->source === 'magasin' ? 'Reçu du magasin' : 'Saisi à l\'atelier';
    }

    /**
     * Reporte la référence des pièces du BT sur les lignes du bon de commande
     * et du devis qui n'en ont pas encore (pièces identifiées à la main par le
     * vendeur du magasin). Correspondance par position (« index », comme le
     * reste de l'API), sinon par désignation. Une référence déjà saisie à
     * l'atelier n'est jamais écrasée.
     *
     * @return int nombre de lignes complétées
     */
    public function reporterReferences(): int
    {
        $lignesBc   = $this->bonCommande->lignes()->with('ligneDevis')->get()->values();
        $completees = 0;

        foreach ($this->lignes ?? [] as $ligne) {
            $reference = trim((string) ($ligne['reference'] ?? ''));
            if ($reference === '') {
                continue;
            }

            $cible = isset($ligne['index']) && is_numeric($ligne['index'])
                ? $lignesBc->get((int) $ligne['index'])
                : null;
            if (! $cible && ! empty($ligne['designation'])) {
                $cible = $lignesBc->first(fn ($l) => mb_strtolower(trim($l->designation)) === mb_strtolower(trim($ligne['designation'])));
            }
            if (! $cible) {
                continue;
            }

            if (blank($cible->reference)) {
                $cible->update(['reference' => $reference]);
                $completees++;
            }
            if ($cible->ligneDevis && blank($cible->ligneDevis->reference)) {
                $cible->ligneDevis->update(['reference' => $reference]);
            }
        }

        return $completees;
    }

    /**
     * Un BT portant un autre numéro remplace le précédent : le fichier de
     * l'ancien BT ne doit pas rester attaché au nouveau.
     */
    public function oublierFichierSiAutreNumero(string $nouveauNumero): void
    {
        if ($this->exists && $this->fichier_chemin && $this->numero !== $nouveauNumero) {
            Storage::disk('public')->delete($this->fichier_chemin);
            $this->update(['fichier_chemin' => null, 'fichier_nom_original' => null]);
        }
    }

    /** Remplace le fichier joint (l'ancien est supprimé du disque) */
    public function remplacerFichier(?\Illuminate\Http\UploadedFile $fichier): void
    {
        if (! $fichier) {
            return;
        }
        if ($this->fichier_chemin) {
            Storage::disk('public')->delete($this->fichier_chemin);
        }
        $this->update([
            'fichier_chemin'       => $fichier->store("bons-transfert/{$this->bon_commande_id}", 'public'),
            'fichier_nom_original' => $fichier->getClientOriginalName(),
        ]);
    }
}
