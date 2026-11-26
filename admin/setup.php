<?php
/**
 * MADAM CAKES — one-time administrator setup.
 * Creates the first admin account. Disabled as soon as one admin exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$adminCount = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0);
$errors = [];
$done = false;

if ($adminCount > 0) {
    http_response_code(403);
}

if (is_post() && $adminCount === 0) {
    require_csrf();

    $fullName = trim((string) post('full_name'));
    $email    = strtolower(trim((string) post('email')));
    $phone    = trim((string) post('phone'));
    $password = (string) post('password');
    $confirm  = (string) post('password_confirm');

    if (mb_strlen($fullName) < 3) {
        $errors[] = 'Enter the administrator\'s full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (Database::value('SELECT id FROM users WHERE email = ?', [$email])) {
        $errors[] = 'That email address is already registered.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'The password must be at least 10 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'The password must contain at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if ($errors === []) {
        try {
            Database::begin();
            $id = Database::insert(
                'INSERT INTO users (role, full_name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?)',
                ['admin', $fullName, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), 'active']
            );
            Database::execute(
                'INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, 'admin.created', 'user', $id, 'Initial administrator account created via setup.', client_ip()]
            );
            Database::commit();
            $done = true;
            $adminCount = 1;
        } catch (Throwable $e) {
            Database::rollback();
            error_log('Admin setup failed: ' . $e->getMessage());
            $errors[] = 'The administrator account could not be created. Please try again.';
        }
    }

    keep_old($_POST);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Administrator Setup · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/theme.css')) ?>">
</head>
<body class="setup-body">
<main class="setup-card">
  <p class="setup-eyebrow"><?= e(APP_TAGLINE) ?></p>
  <h1 class="setup-title"><?= e(APP_NAME) ?> <span>Administrator</span></h1>

  <?php if ($done): ?>
    <p class="note note-success">Administrator account created. Keep these credentials safe — they are not recoverable from the database.</p>
    <p><a class="btn btn-primary" href="<?= e(url('admin/login.php')) ?>">Continue to sign-in</a></p>
  <?php elseif ($adminCount > 0): ?>
    <p class="note note-warning">An administrator account already exists. This setup screen is now disabled.</p>
    <p><a class="btn btn-outline" href="<?= e(url('admin/login.php')) ?>">Go to administrator sign-in</a></p>
  <?php else: ?>
    <p class="setup-lead">Create the first administrator. This page stops working once the account exists.</p>

    <?php foreach ($errors as $error): ?>
      <p class="note note-error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" autocomplete="off" novalidate>
      <?= csrf_field() ?>
      <label class="field">
        <span>Full name</span>
        <input type="text" name="full_name" value="<?= e(old('full_name')) ?>" required maxlength="120">
      </label>
      <label class="field">
        <span>Email address</span>
        <input type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190">
      </label>
      <label class="field">
        <span>Phone (optional)</span>
        <input type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="30">
      </label>
      <label class="field">
        <span>Password (min. 10 characters, letters and numbers)</span>
        <input type="password" name="password" required minlength="10" autocomplete="new-password">
      </label>
      <label class="field">
        <span>Confirm password</span>
        <input type="password" name="password_confirm" required minlength="10" autocomplete="new-password">
      </label>
      <button type="submit" class="btn btn-primary btn-block">Create administrator</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
<?php clear_old(); ?>
