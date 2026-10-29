<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$token = stekjes_clean((string) ($_GET['token'] ?? $_POST['token'] ?? ''), 128);
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_reset_password($token, (string) ($_POST['password'] ?? ''), $_POST);
    if (!empty($result['ok'])) {
        stekjes_flash_set('ok', 'Wachtwoord aangepast.');
        stekjes_redirect(stekjes_path('/account/'));
    }
    $error = $result['error'] ?? 'Reset mislukt.';
}

stekjes_render_header(['title' => 'Nieuw wachtwoord', 'canonical_path' => '/account/wachtwoord-reset.php']);
?>
<section class="section wrap">
  <h1>Nieuw wachtwoord</h1>
  <?php if ($error): ?><p class="error"><?= stekjes_e($error) ?></p><?php endif; ?>
  <form class="form" method="post">
    <?= stekjes_csrf_field() ?>
    <input type="hidden" name="token" value="<?= stekjes_e($token) ?>">
    <label>Nieuw wachtwoord (minstens 10 tekens)
      <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <button class="btn" type="submit">Opslaan</button>
  </form>
</section>
<?php stekjes_render_footer(); ?>
