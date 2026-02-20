<?php
/**
 * Reçoit le JSON du formulaire Manufa et crée une page dans la base Notion.
 * Tous les champs du formulaire sont mappés vers les propriétés de la base "Événements en direct".
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$apiKey = notion_api_key();
$databaseId = notion_database_id();

if ($apiKey === '' || $databaseId === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'NOTION_API_KEY ou NOTION_DATABASE_ID manquant (config serveur).',
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Body JSON invalide.']);
    exit;
}

// Optional URL param fallback: ?event_type=... (or legacy ?type=...)
$eventTypeParam = '';
if (isset($_GET['event_type']) && is_string($_GET['event_type'])) {
    $eventTypeParam = trim($_GET['event_type']);
} elseif (isset($_GET['type']) && is_string($_GET['type'])) {
    $eventTypeParam = trim($_GET['type']);
}
if ($eventTypeParam !== '') {
    $map = [
        'reunions' => 'Réunions et conférence',
        'production-evenementielle' => 'Production événementielle',
        'regie-live' => 'Régie audiovisuelle et live streaming',
    ];
    $slug = strtolower($eventTypeParam);
    $eventTypeValue = $map[$slug] ?? str_replace('-', ' ', $eventTypeParam);
    if (!isset($payload['event']) || !is_array($payload['event'])) {
        $payload['event'] = [];
    }
    if (empty($payload['event']['event_type']) && $eventTypeValue !== '') {
        $payload['event']['event_type'] = $eventTypeValue;
    }
}

/** Sanitize string for Notion: strip tags, trim, limit length */
function sanitizeString($value, int $maxLength = 2000): string
{
    if (!is_string($value)) {
        return '';
    }
    $s = trim(strip_tags($value));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    return strlen($s) > $maxLength ? substr($s, 0, $maxLength) : $s;
}

