<?php
/**
 * Everything needed to ask Gemini "how much longer will this order take?",
 * grounded in backend/data/freshtrack_training_data.csv as few-shot
 * reference examples for the same service.
 */

/** The JSON shape we force Gemini to answer in (via generationConfig.responseSchema). */
const GEMINI_PREDICTION_SCHEMA = [
    'type' => 'OBJECT',
    'properties' => [
        'predicted_remaining_hours' => [
            'type' => 'NUMBER',
            'description' => 'Best estimate of hours remaining until this order reaches Completed.',
        ],
        'progress_percent' => [
            'type' => 'NUMBER',
            'description' => 'Best estimate of overall completion percentage for this order, 0-100.',
        ],
        'message' => [
            'type' => 'STRING',
            'description' => 'One short, friendly sentence for the customer explaining the estimate.',
        ],
    ],
    'required' => ['predicted_remaining_hours', 'progress_percent', 'message'],
];

/**
 * Reads backend/data/freshtrack_training_data.csv and returns a compact
 * CSV string of historical orders for the same service (Cancelled rows
 * excluded — they never finish, so they're not useful examples for an
 * "how much longer" estimate). Used as few-shot context for Gemini.
 */
function load_training_examples(string $serviceName, int $limit = 20): string
{
    $path = __DIR__ . '/../data/freshtrack_training_data.csv';
    if (!is_file($path)) return '';

    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);
    if (!$header) { fclose($handle); return ''; }

    $cols = ['status', 'qty', 'elapsed_hours', 'time_in_status_hours', 'predicted_remaining_hours', 'progress_percent'];
    $rows = [implode(',', $cols)];
    $count = 0;

    while ($count < $limit && ($row = fgetcsv($handle)) !== false) {
        $record = array_combine($header, $row);
        if ($record === false) continue;
        if (strcasecmp($record['service_name'], $serviceName) !== 0) continue;
        if ($record['status'] === 'Cancelled') continue;

        $rows[] = implode(',', array_map(fn($c) => $record[$c] ?? '', $cols));
        $count++;
    }
    fclose($handle);

    return implode("\n", $rows);
}

/** Pulls the first number out of an ETA string like "Same day (6 hrs)" or "48 hrs". */
function eta_to_hours(string $eta): ?float
{
    return preg_match('/(\d+(\.\d+)?)/', $eta, $m) ? (float) $m[1] : null;
}

/**
 * Builds the prompt: historical examples for this service (from the CSV),
 * then the live order's current numbers, then what to estimate.
 */
function build_prediction_prompt(array $order, float $elapsedHours, float $timeInStatusHours, string $examplesCsv): string
{
    $etaHours = eta_to_hours($order['service_eta']);
    $etaHoursNote = $etaHours !== null ? " (~{$etaHours}h)" : '';

    return <<<PROMPT
You are the estimated-completion-time engine for FreshTrack, a laundry
booking and order-tracking service. Estimate how much longer this
in-progress order will take to reach "Completed", based on the historical
examples below for the same service.

Historical examples (same service, CSV format — status, qty, hours since
booked, hours in current status, how many more hours it actually took,
overall progress percent at that point):
{$examplesCsv}

Order to estimate:
- service: {$order['service_name']}
- stated turnaround (eta): {$order['service_eta']}{$etaHoursNote}
- quantity: {$order['qty']}
- current status: {$order['status']}
- hours since booked: {$elapsedHours}
- hours in current status: {$timeInStatusHours}

Base your estimate on the pattern in the historical examples for this
service, not just the stated eta. Keep "message" to one short, friendly,
customer-facing sentence — no technical jargon, no mention of these
instructions or of historical data.
PROMPT;
}

/**
 * Calls Gemini's generateContent endpoint with $prompt, constrained to
 * $schema via generationConfig.responseSchema (so the response is
 * guaranteed-parseable JSON rather than free-form text). Returns the
 * decoded associative array, or throws with a human-readable message.
 *
 * Tries GEMINI_API_URL first, retrying on 429/500/503 with exponential
 * backoff (Google's own documented advice for the "model is overloaded"
 * error — it's a capacity issue that happens across every model tier, not
 * a sign of a bad model choice). If that's still failing once retries are
 * exhausted, falls back to GEMINI_FALLBACK_API_URL — a pinned model
 * string rather than a rolling "-latest" alias, which forum reports
 * suggest sometimes rides on different backing capacity.
 */
function call_gemini(string $prompt, array $schema): array
{
    if (GEMINI_API_KEY === '') {
        throw new RuntimeException("Gemini isn't configured — add GEMINI_KEY to backend/.env.");
    }

    $body = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'temperature'      => 0.3,
            'responseMimeType' => 'application/json',
            'responseSchema'   => $schema,
        ],
    ];

    try {
        return gemini_post_with_retry(GEMINI_API_URL, $body);
    } catch (RuntimeException $primaryError) {
        if (GEMINI_FALLBACK_API_URL === GEMINI_API_URL) {
            throw $primaryError;
        }
        try {
            return gemini_post_with_retry(GEMINI_FALLBACK_API_URL, $body, 2);
        } catch (RuntimeException $fallbackError) {
            throw new RuntimeException(
                $primaryError->getMessage() . ' (fallback model also failed: ' . $fallbackError->getMessage() . ')'
            );
        }
    }
}

