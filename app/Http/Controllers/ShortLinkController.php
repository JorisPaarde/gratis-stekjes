<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PostLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Korte deellink /s/{code} → 302 naar target_path?utm_… (default /ga/word-lid).
 * Geen open redirect: doelpad komt uit PostLog::trackingPath() (alleen interne paden).
 */
class ShortLinkController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        abort_unless(Schema::hasTable('post_logs'), 404);

        $normalized = PostLog::normalizeShortCode($code);
        if ($normalized === null) {
            abort(404);
        }

        $post = PostLog::query()->where('short_code', $normalized)->first();
        if ($post === null) {
            abort(404);
        }

        // Relatief pad op eigen host — nooit externe Location.
        return redirect()
            ->to($post->trackingPath())
            ->setStatusCode(302)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
