<?php
/**
 * Gemini API configuration — powers the "predict finish time" feature on
 * the customer dashboard/orders page.
 *
 * GEMINI_KEY is your Google AI Studio API key (see .env.example). Without
 * it, backend/api/customer/predict.php returns a clear error instead of
 * failing silently.
 *
 * GEMINI_MODEL defaults to Flash-Lite — the fastest, cheapest tier, which
 * is plenty for this small structured-JSON prediction task (no need for
 * Pro-level reasoning). "-latest" aliases auto-follow Google's current
 * release in that family. Google's own 503 "model is overloaded" errors
 * hit every tier intermittently (it's a capacity issue, not specific to
 * one model), so includes/gemini.php also retries with backoff and falls
 * back to GEMINI_FALLBACK_MODEL — a *pinned* version on (reportedly)
 * different backing infra than the rolling alias — if the primary model
 * is still failing after retries.
 */
define('GEMINI_API_KEY', getenv('GEMINI_KEY') ?: '');
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-flash-lite-latest');
define('GEMINI_FALLBACK_MODEL', getenv('GEMINI_FALLBACK_MODEL') ?: 'gemini-2.5-flash-lite');

function gemini_api_url(string $model): string
{
    return 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
}

define('GEMINI_API_URL', gemini_api_url(GEMINI_MODEL));
define('GEMINI_FALLBACK_API_URL', gemini_api_url(GEMINI_FALLBACK_MODEL));
