<?php
/**
 * MADAM CAKES — administrator sign out (POST only).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_post() || !verify_csrf()) {
    redirect('admin/index.php');
}

log_admin_action('admin.logout', 'user', (int) (auth_user()['id'] ?? 0), 'Administrator signed out.');
logout_user();
header('Location: ' . url('admin/login.php'));
exit;
