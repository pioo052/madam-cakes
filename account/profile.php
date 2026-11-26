<?php
/**
 * MADAM CAKES — customer profile: name, email, phone and photo.
 * Customers can never change their own role or status here.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];
$errors      = [];

if (is_post()) {
    require_csrf();

    $fullName = trim((string) post('full_name'));
    $email    = strtolower(trim((string) post('email')));
    $phone    = trim((string) post('phone'));
    $removeAvatar = !empty(post('remove_avatar'));

    if (mb_strlen($fullName) < 3) {
        $errors[] = 'Enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    $duplicate = Database::value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId]);
    if ($duplicate) {
        $errors[] = 'That email address is already used by another account.';
    }
    if ($phone !== '' && mb_strlen(preg_replace('/\D+/', '', $phone) ?? '') < 9) {
        $errors[] = 'Enter a phone number with at least 9 digits.';
    }

    $avatar = (string) ($accountUser['avatar'] ?? '');
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $avatar = upload_image($_FILES['avatar'], 'avatars');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($removeAvatar) {
        $avatar = '';
    }

    if ($errors === []) {
        try {
            Database::execute(
                'UPDATE users SET full_name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?',
                [$fullName, $email, $phone ?: null, $avatar ?: null, $userId]
            );
            flash('success', 'Your profile has been updated.');
            redirect('account/profile.php');
        } catch (Throwable $e) {
            error_log('Profile update failed: ' . $e->getMessage());
            $errors[] = 'Your profile could not be saved. Please try again.';
        }
    }
}

$accountActive   = 'profile';
$pageTitle       = 'My profile';
$pageDescription = 'Update your MADAM CAKES account details.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">My profile</h1>
      <p class="section-sub">Keep your contact details current so we can reach you about your cakes.</p>
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

          <div class="avatar-row">
            <?php if (!empty($accountUser['avatar'])): ?>
              <img class="account-avatar account-avatar-lg" src="<?= e(upload_url($accountUser['avatar'])) ?>" alt="Your profile photograph">
            <?php else: ?>
              <span class="account-avatar account-avatar-lg account-avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $accountUser['full_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <div>
              <p class="account-name"><?= e($accountUser['full_name']) ?></p>
              <p class="account-email">Member since <?= e(format_date($accountUser['created_at'], 'd F Y')) ?></p>
            </div>
          </div>

          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label class="field">
              <span>Full name</span>
              <input type="text" name="full_name" value="<?= e($accountUser['full_name']) ?>" required maxlength="120" autocomplete="name">
            </label>
            <label class="field">
              <span>Email address</span>
              <input type="email" name="email" value="<?= e($accountUser['email']) ?>" required maxlength="190" autocomplete="email">
            </label>
            <label class="field">
              <span>Phone number</span>
              <input type="tel" name="phone" value="<?= e((string) ($accountUser['phone'] ?? '')) ?>" maxlength="30" autocomplete="tel">
            </label>
            <label class="field">
              <span>Profile photograph (JPG, PNG, WEBP or GIF, max 5 MB)</span>
              <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            <?php if (!empty($accountUser['avatar'])): ?>
              <label class="checkbox-line"><input type="checkbox" name="remove_avatar" value="1"><span>Remove my current photograph</span></label>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Save changes</button>
          </form>
        </div>

        <p class="account-footnote">Need to close your account? <a href="<?= e(url('account/delete-account.php')) ?>">Read about account closure</a>.</p>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
