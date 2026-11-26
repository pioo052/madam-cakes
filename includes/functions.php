<?php
/**
 * MADAM CAKES — shared helpers.
 * Escaping, URLs, money, settings, CSRF, flash messages, uploads, pagination.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ---------------------------------------------------------------------------
// Output / URLs
// ---------------------------------------------------------------------------

/** HTML-escape any value for safe output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/** Public URL for a stored upload path, or '' when none. */
function upload_url(?string $path): string
{
    $path = trim((string) $path);
    return $path === '' ? '' : url($path);
}

function redirect(string $path, int $code = 302): never
{
    $target = preg_match('~^https?://~i', $path) ? $path : url($path);
    header('Location: ' . $target, true, $code);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed
{
    return $_GET[$key] ?? $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

/** Format an amount as TZS (decimals controlled by business settings). */
function money(mixed $amount, ?int $decimals = null): string
{
    if ($decimals === null) {
        $decimals = (int) (setting('currency_decimals') ?? '0');
    }
    return (setting('currency_code') ?? 'TZS') . ' ' . number_format((float) $amount, max(0, $decimals));
}

/** Plain number, e.g. 125,000 (no currency). */
function amount(mixed $value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function format_datetime(?string $datetime, string $format = 'd M Y, H:i'): string
{
    return format_date($datetime, $format);
}

function excerpt(?string $text, int $length = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text) ?? '');
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text === '' ? 'item-' . bin2hex(random_bytes(3)) : $text;
}

/** Unique slug for a table column, e.g. unique_slug('products', 'slug', $name). */
function unique_slug(string $table, string $column, string $name, ?int $ignoreId = null): string
{
    $allowed = ['products' => 'slug', 'categories' => 'slug'];
    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new InvalidArgumentException('Slug helper is not allowed for that table.');
    }

    $base = slugify($name);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE {$column} = ?";
        $params = [$slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $exists = Database::value($sql, $params);
        if (!$exists) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

// ---------------------------------------------------------------------------
// Settings and editable content
// ---------------------------------------------------------------------------

function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (Database::fetchAll('SELECT setting_key, setting_value FROM business_settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            error_log('Settings load failed: ' . $e->getMessage());
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $all = settings_all();
    $value = $all[$key] ?? null;
    return ($value === null || $value === '') ? $default : $value;
}

function setting_set(string $key, ?string $value, string $group = 'general'): void
{
    Database::execute(
        'INSERT INTO business_settings (setting_key, setting_value, group_name) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, (string) $value, $group]
    );
}

/** Raw site_content row for a key, or null. */
function content_row(string $key): ?array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (Database::fetchAll('SELECT content_key, title, body, image FROM site_content') as $row) {
                $cache[$row['content_key']] = $row;
            }
        } catch (Throwable $e) {
            error_log('Content load failed: ' . $e->getMessage());
        }
    }
    return $cache[$key] ?? null;
}

/** Body when present, otherwise the title. */
function content(string $key, ?string $default = null): ?string
{
    $row = content_row($key);
    if (!$row) {
        return $default;
    }
    $text = $row['body'] !== null && $row['body'] !== '' ? $row['body'] : $row['title'];
    return ($text === null || $text === '') ? $default : $text;
}

/** The heading of a content block only. */
function content_title(string $key, ?string $default = null): ?string
{
    $row = content_row($key);
    $text = $row['title'] ?? null;
    return ($text === null || trim((string) $text) === '') ? $default : (string) $text;
}

/** The supporting paragraph of a content block only. */
function content_body(string $key, ?string $default = null): ?string
{
    $row = content_row($key);
    $text = $row['body'] ?? null;
    return ($text === null || trim((string) $text) === '') ? $default : (string) $text;
}

// ---------------------------------------------------------------------------
// Owner-configured presentation lists
// ---------------------------------------------------------------------------

/** Social profiles that have a URL set, in display order. */
function social_links(): array
{
    $links = [];
    foreach ([
        'instagram_url' => 'Instagram',
        'facebook_url'  => 'Facebook',
        'tiktok_url'    => 'TikTok',
        'x_url'         => 'X',
    ] as $key => $label) {
        $href = trim((string) setting($key, ''));
        if ($href !== '') {
            $links[] = [
                'key'   => str_replace('_url', '', $key),
                'label' => $label,
                'href'  => $href,
            ];
        }
    }
    return $links;
}

/** Display labels for the payment methods enabled in settings. */
function payment_methods(): array
{
    static $labels = [
        'cash'         => 'Cash',
        'mpesa'        => 'M-Pesa',
        'airtel_money' => 'Airtel Money',
        'mixx_by_yas'  => 'Mixx by Yas',
        'tigo_pesa'    => 'Tigo Pesa',
        'halopesa'     => 'HaloPesa',
        'bank'         => 'Bank transfer',
        'card'         => 'Card',
    ];

    $methods = [];
    foreach (array_filter(array_map('trim', explode(',', (string) setting('accepted_payments', '')))) as $code) {
        $methods[] = $labels[$code] ?? ucwords(str_replace('_', ' ', $code));
    }
    return $methods;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (($_SESSION['csrf_token'] ?? '') === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token ??= (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = (string) ($_SESSION['csrf_token'] ?? '');
    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

/** Abort the request unless a valid CSRF token was supplied. */
function require_csrf(): void
{
    if (verify_csrf()) {
        return;
    }
    // 403 rather than the unofficial 419: Apache rewrites unknown status codes to 500.
    http_response_code(403);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "CSRF verification failed.\n");
        exit(1);
    }
    exit('<h1>Session expired</h1><p>Your form session expired. Please go back, refresh the page and submit again.</p>');
}

// ---------------------------------------------------------------------------
// Flash messages and form repopulation
// ---------------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

/** Keep submitted values for redisplay after a validation error. */
function keep_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['csrf_token']);
    $_SESSION['old'] = $data;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

