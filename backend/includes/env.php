<?php
/**
 * Minimal .env loader — no Composer package needed for something this small.
 *
 * Reads KEY=VALUE pairs from a .env file into getenv()/$_ENV, so
 * config/database.php and config/websocket.php — which already read their
 * settings via getenv() — pick up values from .env automatically.
 *
 * Blank lines and lines starting with # are skipped. Values can optionally
 * be wrapped in single or double quotes. A real environment variable
 * (e.g. set by Apache's SetEnv, or by your hosting platform) always takes
 * priority and is never overwritten by .env.
 */
function load_env(string $path): void
{
    if (!is_file($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
