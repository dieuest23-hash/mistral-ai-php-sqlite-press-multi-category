<?php
// api_mistral.php — Mistral API helper
// Gestion: timeout long, retry, JSON safe, streaming-ready

define('MISTRAL_API_KEY', getenv('MISTRAL_API_KEY') ?: ' pwl2CFGont8ohtanaGCHGA8nAHd3WpFo
 ');
define('MISTRAL_URL',     'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL',   'mistral-large-2411');

// Augmenter drastiquement les timeouts PHP pour les requêtes longues
set_time_limit(300);
ini_set('max_execution_time', 300);

/**
 * Appel Mistral avec retry et gestion d'erreurs robuste.
 * Retourne ['ok' => true, 'content' => '...'] ou ['ok' => false, 'error' => '...']
 */
function mistralCall(array $messages, int $maxTokens = 2000, float $temperature = 0.7, int $retries = 2): array {
    $payload = [
        'model'       => MISTRAL_MODEL,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $maxTokens,
    ];

    $lastError = '';
    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        if ($attempt > 0) {
            // Délai exponentiel entre retries
            sleep(min(2 ** $attempt, 8));
        }

        $ch = curl_init(MISTRAL_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . MISTRAL_API_KEY,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 120,       // 2 min par requête
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $lastError = "CURL error: $curlErr";
            continue;
        }

        if ($httpCode === 429) {
            // Rate limit — pause 3s + retry
            sleep(3);
            $lastError = "Rate limited (429)";
            continue;
        }

        if ($httpCode !== 200) {
            $lastError = "HTTP $httpCode: " . substr($raw, 0, 200);
            continue;
        }

        // Parse JSON safely
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $lastError = "JSON decode error: " . json_last_error_msg();
            continue;
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            $lastError = "No content in response";
            continue;
        }

        return ['ok' => true, 'content' => trim($content)];
    }

    return ['ok' => false, 'error' => $lastError];
}

/**
 * Extrait un JSON depuis une réponse IA (qui peut contenir du texte autour).
 * Essaie d'abord un JSON pur, puis cherche le premier [...] ou {...}
 */
function extractJson(string $text): mixed {
    // Nettoyer les blocs markdown ```json ... ```
    $cleaned = preg_replace('/```json\s*/i', '', $text);
    $cleaned = preg_replace('/```\s*/', '', $cleaned);
    $cleaned = trim($cleaned);

    // Essai direct
    $decoded = json_decode($cleaned, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;

    // Chercher le premier tableau JSON [...]
    if (preg_match('/\[[\s\S]*\]/', $cleaned, $m)) {
        $decoded = json_decode($m[0], true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;
    }

    // Chercher le premier objet JSON {...}
    if (preg_match('/\{[\s\S]*\}/', $cleaned, $m)) {
        $decoded = json_decode($m[0], true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;
    }

    return null;
}

/**
 * Appel Mistral attendant une réponse JSON (array ou object).
 * Retourne le tableau décodé ou null en cas d'échec.
 */
function mistralJson(array $messages, int $maxTokens = 1000, float $temperature = 0.5): ?array {
    // Ajouter instruction JSON explicite
    $messages[] = [
        'role'    => 'system',
        'content' => 'Réponds UNIQUEMENT avec du JSON valide, sans texte avant ni après, sans balises markdown.'
    ];

    $result = mistralCall($messages, $maxTokens, $temperature);
    if (!$result['ok']) return null;

    $decoded = extractJson($result['content']);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Fetch et parse un flux RSS Google News.
 * Retourne un tableau d'articles ['titre', 'lien', 'description', 'pubdate', 'source']
 */
function fetchRSS(string $query): array {
    $url = 'https://news.google.com/rss/search?q=' . urlencode($query) . '&hl=fr&gl=DZ&ceid=DZ:fr';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NewsBot/1.0)',
    ]);

    $xml = curl_exec($ch);
    curl_close($ch);

    if (!$xml) return [];

    // Supprimer les namespaces qui peuvent gêner SimpleXML
    $xml = preg_replace('/<\?xml[^?]*\?>/i', '', $xml);
    $xml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml);
    $xml = str_replace(['<dc:', '</dc:', '<media:', '</media:'], ['<dc_', '</dc_', '<media_', '</media_'], $xml);

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    libxml_clear_errors();

    if (!$feed) return [];

    $articles = [];
    $items    = $feed->channel->item ?? [];

    foreach ($items as $item) {
        $titre = html_entity_decode((string)($item->title ?? ''), ENT_QUOTES, 'UTF-8');
        $lien  = (string)($item->link ?? '');
        $desc  = html_entity_decode(strip_tags((string)($item->description ?? '')), ENT_QUOTES, 'UTF-8');
        $pub   = (string)($item->pubDate ?? '');
        $src   = (string)($item->source ?? '');

        // Nettoyer le titre (Google News y inclut souvent le nom de source)
        $titre = preg_replace('/\s*-\s*[^-]+$/', '', $titre);

        if ($titre && $lien) {
            $articles[] = [
                'titre'       => mb_substr($titre, 0, 300),
                'lien'        => $lien,
                'description' => mb_substr($desc, 0, 1000),
                'pubdate'     => $pub,
                'source'      => $src,
            ];
        }

        if (count($articles) >= 15) break;
    }

    return $articles;
}
