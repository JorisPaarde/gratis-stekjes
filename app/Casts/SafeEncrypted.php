<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted cast met veilige fallback bij verkeerde APP_KEY (MAC invalid).
 * Geeft null terug bij onleesbare ciphertext; markeert het model via
 * {@see \App\Models\Concerns\HasSafeEncryptedAttributes}.
 * Wire-format gelijk aan Laravel `encrypted` (encrypt/decrypt zonder serialize).
 */
final class SafeEncrypted implements CastsAttributes
{
    public const VIEW_FALLBACK = 'Inhoud niet leesbaar';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value === '' ? '' : null;
        }

        try {
            return Crypt::decrypt((string) $value, false);
        } catch (DecryptException) {
            if (method_exists($model, 'markAttributeUnreadable')) {
                $model->markAttributeUnreadable($key);
            }

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;
        // Nooit de UI-fallback of lege “wis placeholder” over ciphertext schrijven.
        if ($string === self::VIEW_FALLBACK || $string === 'Inhoud niet leesbaar (oude sleutel)') {
            return array_key_exists($key, $attributes) ? (string) $attributes[$key] : null;
        }

        return Crypt::encrypt($string, false);
    }
}
