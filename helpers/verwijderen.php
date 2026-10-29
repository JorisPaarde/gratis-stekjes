<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$token = stekjes_clean((string) ($_GET['token'] ?? ''), 128);
$done = false;
$error = null;
$requested = false;

if ($token !== '') {
    $result = stekjes_confirm_helper_delete($token);
    if (!empty($result['ok'])) {
        $done = true;
    } else {
        $error = $result['error'] ?? 'Verwijderen mislukt.';
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_request_helper_delete((string) ($_POST['email'] ?? ''), $_POST);
    if (!empty($result['ok'])) {
        $requested = true;
    } else {
        $error = $result['error'] ?? 'Aanvraag mislukt.';
    }
}

stekjes_render_header(['title' => 'Helper-gegevens verwijderen', 'canonical_path' => '/helpers/verwijderen.php']);
?>
<section class="section wrap prose">
  <h1>Helper-aanmelding verwijderen</h1>
  <?php if ($done): ?>
    <p class="flash flash-ok">Je helper-gegevens zijn verwijderd.</p>
  <?php elseif ($requested): ?>
    <p>Als dit e-mailadres bij ons bekend is, sturen we een bevestigingslink om te verwijderen.</p>
  <?php else: ?>
    <p>Helpers hebben geen account. Vul je e-mail in; je krijgt een bevestigingslink.</p>
    <?php if ($error): ?><p class="error"><?= stekjes_e($error) ?></p><?php endif; ?>
    <form class="form" method="post">
      <?= stekjes_csrf_field() ?>
      <?= stekjes_honeypot_and_timing_fields() ?>
      <label>E-mail
        <input type="email" name="email" required maxlength="254" autocomplete="email">
      </label>
      <button class="btn" type="submit">Stuur verwijderlink</button>
    </form>
  <?php endif; ?>
</section>
<?php stekjes_render_footer(); ?>
