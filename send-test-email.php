<?php
/**
 * Envoi d'un email de test (exécuter en CLI ou via navigateur).
 * Usage: php send-test-email.php [email]
 * Ou définir FORM_SMTP_PASSWORD dans .env puis ouvrir send-test-email.php?to=adagbeleandro55@gmail.com
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp-send.php';

$to = $argv[1] ?? ($_GET['to'] ?? 'adagbeleandro55@gmail.com');
$to = trim($to);
if ($to === '') {
    $to = 'adagbeleandro55@gmail.com';
}

$subject = 'Test formulaire Manufa — ' . date('d/m/Y H:i');
$body = "Ceci est un email de test envoyé depuis le formulaire Manufa.\n\n";
$body .= "Date : " . date('Y-m-d H:i:s') . "\n";
$body .= "SMTP : " . smtp_host() . ':' . smtp_port() . "\n";

$fromEmail = smtp_user();
$fromName = 'Formulaire Manufa (test)';

if (smtp_password() === '') {
    if (php_sapi_name() === 'cli') {
        echo "Définir FORM_SMTP_PASSWORD dans .env puis relancer.\n";
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Définir FORM_SMTP_PASSWORD dans .env pour envoyer le test.";
    }
    exit(1);
}

[$ok, $smtpError] = send_mail_smtp($to, $subject, $body, $fromEmail, $fromName);

if (php_sapi_name() === 'cli') {
    echo $ok ? "Email de test envoyé à $to\n" : "Échec : $smtpError\n";
    exit($ok ? 0 : 1);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => $ok,
    'to' => $to,
    'message' => $ok ? 'Email de test envoyé.' : ('Échec envoi SMTP : ' . $smtpError),
    'smtp_error' => $ok ? null : $smtpError,
]);
