<?php
/**
 * MADAM CAKES — authentication and authorization.
 * Roles: customer, admin. All checks are server-side.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function auth_user(bool $refresh = false): ?array
{
    static $cached = null;

    if ($cached !== null && !$refresh) {
        return $cached;
    }

    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return $cached = null;
    }

    $user = Database::fetch(
        'SELECT id, role, full_name, email, phone, status, last_login_at, created_at
         FROM users WHERE id = ? LIMIT 1',
        [$id]
    );

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return $cached = null;
    }

    return $cached = $user;
}

function is_logged_in(): bool
{
    return auth_user() !== null;
}

function is_admin(): bool
{
    $user = auth_user();
    return $user !== null && $user['role'] === 'admin';
}

/** Log a verified user in and rotate the session id. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function require_login(string $redirectTo = 'auth/login.php'): array
{
    $user = auth_user();
    if ($user === null) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warning', 'Please sign in to continue.');
        redirect($redirectTo);
    }
    return $user;
}

/** Hard gate for every /admin page. */
function require_admin(): array
{
    $user = auth_user();
    if ($user === null || $user['role'] !== 'admin') {
        if ($user === null) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
            flash('warning', 'Administrator sign-in is required.');
            redirect('admin/login.php');
        }
        http_response_code(403);
        exit('<h1>403 — Not permitted</h1><p>This area is restricted to MADAM CAKES administrators.</p>');
    }
    return $user;
}

/**
 * Verify a password against a stored hash, with a dummy check when the
 * account does not exist so response timing does not reveal valid emails.
 */
function attempt_login(string $email, string $password, ?string $role = null): ?array
{
    $user = Database::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [strtolower(trim($email))]);

    if (!$user) {
        password_verify($password, '$2y$10$usesomesillystringforsalt0123456789012345678901234567');
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    if ($user['status'] !== 'active') {
        return null;
    }

    if ($role !== null && $user['role'] !== $role) {
        return null;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            $user['id'],
        ]);
    }

    return $user;
}
