<?php
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $primary = trim($_POST['theme_primary'] ?? '');
    $secondary = trim($_POST['theme_secondary'] ?? '');
    $seasonalEnabled = !empty($_POST['seasonal_enabled']);
    $seasonalEffect = in_array($_POST['seasonal_effect'] ?? '', ['snow', 'leaves', 'rain'], true) ? $_POST['seasonal_effect'] : 'snow';

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) $errors[] = 'Primary color must be a valid hex code, e.g. #a97c34.';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondary)) $errors[] = 'Secondary color must be a valid hex code, e.g. #5f7d5b.';

    if (!$errors) {
        set_setting('theme_primary', $primary);
        set_setting('theme_secondary', $secondary);
        set_setting('seasonal_enabled', $seasonalEnabled ? '1' : '0');
        set_setting('seasonal_effect', $seasonalEffect);
        flash_set('success', 'Theme settings saved.');
        redirect('/admin/theme_settings.php');
    }
}

$theme = theme_settings();
$pageTitle = 'Theme & seasonal effects';
require __DIR__ . '/includes/header.php';
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel-head"><h2>Theme colors</h2></div>
    <div class="panel-body">
      <p style="color:var(--ink-faint);font-size:0.85rem;margin-top:-4px;">Changes apply storefront-wide immediately — buttons, links, badges and accents.</p>
      <div class="field-row">
        <div class="field">
          <label for="theme_primary">Primary color</label>
          <div style="display:flex;gap:10px;align-items:center;">
            <input type="color" id="theme_primary_picker" value="<?= e($theme['primary']) ?>" style="width:44px;height:38px;padding:2px;border:1px solid var(--line);border-radius:6px;">
            <input type="text" id="theme_primary" name="theme_primary" value="<?= e($theme['primary']) ?>" pattern="^#[0-9a-fA-F]{6}$" required style="max-width:140px;">
          </div>
          <div class="hint">Used for buttons, links and accents (default #a97c34).</div>
        </div>
        <div class="field">
          <label for="theme_secondary">Secondary color</label>
          <div style="display:flex;gap:10px;align-items:center;">
            <input type="color" id="theme_secondary_picker" value="<?= e($theme['secondary']) ?>" style="width:44px;height:38px;padding:2px;border:1px solid var(--line);border-radius:6px;">
            <input type="text" id="theme_secondary" name="theme_secondary" value="<?= e($theme['secondary']) ?>" pattern="^#[0-9a-fA-F]{6}$" required style="max-width:140px;">
          </div>
          <div class="hint">Used for "in stock" badges and secondary accents (default #5f7d5b).</div>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Seasonal effects</h2></div>
    <div class="panel-body">
      <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:16px;">
        <input type="checkbox" name="seasonal_enabled" <?= $theme['seasonal_enabled'] ? 'checked' : '' ?>> Enable a subtle animated overlay on the storefront
      </label>
      <div class="field" style="max-width:320px;">
        <label for="seasonal_effect">Effect</label>
        <select id="seasonal_effect" name="seasonal_effect">
          <option value="snow" <?= $theme['seasonal_effect'] === 'snow' ? 'selected' : '' ?>>❄️ Snow — gentle falling snowflakes</option>
          <option value="leaves" <?= $theme['seasonal_effect'] === 'leaves' ? 'selected' : '' ?>>🍂 Falling leaves — drifting autumn leaves</option>
          <option value="rain" <?= $theme['seasonal_effect'] === 'rain' ? 'selected' : '' ?>>🌧️ Rain — light rainfall streaks</option>
        </select>
        <div class="hint">Kept deliberately light so it never gets in the way of shopping. Respects visitors' "reduce motion" preference.</div>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Save settings</button>
  </div>
</form>

<script>
  document.getElementById('theme_primary_picker').addEventListener('input', function () {
    document.getElementById('theme_primary').value = this.value;
  });
  document.getElementById('theme_secondary_picker').addEventListener('input', function () {
    document.getElementById('theme_secondary').value = this.value;
  });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
