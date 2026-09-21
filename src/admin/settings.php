<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/mail.php';
require_admin();

$errors = [];
$section = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $section = $_POST['section'] ?? '';

    if ($section === 'topbar') {
        $tb0 = topbar_settings();
        $text = mb_substr(trim($_POST['topbar_text'] ?? ''), 0, 200);
        $link = trim($_POST['topbar_link'] ?? '');
        $enabled = !empty($_POST['topbar_enabled']);
        if ($link !== '' && !preg_match('~^(https?://|/)~i', $link)) $errors[] = 'The link must start with https:// (or / for a page on your own site).';
        if ($enabled && $text === '') $errors[] = 'Type a message first, or switch the bar off.';
        if (!$errors) {
            set_setting('topbar_enabled', $enabled ? '1' : '0');
            set_setting('topbar_text', $text);
            set_setting('topbar_link', $link);
            admin_log('settings.announcement', $enabled ? 'Announcement bar switched on: "' . admin_log_clip($text, 100) . '"' : 'Announcement bar switched off',
                null, null, ['changes' => admin_log_diff(['on' => $tb0['enabled'] ? 'yes' : 'no', 'text' => $tb0['text'], 'link' => $tb0['link']], ['on' => $enabled ? 'yes' : 'no', 'text' => $text, 'link' => $link], ['on' => 'Shown', 'text' => 'Message', 'link' => 'Link'])]);
            flash_set('success', $enabled ? 'Announcement bar saved and showing on every page.' : 'Announcement bar is switched off.');
            redirect('/admin/settings.php#topbar');
        }
    }

    if ($section === 'store') {
        $email = trim($_POST['store_email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'The store email address doesn\'t look right.';
        if (trim($_POST['store_name'] ?? '') === '') $errors[] = 'The store name can\'t be empty.';
        $links = [];
        foreach (['facebook' => 'Facebook page', 'messenger' => 'Messenger', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $k => $label) {
            $u = trim($_POST['social_' . $k] ?? '');
            if ($u !== '' && !preg_match('~^https?://[^\s]+$~i', $u)) $errors[] = $label . ' link must start with https:// (or leave it empty to hide it).';
            $links[$k] = $u;
        }
        // WhatsApp accepts a wa.me link or just a phone number, and is stored as a clean https link.
        $wa = whatsapp_link_normalize((string) ($_POST['social_whatsapp'] ?? ''));
        if ($wa === null) $errors[] = 'WhatsApp: enter a wa.me link (like https://wa.me/8801XXXXXXXXX) or just the phone number, or leave it empty to hide it.';
        else $links['whatsapp'] = $wa;
        foreach (['store_phone' => 'The phone number', 'store_phone2' => 'The second phone number'] as $pk => $plabel) {
            $pv = trim($_POST[$pk] ?? '');
            if ($pv !== '' && !preg_match('/^\+?[\d\s().-]{5,40}$/', $pv)) $errors[] = $plabel . ' should only contain digits, spaces, + ( ) - or a dot.';
        }
        if (!$errors) {
            $before = store_info();
            $beforeSocial = array_map(fn ($x) => $x['url'], store_socials());
            set_setting('store_name', mb_substr(trim($_POST['store_name'] ?? ''), 0, 80));
            set_setting('store_tagline', mb_substr(trim($_POST['store_tagline'] ?? ''), 0, 80));
            set_setting('store_phone', mb_substr(trim($_POST['store_phone'] ?? ''), 0, 40));
            set_setting('store_phone2', mb_substr(trim($_POST['store_phone2'] ?? ''), 0, 40));
            set_setting('store_email', $email);
            set_setting('store_address', mb_substr(trim(str_replace("\r", '', $_POST['store_address'] ?? '')), 0, 300));
            foreach ($links as $k => $u) set_setting('social_' . $k, mb_substr($u, 0, 255));
            $oldV = ['name' => $before['name'], 'tagline' => $before['tagline'], 'phone' => $before['phone'], 'phone2' => $before['phone2'], 'email' => $before['email'], 'address' => $before['address']];
            $newV = ['name' => trim($_POST['store_name'] ?? ''), 'tagline' => trim($_POST['store_tagline'] ?? ''), 'phone' => trim($_POST['store_phone'] ?? ''), 'phone2' => trim($_POST['store_phone2'] ?? ''),
                     'email' => $email, 'address' => trim(str_replace("\r", '', $_POST['store_address'] ?? ''))];
            $labels = ['name' => 'Store name', 'tagline' => 'Tagline', 'phone' => 'Phone', 'phone2' => 'Second phone', 'email' => 'Email', 'address' => 'Address'];
            foreach ($links as $k => $u) { $oldV['social_' . $k] = $beforeSocial[$k] ?? ''; $newV['social_' . $k] = $u; $labels['social_' . $k] = ucfirst($k) . ' link'; }
            $diff = admin_log_diff($oldV, $newV, $labels);
            admin_log('settings.store', 'Edited store details' . ($diff ? ': ' . admin_log_diff_summary($diff) : ' (saved, nothing changed)'), null, null, $diff ? ['changes' => $diff] : []);
            flash_set('success', 'Store details saved — they now show across the whole site: header, footer, contact page, legal pages, emails and invoices.');
            redirect('/admin/settings.php#store');
        }
    }

    if ($section === 'smtp') {
        $smtp0 = smtp_settings();
        $port = (int) ($_POST['smtp_port'] ?? 0);
        $from = trim($_POST['smtp_from_email'] ?? '');
        $secure = in_array($_POST['smtp_secure'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_secure'] : 'tls';
        if ($port && ($port < 1 || $port > 65535)) $errors[] = 'Port must be a number between 1 and 65535 (587 or 465 are typical).';
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) $errors[] = 'The "from" email address doesn\'t look right.';
        if (!$errors) {
            set_setting('smtp_host', trim($_POST['smtp_host'] ?? ''));
            set_setting('smtp_port', $port ? (string) $port : '');
            set_setting('smtp_secure', $secure === 'none' ? '' : $secure);
            set_setting('smtp_user', trim($_POST['smtp_user'] ?? ''));
            // The password field is write-only: leave it blank to keep the saved one.
            if (!empty($_POST['smtp_pass_clear'])) set_setting('smtp_pass', '');
            elseif (($_POST['smtp_pass'] ?? '') !== '') set_setting('smtp_pass', $_POST['smtp_pass']);
            set_setting('smtp_from_email', $from);
            set_setting('smtp_from_name', mb_substr(trim($_POST['smtp_from_name'] ?? ''), 0, 80));
            $sNew = ['host' => trim($_POST['smtp_host'] ?? ''), 'port' => $port ? (string) $port : '', 'secure' => $secure === 'none' ? '' : $secure, 'user' => trim($_POST['smtp_user'] ?? ''),
                     'from' => $from, 'from_name' => trim($_POST['smtp_from_name'] ?? '')];
            $sOld = ['host' => $smtp0['host'], 'port' => (string) $smtp0['port'], 'secure' => $smtp0['secure'], 'user' => $smtp0['user'], 'from' => $smtp0['from_email'], 'from_name' => $smtp0['from_name']];
            $diff = admin_log_diff($sOld, $sNew, ['host' => 'SMTP server', 'port' => 'Port', 'secure' => 'Encryption', 'user' => 'Username', 'from' => 'From address', 'from_name' => 'From name']);
            // The password itself is never written to the log — only the fact that it changed.
            if (!empty($_POST['smtp_pass_clear'])) $diff['Password'] = ['(saved)', 'cleared'];
            elseif (($_POST['smtp_pass'] ?? '') !== '') $diff['Password'] = ['(hidden)', 'changed'];
            admin_log('settings.email', 'Edited email (SMTP) settings' . ($diff ? ': ' . admin_log_diff_summary($diff) : ' (saved, nothing changed)'), null, null, $diff ? ['changes' => $diff] : []);
            flash_set('success', 'Email settings saved. Send a test email below to make sure they work.');
            redirect('/admin/settings.php#smtp');
        }
    }

    if ($section === 'test_email') {
        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address to send the test to.';
        } else {
            $ok = send_email($to, $to, 'Test email from ' . store_info()['name'],
                email_wrap('It works!', '<p>This is a test message sent from your store\'s admin panel. If you\'re reading it, your email settings are correct and order/verification emails will be delivered.</p>'));
            admin_log('settings.test_email', ($ok ? 'Sent' : 'Tried to send') . ' a test email to ' . $to . ($ok ? '' : ' (failed)'));
            if ($ok) { flash_set('success', 'Test email sent to ' . $to . '. Check the inbox (and spam folder).'); }
            else { flash_set('error', 'The test email failed: ' . (mail_last_error() ?: 'unknown error')); }
            redirect('/admin/settings.php#smtp');
        }
    }
}

$tb = topbar_settings();
$store = store_info();
$socials = store_socials();
$smtp = smtp_settings();
$smtpFromDb = (string) get_setting('smtp_host', '') !== '';
$smtpConfigured = $smtp['host'] !== '';
$secureChoice = $smtp['secure'] === '' ? 'none' : $smtp['secure'];

$pageTitle = 'Settings & email';
require __DIR__ . '/includes/header.php';
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<!-- ───────────── Announcement bar ───────────── -->
<form method="post" id="topbar" class="panel">
  <?= csrf_field() ?><input type="hidden" name="section" value="topbar">
  <div class="panel-head"><h2>Announcement bar</h2></div>
  <div class="panel-body">
    <p class="help">A slim bar above the header on every page — good for sales, holiday delivery notices or anything you want everyone to see. When it's off, the bar is hidden completely.</p>
    <div class="field">
      <label class="switch"><input type="checkbox" name="topbar_enabled" value="1" id="tbOn" <?= ($section === 'topbar' ? !empty($_POST['topbar_enabled']) : $tb['raw_enabled']) ? 'checked' : '' ?>><span class="track"></span><span>Show the announcement bar</span></label>
    </div>
    <div class="field">
      <label for="tbText">Message <span class="counter" data-counter-for="tbText" data-max="200"></span></label>
      <input type="text" id="tbText" name="topbar_text" maxlength="200" value="<?= e($section === 'topbar' ? ($_POST['topbar_text'] ?? '') : $tb['text']) ?>" placeholder="e.g. Eid sale — 15% off all leather goods this week">
    </div>
    <div class="field" style="margin-bottom:0;">
      <label for="tbLink">Link <span class="muted" style="font-weight:400;">(optional)</span></label>
      <input type="text" id="tbLink" name="topbar_link" value="<?= e($section === 'topbar' ? ($_POST['topbar_link'] ?? '') : $tb['link']) ?>" placeholder="https://… or /category.php?slug=leather">
      <div class="hint">If set, the whole message becomes a link.</div>
    </div>
    <div class="preview" style="margin-top:18px;"><div class="pv-top" id="tbPreview" style="background:var(--surface-dark);color:var(--on-dark);"></div></div>
  </div>
  <div class="panel-foot"><button class="btn btn-primary" type="submit">Save announcement bar</button></div>
</form>

<!-- ───────────── Store details ───────────── -->
<form method="post" id="store" class="panel">
  <?= csrf_field() ?><input type="hidden" name="section" value="store">
  <div class="panel-head"><h2>Store details <span class="sub">used everywhere on the site</span></h2></div>
  <div class="panel-body">
    <p class="help">Change these once and they update the header, footer, contact page, About and legal pages, page titles, emails and invoices. The logo, favicon and share image live under <a href="/admin/branding.php" style="text-decoration:underline;font-weight:600;">Branding &amp; sharing</a>.</p>
    <div class="field-row">
      <div class="field"><label for="store_name">Store name</label><input type="text" id="store_name" name="store_name" value="<?= e($section === 'store' ? ($_POST['store_name'] ?? '') : $store['name']) ?>" maxlength="80" required></div>
      <div class="field"><label for="store_tagline">Tagline <span class="muted" style="font-weight:400;">(optional)</span></label><input type="text" id="store_tagline" name="store_tagline" value="<?= e($section === 'store' ? ($_POST['store_tagline'] ?? '') : $store['tagline']) ?>" maxlength="80" placeholder="EDC gear, bags &amp; leather goods"><div class="hint">Shown in the homepage's browser-tab title: “<?= e($store['name']) ?> — tagline”.</div></div>
    </div>
    <div class="field-row">
      <div class="field"><label for="store_phone">Phone number</label><input type="text" id="store_phone" name="store_phone" value="<?= e($section === 'store' ? ($_POST['store_phone'] ?? '') : $store['phone']) ?>" maxlength="40" placeholder="+880 1XXX-XXXXXX"></div>
      <div class="field"><label for="store_phone2">Second phone number <span class="muted" style="font-weight:400;">(optional)</span></label><input type="text" id="store_phone2" name="store_phone2" value="<?= e($section === 'store' ? ($_POST['store_phone2'] ?? '') : $store['phone2']) ?>" maxlength="40" placeholder="+880 1XXX-XXXXXX"><div class="hint">Shown next to the main number in the footer, contact page and invoices. Leave empty to hide.</div></div>
    </div>
    <div class="field-row">
      <div class="field"><label for="store_email">Email</label><input type="email" id="store_email" name="store_email" value="<?= e($section === 'store' ? ($_POST['store_email'] ?? '') : $store['email']) ?>"><div class="hint">Also where messages from the contact form are delivered.</div></div>
    </div>
    <div class="field"><label for="store_address">Address</label><textarea id="store_address" name="store_address" rows="3" maxlength="300" placeholder="House, road, area&#10;City"><?= e($section === 'store' ? ($_POST['store_address'] ?? '') : $store['address']) ?></textarea></div>

    <h3 class="subhead">Social links</h3>
    <p class="help">Shown as icons in the footer, mobile menu and contact page. Leave one empty to hide it.</p>
    <div class="field-row">
      <?php foreach (['facebook' => ['Facebook page', 'https://www.facebook.com/yourpage'], 'messenger' => ['Messenger', 'https://m.me/yourpage'], 'instagram' => ['Instagram', 'https://www.instagram.com/yourname/'], 'youtube' => ['YouTube', 'https://www.youtube.com/@yourchannel'], 'whatsapp' => ['WhatsApp', 'https://wa.me/8801XXXXXXXXX']] as $k => [$label, $ph]): ?>
        <div class="field"><label for="social_<?= $k ?>"><?= e($label) ?></label><input type="<?= $k === 'whatsapp' ? 'text' : 'url' ?>" id="social_<?= $k ?>" name="social_<?= $k ?>" value="<?= e($section === 'store' ? ($_POST['social_' . $k] ?? '') : ($socials[$k]['url'] ?? '')) ?>" placeholder="<?= e($ph) ?>"><?php if ($k === 'whatsapp'): ?><div class="hint">A wa.me link, or just the number (01XXXXXXXXX works) — it's turned into a link for you. Shown with the other social icons.</div><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel-foot"><button class="btn btn-primary" type="submit">Save store details</button></div>
</form>

<!-- ───────────── SMTP ───────────── -->
<form method="post" id="smtp" class="panel">
  <?= csrf_field() ?><input type="hidden" name="section" value="smtp">
  <div class="panel-head">
    <h2>Email (SMTP)</h2>
    <?php if ($smtpConfigured): ?><span class="pill pill-sage">Configured<?= $smtpFromDb ? '' : ' via server settings' ?></span><?php else: ?><span class="pill pill-rust">Not set up</span><?php endif; ?>
  </div>
  <div class="panel-body">
    <?php if (!$smtpConfigured): ?>
      <div class="alert alert-warn">No SMTP server is configured, so the store falls back to PHP's built-in mail — which doesn't work from a Docker container. Order confirmations, status updates, account verification and contact-form messages won't be delivered until you fill this in.</div>
    <?php endif; ?>
    <p class="help">Enter the details from your email provider (Gmail, Brevo, Zoho, your hosting's mail server…). Values saved here override anything in the server's <code>.env</code> file.</p>
    <div class="field-row cols-3">
      <div class="field"><label for="smtp_host">SMTP host</label><input type="text" id="smtp_host" name="smtp_host" value="<?= e($smtp['host']) ?>" placeholder="smtp.gmail.com"></div>
      <div class="field"><label for="smtp_port">Port</label><input type="number" id="smtp_port" name="smtp_port" value="<?= e($smtp['port']) ?>" placeholder="587"></div>
      <div class="field"><label for="smtp_secure">Encryption</label>
        <select id="smtp_secure" name="smtp_secure">
          <option value="tls" <?= $secureChoice === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587)</option>
          <option value="ssl" <?= $secureChoice === 'ssl' ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
          <option value="none" <?= $secureChoice === 'none' ? 'selected' : '' ?>>None (not recommended)</option>
        </select>
      </div>
    </div>
    <div class="field-row">
      <div class="field"><label for="smtp_user">Username</label><input type="text" id="smtp_user" name="smtp_user" value="<?= e($smtp['user']) ?>" autocomplete="off" placeholder="you@yourdomain.com"></div>
      <div class="field"><label for="smtp_pass">Password</label>
        <input type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password" placeholder="<?= $smtp['pass'] !== '' ? '•••••••• (saved — leave blank to keep)' : '' ?>">
        <?php if ($smtp['pass'] !== '' && $smtpFromDb): ?><label class="switch" style="margin-top:8px;font-size:0.8rem;"><input type="checkbox" name="smtp_pass_clear" value="1"><span class="track"></span><span>Remove the saved password</span></label><?php endif; ?>
        <div class="hint">For Gmail use an <em>App Password</em>, not your normal password.</div>
      </div>
    </div>
    <div class="field-row">
      <div class="field"><label for="smtp_from_email">"From" email</label><input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= e((string) get_setting('smtp_from_email', '')) ?>" placeholder="<?= e($smtp['from_email']) ?>"><div class="hint">Usually the same as the username — many providers reject other addresses. Leave blank to use <?= e($smtp['from_email']) ?>.</div></div>
      <div class="field"><label for="smtp_from_name">"From" name</label><input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e((string) get_setting('smtp_from_name', '')) ?>" placeholder="<?= e(store_name()) ?>"><div class="hint">Leave blank to use the store name (<?= e(store_name()) ?>) — it then follows any rename.</div></div>
    </div>
  </div>
  <div class="panel-foot"><button class="btn btn-primary" type="submit">Save email settings</button></div>
</form>

<form method="post" class="panel">
  <?= csrf_field() ?><input type="hidden" name="section" value="test_email">
  <div class="panel-head"><h2>Send a test email</h2></div>
  <div class="panel-body" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
    <div class="field" style="margin:0;flex:1 1 280px;max-width:380px;"><label for="test_to">Send to</label><input type="email" id="test_to" name="test_to" required value="<?= e($_POST['test_to'] ?? '') ?>" placeholder="your@email.com"></div>
    <button class="btn btn-outline" type="submit">Send test</button>
    <div class="hint" style="flex-basis:100%;">Uses the settings saved above — save first, then test. If it fails, the exact error from the mail server is shown.</div>
  </div>
</form>

<script>
(function () {
  var on = document.getElementById('tbOn'), text = document.getElementById('tbText'), prev = document.getElementById('tbPreview');
  function paint() {
    var t = text.value.trim();
    prev.textContent = on.checked ? (t || 'Your message appears here') : 'The bar is off — nothing is shown above the header.';
    prev.style.opacity = on.checked && t ? 1 : .55;
  }
  on.addEventListener('change', paint); text.addEventListener('input', paint); paint();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
