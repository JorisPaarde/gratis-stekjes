<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Bij SafeEncrypted-cast: markeer onleesbare ciphertext (verkeerde APP_KEY).
 */
trait HasSafeEncryptedAttributes
{
    /** @var array<string, bool> */
    protected array $unreadableAttributes = [];

    public function markAttributeUnreadable(string $key): void
    {
        $this->unreadableAttributes[$key] = true;
    }

    public function isUnreadable(string $key): bool
    {
        if (array_key_exists($key, $this->unreadableAttributes)) {
            return (bool) $this->unreadableAttributes[$key];
        }

        // Forceer cast zodat SafeEncrypted mag markeren.
        $this->getAttribute($key);

        return (bool) ($this->unreadableAttributes[$key] ?? false);
    }

    /**
     * Vul een encrypted veld alleen als de gebruiker iets nieuws invult.
     * Onleesbare ciphertext blijft onaangeroerd bij lege input.
     * Lege input op een leesbaar veld wist naar '' (niet null; NOT NULL-kolommen).
     */
    public function fillEncryptedIfProvided(string $key, mixed $newValue): void
    {
        $trimmed = trim((string) ($newValue ?? ''));
        if ($this->isUnreadable($key) && $trimmed === '') {
            return;
        }

        $this->setAttribute($key, $trimmed);
    }
}