/** Validate and sanitize payload; returns [error message] or null and sanitizes $payload in place */
function validateAndSanitizePayload(array &$payload): ?string
{
    $required = [
        'general' => ['name_company', 'event_date', 'contact_number', 'location'],
    ];
    foreach ($required as $group => $fields) {
        $data = $payload[$group] ?? [];
        if (!is_array($data)) {
            $payload[$group] = [];
            $data = [];
        }
        foreach ($fields as $field) {
            $v = $data[$field] ?? '';
            $v = is_string($v) ? trim($v) : '';
            if ($v === '') {
                $labels = [
                    'name_company' => 'Nom / Entreprise',
                    'event_date' => 'Date de l\'événement',
                    'contact_number' => 'Numéro ou contact',
                    'location' => 'Lieu',
                ];
                return 'Champ requis manquant : ' . ($labels[$field] ?? $field);
            }
            $payload[$group][$field] = $v;
        }
    }

    $g = &$payload['general'];
    $g['name_company'] = sanitizeString($g['name_company'] ?? '', 200);
    $g['location'] = sanitizeString($g['location'] ?? '', 500);
    $g['duration'] = sanitizeString($g['duration'] ?? '', 200);
    $g['contact_number'] = sanitizeString($g['contact_number'] ?? '', 50);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $g['event_date'] ?? '')) {
        return 'Date de l\'événement invalide (attendu : AAAA-MM-JJ).';
    }
    $date = $g['event_date'];
    $year = (int) substr($date, 0, 4);
    if ($year < 2000 || $year > 2100) {
        return 'Date de l\'événement invalide : l\'année doit être entre 2000 et 2100 (4 chiffres).';
    }
    if (date('Y-m-d', strtotime($date)) !== $date) {
        return 'Date de l\'événement invalide.';
    }

    $allowedRadio = [
        'broadcast_type' => ['publique', 'privee'],
        'simulcast' => ['oui', 'non'],
        'interaction' => ['oui', 'non'],
        'bonded' => ['oui', 'non', 'a_evaluer'],
        'scouting' => ['oui', 'non', 'a_evaluer'],
        'graphics' => ['oui', 'non', 'a_evaluer'],
        'translation' => ['oui', 'non', 'a_evaluer'],
    ];
    $event = &$payload['event'];
    if (!is_array($event)) {
        $payload['event'] = [];
        $event = &$payload['event'];
    }
    foreach ($allowedRadio as $key => $allowed) {
        if (isset($event[$key]) && !in_array($event[$key], $allowed, true)) {
            $event[$key] = '';
        }
    }
    $event['event_type'] = sanitizeString($event['event_type'] ?? '', 200);
    $event['interaction_note'] = sanitizeString($event['interaction_note'] ?? '', 500);
    if (isset($event['platforms']) && is_array($event['platforms'])) {
        $event['platforms'] = array_slice(array_map(function ($v) {
            return sanitizeString($v, 100);
        }, array_filter($event['platforms'], 'is_string')), 0, 20);
    } else {
        $event['platforms'] = [];
    }

    $tech = &$payload['tech'];
    if (!is_array($tech)) {
        $payload['tech'] = [];
        $tech = &$payload['tech'];
    }
    $tech['camera_count'] = sanitizeString($tech['camera_count'] ?? '', 50);
    $tech['capture_type'] = sanitizeString($tech['capture_type'] ?? '', 200);
    $tech['audio_source'] = sanitizeString($tech['audio_source'] ?? '', 200);
    if (isset($tech['mics_needed']) && is_array($tech['mics_needed'])) {
        $tech['mics_needed'] = array_slice(array_map(function ($v) {
            return sanitizeString($v, 50);
        }, array_filter($tech['mics_needed'], 'is_string')), 0, 10);
    } else {
        $tech['mics_needed'] = [];
    }
    if (isset($tech['extra_content']) && is_array($tech['extra_content'])) {
        $tech['extra_content'] = array_slice(array_map(function ($v) {
            return sanitizeString($v, 100);
        }, array_filter($tech['extra_content'], 'is_string')), 0, 10);
    } else {
        $tech['extra_content'] = [];
    }

    $logistics = &$payload['logistics'];
    if (!is_array($logistics)) {
        $payload['logistics'] = [];
        $logistics = &$payload['logistics'];
    }
    $logistics['internet'] = sanitizeString($logistics['internet'] ?? '', 200);

    $post = &$payload['post'];
    if (!is_array($post)) {
        $payload['post'] = [];
        $post = &$payload['post'];
    }
    $post['recording'] = sanitizeString($post['recording'] ?? '', 200);

    $contact = &$payload['contact'];
    if (!is_array($contact)) {
        $payload['contact'] = [];
        $contact = &$payload['contact'];
    }
    $email = sanitizeString($contact['email'] ?? '', 254);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Adresse e-mail invalide.';
    }
    $contact['email'] = $email;
    $contact['notes'] = sanitizeString($contact['notes'] ?? '', 2000);

    if (!empty($payload['_hp'])) {
        return 'Soumission refusée.';
    }

    return null;
}

$validationError = validateAndSanitizePayload($payload);
if ($validationError !== null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $validationError]);
    exit;
}

function truncate(string $str, int $max): string
{
    return strlen($str) <= $max ? $str : substr($str, 0, $max - 3) . '...';
}

function rt(string $s, int $max = 2000): array
{
    $s = trim($s);
    if ($s === '') {
        return [];
    }
    return [['text' => ['content' => truncate($s, $max)]]];
}

