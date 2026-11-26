<?php
/**
 * MADAM CAKES — business settings: contact, social, commerce and branding.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $groups = ['general', 'contact', 'social', 'commerce'];

    foreach ($groups as $group) {
        $submitted = (array) post($group, []);
        $rows = Database::fetchAll('SELECT setting_key FROM business_settings WHERE group_name = ?', [$group]);
        foreach ($rows as $row) {
            $key = $row['setting_key'];
            if ($key === 'logo_path') {
                continue;
            }
            if (array_key_exists($key, $submitted)) {
                $value = is_string($submitted[$key]) ? trim($submitted[$key]) : (string) $submitted[$key];
                setting_set($key, $value, $group);
                continue;
            }
            // Unchecked checkboxes are absent from POST: only flip known flags.
            if (in_array($key, ['promo_banner_active', 'delivery_enabled', 'pickup_enabled', 'surprise_delivery_enabled', 'newsletter_enabled'], true)) {
                setting_set($key, '0', $group);
            }
        }
    }

    if (!empty($_FILES['logo']['name'])) {
        try {
            $path = upload_image($_FILES['logo'], 'settings');
            setting_set('logo_path', $path, 'general');
            flash('success', 'Settings saved and logo updated.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
    } else {
        flash('success', 'Settings saved.');
    }
    if (!empty(post('remove_logo'))) {
        setting_set('logo_path', '', 'general');
    }

    log_admin_action('settings.update', 'settings', null, 'Updated business settings.');
    redirect('admin/settings.php');
}

$all = Database::fetchAll('SELECT * FROM business_settings ORDER BY group_name, setting_key');
$grouped = [];
foreach ($all as $row) {
    $grouped[$row['group_name']][] = $row;
}

$groupTitles = [
    'general'  => 'Business identity',
    'contact'  => 'Contact details',
    'social'   => 'Social profiles',
    'commerce' => 'Ordering and payments',
];
$groupHelp = [
    'general'  => 'The name and tagline appear across the whole website.',
    'contact'  => 'Only filled-in details are shown to customers. A blank field is hidden instead of shown as an empty value.',
    'social'   => 'Add full profile URLs. Profiles left blank are not displayed.',
    'commerce' => 'These values control checkout: accepted payment codes (comma separated), advance notice, and whether delivery or collection is offered. Prices are edited on each cake.',
];
$flagKeys = ['promo_banner_active', 'delivery_enabled', 'pickup_enabled', 'surprise_delivery_enabled', 'newsletter_enabled'];
$longKeys = ['payment_instructions', 'address', 'opening_hours', 'promo_banner_text'];

$adminTitle  = 'Settings';
$adminActive = 'settings';
require __DIR__ . '/includes/header.php';
?>

<form method="post" enctype="multipart/form-data" class="settings-form">
  <?= csrf_field() ?>

  <?php foreach ($grouped as $group => $rows): ?>
    <section class="admin-panel">
      <div class="panel-head"><h2><?= e($groupTitles[$group] ?? ucfirst($group)) ?></h2></div>
      <?php if (isset($groupHelp[$group])): ?><p class="admin-muted"><?= e($groupHelp[$group]) ?></p><?php endif; ?>

      <?php foreach ($rows as $row): ?>
        <?php $key = $row['setting_key']; $value = (string) $row['setting_value']; ?>
        <?php if ($key === 'logo_path'): ?>
          <div class="field">
            <span>Logo</span>
            <?php if ($value !== ''): ?>
              <img class="content-preview" src="<?= e(upload_url($value)) ?>" alt="Current logo" width="160">
              <label class="checkbox-line"><input type="checkbox" name="remove_logo" value="1"><span>Remove the logo and use the wordmark</span></label>
            <?php else: ?>
              <p class="admin-muted">No logo uploaded — the wordmark is used.</p>
            <?php endif; ?>
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
          </div>
        <?php elseif (in_array($key, $flagKeys, true)): ?>
          <label class="checkbox-line">
            <input type="checkbox" name="<?= e($group) ?>[<?= e($key) ?>]" value="1"<?= $value === '1' ? ' checked' : '' ?>>
            <span><?= e(ucwords(str_replace('_', ' ', $key))) ?></span>
          </label>
        <?php elseif (in_array($key, $longKeys, true)): ?>
          <label class="field">
            <span><?= e(ucwords(str_replace('_', ' ', $key))) ?></span>
            <textarea name="<?= e($group) ?>[<?= e($key) ?>]" maxlength="2000"><?= e($value) ?></textarea>
          </label>
        <?php else: ?>
          <label class="field">
            <span><?= e(ucwords(str_replace('_', ' ', $key))) ?></span>
            <input type="text" name="<?= e($group) ?>[<?= e($key) ?>]" value="<?= e($value) ?>" maxlength="255">
            <?php if ($key === 'accepted_payments'): ?>
              <small class="admin-muted">Codes: cash, mpesa, airtel_money, mixx_by_yas, tigo_pesa, halopesa, bank, card</small>
            <?php elseif ($key === 'currency_decimals'): ?>
              <small class="admin-muted">0 for whole shillings.</small>
            <?php endif; ?>
          </label>
        <?php endif; ?>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <div class="admin-panel">
    <button type="submit" class="btn btn-primary">Save all settings</button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
