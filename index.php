<?php
/**
 * Formulaire Devis Live — Multi-étapes + LocalStorage + CRM
 * Version standalone (sans WordPress).
 * Soumet en JSON vers ce fichier ; le PHP forward vers un webhook CRM si configuré.
 */

// ——— Config (à adapter) ———
$GM_QUOTE_WEBHOOK = '';  // ex: https://ton-crm.com/webhook/lead
$GM_QUOTE_SECRET  = '';  // envoyé en header X-GM-Secret

// ——— Traitement soumission JSON ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $ctype = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (strpos($ctype, 'application/json') !== false && $raw !== false) {
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = [];

        // Honeypot
        if (!empty($data['_hp'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }

        // Sanitize (même structure que le plugin WP)
        $payload = [
            'meta' => [
                'source'     => 'standalone_php_devis_live',
                'submitted'  => date('Y-m-d H:i:s'),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8'), 0, 500) : '',
                'ip'         => isset($_SERVER['REMOTE_ADDR']) ? htmlspecialchars($_SERVER['REMOTE_ADDR'], ENT_QUOTES, 'UTF-8') : '',
            ],
            'general' => [
                'name_company' => htmlspecialchars($data['general']['name_company'] ?? '', ENT_QUOTES, 'UTF-8'),
                'event_date'   => htmlspecialchars($data['general']['event_date'] ?? '', ENT_QUOTES, 'UTF-8'),
                'location'     => htmlspecialchars($data['general']['location'] ?? '', ENT_QUOTES, 'UTF-8'),
                'duration'     => htmlspecialchars($data['general']['duration'] ?? '', ENT_QUOTES, 'UTF-8'),
            ],
            'event' => [
                'event_type'       => htmlspecialchars($data['event']['event_type'] ?? '', ENT_QUOTES, 'UTF-8'),
                'broadcast_type'   => htmlspecialchars($data['event']['broadcast_type'] ?? '', ENT_QUOTES, 'UTF-8'),
                'platforms'        => array_map(function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }, (array)($data['event']['platforms'] ?? [])),
                'simulcast'        => htmlspecialchars($data['event']['simulcast'] ?? '', ENT_QUOTES, 'UTF-8'),
                'interaction'      => htmlspecialchars($data['event']['interaction'] ?? '', ENT_QUOTES, 'UTF-8'),
                'interaction_note' => htmlspecialchars($data['event']['interaction_note'] ?? '', ENT_QUOTES, 'UTF-8'),
            ],
            'tech' => [
                'camera_count'  => htmlspecialchars($data['tech']['camera_count'] ?? '', ENT_QUOTES, 'UTF-8'),
                'capture_type'  => htmlspecialchars($data['tech']['capture_type'] ?? '', ENT_QUOTES, 'UTF-8'),
                'audio_source'  => htmlspecialchars($data['tech']['audio_source'] ?? '', ENT_QUOTES, 'UTF-8'),
                'mics_needed'   => array_map(function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }, (array)($data['tech']['mics_needed'] ?? [])),
                'extra_content' => array_map(function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }, (array)($data['tech']['extra_content'] ?? [])),
            ],
            'logistics' => [
                'internet' => htmlspecialchars($data['logistics']['internet'] ?? '', ENT_QUOTES, 'UTF-8'),
                'bonded'   => htmlspecialchars($data['logistics']['bonded'] ?? '', ENT_QUOTES, 'UTF-8'),
                'scouting' => htmlspecialchars($data['logistics']['scouting'] ?? '', ENT_QUOTES, 'UTF-8'),
            ],
            'post' => [
                'recording'   => htmlspecialchars($data['post']['recording'] ?? '', ENT_QUOTES, 'UTF-8'),
                'graphics'    => htmlspecialchars($data['post']['graphics'] ?? '', ENT_QUOTES, 'UTF-8'),
                'translation' => htmlspecialchars($data['post']['translation'] ?? '', ENT_QUOTES, 'UTF-8'),
            ],
            'contact' => [
                'email' => filter_var($data['contact']['email'] ?? '', FILTER_SANITIZE_EMAIL),
                'phone' => htmlspecialchars($data['contact']['phone'] ?? '', ENT_QUOTES, 'UTF-8'),
                'notes' => htmlspecialchars($data['contact']['notes'] ?? '', ENT_QUOTES, 'UTF-8'),
            ],
        ];

        $missing = [];
        if (empty($payload['general']['name_company'])) $missing[] = 'Nom/Entreprise';
        if (empty($payload['general']['event_date']))   $missing[] = 'Date';
        if (empty($payload['contact']['phone']))       $missing[] = 'Téléphone';
        if (!empty($missing)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Champs requis manquants: ' . implode(', ', $missing) . '.']);
            exit;
        }

        if (!empty($GM_QUOTE_WEBHOOK)) {
            $headers = ['Content-Type' => 'application/json; charset=utf-8'];
            if ($GM_QUOTE_SECRET !== '') $headers['X-GM-Secret'] = $GM_QUOTE_SECRET;
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => implode("\r\n", array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers)),
                    'content' => json_encode($payload),
                    'timeout' => 15,
                ]
            ]);
            $resp = @file_get_contents($GM_QUOTE_WEBHOOK, false, $ctx);
            $code = 200;
            if (isset($http_response_header)) {
                preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $m);
                if (!empty($m[1])) $code = (int)$m[1];
            }
            if ($code < 200 || $code >= 300) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(502);
                echo json_encode([
                    'ok' => false,
                    'message' => 'CRM a répondu avec code ' . $code,
                    'crm_body' => $resp !== false ? substr($resp, 0, 500) : '',
                ]);
                exit;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => 'Demande envoyée ✅']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de devis — Streaming / Direct</title>
