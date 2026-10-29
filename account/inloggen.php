<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$next = stekjes_clean((string) ($_GET['next'] ?? $_POST['next'] ?? '/account/'), 200);
if ($next === '' || $next[0] !== '/') {
    $next = '/account/';
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), $_POST);
    if (!empty($result['ok'])) {
        stekjes_redirect(stekjes_path($next));
    }
    $error = $result['error'] ?? 'Inloggen mislukt.';
}

stekjes_render_header(['title' => 'Inloggen', 'canonical_path' => '/account/inloggen.php']);
?>
<section class="section wrap">
  <h1>Inloggen</h1>
  <?php if ($error): ?><p class="error" role="alert"><?= stekjes_e($error) ?></p><?php endif; ?>
  <form class="form" method="post">
    <?= stekjes_csrf_field() ?>
    <?= stekjes_honeypot_and_timing_fields() ?>
    <input type="hidden" name="next" value="<?= stekjes_e($next) ?>">
    <label>E-mail
      <input type="email" name="email" required maxlength="254" autocomplete="email">
    </label>
    <label>Wachtwoord
      <input type="password" name="password" required maxlength="200" autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Inloggen</button>
  </form>
  <p class="muted"><a href="<?= stekjes_e(stekjes_path('/account/wachtwoord-vergeten.php')) ?>">Wachtwoord vergeten</a> ·
    <a href="<?= stekjes_e(stekjes_path('/account/registreren.php')) ?>">Account maken</a></p>
</section>
<?php stekjes_render_footer(); ?>
