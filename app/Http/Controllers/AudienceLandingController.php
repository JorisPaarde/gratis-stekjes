<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HelpMeeInterest;
use App\Support\FormGuard;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AudienceLandingController extends Controller
{
    public const MAX_NAME = 120;

    public const MAX_EMAIL = 254;

    public const MAX_NOTE = 2000;

    public const RATE_MAX = 5;

    public const RATE_DECAY = 3600;

    /** @return list<array{slug: string, route: string, label: string}> */
    public static function series(): array
    {
        return [
            ['slug' => 'volkstuinen', 'route' => 'volkstuinen', 'label' => 'Volkstuinverenigingen'],
            ['slug' => 'help-mee', 'route' => 'help-mee', 'label' => 'Help mee'],
            ['slug' => 'natuur', 'route' => 'natuur', 'label' => 'Natuurliefhebbers'],
            ['slug' => 'verbinding', 'route' => 'verbinding', 'label' => 'Verbinding'],
        ];
    }

    public function volkstuinen(): View
    {
        $site = config('site.name');
        $title = 'Volkstuinverenigingen';
        $meta = 'Hoe leden van één volkstuinvereniging planten, stekjes en zaden delen via '.$site.'.';
        $canonical = route('volkstuinen');

        return view('pages.audience.volkstuinen', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'volkstuinen',
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    public function helpMee(): View
    {
        $site = config('site.name');
        $title = 'Help mee';
        $meta = 'Word verbinder voor '.$site.': social media, volkstuinen, voedselbossen en initiatieven zoals Meer Bomen Nu.';
        $canonical = route('help-mee');

        return view('pages.audience.help-mee', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'help-mee',
            'maxName' => self::MAX_NAME,
            'maxNote' => self::MAX_NOTE,
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    public function helpMeeStore(Request $request): RedirectResponse
    {
        if (FormGuard::honeypotFilled($request->input('website'))) {
            return redirect()
                ->route('help-mee')
                ->with('status', 'Dank je. Ik heb je bericht ontvangen en lees het zelf.');
        }
        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()
                ->withInput($request->except(['website']))
                ->withErrors(['email' => 'Even geduld, het formulier ging te snel.']);
        }

        $throttleKey = 'help-mee:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return back()
                ->withInput($request->except(['website']))
                ->withErrors(['email' => ThrottleMessage::forKey($throttleKey)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.self::MAX_NAME],
            'email' => ['required', 'string', 'email:filter', 'max:'.self::MAX_EMAIL],
            'note' => ['nullable', 'string', 'max:'.self::MAX_NOTE],
            'privacy' => ['accepted'],
        ], [
            'name.required' => 'Vul je naam of voornaam in.',
            'email.required' => 'Vul een e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'privacy.accepted' => 'Bevestig dat je akkoord gaat met de privacyverklaring.',
        ]);

        $email = strtolower(trim((string) $data['email']));
        if (FormGuard::isDisposableEmail($email)) {
            return back()
                ->withInput($request->except(['website']))
                ->withErrors(['email' => 'Gebruik een gewoon e-mailadres, geen wegwerpmailbox.']);
        }

        $name = trim((string) $data['name']);
        $note = trim((string) ($data['note'] ?? ''));

        HelpMeeInterest::query()->create([
            'name' => $name,
            'email' => $email,
            'note' => $note === '' ? null : $note,
            'privacy_at' => now(),
        ]);

        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        return redirect()
            ->route('help-mee')
            ->with('status', 'Dank je. Ik heb je bericht ontvangen en lees het zelf.');
    }

    public function natuur(): View
    {
        $site = config('site.name');
        $title = 'Natuurliefhebbers';
        $meta = 'Meer planten en meer groen: hoe je via '.$site.' stekjes deelt en de wereld een stukje groener maakt.';
        $canonical = route('natuur');

        return view('pages.audience.natuur', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'natuur',
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    public function verbinding(): View
    {
        $site = config('site.name');
        $title = 'Verbinding';
        $meta = 'Planten delen als manier om buren te ontmoeten — via '.$site.', met een praatje als enige prijs.';
        $canonical = route('verbinding');

        return view('pages.audience.verbinding', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'verbinding',
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    /** @return array<string, mixed> */
    private function webPageSchema(string $title, string $description, string $url): array
    {
        $site = config('site.name');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $url,
            'description' => $description,
            'inLanguage' => 'nl-NL',
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $site,
                'url' => url('/'),
            ],
        ];
    }
}
