<?php

declare(strict_types=1);

/**
 * Wegwerpmail-blokkade op basis van gebundelde open-source domeinlijst.
 * Bron: disposable-email-domains (MIT) — zie vendor/disposable/LICENSE
 */

function stekjes_disposable_domains(): array
{
    static $set = null;
    if ($set !== null) {
        return $set;
    }
    $file = dirname(__DIR__) . '/vendor/disposable/domains.txt';
    $set = [];
    if (!is_file($file)) {
        return $set;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines ?: [] as $line) {
        $line = strtolower(trim($line));
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $set[$line] = true;
    }
    return $set;
}

function stekjes_is_disposable_email(string $email): bool
{
    $email = strtolower(trim($email));
    $pos = strrpos($email, '@');
    if ($pos === false) {
        return false;
    }
    $domain = substr($email, $pos + 1);
    if ($domain === '') {
        return false;
    }
    $domains = stekjes_disposable_domains();
    if (isset($domains[$domain])) {
        return true;
    }
    // check parent domains (a.b.mailinator.com)
    $parts = explode('.', $domain);
    while (count($parts) > 2) {
        array_shift($parts);
        $parent = implode('.', $parts);
        if (isset($domains[$parent])) {
            return true;
        }
    }
    return false;
}
