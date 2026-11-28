<?php

declare(strict_types=1);

namespace App\Support;

/** Technische mailtypes en -fouten in gewone taal voor beheer. */
final class MailLabels
{
    private const TYPES = [
        'VerifyEmailDutch' => 'Bevestigingsmail',
        'VerifyEmailReminderDutch' => 'Bevestigingsherinnering',
        'VerifyEmailReminderDay7Dutch' => 'Bevestigingsherinnering dag 7',
        'VerifyEmailReminderDay14Dutch' => 'Bevestigingsherinnering dag 14',
        'UnverifiedDeletionNoticeDutch' => 'Verwijdermail onbevestigd account',
        'ActivatieHerinneringMail' => 'Activatieherinnering',
        'ResetPasswordDutch' => 'Wachtwoord vergeten',
        'EmailChangeNoticeDutch' => 'E-mailadres gewijzigd',
        'NewMessageMail' => 'Nieuw bericht',
        'AanbodExpiryReminderMail' => 'Aanbod verloopt',
        'EersteStekjeMail' => 'Eerste stekje',
        'DeelplekAanmeldingBevestiging' => 'Aanmelding deelplek',
        'DeelplekAanmeldingAdminMelding' => 'Nieuwe aanmelding (beheer)',
        'DeelplekGoedgekeurd' => 'Deelplek goedgekeurd',
        'VerenigingAangemeld' => 'Nieuwe vereniging (beheer)',
        'VerenigingGoedgekeurd' => 'Vereniging goedgekeurd',
        'AccountBestaatAl' => 'Account bestaat al',
    ];

    public static function type(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function fout(?string $fout): string
    {
        $f = mb_strtolower((string) $fout);

        return match (true) {
            $f === '' => '',
            str_contains($f, 'could not be established'), str_contains($f, 'connection refused'), str_contains($f, 'timed out') => 'Mailserver niet bereikbaar',
            str_contains($f, 'authenticate'), str_contains($f, '535') => 'Inloggen bij de mailserver mislukt',
            str_contains($f, '550'), str_contains($f, 'mailbox unavailable'), str_contains($f, 'does not exist'), str_contains($f, 'user unknown') => 'Adres bestaat niet of weigert mail',
            str_contains($f, '552'), str_contains($f, 'quota') => 'Mailbox van de ontvanger is vol',
            str_contains($f, '554'), str_contains($f, 'spam'), str_contains($f, 'blocked') => 'Mail geweigerd als spam',
            str_contains($f, 'rate'), str_contains($f, '421'), str_contains($f, '451') => 'Te veel mails tegelijk, later opnieuw',
            default => 'Onbekende fout',
        };
    }
}
