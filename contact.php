<?php
/**
 * MADAM CAKES — contact page and message form.
 * Messages are stored in MySQL and shown to the administrator.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$user = auth_user();
$values = [
    'name'    => $user ? (string) $user['full_name'] : '',
    'email'   => $user ? (string) $user['email'] : '',
    'phone'   => $user ? (string) ($user['phone'] ?? '') : '',
    'subject' => '',
    'message' => '',
];
$errors  = [];
$success = false;

if (is_post()) {
    require_csrf();
    foreach ($values as $key => $default) {
        $values[$key] = trim((string) post($key));
    }

    if (mb_strlen($values['name']) < 2) {
        $errors[] = 'Tell us your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address so we can reply.';
    }
    if (mb_strlen($values['message']) < 10) {
        $errors[] = 'Please write a little more in your message.';
    }

    if ($errors === []) {
        try {
            Database::insert(
                'INSERT INTO contact_messages (user_id, name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $user ? (int) $user['id'] : null,
                    $values['name'], $values['email'], $values['phone'] ?: null,
                    $values['subject'] ?: null, $values['message'],
                ]
            );
            notify_admin('New website message', $values['name'] . ': ' . excerpt($values['message'], 120), 'contact', 'admin/messages.php');
            $success = true;
            $values['subject'] = '';
            $values['message'] = '';
        } catch (Throwable $e) {
            error_log('Contact message failed: ' . $e->getMessage());
            $errors[] = 'Your message could not be sent. Please try again or call the studio.';
        }
    }
}

$contactDetails = array_filter([
    'Phone'         => (string) setting('phone', ''),
    'Email'         => (string) setting('email', ''),
    'Studio'        => (string) setting('address', ''),
    'Opening hours' => (string) setting('opening_hours', ''),
], static fn (string $value): bool => trim($value) !== '');
$whatsapp = whatsapp_number();
$mapsUrl  = trim((string) setting('maps_url', ''));

$pageTitle       = 'Contact';
$pageDescription = 'Talk to MADAM CAKES about your cake, delivery or a custom design.';
$activeNav       = 'contact';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Say hello</p>
      <h1 class="section-title">Contact the studio</h1>
      <p class="section-sub"><?= e((string) content_body('contact_note', 'Order online or send us a message — we reply during opening hours.')) ?></p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container contact-layout">
      <div class="contact-card">
        <h2>Details</h2>
        <?php if ($contactDetails): ?>
          <dl class="contact-list">
            <?php foreach ($contactDetails as $label => $value): ?>
              <div>
                <dt><?= e($label) ?></dt>
                <dd>
                  <?php if ($label === 'Email'): ?>
                    <a href="mailto:<?= e($value) ?>"><?= e($value) ?></a>
                  <?php elseif ($label === 'Phone'): ?>
                    <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $value) ?? '') ?>"><?= e($value) ?></a>
                  <?php else: ?>
                    <?= e($value) ?>
                  <?php endif; ?>
                </dd>
              </div>
            <?php endforeach; ?>
          </dl>
        <?php else: ?>
          <p class="note">The studio is adding its phone number, email and address. Please use the form and we will reply.</p>
        <?php endif; ?>

        <?php if ($whatsapp !== ''): ?>
          <a class="btn btn-primary btn-block" href="<?= e(whatsapp_url('Hello ' . setting('business_name', APP_NAME) . '! I would like to ask about a cake.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a>
        <?php endif; ?>
        <?php if ($mapsUrl !== ''): ?>
          <a class="btn btn-outline btn-block btn-small" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener">Find us on the map</a>
        <?php endif; ?>
      </div>

      <div class="form-card">
        <h2>Send a message</h2>
        <?php if ($success): ?>
          <p class="note note-success">Thank you — your message has reached the studio. We will reply during opening hours.</p>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
          <p class="note note-error"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="field-row">
            <label class="field">
              <span>Your name</span>
              <input type="text" name="name" value="<?= e($values['name']) ?>" required maxlength="120" autocomplete="name">
            </label>
            <label class="field">
              <span>Email address</span>
              <input type="email" name="email" value="<?= e($values['email']) ?>" required maxlength="190" autocomplete="email">
            </label>
          </div>
          <div class="field-row">
            <label class="field">
              <span>Phone (optional)</span>
              <input type="tel" name="phone" value="<?= e($values['phone']) ?>" maxlength="30" autocomplete="tel">
            </label>
            <label class="field">
              <span>Subject (optional)</span>
              <input type="text" name="subject" value="<?= e($values['subject']) ?>" maxlength="160">
            </label>
          </div>
          <label class="field">
            <span>Your message</span>
            <textarea name="message" required minlength="10" maxlength="3000"><?= e($values['message']) ?></textarea>
          </label>
          <button type="submit" class="btn btn-primary">Send message</button>
        </form>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
