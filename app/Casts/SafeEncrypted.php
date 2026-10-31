<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Laravel encrypted-cast met veilige fallback bij verkeerde APP_KEY (MAC invalid).
 * Zelfde wire-format als de ingebouwde cast `encrypted` (encrypt/decrypt zonder serialize).
 */
final class SafeEncrypted implements CastsAttributes
{
    public const UNREADABLE = 'Inhoud niet leesbaar (oude sleutel)';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decrypt((string) $value, false);
        } catch (DecryptException) {
            return self::UNREADABLE;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encrypt((string) $value, false);
    }
}
