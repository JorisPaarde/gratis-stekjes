<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_delete_user_account((int) $user['id'], $_POST);
    if (!empty($result['ok'])) {
        stekjes_flash_set('ok', 'Je account en bijbehorende gegevens zijn verwijderd.');
        stekjes_redirect(stekjes_path('/'));
    }
    $error = $result['error'] ?? 'Verwijderen mislukt.';
}

stekjes_render_header(['title' => 'Account verwijderen', 'canonical_path' => '/account/verwijderen.php']);
?>
<section class="section wrap prose">
  <h1>Account verwijderen</h1>
  <p>Dit wist je account, aanbod, gesprekken, berichten, blokkades en meldingen die bij jou horen. Dit kun je niet terugdraaien.</p>
  <p>Download eerst eventueel een <a href="<?= stekjes_e(stekjes_path('/account/export.php')) ?>">gegevensexport (JSON)</a>.</p>
  <?php if ($error): ?><p class="error" role="alert"><?= stekjes_e($error) ?></p><?php endif; ?>
  <form class="form" method="post">
    <?= stekjes_csrf_field() ?>
    <label>Typ <strong>verwijder</strong> om te bevestigen
      <input name="bevestig" required autocomplete="off">
    </label>
    <button class="btn" type="submit">Account definitief verwijderen</button>
  </form>
  <p><a href="<?= stekjes_e(stekjes_path('/account/')) ?>">Annuleren</a></p>
</section>
<?php stekjes_render_footer(); ?>
