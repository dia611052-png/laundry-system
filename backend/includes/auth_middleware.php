<?php
/**
 * Authentication middleware.
 *
 * Sessions store only the non-sensitive fields (id, name, email, role) —
 * never the password hash. Every protected endpoint under api/customer/,
 * api/staff/, and api/admin/ starts by calling require_role() with its
 * own required role, so a customer can never call a staff or admin
 * endpoint (and vice versa) even by hitting the URL directly — the
 * frontend hiding a link is just UX, this is the actual enforcement.
 */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

/** Stores the minimum needed to identify the user and their role for the rest of the session. */
function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    session_regenerate_id(true); // new session id on privilege change, prevents session fixation
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * 401s unless someone is logged in. Same guard as before; it responds
 * with a JSON error instead of a redirect, since API endpoints don't
 * have a login page to send the browser to.
 */
function require_login(): array
{
    if (!is_logged_in()) {
        json_error('You need to log in first.', 401);
    }
    return current_user();
}

/**
 * 403s unless the logged-in user has exactly this role. A staff member
 * calling an admin endpoint, or a customer calling a staff endpoint,
 * gets rejected here rather than shown the data.
 */
function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        json_error('You need to log in with the right account for that.', 403);
    }
    return $user;
}
