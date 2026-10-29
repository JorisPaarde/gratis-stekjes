<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$rol = stekjes_clean((string) ($_GET['rol'] ?? $_POST['rol'] ?? ''), 20);
if (!in_array($rol, ['uitdelen', 'ophalen'], true)) {
    $rol = '';
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_register(
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['voornaam'] ?? ''),
        $_POST
    );
    if (!empty($result['ok'])) {
        stekjes_flash_set('ok', 'Check je e-mail om je aanmelding te bevestigen.');
        stekjes_redirect(stekjes_path('/account/bevestiging-nodig.php'));
    }
    $error = $result['error'] ?? 'Er ging iets mis.';
}

stekjes_render_header([
    'title' => 'Registreren',
    'canonical_path' => '/account/registreren.php',
]);
?>
<section class="section wrap">
  <h1>Account maken</h1>
  <p class="section-intro">Gratis meedoen. Stekjes blijven gratis — geen verkoop. Na registratie bevestig je je e-mail.</p>
  <?php if ($error): ?><p class="error" role="alert"><?= stekjes_e($error) ?></p><?php endif; ?>
  <form class="form" method="post" action="">
    <?= stekjes_csrf_field() ?>
    <?= stekjes_honeypot_and_timing_fields() ?>
    <input type="hidden" name="rol" value="<?= stekjes_e($rol) ?>">
    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" autocomplete="nickname" value="<?= stekjes_e((string) ($_POST['voornaam'] ?? '')) ?>">
    </label>
    <label>E-mail *
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="<?= stekjes_e((string) ($_POST['email'] ?? '')) ?>">
    </label>
    <label>Wachtwoord * <span class="hint">Minstens 10 tekens</span>
      <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <?php if ($rol): ?>
      <p class="muted">Je koos: <strong><?= stekjes_e($rol === 'uitdelen' ? 'stekjes uitdelen' : 'stekjes ophalen') ?></strong>. Na het bevestigen vul je je profiel en eventueel aanbod in.</p>
    <?php endif; ?>
    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required <?= !empty($_POST['gratis_akkoord']) ? 'checked' : '' ?>>
      <span>Ik ga akkoord: alleen <strong>gratis</strong>, geen verkoop van stekjes via deze site.</span>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required>
      <span>Ik ga akkoord met de <a href="<?= stekjes_e(stekjes_path('/privacy/')) ?>">privacyverklaring</a>.</span>
    </label>
    <button class="btn" type="submit">Registreren</button>
  </form>
  <p class="muted">Al een account? <a href="<?= stekjes_e(stekjes_path('/account/inloggen.php')) ?>">Inloggen</a></p>
</section>
<?php stekjes_render_footer(); ?>
