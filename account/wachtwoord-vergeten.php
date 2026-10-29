<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$done = false;
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_request_password_reset((string) ($_POST['email'] ?? ''), $_POST);
    if (!empty($result['ok'])) {
        $done = true;
    } else {
        $error = $result['error'] ?? 'Mislukt.';
    }
}

stekjes_render_header(['title' => 'Wachtwoord vergeten', 'canonical_path' => '/account/wachtwoord-vergeten.php']);
?>
<section class="section wrap">
  <h1>Wachtwoord vergeten</h1>
  <?php if ($done): ?>
    <p>Als dit e-mailadres bij ons bekend is, sturen we een resetlink.</p>
  <?php else: ?>
    <?php if ($error): ?><p class="error"><?= stekjes_e($error) ?></p><?php endif; ?>
    <form class="form" method="post">
      <?= stekjes_csrf_field() ?>
      <?= stekjes_honeypot_and_timing_fields() ?>
      <label>E-mail
        <input type="email" name="email" required maxlength="254" autocomplete="email">
      </label>
      <button class="btn" type="submit">Stuur resetlink</button>
    </form>
  <?php endif; ?>
</section>
<?php stekjes_render_footer(); ?>