function buildNotionProperties(array $payload): array
{
    $general = $payload['general'] ?? [];
    $event = $payload['event'] ?? [];
    $tech = $payload['tech'] ?? [];
    $logistics = $payload['logistics'] ?? [];
    $post = $payload['post'] ?? [];
    $contact = $payload['contact'] ?? [];

    $title = $general['name_company'] ?? 'Sans nom';
    $props = [
        'Nom' => [
            'title' => [['text' => ['content' => truncate($title, 200)]]],
        ],
    ];

    $date = $general['event_date'] ?? null;
    if ($date !== null && $date !== '') {
        $props['Date événement'] = ['date' => ['start' => $date]];
    }

    // Général
    if (!empty($general['location'])) {
        $props['Lieu'] = ['rich_text' => rt($general['location'])];
    }
    if (!empty($general['duration'])) {
        $props['Durée'] = ['rich_text' => rt($general['duration'])];
    }
    if (!empty($general['contact_number'])) {
        $props['Contact'] = ['phone_number' => truncate($general['contact_number'], 200)];
    }

    // Helpers for Notion select fields
    $toSelect = static function ($value): ?array {
        if (!is_string($value)) {
            return null;
        }
        $name = trim($value);
        if ($name === '') {
            return null;
        }
        return ['select' => ['name' => truncate($name, 100)]];
    };
    $toMultiSelect = static function ($values): ?array {
        if (!is_array($values) || $values === []) {
            return null;
        }
        $items = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $name = trim($value);
            if ($name === '') {
                continue;
            }
            $items[] = ['name' => truncate($name, 100)];
        }
        if ($items === []) {
            return null;
        }
        return ['multi_select' => $items];
    };

    // Événement
    if (!empty($event['event_type'])) {
        $select = $toSelect($event['event_type']);
        if ($select !== null) {
            $props['Type événement'] = $select;
        }
    }
    if (!empty($event['broadcast_type'])) {
        $v = $event['broadcast_type'] === 'publique' ? 'Publique' : 'Privée';
        $select = $toSelect($v);
        if ($select !== null) {
            $props['Diffusion'] = $select;
        }
    }
    if (!empty($event['platforms']) && is_array($event['platforms'])) {
        $multi = $toMultiSelect($event['platforms']);
        if ($multi !== null) {
            $props['Plateformes'] = $multi;
        }
    }
    if (!empty($event['simulcast'])) {
        $v = $event['simulcast'] === 'oui' ? 'Oui' : 'Non';
        $select = $toSelect($v);
        if ($select !== null) {
            $props['Simulcast'] = $select;
        }
    }
    if (!empty($event['interaction'])) {
        $v = $event['interaction'] === 'oui' ? 'Oui' : 'Non';
        $select = $toSelect($v);
        if ($select !== null) {
            $props['Interaction Q&R'] = $select;
        }
    }
    if (!empty($event['interaction_note'])) {
        $props['Détails interaction'] = ['rich_text' => rt($event['interaction_note'])];
    }

    // Technique
    if (!empty($tech['camera_count'])) {
        $select = $toSelect($tech['camera_count']);
        if ($select !== null) {
            $props['Nombre caméras'] = $select;
        }
    }
    if (!empty($tech['capture_type'])) {
        $select = $toSelect($tech['capture_type']);
        if ($select !== null) {
            $props['Type captation'] = $select;
        }
    }
    if (!empty($tech['audio_source'])) {
        $select = $toSelect($tech['audio_source']);
        if ($select !== null) {
            $props['Gestion du son'] = $select;
        }
    }
    if (!empty($tech['mics_needed']) && is_array($tech['mics_needed'])) {
        $multi = $toMultiSelect($tech['mics_needed']);
        if ($multi !== null) {
            $props['Micros nécessaires'] = $multi;
        }
    }
    if (!empty($tech['extra_content']) && is_array($tech['extra_content'])) {
        $multi = $toMultiSelect($tech['extra_content']);
        if ($multi !== null) {
            $props['Contenu additionnel'] = $multi;
        }
    }

    // Logistique
    if (!empty($logistics['internet'])) {
        $select = $toSelect($logistics['internet']);
        if ($select !== null) {
            $props['Internet'] = $select;
        }
    }
    if (!empty($logistics['bonded'])) {
        $v = $logistics['bonded'];
        $select = $toSelect($v === 'a_evaluer' ? 'À évaluer' : ($v === 'oui' ? 'Oui' : 'Non'));
        if ($select !== null) {
            $props['Kit 4G/5G Starlink'] = $select;
        }
    }
    if (!empty($logistics['scouting'])) {
        $v = $logistics['scouting'];
        $select = $toSelect($v === 'a_evaluer' ? 'À évaluer' : ($v === 'oui' ? 'Oui' : 'Non'));
        if ($select !== null) {
            $props['Repérage technique'] = $select;
        }
    }

    // Post-production
    if (!empty($post['recording'])) {
        $select = $toSelect($post['recording']);
        if ($select !== null) {
            $props['Enregistrement'] = $select;
        }
    }
    if (!empty($post['graphics'])) {
        $v = $post['graphics'];
        $select = $toSelect($v === 'a_evaluer' ? 'À évaluer' : ($v === 'oui' ? 'Oui' : 'Non'));
        if ($select !== null) {
            $props['Habillage graphique'] = $select;
        }
    }
    if (!empty($post['translation'])) {
        $v = $post['translation'];
        $select = $toSelect($v === 'a_evaluer' ? 'À évaluer' : ($v === 'oui' ? 'Oui' : 'Non'));
        if ($select !== null) {
            $props['Traduction'] = $select;
        }
    }

    // Contact (Notion type: email)
    if (!empty($contact['email'])) {
        $props['Email'] = ['email' => truncate($contact['email'], 254)];
    }

    // Notes : notes contact + récap optionnel
    $notesParts = array_filter([
        !empty($contact['notes']) ? $contact['notes'] : null,
        '---',
        'JSON: ' . truncate(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 1500),
    ]);
    $notesContent = implode("\n\n", $notesParts);
    if ($notesContent !== '---') {
        $props['Notes'] = ['rich_text' => rt($notesContent, 2000)];
    }

    return $props;
}

