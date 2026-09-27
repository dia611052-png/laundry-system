<?php
/**
 * WebSocket (live updates) settings.
 *
 * There are two addresses in play, and they're often NOT the same once
 * this is deployed:
 *
 *   WS_HOST / WS_PORT   — where ws-server.php itself binds and listens.
 *                          On a normal deployment this is fine left as
 *                          0.0.0.0:8080 (listen on every interface).
 *
 *   WS_PUBLIC_URL        — the ws:// (or wss://) address the BROWSER
 *                          connects to. Locally with XAMPP this is the
 *                          same host/port as above. In production, if
 *                          you put the WebSocket server behind Apache/
 *                          nginx or a different public domain/port, set
 *                          this to whatever the browser should actually
 *                          dial (e.g. "wss://freshtrack.example.com/ws").
 *
 * All four settings can be overridden with environment variables, so a
 * deployment can configure this without touching code — e.g. in Apache's
 * vhost config: SetEnv WS_PUBLIC_URL wss://freshtrack.example.com/ws
 * or, on the CLI before starting the server: WS_PORT=9090 php ws-server.php
 */
define('WS_HOST', getenv('WS_HOST') ?: '0.0.0.0');
define('WS_PORT', (int) (getenv('WS_PORT') ?: 8080));

// How often (in seconds) the server re-checks the database and pushes
// fresh data to every connected client. Lower = snappier updates, more
// database load; higher = the reverse. 2 seconds is a reasonable default
// for a small deployment.
define('WS_BROADCAST_INTERVAL', (float) (getenv('WS_BROADCAST_INTERVAL') ?: 2));

// Default the public URL to the same host the page was served from, on
// WS_PORT, over plain ws://. This "just works" for local XAMPP use and
// for a single-server deployment with the port opened directly.
// Override WS_PUBLIC_URL explicitly (env var, or edit the line below)
// once you're behind a reverse proxy, using TLS (wss://), or serving the
// WebSocket on a different host/path than the site itself.
$wsDefaultHost = (php_sapi_name() !== 'cli' && !empty($_SERVER['SERVER_NAME']))
    ? $_SERVER['SERVER_NAME']
    : 'localhost';
define('WS_PUBLIC_URL', getenv('WS_PUBLIC_URL') ?: ('ws://' . $wsDefaultHost . ':' . WS_PORT));
