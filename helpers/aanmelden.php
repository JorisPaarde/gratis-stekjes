<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$error = null;
$done = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (empty($_POST['privacy'])) {
        $error = 'Je moet akkoord gaan met de privacyverklaring.';
    } else {
        $result = stekjes_register_helper(
            (string) ($_POST['naam'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['wat'] ?? ''),
            $_POST
        );
        if (!empty($result['ok'])) {
            $done = true;
        } else {
            $error = $result['error'] ?? 'Aanmelden mislukt.';
        }
    }
}

stekjes_render_header(['title' => 'Helpen', 'canonical_path' => '/helpers/aanmelden.php']);
?>
<section class="section wrap">
  <h1>Ik help op een andere manier</h1>
  <p class="section-intro">Geen volledig account nodig. Na bevestiging via e-mail komt je aanmelding in de helperslijst.</p>
  <?php if ($done): ?>
    <p class="flash flash-ok">Check je e-mail om te bevestigen.</p>
  <?php else: ?>
    <?php if ($error): ?><p class="error"><?= stekjes_e($error) ?></p><?php endif; ?>
    <form class="form" method="post">
      <?= stekjes_csrf_field() ?>
      <?= stekjes_honeypot_and_timing_fields() ?>
      <label>Naam of bijnaam *
        <input name="naam" required maxlength="80" autocomplete="nickname">
      </label>
      <label>E-mail *
        <input type="email" name="email" required maxlength="254" autocomplete="email">
      </label>
      <label>Wat wil je doen? *
        <textarea name="wat" required maxlength="2000"></textarea>
      </label>
      <label class="checkbox">
        <input type="checkbox" name="privacy" value="1" required>
        <span>Ik ga akkoord met de <a href="<?= stekjes_e(stekjes_path('/privacy/')) ?>">privacyverklaring</a>.</span>
      </label>
      <button class="btn" type="submit">Aanmelden als helper</button>
    </form>
  <?php endif; ?>
</section>
<?php stekjes_render_footer(); ?>