$properties = buildNotionProperties($payload);

$body = [
    'parent' => ['database_id' => $databaseId],
    'properties' => $properties,
];

$ch = curl_init('https://api.notion.com/v1/pages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Notion-Version: 2022-06-28',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($data === null) {
    $data = [];
}

if ($httpCode < 200 || $httpCode >= 300) {
    $msg = $data['message'] ?? $data['code'] ?? 'Notion API ' . $httpCode;
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Erreur Notion: ' . $msg]);
    exit;
}

// Envoi d’une copie par email pour suivi immédiat (SMTP si configuré, sinon mail())
$notificationEmail = form_notification_email();
if ($notificationEmail !== '') {
    $g = $payload['general'] ?? [];
    $c = $payload['contact'] ?? [];
    $e = $payload['event'] ?? [];
    $subject = 'Nouvelle demande de devis Manufa — ' . ($g['name_company'] ?? 'Sans nom');
    $lines = [
        'Nouvelle demande de devis (formulaire Manufa)',
        '--------------------------------------------',
        'Entreprise : ' . ($g['name_company'] ?? '—'),
        'Contact / Tél : ' . ($g['contact_number'] ?? '—'),
        'Date événement : ' . ($g['event_date'] ?? '—'),
        'Lieu : ' . ($g['location'] ?? '—'),
        'Durée : ' . ($g['duration'] ?? '—'),
        'Type d\'événement : ' . ($e['event_type'] ?? '—'),
        'Email : ' . ($c['email'] ?? '—'),
        '',
        'Détails complets (JSON) :',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ];
    $body = implode("\n", $lines);
    $fromEmail = smtp_user();
    $fromName = 'Formulaire Manufa';

    if (smtp_password() !== '') {
        require_once __DIR__ . '/smtp-send.php';
        [$mailOk, ] = @send_mail_smtp($notificationEmail, $subject, $body, $fromEmail, $fromName);
    } else {
        $headers = "Content-Type: text/plain; charset=UTF-8\r\nFrom: $fromName <$fromEmail>\r\n";
        @mail($notificationEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }
}

echo json_encode([
    'ok' => true,
    'message' => 'Demande envoyée ✅',
    'id' => $data['id'] ?? null,
]);
