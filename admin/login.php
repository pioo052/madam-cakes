<?php
/**
 * MADAM CAKES — administrator sign-in.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_admin()) {
    redirect('admin/index.php');
}

$errors = [];
$email  = '';

if (is_post()) {
    require_csrf();
    $email    = strtolower(trim((string) post('email')));
    $password = (string) post('password');

    if ($email === '' || $password === '') {
        $errors[] = 'Enter your email address and password.';
    } else {
        $user = attempt_login($email, $password, 'admin');
        if (!$user) {
            $errors[] = 'Those details did not match an active administrator account.';
        } else {
            login_user($user);
            log_admin_action('admin.login', 'user', (int) $user['id'], 'Administrator signed in.');
            redirect('admin/index.php');
        }
    }
}

$adminCount = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Administrator sign in · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/theme.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="setup-body">
<main class="setup-card">
  <p class="setup-eyebrow"><?= e(APP_TAGLINE) ?></p>
  <h1 class="setup-title"><?= e(APP_NAME) ?> <span>Studio administration</span></h1>

  <?php foreach ($errors as $error): ?>
    <p class="note note-error"><?= e($error) ?></p>
  <?php endforeach; ?>

  <?php if ($adminCount === 0): ?>
    <p class="note note-warning">No administrator account exists yet.</p>
    <p><a class="btn btn-primary btn-block" href="<?= e(url('admin/setup.php')) ?>">Create the first administrator</a></p>
  <?php else: ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <label class="field">
        <span>Email address</span>
        <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username">
      </label>
      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>
  <?php endif; ?>

  <p class="form-foot"><a href="<?= e(url('')) ?>">&larr; Back to the website</a></p>
</main>
</body>
</html>
