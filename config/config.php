<?php
/**
 * MADAM CAKES — global configuration.
 *
 * Machine-specific overrides (database password, URLs) belong in
 * config/config.local.php, which is blocked from web access and never committed.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Optional per-machine overrides
// ---------------------------------------------------------------------------
$local = [];
if (is_file(__DIR__ . '/config.local.php')) {
    $maybe = require __DIR__ . '/config.local.php';
    if (is_array($maybe)) {
        $local = $maybe;
    }
}

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------
define('APP_NAME', 'MADAM CAKES');
define('APP_TAGLINE', 'Every Cake Tells a Sweet Story.');
define('APP_VERSION', '1.0.0');

define('APP_ENV', $local['env'] ?? (getenv('MADAM_ENV') ?: 'development'));
define('APP_DEBUG', APP_ENV !== 'production');
define('APP_URL', rtrim($local['url'] ?? (getenv('MADAM_URL') ?: 'http://localhost/madam-cakes'), '/'));

define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// ---------------------------------------------------------------------------
// Database
// Environment variables let a container supply credentials without writing
// them to disk; config.local.php still takes precedence on a normal install.
// ---------------------------------------------------------------------------
define('DB_HOST', $local['db_host'] ?? (getenv('MADAM_DB_HOST') ?: 'localhost'));
define('DB_PORT', (int) ($local['db_port'] ?? (getenv('MADAM_DB_PORT') ?: 3306)));
define('DB_NAME', $local['db_name'] ?? (getenv('MADAM_DB_NAME') ?: 'madam_cakes_db'));
define('DB_USER', $local['db_user'] ?? (getenv('MADAM_DB_USER') ?: 'root'));
define('DB_PASS', $local['db_pass'] ?? (getenv('MADAM_DB_PASS') ?: ''));
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------------
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB per image
define('ALLOWED_IMAGE_MIMES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
]);
define('UPLOAD_FOLDERS', ['products', 'categories', 'gallery', 'custom-orders', 'reviews', 'content', 'settings', 'avatars']);

// ---------------------------------------------------------------------------
// Runtime
// ---------------------------------------------------------------------------
date_default_timezone_set('Africa/Dar_es_Salaam');

if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0775, true);
}

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/error.log');
    error_reporting(E_ALL);
}

// Security headers (safe defaults; hardened further by .htaccess).
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // MADAM_HTTPS=1 is for deployments that terminate TLS at a reverse proxy,
    // where PHP cannot see HTTPS on the incoming request itself.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || getenv('MADAM_HTTPS') === '1';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_name('MADAMCAKESSESSID');
    session_start();
}
