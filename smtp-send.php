<?php
/**
 * Envoi d’email via SMTP (auth LOGIN, STARTTLS sur port 587).
 * Utilise les fonctions smtp_* de config.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * @return array{0: bool, 1: string} [success, errorMessage]
 */
function send_mail_smtp(string $to, string $subject, string $bodyPlain, string $fromEmail, string $fromName = ''): array
{
    $host = smtp_host();
    $port = smtp_port();
    $user = smtp_user();
    $password = smtp_password();

    if ($password === '') {
        return [false, 'FORM_SMTP_PASSWORD non défini dans .env'];
    }

    $errno = 0;
    $errstr = '';
    $protocol = ($port === 465) ? 'ssl://' : 'tcp://';
    $s = @stream_socket_client(
        $protocol . $host . ':' . $port,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT
    );
    if (!$s) {
        return [false, "Connexion impossible : $errstr ($errno)"];
    }

    $lastLine = '';
    $read = function () use ($s): string {
        $line = @fgets($s);
        return $line !== false ? trim($line) : '';
    };
    $send = function (string $line) use ($s): void {
        fwrite($s, $line . "\r\n");
    };
    $expect = function (int $code) use ($read, &$lastLine): bool {
        $lastLine = '';
        while (($line = $read()) !== '') {
            $lastLine = $line;
            if (strlen($line) < 4) {
                return false;
            }
            $num = (int) substr($line, 0, 3);
            $sep = $line[3];
            if ($num === $code && $sep === ' ') {
                return true;
            }
            if ($num === $code && $sep === '-') {
                continue;
            }
            return false;
        }
        return false;
    };

    if (!$expect(220)) {
        fclose($s);
        return [false, 'SMTP 220: ' . $lastLine];
    }

    $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if (!$expect(250)) {
        fclose($s);
        return [false, 'SMTP EHLO: ' . $lastLine];
    }

    if ($port === 587) {
        $send('STARTTLS');
        if (!$expect(220)) {
            fclose($s);
            return [false, 'SMTP STARTTLS: ' . $lastLine];
        }
        if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($s);
            return [false, 'Échec chiffrement TLS'];
        }
        $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$expect(250)) {
            fclose($s);
            return [false, 'SMTP EHLO après TLS: ' . $lastLine];
        }
    }

    $send('AUTH LOGIN');
    if (!$expect(334)) {
        fclose($s);
        return [false, 'SMTP AUTH LOGIN: ' . $lastLine];
    }
    $send(base64_encode($user));
    if (!$expect(334)) {
        fclose($s);
        return [false, 'SMTP user: ' . $lastLine];
    }
    $send(base64_encode($password));
    if (!$expect(235)) {
        fclose($s);
        return [false, 'SMTP auth (vérifiez identifiant/mot de passe): ' . $lastLine];
    }

    $send('MAIL FROM:<' . $user . '>');
    if (!$expect(250)) {
        fclose($s);
        return [false, 'SMTP MAIL FROM: ' . $lastLine];
    }
    $send('RCPT TO:<' . $to . '>');
    if (!$expect(250)) {
        fclose($s);
        return [false, 'SMTP RCPT TO: ' . $lastLine];
    }
    $send('DATA');
    if (!$expect(354)) {
        fclose($s);
        return [false, 'SMTP DATA: ' . $lastLine];
    }

    $fromHeader = $fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>' : $fromEmail;
    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $data = "From: $fromHeader\r\nTo: $to\r\nSubject: $subjectEnc\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
    $data .= $bodyPlain;
    $data = preg_replace('/\r?\n/', "\r\n", $data);
    $data = preg_replace('/^\./m', '..', $data);
    $send($data);
    $send('.');

    if (!$expect(250)) {
        fclose($s);
        return [false, 'SMTP envoi message: ' . $lastLine];
    }
    $send('QUIT');
    fclose($s);
    return [true, ''];
}
