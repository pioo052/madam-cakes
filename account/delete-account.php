<?php
/**
 * MADAM CAKES — account closure.
 * Accounts are deactivated, never deleted: orders, payments and invoices are
 * financial records the business must keep.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];
$errors      = [];

if (is_post()) {
    require_csrf();
    $password = (string) post('password');
    $confirm  = (string) post('confirm');

    $row = Database::fetch('SELECT password_hash FROM users WHERE id = ?', [$userId]);
    if (!$row || !password_verify($password, (string) $row['password_hash'])) {
        $errors[] = 'Enter your current password to confirm.';
    }
    if ($confirm !== 'close') {
        $errors[] = 'Type close exactly as shown to confirm.';
    }
    $openOrders = (int) Database::value(
        "SELECT COUNT(*) FROM orders WHERE user_id = ? AND status NOT IN ('delivered','cancelled','rejected')",
        [$userId],
        0
    );
    if ($openOrders > 0) {
        $errors[] = 'You still have ' . $openOrders . ' order(s) in progress. Please wait until they are completed.';
    }

    if ($errors === []) {
        Database::execute(
            "UPDATE users SET status = 'blocked', closed_at = NOW() WHERE id = ?",
            [$userId]
        );
        logout_user();
        session_start();
        flash('success', 'Your account has been closed. Your order records have been kept as required, and you can contact the studio to reopen it.');
        redirect('auth/login.php');
    }
}

$accountActive   = 'profile';
$pageTitle       = 'Close my account';
$pageDescription = 'Close your MADAM CAKES account.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">Close my account</h1>
      <p class="section-sub">You are signed in as <?= e($accountUser['email']) ?>.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <div class="form-card">
          <h2 class="section-title">What happens when you close your account</h2>
          <ul class="plain-list">
            <li>You will no longer be able to sign in or place new orders.</li>
            <li>Your name, email, phone and saved addresses stop being used for the account.</li>
            <li>Orders, payments and receipts are kept as business and tax records.</li>
            <li>If you have an order in progress, please wait until it is delivered or cancelled.</li>
            <li>Contact the studio if you later want the account reopened.</li>
          </ul>

          <?php foreach ($errors as $error): ?>
            <p class="note note-error"><?= e($error) ?></p>
          <?php endforeach; ?>

          <form method="post">
            <?= csrf_field() ?>
            <label class="field">
              <span>Current password</span>
              <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="field">
              <span>Type <strong>close</strong> to confirm</span>
              <input type="text" name="confirm" required autocomplete="off" pattern="close">
            </label>
            <button type="submit" class="btn btn-primary">Close my account</button>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
