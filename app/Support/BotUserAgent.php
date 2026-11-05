<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Gedeelde bot-UA-classificatie voor pageviews en conversiemeting.
 */
final class BotUserAgent
{
    public static function isBot(Request $request): bool
    {
        $ua = trim((string) $request->userAgent());
        if ($ua === '') {
            return false;
        }

        // \b\w*bot\b vangt googlebot/bingbot/discordbot, niet Symfony «BrowserKit» (Pest/Laravel HTTP-tests).
        return (bool) preg_match(
            '/\b\w*bot\b|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|outbrain|pinterest|vkshare|whatsapp|telegram|headless|wget|curl\/|python-requests|scrapy|httpclient|libwww/i',
            $ua
        );
    }
}
