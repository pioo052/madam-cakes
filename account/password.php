<?php
/**
 * MADAM CAKES — change password. The current password is always required.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$errors      = [];

if (is_post()) {
    require_csrf();

    $current = (string) post('current_password');
    $new     = (string) post('new_password');
    $confirm = (string) post('new_password_confirm');

    $row = Database::fetch('SELECT password_hash FROM users WHERE id = ?', [(int) $accountUser['id']]);
    if (!$row || !password_verify($current, (string) $row['password_hash'])) {
        $errors[] = 'Your current password is not correct.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'Your new password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $errors[] = 'Your new password must include at least one letter and one number.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The two new passwords do not match.';
    }
    if ($new !== '' && $new === $current) {
        $errors[] = 'Choose a password that is different from your current one.';
    }

    if ($errors === []) {
        Database::execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT), (int) $accountUser['id']]
        );
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        notify_customer((int) $accountUser['id'], 'Your password was changed', 'If this was not you, contact the studio immediately.', 'account', 'account/password.php');
        flash('success', 'Your password has been changed.');
        redirect('account/password.php');
    }
}

$accountActive   = 'password';
$pageTitle       = 'Change password';
$pageDescription = 'Change the password for your MADAM CAKES account.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">Change password</h1>
      <p class="section-sub">Choose something only you know.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <div class="form-card">
          <?php foreach ($errors as $error): ?>
            <p class="note note-error"><?= e($error) ?></p>
          <?php endforeach; ?>

          <form method="post">
            <?= csrf_field() ?>
            <label class="field">
              <span>Current password</span>
              <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label class="field">
              <span>New password (at least 8 characters, letters and numbers)</span>
              <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
            </label>
            <label class="field">
              <span>Confirm new password</span>
              <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn-primary">Update password</button>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
