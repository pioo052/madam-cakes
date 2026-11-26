<?php
/**
 * MADAM CAKES — sign in for customers and administrators.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'account/index.php');
}

$errors = [];
$email  = '';

if (is_post()) {
    require_csrf();
    $email    = strtolower(trim((string) post('email')));
    $password = (string) post('password');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter your email address and password.';
    } else {
        $user = attempt_login($email, $password);
        if (!$user) {
            $errors[] = 'Those details did not match an active account.';
        } else {
            login_user($user);
            $intended = $_SESSION['intended'] ?? '';
            unset($_SESSION['intended']);
            if ($intended !== '') {
                redirect($intended);
            }
            redirect($user['role'] === 'admin' ? 'admin/index.php' : 'account/index.php');
        }
    }
}

$pageTitle       = 'Sign in';
$pageDescription = 'Sign in to your MADAM CAKES account to track orders and manage your details.';
$activeNav       = 'account';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Welcome back</p>
      <h1 class="section-title">Sign in</h1>
      <p class="section-sub">Your orders, addresses and saved cakes are waiting.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container auth-wrap">
      <div class="form-card">
        <?php foreach ($errors as $error): ?>
          <p class="note note-error"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <label class="field">
            <span>Email address</span>
            <input type="email" name="email" value="<?= e(old('email', $email)) ?>" required autocomplete="email">
          </label>
          <label class="field">
            <span>Password</span>
            <input type="password" name="password" required autocomplete="current-password">
          </label>
          <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>

        <p class="form-foot">New here? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a></p>
        <p class="form-foot form-foot-muted">Forgotten your password? Please contact the studio and we will help you regain access.</p>
      </div>
    </div>
  </section>
</main>

<?php clear_old(); require __DIR__ . '/../includes/footer.php'; ?>
