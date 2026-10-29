<?php

declare(strict_types=1);

/**
 * Uitgaande mail alleen via SMTP uit config (geen externe mail-API).
 */

function stekjes_send_mail(string $to, string $subject, string $bodyText): bool
{
    $config = stekjes_config();
    $mail = $config['mail'] ?? [];
    $fromEmail = (string) ($mail['from_email'] ?? '');
    $fromName = (string) ($mail['from_name'] ?? stekjes_site_name());
    $host = (string) ($mail['smtp_host'] ?? '');
    $port = (int) ($mail['smtp_port'] ?? 465);
    $user = (string) ($mail['smtp_user'] ?? '');
    $pass = (string) ($mail['smtp_pass'] ?? '');
    $enc = strtolower((string) ($mail['smtp_encryption'] ?? 'ssl'));

    if (getenv('STEKJES_MAIL_CAPTURE')) {
        $file = getenv('STEKJES_MAIL_CAPTURE');
        file_put_contents(
            $file,
            json_encode(['to' => $to, 'subject' => $subject, 'body' => $bodyText], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );
        return true;
    }

    if ($host === '' || $fromEmail === '' || $pass === '') {
        error_log('stekjes_mail: SMTP niet geconfigureerd');
        return false;
    }

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        error_log("stekjes_mail: connect mislukt: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 15);

    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };
    $expect = static function (string $resp, string $code) : bool {
        return str_starts_with($resp, $code);
    };

    try {
        if (!$expect($read(), '220')) {
            throw new RuntimeException('geen 220');
        }
        $write('EHLO gratis-stekjes.local');
        $ehlo = $read();
        if (!$expect($ehlo, '250')) {
            throw new RuntimeException('EHLO mislukt');
        }
        if ($enc === 'tls') {
            $write('STARTTLS');
            if (!$expect($read(), '220')) {
                throw new RuntimeException('STARTTLS mislukt');
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS mislukt');
            }
            $write('EHLO gratis-stekjes.local');
            if (!$expect($read(), '250')) {
                throw new RuntimeException('EHLO na TLS mislukt');
            }
        }

        $write('AUTH LOGIN');
        if (!$expect($read(), '334')) {
            throw new RuntimeException('AUTH');
        }
        $write(base64_encode($user));
        if (!$expect($read(), '334')) {
            throw new RuntimeException('USER');
        }
        $write(base64_encode($pass));
        if (!$expect($read(), '235')) {
            throw new RuntimeException('PASS');
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        if (!$expect($read(), '250')) {
            throw new RuntimeException('MAIL FROM');
        }
        $write('RCPT TO:<' . $to . '>');
        if (!$expect($read(), '250')) {
            throw new RuntimeException('RCPT TO');
        }
        $write('DATA');
        if (!$expect($read(), '354')) {
            throw new RuntimeException('DATA');
        }

        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $fromEmail),
            'To: <' . $to . '>',
            'Subject: ' . stekjes_mail_encode_subject($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@local>',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $bodyText) . "\r\n.";
        $write($payload);
        if (!$expect($read(), '250')) {
            throw new RuntimeException('DATA end');
        }
        $write('QUIT');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('stekjes_mail: ' . $e->getMessage());
        fclose($fp);
        return false;
    }
}

function stekjes_mail_encode_subject(string $subject): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $subject)) {
        return $subject;
    }
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function stekjes_site_url(string $path = '/'): string
{
    return stekjes_abs_url($path);
}

function stekjes_mail_confirm(string $email, string $token): bool
{
    $site = stekjes_site_name();
    $url = stekjes_site_url('/account/bevestigen.php?token=' . rawurlencode($token));
    $body = "Hoi,\n\nBevestig je aanmelding bij $site via deze link:\n$url\n\nHeb je je niet aangemeld? Negeer deze mail.\n\nGroet,\nJoris ($site)\n";
    return stekjes_send_mail($email, 'Bevestig je aanmelding bij ' . $site, $body);
}

function stekjes_mail_reset(string $email, string $token): bool
{
    $site = stekjes_site_name();
    $url = stekjes_site_url('/account/wachtwoord-reset.php?token=' . rawurlencode($token));
    $body = "Hoi,\n\nJe kunt een nieuw wachtwoord instellen via:\n$url\n\nDe link is een uur geldig. Heb je dit niet gevraagd? Negeer deze mail.\n\nGroet,\nJoris ($site)\n";
    return stekjes_send_mail($email, 'Nieuw wachtwoord voor ' . $site, $body);
}

function stekjes_mail_new_message(string $email): bool
{
    $site = stekjes_site_name();
    $url = stekjes_site_url('/account/inbox.php');
    $body = "Hoi,\n\nJe hebt een nieuw bericht op $site.\nLog in om het te lezen: $url\n\n(In deze mail staat het bericht zelf niet, en ook geen contactgegevens van de ander.)\n\nGroet,\nJoris ($site)\n";
    return stekjes_send_mail($email, 'Je hebt een nieuw bericht op ' . $site, $body);
}

function stekjes_mail_helper_confirm(string $email, string $token): bool
{
    $site = stekjes_site_name();
    $url = stekjes_site_url('/helpers/bevestigen.php?token=' . rawurlencode($token));
    $body = "Hoi,\n\nBevestig je aanmelding als helper bij $site via deze link:\n$url\n\nHeb je je niet aangemeld? Negeer deze mail.\n\nGroet,\nJoris ($site)\n";
    return stekjes_send_mail($email, 'Bevestig je helper-aanmelding bij ' . $site, $body);
}
