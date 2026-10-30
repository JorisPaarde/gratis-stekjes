<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Support\FormGuard;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public const MAX_BODY = 2000;

    public const MAX_PATH = 160;

    public const RATE_MAX = 5;

    public const RATE_DECAY = 3600;

    public function create(Request $request): View
    {
        $pagePath = $this->normalizePagePath(
            (string) $request->query('page_path', $request->query('vanaf', '/'))
        );

        return view('feedback.create', [
            'title' => 'Feedback',
            'metaDescription' => 'Deel wat beter kan of wat je goed vindt op '.config('site.name').'.',
            'canonical' => route('feedback.create'),
            'pagePath' => $pagePath,
            'maxBody' => self::MAX_BODY,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (FormGuard::honeypotFilled($request->input('website'))) {
            return redirect()
                ->route('feedback.create')
                ->with('status', 'Dank je, ik lees alles zelf.');
        }
        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()
                ->withInput()
                ->withErrors(['body' => 'Even geduld, het formulier ging te snel.']);
        }

        $throttleKey = 'feedback-store:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return back()
                ->withInput()
                ->withErrors(['body' => ThrottleMessage::forKey($throttleKey)]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
            'page_path' => ['nullable', 'string', 'max:'.self::MAX_PATH],
        ], [
            'body.required' => 'Schrijf kort wat beter kan of wat je goed vindt.',
            'body.max' => 'Houd het bij maximaal '.self::MAX_BODY.' tekens.',
        ]);

        $body = trim((string) $data['body']);
        if ($body === '') {
            return back()
                ->withInput()
                ->withErrors(['body' => 'Schrijf kort wat beter kan of wat je goed vindt.']);
        }

        Feedback::query()->create([
            'user_id' => $user->id,
            'page_path' => $this->normalizePagePath((string) ($data['page_path'] ?? '/')),
            'body' => $body,
        ]);

        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        return redirect()
            ->route('feedback.create')
            ->with('status', 'Dank je, ik lees alles zelf.');
    }

    private function normalizePagePath(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '/';
        }

        // Alleen pad, geen query/fragment/host.
        $path = parse_url($raw, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = str_starts_with($raw, '/') ? explode('?', $raw, 2)[0] : '/'.$raw;
        }

        $path = '/'.ltrim($path, '/');
        $path = rawurldecode($path);
        if (mb_strlen($path) > self::MAX_PATH) {
            $path = mb_substr($path, 0, self::MAX_PATH);
        }

        return $path === '' ? '/' : $path;
    }
}
