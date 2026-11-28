<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Mail\ActivatieHerinneringMail;
use App\Mail\LedenupdateMail;
use App\Models\Deelplek;
use App\Models\User;
use App\Models\Vereniging;
use App\Notifications\AccountBestaatAl;
use App\Notifications\DeelplekAanmeldingAdminMelding;
use App\Notifications\DeelplekAanmeldingBevestiging;
use App\Notifications\DeelplekGoedgekeurd;
use App\Notifications\EmailChangeNoticeDutch;
use App\Notifications\PartnerAanvraag;
use App\Notifications\ResetPasswordDutch;
use App\Notifications\UnverifiedDeletionNoticeDutch;
use App\Notifications\VerenigingAangemeld;
use App\Notifications\VerenigingGoedgekeurd;
use App\Notifications\VerifyEmailDutch;
use App\Notifications\VerifyEmailReminderDay14Dutch;
use App\Notifications\VerifyEmailReminderDay7Dutch;
use App\Notifications\VerifyEmailReminderDutch;
use App\Notifications\VerifyPendingEmailDutch;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Rendert alle HTML-mails van de site (zonder te versturen), voor layout-tests
 * en voor screenshots. Gebruikt alleen neppe voorbeelddata.
 */
final class MailRenderer
{
    /** @return array<string, array{html: string, text: string}> */
    public static function all(): array
    {
        $user = User::factory()->create([
            'name' => 'Sam',
            'email' => 'voorbeeld-mail@example.com',
            'email_verification_token' => str_repeat('a1b2c3d4', 8),
        ]);

        $plek = new Deelplek;
        $plek->forceFill([
            'naam' => 'Buurttuin Voorbeeld',
            'type' => 'buurttuin',
            'plaats' => 'Haarlem',
            'contact_email' => 'plek@example.com',
        ]);
        $vereniging = new Vereniging;
        $vereniging->forceFill([
            'naam' => 'Tuinvereniging Voorbeeld',
            'plaats' => 'Haarlem',
            'slug' => 'tuinvereniging-voorbeeld',
            'type' => 'tuinvereniging',
        ]);

        $notifications = [
            'bevestigen' => new VerifyEmailDutch,
            'bevestigen-herinnering' => new VerifyEmailReminderDutch,
            'bevestigen-herinnering-dag7' => new VerifyEmailReminderDay7Dutch,
            'bevestigen-herinnering-dag14' => new VerifyEmailReminderDay14Dutch,
            'onbevestigd-verwijderen' => new UnverifiedDeletionNoticeDutch(now()->addDays(7)),
            'wachtwoord-reset' => new ResetPasswordDutch(str_repeat('f9e8d7c6', 8)),
            'nieuw-emailadres-bevestigen' => new VerifyPendingEmailDutch('nieuw@example.com', (int) $user->getKey()),
            'email-wijziging-melding' => new EmailChangeNoticeDutch('nieuw@example.com'),
            'account-bestaat-al' => new AccountBestaatAl,
            'deelplek-bevestiging' => new DeelplekAanmeldingBevestiging($plek),
            'deelplek-admin' => new DeelplekAanmeldingAdminMelding($plek),
            'deelplek-goedgekeurd' => new DeelplekGoedgekeurd($plek),
            'vereniging-aangemeld' => new VerenigingAangemeld($vereniging),
            'vereniging-goedgekeurd' => new VerenigingGoedgekeurd($vereniging),
            'partner-aanvraag' => new PartnerAanvraag('Sam', 'Organisatie Voorbeeld', 'gemeente', 'sam@example.com', "Hoi,\n\nDit is een voorbeeldbericht."),
        ];

        $out = [];
        foreach ($notifications as $key => $notification) {
            /** @var MailMessage $message */
            $message = $notification->toMail($user);
            $out[$key] = [
                'html' => (string) $message->render(),
                'text' => (string) app(Markdown::class)->renderText($message->markdown, $message->data()),
            ];
        }

        $herinnering = new ActivatieHerinneringMail(
            'Sam',
            url('/aanbieden'),
            URL::signedRoute('activatieherinnering.uit', ['user' => $user->getKey()])
        );
        $out['activatie-herinnering'] = ['html' => $herinnering->render(), 'text' => ''];

        $ledenupdate = new LedenupdateMail(['naam' => 'Sam', 'nr' => 12, 'tier' => 1, 'vraag_postcode' => true]);
        $out['ledenupdate'] = ['html' => $ledenupdate->render(), 'text' => ''];

        return $out;
    }
}
