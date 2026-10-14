<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signature électronique du client : le formulaire envoie le dessin du cadre
 * de signature sous forme de data URL PNG (cf. partials/signature-pad) ; on le
 * vérifie puis on l'enregistre en fichier PNG sur le disque public.
 */
class SignatureService
{
    private const PREFIXE_DATA_URL = 'data:image/png;base64,';

    /** Taille maximale d'une signature décodée (octets) */
    private const TAILLE_MAX = 2 * 1024 * 1024;

    /** Règles de validation du champ caché envoyé par le cadre de signature */
    public static function regles(): array
    {
        return ['required', 'string', 'starts_with:' . self::PREFIXE_DATA_URL];
    }

    /** Messages d'erreur associés à regles() */
    public static function messages(string $champ): array
    {
        return [
            "{$champ}.required"    => 'La signature du client est obligatoire.',
            "{$champ}.starts_with" => 'La signature du client est invalide — faites signer à nouveau.',
        ];
    }

    /**
     * Décode et enregistre la signature ; renvoie le chemin sur le disque public.
     *
     * @throws ValidationException si le contenu n'est pas une image PNG valide
     */
    public static function enregistrer(string $dataUrl, string $champ, string $prefixeFichier): string
    {
        $binaire = base64_decode(substr($dataUrl, strlen(self::PREFIXE_DATA_URL)), true);
        $infos   = $binaire ? @getimagesizefromstring($binaire) : false;

        if (! $binaire || strlen($binaire) > self::TAILLE_MAX || ! $infos || $infos[2] !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages([$champ => 'La signature du client est invalide — faites signer à nouveau.']);
        }

        $chemin = 'signatures/' . Str::slug($prefixeFichier) . '-' . now()->format('YmdHis') . '-' . Str::random(6) . '.png';
        Storage::disk('public')->put($chemin, $binaire);

        return $chemin;
    }
}
