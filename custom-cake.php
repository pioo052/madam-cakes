<?php
/**
 * MADAM CAKES — custom cake designer.
 * Options and any prices come from owner-managed cake_options rows; the studio
 * quotes every request personally.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$user = auth_user();

$groups = [
    'occasion'     => 'The occasion',
    'shape'        => 'Shape',
    'size'         => 'Size',
    'flavour'      => 'Flavour',
    'decoration'   => 'Decoration style',
    'cream_colour' => 'Cream colour',
];

$options = [];
foreach (array_keys($groups) as $group) {
    $options[$group] = Database::fetchAll(
        'SELECT * FROM cake_options WHERE group_key = ? AND is_active = 1 ORDER BY sort_order, id',
        [$group]
    );
}
$layers = Database::fetchAll('SELECT * FROM cake_layer_prices WHERE is_active = 1 ORDER BY layers');

$values = [
    'customer_name'  => $user ? (string) $user['full_name'] : '',
    'customer_phone' => $user ? (string) ($user['phone'] ?? '') : '',
    'customer_email' => $user ? (string) $user['email'] : '',
    'occasion_option_id'   => '',
    'shape_option_id'      => '',
    'size_option_id'       => '',
    'flavour_option_id'    => '',
    'decoration_option_id' => '',
    'cream_colour'   => '',
    'layers'         => (string) ($layers[0]['layers'] ?? 1),
    'cake_message'   => '',
    'additional_instructions' => '',
    'required_date'  => '',
];

$errors  = [];
$success = null;

if (is_post()) {
    require_csrf();

    foreach ($values as $key => $default) {
        if (isset($_POST[$key])) {
            $values[$key] = trim((string) $_POST[$key]);
        }
    }

    if (mb_strlen($values['customer_name']) < 3) {
        $errors[] = 'Tell us your name.';
    }
    if (mb_strlen(preg_replace('/\D+/', '', $values['customer_phone']) ?? '') < 9) {
        $errors[] = 'Enter a phone number with at least 9 digits.';
    }
    if ($values['customer_email'] !== '' && !filter_var($values['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    $minDays = (int) setting('min_advance_days', '0');
    if ($values['required_date'] !== '' && strtotime($values['required_date']) < strtotime('today +' . $minDays . ' days')) {
        $errors[] = 'Please choose a date at least ' . $minDays . ' day(s) away.';
    }

    $chosen = [];
    $estimate = 0.0;
    $breakdown = [];
    foreach (array_keys($groups) as $group) {
        $field = $group . '_option_id';
        if ($group === 'cream_colour') {
            $chosen[$group] = null;
            continue;
        }
        $id = (int) ($_POST[$field] ?? 0);
        if ($id <= 0) {
            $chosen[$group] = null;
            continue;
        }
        $row = Database::fetch(
            'SELECT * FROM cake_options WHERE id = ? AND group_key = ? AND is_active = 1 LIMIT 1',
            [$id, $group]
        );
        if (!$row) {
            $errors[] = 'One of the selected cake options is no longer available.';
            $chosen[$group] = null;
            continue;
        }
        $chosen[$group] = (int) $row['id'];
        $estimate += (float) $row['price'];
        if ((float) $row['price'] > 0) {
            $breakdown[$groups[$group] . ': ' . $row['name']] = (float) $row['price'];
        }
    }

    $cream = null;
    if ($values['cream_colour'] !== '') {
        foreach ($options['cream_colour'] as $option) {
            if (strcasecmp($option['name'], $values['cream_colour']) === 0) {
                $cream = $option['name'];
            }
        }
    }
    if ($cream === null) {
        $errors[] = 'Choose a cream colour.';
    }

    $layersChosen = (int) $values['layers'];
    $layerRow = Database::fetch('SELECT * FROM cake_layer_prices WHERE layers = ? AND is_active = 1 LIMIT 1', [$layersChosen]);
    if (!$layerRow) {
        $errors[] = 'Choose how many layers you would like.';
        $layerRow = null;
    } else {
        $estimate += (float) $layerRow['price'];
        if ((float) $layerRow['price'] > 0) {
            $breakdown['Layers: ' . $layersChosen] = (float) $layerRow['price'];
        }
    }

    $referencePath = null;
    if (!empty($_FILES['reference_image']['name'])) {
        try {
            $referencePath = upload_image($_FILES['reference_image'], 'custom-orders');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors === []) {
        try {
            $reference = custom_reference();
            $customId = Database::insert(
                "INSERT INTO custom_orders
                  (order_reference, user_id, customer_name, customer_phone, customer_email,
                   occasion_option_id, shape_option_id, size_option_id, flavour_option_id, decoration_option_id,
                   cream_colour, layers, cake_message, additional_instructions, required_date,
                   reference_image, estimated_price, estimate_breakdown, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                [
                    $reference,
                    $user ? (int) $user['id'] : null,
                    $values['customer_name'],
                    $values['customer_phone'],
                    $values['customer_email'] ?: null,
                    $chosen['occasion'], $chosen['shape'], $chosen['size'], $chosen['flavour'], $chosen['decoration'],
                    $cream, $layersChosen,
                    $values['cake_message'] ?: null,
                    $values['additional_instructions'] ?: null,
                    $values['required_date'] ?: null,
                    $referencePath,
                    $estimate > 0 ? round($estimate, 2) : null,
                    $breakdown === [] ? null : json_encode($breakdown, JSON_UNESCAPED_UNICODE),
                ]
            );

            notify_admin(
                'New custom cake request ' . $reference,
                $values['customer_name'] . ' submitted a design for quotation.',
                'custom',
                'admin/custom-request.php?id=' . $customId
            );
            if ($user) {
                notify_customer(
                    (int) $user['id'],
                    'Custom cake request received — ' . $reference,
                    'We are preparing your quotation and will be in touch.',
                    'custom',
                    'account/custom-requests.php'
                );
            }

            $success = ['reference' => $reference, 'estimated' => $estimate];
            $values = array_map(static fn (): string => '', $values);
            if ($user) {
                $values['customer_name']  = (string) $user['full_name'];
                $values['customer_phone'] = (string) ($user['phone'] ?? '');
                $values['customer_email'] = (string) $user['email'];
            }
            $values['layers'] = (string) ($layers[0]['layers'] ?? 1);
        } catch (Throwable $e) {
            error_log('Custom cake request failed: ' . $e->getMessage());
            $errors[] = 'Your request could not be saved. Please try again.';
        }
    }
}

$pageTitle       = 'Design your cake';
$pageDescription = 'Design a custom cake with MADAM CAKES and receive a personal quotation.';
$activeNav       = 'cakes';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Made for you</p>
      <h1 class="section-title">Design your cake</h1>
      <p class="section-sub">Choose the details you love, add a reference photograph and we will send a personal quotation.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <?php if ($success): ?>
        <div class="empty-state success-panel">
          <h2>Request received</h2>
          <p>Your reference is <strong><?= e($success['reference']) ?></strong>. The studio will review your design and send a quotation.</p>
          <?php if ($success['estimated'] > 0): ?>
            <p class="note">The options you chose add up to approximately <?= e(money($success['estimated'])) ?>. This is an estimate only — the final price is confirmed in your quotation.</p>
          <?php endif; ?>
          <?php if ($user): ?>
            <a class="btn btn-primary" href="<?= e(url('account/custom-requests.php')) ?>">View my requests</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php foreach ($errors as $error): ?>
        <p class="note note-error"><?= e($error) ?></p>
      <?php endforeach; ?>

      <form method="post" enctype="multipart/form-data" class="designer-form">
        <?= csrf_field() ?>

        <fieldset class="form-section">
          <legend>Your details</legend>
          <div class="field-row">
            <label class="field">
              <span>Full name</span>
              <input type="text" name="customer_name" value="<?= e($values['customer_name']) ?>" required maxlength="120" autocomplete="name">
            </label>
            <label class="field">
              <span>Phone number</span>
              <input type="tel" name="customer_phone" value="<?= e($values['customer_phone']) ?>" required maxlength="30" autocomplete="tel">
            </label>
          </div>
          <label class="field">
            <span>Email address (optional)</span>
            <input type="email" name="customer_email" value="<?= e($values['customer_email']) ?>" maxlength="190" autocomplete="email">
          </label>
        </fieldset>

        <fieldset class="form-section">
          <legend>The cake</legend>

          <?php foreach (['occasion', 'shape', 'size', 'flavour', 'decoration'] as $group): ?>
            <label class="field">
              <span><?= e($groups[$group]) ?></span>
              <select name="<?= e($group) ?>_option_id">
                <option value="">Choose <?= e(strtolower($groups[$group])) ?></option>
                <?php foreach ($options[$group] as $option): ?>
                  <option value="<?= (int) $option['id'] ?>"<?= $values[$group . '_option_id'] === (string) $option['id'] ? ' selected' : '' ?>>
                    <?= e($option['name']) ?><?= (float) $option['price'] > 0 ? ' (+' . money($option['price']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php endforeach; ?>

          <div class="field-row">
            <label class="field">
              <span>Cream colour</span>
              <select name="cream_colour" required>
                <option value="">Choose a cream colour</option>
                <?php foreach ($options['cream_colour'] as $option): ?>
                  <option value="<?= e($option['name']) ?>"<?= $values['cream_colour'] === $option['name'] ? ' selected' : '' ?>><?= e($option['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span>Layers</span>
              <select name="layers">
                <?php foreach ($layers as $layer): ?>
                  <option value="<?= (int) $layer['layers'] ?>"<?= $values['layers'] === (string) $layer['layers'] ? ' selected' : '' ?>>
                    <?= (int) $layer['layers'] ?> layer<?= (int) $layer['layers'] === 1 ? '' : 's' ?><?= (float) $layer['price'] > 0 ? ' (+' . money($layer['price']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="field-row">
            <label class="field">
              <span>Message on the cake (optional)</span>
              <input type="text" name="cake_message" value="<?= e($values['cake_message']) ?>" maxlength="200">
            </label>
            <label class="field">
              <span>Date needed</span>
              <input type="date" name="required_date" value="<?= e($values['required_date']) ?>" min="<?= e(date('Y-m-d', strtotime('+' . (int) setting('min_advance_days', '0') . ' days'))) ?>">
            </label>
          </div>

          <label class="field">
            <span>Tell us more (inspiration, colours, theme)</span>
            <textarea name="additional_instructions" maxlength="2000"><?= e($values['additional_instructions']) ?></textarea>
          </label>

          <label class="field">
            <span>Reference photograph (optional, max 5 MB)</span>
            <input type="file" name="reference_image" accept="image/jpeg,image/png,image/webp,image/gif">
          </label>
        </fieldset>

        <p class="note">Prices shown here are estimates based on the options the studio has priced. Your quotation is the confirmed price.</p>
        <button type="submit" class="btn btn-primary">Send my design</button>
      </form>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
