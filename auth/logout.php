<?php
/**
 * MADAM CAKES — sign out. POST only, so a link cannot log a visitor out.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

if (is_post()) {
    require_csrf();
    $wasAdmin = is_admin();
    logout_user();
    header('Location: ' . url($wasAdmin ? 'admin/login.php' : ''));
    exit;
}

$pageTitle       = 'Sign out';
$pageDescription = 'Sign out of your MADAM CAKES account.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="section">
    <div class="container auth-wrap">
      <div class="form-card">
        <h1 class="section-title">Sign out?</h1>
        <p>You will need to sign in again to see your orders.</p>
        <form method="post">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary btn-block">Yes, sign me out</button>
        </form>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
