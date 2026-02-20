<?php
/**
 * Configuration formulaire Manufa — Notion et endpoint.
 * Clés définies ici ; .env peut éventuellement les surcharger.
 */

declare(strict_types=1);

// Ne pas mettre de clé API en dur : définir NOTION_API_KEY et NOTION_DATABASE_ID dans .env
const NOTION_DATABASE_ID_DEFAULT = '30338b0dfbe3813ca21acab15b75273b';

if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
            $key = trim($m[1]);
            $value = trim($m[2]);
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

function notion_api_key(): string
{
    return (string) (getenv('NOTION_API_KEY') ?: '');
}

function notion_database_id(): string
{
    $id = getenv('NOTION_DATABASE_ID') ?: NOTION_DATABASE_ID_DEFAULT;
    return str_replace('-', '', (string) $id);
}

function submit_url(): string
{
    $base = (string) (getenv('FORM_BASE_URL') ?? '');
    if ($base !== '') {
        return rtrim($base, '/') . '/submit.php';
    }
    return 'submit.php';
}

/** Adresse email pour recevoir une copie de chaque demande de devis (suivi immédiat). */
function form_notification_email(): string
{
    return (string) (getenv('FORM_NOTIFICATION_EMAIL') ?: 'sales@manufa.agency');
}

/** SMTP : serveur d’envoi (ex. mail.manufa.agency). */
function smtp_host(): string
{
    return (string) (getenv('FORM_SMTP_HOST') ?: 'mail.manufa.agency');
}

/** SMTP : port (587 avec STARTTLS ou 465 en SMTPS). */
function smtp_port(): int
{
    $p = getenv('FORM_SMTP_PORT');
    return $p !== false && $p !== '' ? (int) $p : 587;
}

/** SMTP : identifiant (adresse complète obligatoire). */
function smtp_user(): string
{
    return (string) (getenv('FORM_SMTP_USER') ?: 'contact@manufa.agency');
}

/** SMTP : mot de passe. À définir dans .env (FORM_SMTP_PASSWORD), ne jamais committer. */
function smtp_password(): string
{
    return (string) (getenv('FORM_SMTP_PASSWORD') ?: '');
}