// ---------------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------------

/**
 * Validate and store an uploaded image.
 *
 * @return string Path relative to the project root, e.g. assets/uploads/products/x.jpg
 * @throws RuntimeException when the file is missing, too large or not an allowed image.
 */
function upload_image(array $file, string $folder = 'products'): string
{
    if (!in_array($folder, UPLOAD_FOLDERS, true)) {
        throw new RuntimeException('Unknown upload folder.');
    }

    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file was uploaded.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The upload failed (code ' . (int) $error . ').');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('The uploaded file could not be verified.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Images must be smaller than ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    if (!isset(ALLOWED_IMAGE_MIMES[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }

    // Reject files that actually contain PHP code, even with an image MIME.
    $head = (string) file_get_contents($tmp, false, null, 0, 4096);
    if (preg_match('/<\?php|<script/i', $head)) {
        throw new RuntimeException('This file does not look like a safe image.');
    }

    $ext = ALLOWED_IMAGE_MIMES[$mime];
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;

    $dir = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('The upload folder is not writable.');
    }

    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('The image could not be saved.');
    }
    @chmod($target, 0644);

    return 'assets/uploads/' . $folder . '/' . $name;
}

// ---------------------------------------------------------------------------
// WhatsApp
// ---------------------------------------------------------------------------

/** Digits-only international number from settings, or ''. */
function whatsapp_number(): string
{
    $raw = (string) setting('whatsapp_number', '');
    return preg_replace('/\D+/', '', $raw) ?? '';
}

function whatsapp_url(string $message = ''): string
{
    $number = whatsapp_number();
    $base = $number === '' ? 'https://wa.me/' : 'https://wa.me/' . $number;
    return $message === '' ? $base : $base . '?text=' . rawurlencode($message);
}

// ---------------------------------------------------------------------------
// Cart (counts are always read from the database)
// ---------------------------------------------------------------------------

/** Per-visitor cart key, so guests can hold a cart before signing in. */
function cart_session_key(): string
{
    if (($_SESSION['cart_key'] ?? '') === '') {
        $_SESSION['cart_key'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_key'];
}

/** Id of the current cart row, or null when the visitor has none yet. */
function current_cart_id(bool $create = false): ?int
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    try {
        if ($userId > 0) {
            $id = Database::value('SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        } else {
            $id = Database::value('SELECT id FROM carts WHERE session_key = ? LIMIT 1', [cart_session_key()]);
        }

        if ($id) {
            return (int) $id;
        }

        if (!$create) {
            return null;
        }

        Database::insert('INSERT INTO carts (user_id, session_key) VALUES (?, ?)', [
            $userId > 0 ? $userId : null,
            cart_session_key(),
        ]);

        return (int) Database::value('SELECT id FROM carts WHERE session_key = ? LIMIT 1', [cart_session_key()]);
    } catch (Throwable $e) {
        error_log('Cart lookup failed: ' . $e->getMessage());
        return null;
    }
}

/** Total units in the current cart (0 when empty or unavailable). */
function cart_count(): int
{
    $cartId = current_cart_id();
    if ($cartId === null) {
        return 0;
    }

    try {
        return (int) Database::value('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?', [$cartId], 0);
    } catch (Throwable $e) {
        error_log('Cart count failed: ' . $e->getMessage());
        return 0;
    }
}

// ---------------------------------------------------------------------------
// Order status helpers
// ---------------------------------------------------------------------------

function order_statuses(): array
{
    return [
        'pending'           => 'Pending',
        'awaiting_quotation' => 'Awaiting Quotation',
        'awaiting_payment'  => 'Awaiting Payment',
        'confirmed'         => 'Confirmed',
        'preparing'         => 'Preparing',
        'ready'             => 'Ready',
        'out_for_delivery'  => 'Out for Delivery',
        'delivered'         => 'Delivered',
        'cancelled'         => 'Cancelled',
        'rejected'          => 'Rejected',
    ];
}

function order_status_label(?string $status): string
{
    return order_statuses()[$status ?? ''] ?? ucfirst(str_replace('_', ' ', (string) $status));
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

function current_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function paginate(int $total, int $perPage = 12): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(current_page(), $pages);
    return [
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
        'total'    => $total,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/** Build a URL keeping current query parameters with a new page number. */
function page_url(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------

function notify_admin(string $title, string $message = '', string $type = 'info', ?string $link = null): void
{
    Database::insert(
        'INSERT INTO notifications (user_id, audience, type, title, message, link) VALUES (NULL, ?, ?, ?, ?, ?)',
        ['admin', $type, $title, $message, $link]
    );
}

function notify_customer(?int $userId, string $title, string $message = '', string $type = 'info', ?string $link = null): void
{
    if (!$userId) {
        return;
    }
    Database::insert(
        'INSERT INTO notifications (user_id, audience, type, title, message, link) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, 'customer', $type, $title, $message, $link]
    );
}

function log_admin_action(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    $admin = auth_user();
    if (!$admin || ($admin['role'] ?? '') !== 'admin') {
        return;
    }
    Database::insert(
        'INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
        [$admin['id'], $action, $entityType, $entityId, $details, client_ip()]
    );
}
