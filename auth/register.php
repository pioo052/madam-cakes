<?php
/**
 * MADAM CAKES — customer registration.
 * Public sign-up always creates the 'customer' role, never an administrator.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

if (is_logged_in()) {
    redirect('account/index.php');
}

$errors = [];
$values = ['full_name' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    require_csrf();

    $values['full_name'] = trim((string) post('full_name'));
    $values['email']     = strtolower(trim((string) post('email')));
    $values['phone']     = trim((string) post('phone'));
    $password            = (string) post('password');
    $confirm             = (string) post('password_confirm');

    if (mb_strlen($values['full_name']) < 3) {
        $errors[] = 'Enter your full name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (mb_strlen(preg_replace('/\D+/', '', $values['phone']) ?? '') < 9) {
        $errors[] = 'Enter a phone number with at least 9 digits.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Your password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Your password must include at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }
    if ($errors === [] && Database::value('SELECT id FROM users WHERE email = ?', [$values['email']])) {
        $errors[] = 'An account already exists for that email address. Try signing in instead.';
    }

    if ($errors === []) {
        try {
            $userId = Database::insert(
                "INSERT INTO users (role, full_name, email, phone, password_hash, status)
                 VALUES ('customer', ?, ?, ?, ?, 'active')",
                [
                    $values['full_name'],
                    $values['email'],
                    $values['phone'] ?: null,
                    password_hash($password, PASSWORD_DEFAULT),
                ]
            );

            $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$userId]);
            if ($user) {
                login_user($user);
                notify_customer($userId, 'Welcome to ' . setting('business_name', APP_NAME), 'Your account is ready. You can now order cakes and follow every step from your dashboard.', 'account', 'account/index.php');
            }

            flash('success', 'Your account was created. Welcome to ' . setting('business_name', APP_NAME) . '!');
            $intended = $_SESSION['intended'] ?? '';
            unset($_SESSION['intended']);
            redirect($intended !== '' ? $intended : 'account/index.php');
        } catch (Throwable $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $errors[] = 'Your account could not be created. Please try again.';
        }
    }

    keep_old($values);
}

$pageTitle       = 'Create an account';
$pageDescription = 'Create a MADAM CAKES account to order cakes, track deliveries and save your favourites.';
$activeNav       = 'account';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Your account</p>
      <h1 class="section-title">Create your account</h1>
      <p class="section-sub">Save your addresses, follow your orders and keep every cake you love in one place.</p>
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
            <span>Full name</span>
            <input type="text" name="full_name" value="<?= e(old('full_name', $values['full_name'])) ?>" required maxlength="120" autocomplete="name">
          </label>
          <label class="field">
            <span>Email address</span>
            <input type="email" name="email" value="<?= e(old('email', $values['email'])) ?>" required maxlength="190" autocomplete="email">
          </label>
          <label class="field">
            <span>Phone number</span>
            <input type="tel" name="phone" value="<?= e(old('phone', $values['phone'])) ?>" required maxlength="30" autocomplete="tel">
          </label>
          <label class="field">
            <span>Password (at least 8 characters, letters and numbers)</span>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
          </label>
          <label class="field">
            <span>Confirm password</span>
            <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
          </label>
          <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>

        <p class="form-foot">Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Sign in</a></p>
      </div>
    </div>
  </section>
</main>

<?php clear_old(); require __DIR__ . '/../includes/footer.php'; ?>
