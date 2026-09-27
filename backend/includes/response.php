<?php
/**
 * Small helpers shared by every API endpoint. Nothing here is a business
 * rule — it's just the plumbing every REST endpoint needs (JSON output,
 * method checking, CORS for local dev).
 */

/** Sends the CORS headers needed if the frontend is ever served from a
 *  different origin/port than the backend (e.g. a dev server on :5500
 *  instead of XAMPP serving both). Same-origin XAMPP setups don't strictly
 *  need this, but it's harmless either way and safer to always send it. */
function cors_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Preflight requests get an empty 204 and nothing else.
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Sends a JSON body with the given HTTP status and stops the script. */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Shorthand for the common { "error": "..." } shape. */
function json_error(string $message, int $status = 400): void
{
    json_response(['error' => $message], $status);
}

/** Reads and JSON-decodes the request body (POST/PATCH/DELETE payloads). */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** 405s unless the request method is one of $allowed. */
function require_method(array $allowed): string
{
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, $allowed, true)) {
        json_error('Method not allowed. Use: ' . implode(', ', $allowed) . '.', 405);
    }
    return $method;
}
