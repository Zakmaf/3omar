<?php

namespace App\Services;

use App\Http\Requests\PayrollValidation;
use Illuminate\Support\Facades\Validator;

/**
 * Encode et décode une simulation dans une chaîne transportable par URL (issue #50).
 *
 * Aucune donnée n'est conservée côté serveur : la totalité des entrées voyage dans
 * l'URL. La chaîne est compressée puis encodée en base64 URL-safe, ce qui la rend
 * compacte et peu lisible à l'oeil nu, mais ce n'est **pas** un chiffrement : une
 * URL partagée expose les montants qu'elle contient. L'interface doit le dire.
 *
 * Tout payload décodé est traité comme une entrée utilisateur non fiable et repasse
 * par les règles de validation du formulaire avant d'être utilisé.
 */
class SimulationCodec
{
    /**
     * Version du format, préfixée à la charge utile pour permettre une évolution
     * ultérieure sans casser les liens déjà partagés.
     */
    private const VERSION = 1;

    /**
     * Garde-fous contre les payloads hostiles : longueur de la chaîne reçue et
     * taille maximale après décompression (protection contre les bombes zlib).
     */
    public const MAX_PAYLOAD_LENGTH = 4000;

    private const MAX_INFLATED_BYTES = 65536;

    /**
     * Champs transportés. Les drapeaux « inconnu » sont inclus pour qu'un coût
     * employeur partiel reste partiel après rechargement.
     */
    private function transportableFields(): array
    {
        return PayrollValidation::webFields();
    }

    public function encode(array $input): string
    {
        $payload = [];

        foreach ($this->transportableFields() as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            // On ne transporte ni les valeurs vides ni les zéros implicites :
            // le formulaire les reconstruira depuis ses valeurs par défaut.
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $payload[$field] = $value;
        }

        $json = json_encode(['v' => self::VERSION, 'i' => $payload], JSON_UNESCAPED_UNICODE);

        $compressed = gzdeflate($json, 9);

        if ($compressed === false) {
            return '';
        }

        return $this->base64UrlEncode($compressed);
    }

    /**
     * Décode et valide un payload. Retourne null si la chaîne est illisible,
     * dans un format inconnu, ou si les entrées ne passent pas la validation.
     */
    public function decode(?string $payload): ?array
    {
        if (! is_string($payload) || $payload === '' || strlen($payload) > self::MAX_PAYLOAD_LENGTH) {
            return null;
        }

        $binary = $this->base64UrlDecode($payload);

        if ($binary === null) {
            return null;
        }

        $json = @gzinflate($binary, self::MAX_INFLATED_BYTES);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ($decoded['v'] ?? null) !== self::VERSION || ! is_array($decoded['i'] ?? null)) {
            return null;
        }

        return $this->validated($decoded['i']);
    }

    /**
     * Rejoue les règles du formulaire sur un payload décodé. Un lien fabriqué à
     * la main ne doit pas pouvoir injecter un champ ou un type inattendu.
     */
    private function validated(array $input): ?array
    {
        $input = array_intersect_key($input, array_flip($this->transportableFields()));

        $mode = ($input['mode'] ?? null) === 'net_to_gross' ? 'net_to_gross' : 'gross_to_net';
        $input['mode'] = $mode;

        $validator = Validator::make($input, PayrollValidation::webRules($mode));

        if ($validator->fails()) {
            return null;
        }

        $validated = $validator->validated();

        // Les lignes répétables arrivent sous forme de tableaux associatifs
        // potentiellement troués : on les renumérote pour le rendu du formulaire.
        foreach (['heures_sup', 'indemnites'] as $repeatable) {
            if (isset($validated[$repeatable]) && is_array($validated[$repeatable])) {
                $validated[$repeatable] = array_values($validated[$repeatable]);
            }
        }

        return $validated;
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $payload): ?string
    {
        if (preg_match('/^[A-Za-z0-9\-_]+$/', $payload) !== 1) {
            return null;
        }

        $binary = base64_decode(strtr($payload, '-_', '+/'), true);

        return $binary === false ? null : $binary;
    }
}