</head>
<body>
    <div class="gmq-wrap" data-endpoint="">
        <div class="gmq-card">
            <div class="gmq-head">
                <div>
                    <div class="gmq-title">Demande de devis — Streaming / Direct</div>
                    <div class="gmq-sub">Formulaire multi-étapes (sauvegarde automatique) 💡</div>
                </div>
                <div class="gmq-progress">
                    <div class="gmq-progress-bar"><span id="gmqBar"></span></div>
                    <div class="gmq-progress-text"><span id="gmqStepText">Étape 1/6</span></div>
                </div>
            </div>

            <form id="gmqForm" novalidate>
                <input type="text" name="_hp" id="gmqHp" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute; left:-9999px; height:1px; width:1px; opacity:0;">

                <section class="gmq-step is-active" data-step="1">
                    <h3 class="gmq-h3">1) Informations générales</h3>
                    <div class="gmq-grid">
                        <div class="gmq-field">
                            <label>Nom / Entreprise <span class="gmq-req">*</span></label>
                            <input type="text" name="general.name_company" placeholder="Ex: Pandore / SUNU..." required>
                        </div>
                        <div class="gmq-field">
                            <label>Date de l'événement <span class="gmq-req">*</span></label>
                            <input type="date" name="general.event_date" required>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Lieu (Ville + Nom de la salle) <span class="gmq-req">*</span></label>
                            <input type="text" name="general.location" placeholder="Ex: Lomé — Hôtel 2 Février" required>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Durée totale du direct</label>
                            <input type="text" name="general.duration" placeholder="Ex: 2h / 1 journée / 3 jours">
                        </div>
                    </div>
                </section>

                <section class="gmq-step" data-step="2">
                    <h3 class="gmq-h3">2) Type d'événement & plateformes</h3>
                    <div class="gmq-grid">
                        <div class="gmq-field">
                            <label>Type d'événement</label>
                            <select name="event.event_type">
                                <option value="">Sélectionner...</option>
                                <option>Conférence</option>
                                <option>Mariage</option>
                                <option>Concert</option>
                                <option>Sport</option>
                                <option>Webinaire</option>
                                <option>Événement interne</option>
                                <option>Autre</option>
                            </select>
                        </div>
                        <div class="gmq-field">
                            <label>Diffusion</label>
                            <div class="gmq-radio">
                                <label><input type="radio" name="event.broadcast_type" value="publique"> Publique</label>
                                <label><input type="radio" name="event.broadcast_type" value="privee"> Privée</label>
                            </div>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Plateformes (multi-choix)</label>
                            <div class="gmq-chips">
                                <label><input type="checkbox" name="event.platforms[]" value="YouTube"> YouTube</label>
                                <label><input type="checkbox" name="event.platforms[]" value="Facebook"> Facebook</label>
                                <label><input type="checkbox" name="event.platforms[]" value="Zoom"> Zoom</label>
                                <label><input type="checkbox" name="event.platforms[]" value="Plateforme securisee"> Plateforme sécurisée</label>
                                <label><input type="checkbox" name="event.platforms[]" value="Autre"> Autre</label>
                            </div>
                        </div>
                        <div class="gmq-field">
                            <label>Multidiffusion (Simulcast)</label>
                            <div class="gmq-radio">
                                <label><input type="radio" name="event.simulcast" value="oui"> Oui</label>
                                <label><input type="radio" name="event.simulcast" value="non"> Non</label>
                            </div>
                        </div>
                        <div class="gmq-field">
                            <label>Interaction (Q&A à l'écran)</label>
                            <div class="gmq-radio">
                                <label><input type="radio" name="event.interaction" value="oui"> Oui</label>
                                <label><input type="radio" name="event.interaction" value="non"> Non</label>
                            </div>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Détails interaction (si oui)</label>
                            <input type="text" name="event.interaction_note" placeholder="Ex: Questions via WhatsApp / Slido / YouTube chat...">
                        </div>
                    </div>
                </section>

                <section class="gmq-step" data-step="3">
                    <h3 class="gmq-h3">3) Configuration technique (Audio & Vidéo)</h3>
                    <div class="gmq-grid">
                        <div class="gmq-field">
                            <label>Nombre de caméras</label>
                            <select name="tech.camera_count">
                                <option value="">Sélectionner...</option>
                                <option value="1">1 caméra</option>
                                <option value="2-3">2 à 3 caméras</option>
                                <option value="4+">4+ caméras</option>
                            </select>
                        </div>
                        <div class="gmq-field">
                            <label>Type de captation</label>
                            <select name="tech.capture_type">
                                <option value="">Sélectionner...</option>
                                <option>Fixe sur trépied</option>
                                <option>Caméraman mobile (HF)</option>
                                <option>Mixte</option>
                            </select>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Gestion du son</label>
                            <select name="tech.audio_source">
                                <option value="">Sélectionner...</option>
                                <option>Récupération flux externe (salle sonorisée)</option>
                                <option>Micros fournis par nos soins</option>
                                <option>Les deux</option>
                            </select>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Micros nécessaires (si fournis par nos soins)</label>
                            <div class="gmq-chips">
                                <label><input type="checkbox" name="tech.mics_needed[]" value="Cravate"> Cravate</label>
                                <label><input type="checkbox" name="tech.mics_needed[]" value="Main"> Main</label>
                                <label><input type="checkbox" name="tech.mics_needed[]" value="Table"> Table</label>
                            </div>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Contenu additionnel</label>
                            <div class="gmq-chips">
                                <label><input type="checkbox" name="tech.extra_content[]" value="Slides"> Slides (PowerPoint)</label>
                                <label><input type="checkbox" name="tech.extra_content[]" value="Videos"> Vidéos pré-enregistrées</label>
                                <label><input type="checkbox" name="tech.extra_content[]" value="Logos"> Logos / overlays</label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="gmq-step" data-step="4">
                    <h3 class="gmq-h3">4) Connectivité & logistique</h3>
                    <div class="gmq-grid">
                        <div class="gmq-field gmq-col2">
                            <label>Connexion Internet (sur place)</label>
                            <select name="logistics.internet">
                                <option value="">Sélectionner...</option>
                                <option>Ligne Ethernet dédiée (RJ45)</option>
                                <option>Wi-Fi (à tester)</option>
                                <option>Aucune / inconnue</option>
                            </select>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Faut-il prévoir un kit 4G/5G / Starlink ?</label>
                            <div class="gmq-radio">
                                <label><input type="radio" name="logistics.bonded" value="oui"> Oui</label>
                                <label><input type="radio" name="logistics.bonded" value="non"> Non</label>
                                <label><input type="radio" name="logistics.bonded" value="a_evaluer"> À évaluer</label>
                            </div>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Repérage technique en amont</label>
                            <div class="gmq-radio">
                                <label><input type="radio" name="logistics.scouting" value="oui"> Oui</label>
                                <label><input type="radio" name="logistics.scouting" value="non"> Non</label>
                                <label><input type="radio" name="logistics.scouting" value="a_evaluer"> À évaluer</label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="gmq-step" data-step="5">
                    <h3 class="gmq-h3">5) Post-production & options</h3>
                    <div class="gmq-grid">
                        <div class="gmq-field gmq-col2">
                            <label>Enregistrement</label>
                            <select name="post.recording">
                                <option value="">Sélectionner...</option>
                                <option>Fichier brut</option>
                                <option>Montage Best-of</option>
                                <option>Les deux</option>
                            </select>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Habillage graphique (lower thirds)</label>
                            <div class="gmq-radio">
                                <label><input type="radio" name="post.graphics" value="oui"> Oui</label>
                                <label><input type="radio" name="post.graphics" value="non"> Non</label>
                                <label><input type="radio" name="post.graphics" value="a_evaluer"> À évaluer</label>
                            </div>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Traduction / interprétation simultanée</label>
                            <div class="gmq-radio">
                                <label><input type="radio" name="post.translation" value="oui"> Oui</label>
                                <label><input type="radio" name="post.translation" value="non"> Non</label>
                                <label><input type="radio" name="post.translation" value="a_evaluer"> À évaluer</label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="gmq-step" data-step="6">
                    <h3 class="gmq-h3">6) Contact & récapitulatif</h3>
                    <div class="gmq-grid">
                        <div class="gmq-field">
                            <label>Email</label>
                            <input type="email" name="contact.email" placeholder="ex: contact@entreprise.com">
                        </div>
                        <div class="gmq-field">
                            <label>Téléphone <span class="gmq-req">*</span></label>
                            <input type="tel" name="contact.phone" placeholder="+228 ..." required>
                        </div>
                        <div class="gmq-field gmq-col2">
                            <label>Notes / contraintes</label>
                            <textarea name="contact.notes" rows="4" placeholder="Timing, besoins spécifiques, contraintes salle, etc."></textarea>
                        </div>
                    </div>
                    <div class="gmq-summary">
                        <div class="gmq-summary-title">Récapitulatif</div>
                        <pre id="gmqSummary"></pre>
                    </div>
                </section>

                <div class="gmq-actions">
                    <button type="button" class="gmq-btn gmq-btn-ghost" id="gmqBack">Retour</button>
                    <button type="button" class="gmq-btn gmq-btn-ghost" id="gmqReset">Réinitialiser</button>
                    <div class="gmq-spacer"></div>
                    <button type="button" class="gmq-btn" id="gmqNext">Suivant</button>
                    <button type="submit" class="gmq-btn gmq-btn-primary" id="gmqSubmit">Envoyer au CRM</button>
                </div>

                <div class="gmq-toast" id="gmqToast" role="status" aria-live="polite"></div>
            </form>
        </div>
    </div>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #0d0d0d; color: #fff; min-height: 100vh; }
        .gmq-wrap { max-width: 920px; margin: 20px auto; padding: 0 12px; }
        .gmq-card {
            border-radius: 18px;
            background: rgba(18,18,18,0.92);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.10);
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .gmq-head {
            padding: 18px 20px;
            display: flex; gap: 16px; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            background: rgba(255,255,255,0.03);
        }
        .gmq-title { font-size: 18px; font-weight: 700; letter-spacing: -0.02em; }
        .gmq-sub { font-size: 13px; opacity: 0.75; margin-top: 2px; }
        .gmq-progress { min-width: 240px; text-align: right; }
        .gmq-progress-bar { height: 8px; background: rgba(255,255,255,0.10); border-radius: 999px; overflow: hidden; }
        .gmq-progress-bar span { display: block; height: 100%; width: 0%; background: rgba(255,255,255,0.70); transition: width .25s ease; }
        .gmq-progress-text { font-size: 12px; opacity: 0.8; margin-top: 8px; }
        #gmqForm { padding: 18px 20px 20px; }
        .gmq-h3 { margin: 0 0 14px; font-size: 15px; font-weight: 700; opacity: 0.95; }
        .gmq-req { color: rgba(255,255,255,0.75); font-weight: 600; }
        .gmq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .gmq-col2 { grid-column: 1 / -1; }
        .gmq-field label { display: block; font-size: 12px; opacity: 0.85; margin-bottom: 8px; }
        .gmq-field input, .gmq-field select, .gmq-field textarea {
            width: 100%;
            border-radius: 12px;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            color: #fff;
            outline: none;
            transition: border-color .2s ease, background-color .2s ease;
        }
        .gmq-field input:focus, .gmq-field select:focus, .gmq-field textarea:focus {
            border-color: rgba(255,255,255,0.30);
            background: rgba(255,255,255,0.08);
        }
        .gmq-radio { display: flex; gap: 14px; flex-wrap: wrap; }
        .gmq-radio label { display: flex; align-items: center; gap: 8px; margin: 0; }
        .gmq-chips { display: flex; gap: 10px; flex-wrap: wrap; }
        .gmq-chips label {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 10px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.04);
            margin: 0;
            font-size: 13px;
            cursor: pointer;
        }
        .gmq-step { display: none; animation: gmqIn .22s ease; }
        .gmq-step.is-active { display: block; }
        @keyframes gmqIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .gmq-actions {
            display: flex; gap: 10px; align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.10);
        }
        .gmq-spacer { flex: 1; }
        .gmq-btn {
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.10);
            color: #fff;
            border-radius: 12px;
            padding: 10px 14px;
            cursor: pointer;
            transition: transform .15s ease, background-color .2s ease, border-color .2s ease;
            font-weight: 600;
        }
        .gmq-btn:hover { transform: translateY(-1px); background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.25); }
        .gmq-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .gmq-btn-ghost { background: transparent; }
        .gmq-btn-primary { background: rgba(255,255,255,0.85); color: #111; border-color: transparent; }
        .gmq-btn-primary:hover { background: rgba(255,255,255,0.95); }
        .gmq-summary {
            margin-top: 14px;
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(0,0,0,0.25);
            border-radius: 12px;
            padding: 12px;
        }
        .gmq-summary-title { font-weight: 700; font-size: 13px; margin-bottom: 8px; opacity: 0.9; }
        #gmqSummary { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 12px; opacity: 0.85; }
        .gmq-toast {
            display: none;
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.08);
            font-size: 13px;
            opacity: 0.95;
        }
        @media (max-width: 720px) {
            .gmq-head { flex-direction: column; align-items: flex-start; }
            .gmq-progress { width: 100%; text-align: left; }
            .gmq-grid { grid-template-columns: 1fr; }
            .gmq-col2 { grid-column: auto; }
            .gmq-actions { flex-wrap: wrap; }
            .gmq-spacer { display: none; }
        }
    </style>

    <script>
    (function(){
        const wrap = document.querySelector('.gmq-wrap');
        if (!wrap) return;
        const ep = wrap.getAttribute('data-endpoint');
        const endpoint = (ep === null || ep === '') ? 'index.php' : ep;

        const form = document.getElementById('gmqForm');
        const steps = Array.from(document.querySelectorAll('.gmq-step'));
        const btnNext = document.getElementById('gmqNext');
        const btnBack = document.getElementById('gmqBack');
        const btnSubmit = document.getElementById('gmqSubmit');
        const btnReset = document.getElementById('gmqReset');
        const bar = document.getElementById('gmqBar');
        const stepText = document.getElementById('gmqStepText');
        const summary = document.getElementById('gmqSummary');
        const toast = document.getElementById('gmqToast');

        const LS_KEY = 'gm_devis_live_v1';
        let current = 1;
        const total = steps.length;

        function showToast(msg) {
            toast.textContent = msg;
            toast.style.display = 'block';
            setTimeout(function(){ toast.style.display = 'none'; }, 3500);
        }

        function setStep(n) {
            current = Math.min(Math.max(n, 1), total);
            steps.forEach(function(s){ s.classList.remove('is-active'); });
            const active = steps.find(function(s){ return parseInt(s.dataset.step, 10) === current; });
            if (active) active.classList.add('is-active');
            btnBack.disabled = (current === 1);
            btnNext.style.display = (current === total) ? 'none' : 'inline-block';
            btnSubmit.style.display = (current === total) ? 'inline-block' : 'none';
            const pct = Math.round((current - 1) / (total - 1) * 100);
            bar.style.width = pct + '%';
            stepText.textContent = 'Étape ' + current + '/' + total;
            if (current === total) updateSummary();
        }

        function getFormDataObject() {
            const obj = { general: {}, event: {}, tech: {}, logistics: {}, post: {}, contact: {}, _hp: document.getElementById('gmqHp') ? document.getElementById('gmqHp').value : '' };
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(function(el) {
                if (!el.name) return;
                const name = el.name;
                if (name === '_hp') return;
                const parts = name.replace(/\[\]$/, '').split('.');
                if (parts.length !== 2) return;
                const group = parts[0], field = parts[1];
                if (el.type === 'checkbox') {
                    if (!obj[group][field]) obj[group][field] = [];
                    if (el.checked) obj[group][field].push(el.value);
                    return;
                }
                if (el.type === 'radio') {
                    if (el.checked) obj[group][field] = el.value;
                    return;
                }
                obj[group][field] = el.value;
            });
            obj.event.platforms = obj.event.platforms || [];
            obj.tech.mics_needed = obj.tech.mics_needed || [];
            obj.tech.extra_content = obj.tech.extra_content || [];
            return obj;
        }

        function saveToLocal() { localStorage.setItem(LS_KEY, JSON.stringify(getFormDataObject())); }
        function clearLocal() { localStorage.removeItem(LS_KEY); }

        function loadFromLocal() {
            try {
                const raw = localStorage.getItem(LS_KEY);
                if (!raw) return;
                const obj = JSON.parse(raw);
                if (!obj || typeof obj !== 'object') return;
                Object.keys(obj).forEach(function(group) {
                    if (!obj[group] || typeof obj[group] !== 'object') return;
                    Object.keys(obj[group]).forEach(function(field) {
                        const value = obj[group][field];
                        const nameBase = group + '.' + field;
                        if (Array.isArray(value)) {
                            value.forEach(function(v) {
                                const cb = form.querySelector('input[name="' + nameBase + '[]"][value="' + String(v).replace(/"/g, '&quot;') + '"]');
                                if (cb) cb.checked = true;
                            });
                            return;
                        }
                        const r = form.querySelector('input[type="radio"][name="' + nameBase + '"][value="' + String(value).replace(/"/g, '&quot;') + '"]');
                        if (r) { r.checked = true; return; }
                        const el = form.querySelector('[name="' + nameBase + '"]');
                        if (el) el.value = value;
                    });
                });
            } catch(e) {}
        }

        function validateStep(stepNumber) {
            const step = steps.find(function(s){ return parseInt(s.dataset.step, 10) === stepNumber; });
            if (!step) return true;
            let ok = true;
            step.querySelectorAll('[required]').forEach(function(el) {
                const v = (el.value || '').trim();
                if (!v) {
                    ok = false;
                    el.style.borderColor = 'rgba(255,80,80,0.8)';
                    setTimeout(function(){ el.style.borderColor = ''; }, 1200);
                }
            });
            if (!ok) showToast('Veuillez remplir les champs requis (*) avant de continuer.');
            return ok;
        }

        function updateSummary() {
            summary.textContent = JSON.stringify(getFormDataObject(), null, 2);
        }

        form.addEventListener('input', saveToLocal);
        form.addEventListener('change', saveToLocal);

        btnNext.addEventListener('click', function() {
            if (!validateStep(current)) return;
            setStep(current + 1);
            saveToLocal();
        });
        btnBack.addEventListener('click', function() {
            setStep(current - 1);
            saveToLocal();
        });
        btnReset.addEventListener('click', function() {
            form.reset();
            clearLocal();
            setStep(1);
            showToast('Formulaire réinitialisé.');
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!validateStep(current)) return;
            const payload = getFormDataObject();
            if (!payload.general.name_company || !payload.general.event_date || !payload.contact.phone) {
                showToast('Champs requis manquants: Nom/Entreprise, Date, Téléphone.');
                return;
            }
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Envoi...';
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(res){ return res.json().then(function(json){ return { res: res, json: json }; }); })
            .then(function(o) {
                if (!o.res.ok || !o.json.ok) throw new Error(o.json.message || 'Erreur lors de l\'envoi.');
                clearLocal();
                form.reset();
                setStep(1);
                showToast('Demande envoyée ✅ Vous serez recontacté.');
            })
            .catch(function(err) {
                showToast(err.message || 'Erreur réseau. Réessayez.');
            })
            .then(function() {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Envoyer au CRM';
            });
        });

        loadFromLocal();
        setStep(1);
        saveToLocal();
    })();
    </script>
</body>
</html>