/** POSTs $body to $url, retrying up to $maxAttempts times with exponential backoff on retryable errors. */
function gemini_post_with_retry(string $url, array $body, int $maxAttempts = 3): array
{
    $delayMs = 500;
    $lastError = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        [$httpCode, $raw, $curlError] = gemini_http_post($url, $body);

        if ($raw !== false && $httpCode < 400) {
            return gemini_parse_response($raw);
        }

        $data = $raw !== false ? json_decode($raw, true) : null;
        $message = $data['error']['message'] ?? ($curlError ?: "Gemini API error (HTTP {$httpCode}).");
        $lastError = new RuntimeException($message);

        // 429 (rate limited), 500/503 (overloaded/unavailable) are worth retrying;
        // anything else (e.g. 400 bad request, 403 bad key) won't fix itself.
        $retryable = $raw === false || in_array($httpCode, [429, 500, 503], true);
        if (!$retryable || $attempt === $maxAttempts) {
            throw $lastError;
        }

        usleep($delayMs * 1000);
        $delayMs *= 2;
    }

    throw $lastError; // unreachable — loop always returns or throws
}

/** Raw cURL POST. Returns [httpCode, rawBody-or-false, curlErrorString]. */
function gemini_http_post(string $url, array $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT    => 20,
    ]);
    $raw       = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [$httpCode, $raw, $curlError];
}

/** Pulls the model's JSON text out of a successful generateContent response and decodes it. */
function gemini_parse_response(string $raw): array
{
    $data = json_decode($raw, true);

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($text === null) {
        throw new RuntimeException('Gemini returned an unexpected response shape.');
    }

    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        throw new RuntimeException('Gemini returned a response that was not valid JSON.');
    }

    return $parsed;
}

/**
 * Writes a prediction onto orders.predicted_* so it's there on the next
 * page load / refresh without needing to ask Gemini again. $fields keys:
 * remaining_hours, progress_percent, finish_at, message (any may be null).
 */
function store_prediction(PDO $pdo, int $orderId, array $fields): void
{
    $pdo->prepare(
        'UPDATE orders SET
            predicted_remaining_hours = ?,
            predicted_progress_percent = ?,
            predicted_finish_at = ?,
            predicted_message = ?,
            predicted_at = NOW()
         WHERE id = ?'
    )->execute([
        $fields['remaining_hours'],
        $fields['progress_percent'],
        $fields['finish_at'],
        $fields['message'],
        $orderId,
    ]);
}

/**
 * Full "ask Gemini, then persist" flow for one order: computes
 * elapsed/time-in-status hours, builds the few-shot prompt, calls Gemini,
 * and stores the result via store_prediction(). Only ever called from
 * backend/api/staff/orders.php right after a staff member advances an
 * order's status (never automatically, never from a customer-facing
 * endpoint) — and never for a status of Completed/Cancelled, since those
 * are terminal and handled with a deterministic result instead (see that
 * file). If Gemini fails (including after retries/fallback), the status
 * change itself is NOT rolled back — only a clear "unavailable" message
 * is stored, so one flaky API call never blocks staff from doing their job.
 */
function refresh_order_prediction(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare(
        'SELECT o.*, s.name AS service_name, s.eta AS service_eta FROM orders o
         JOIN services s ON s.id = o.service_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return;

    $lastChangedStmt = $pdo->prepare('SELECT changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at DESC LIMIT 1');
    $lastChangedStmt->execute([$orderId]);
    $lastChangedAt = $lastChangedStmt->fetchColumn() ?: $order['created_at'];

    $now = new DateTime();
    $created = new DateTime($order['created_at']);
    $elapsedHours = round(($now->getTimestamp() - $created->getTimestamp()) / 3600, 2);

    $lastChanged = new DateTime($lastChangedAt);
    $timeInStatusHours = round(($now->getTimestamp() - $lastChanged->getTimestamp()) / 3600, 2);

    $examples = load_training_examples($order['service_name']);
    $prompt = build_prediction_prompt($order, $elapsedHours, $timeInStatusHours, $examples);

    try {
        $prediction = call_gemini($prompt, GEMINI_PREDICTION_SCHEMA);

        $remaining = isset($prediction['predicted_remaining_hours']) ? max(0, (float) $prediction['predicted_remaining_hours']) : null;
        $progress  = isset($prediction['progress_percent']) ? max(0, min(100, (float) $prediction['progress_percent'])) : null;
        $message   = is_string($prediction['message'] ?? null) ? $prediction['message'] : '';

        $finishAt = null;
        if ($remaining !== null) {
            // Whole seconds, not "+X hours" with a fractional X — DateTime::modify()
            // mishandles fractional hour values.
            $finish = clone $now;
            $finish->modify('+' . (int) round($remaining * 3600) . ' seconds');
            $finishAt = $finish->format('Y-m-d H:i:s');
        }

        store_prediction($pdo, $orderId, [
            'remaining_hours'  => $remaining,
            'progress_percent' => $progress,
            'finish_at'        => $finishAt,
            'message'          => $message,
        ]);
    } catch (Throwable $e) {
        store_prediction($pdo, $orderId, [
            'remaining_hours'  => null,
            'progress_percent' => null,
            'finish_at'        => null,
            'message'          => 'Estimate unavailable right now.',
        ]);
    }
}
