<?php

declare(strict_types=1);

namespace App\Support;

/**
 * AES-256-CBC blob-formaat voor backups: magic GPB1 + IV(16) + ciphertext.
 * Sleutel komt uit env; nooit loggen of committen.
 */
final class BackupCrypto
{
    public const MAGIC = 'GPB1';

    public static function encrypt(string $plaintext, string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            throw new \InvalidArgumentException('Backup-sleutel ontbreekt.');
        }

        $iv = random_bytes(16);
        $binaryKey = hash('sha256', $key, true);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Versleuteling mislukt.');
        }

        return self::MAGIC.$iv.$ciphertext;
    }

    public static function decrypt(string $blob, string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            throw new \InvalidArgumentException('Backup-sleutel ontbreekt.');
        }
        if (! str_starts_with($blob, self::MAGIC) || strlen($blob) < 21) {
            throw new \InvalidArgumentException('Ongeldig backup-formaat.');
        }

        $iv = substr($blob, 4, 16);
        $ciphertext = substr($blob, 20);
        $binaryKey = hash('sha256', $key, true);
        $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('Ontsleuteling mislukt.');
        }

        return $plain;
    }
}
