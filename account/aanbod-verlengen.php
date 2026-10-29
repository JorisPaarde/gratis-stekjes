<?php

declare(strict_types=1);

/**
 * Eenklik-verlenging via getekend, eenmalig token uit de herinneringsmail.
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$token = stekjes_clean((string) ($_GET['token'] ?? ''), 128);
$result = $token !== ''
    ? stekjes_renew_aanbod_by_token($token)
    : ['ok' => false, 'error' => 'Geen verlenglink gevonden.'];

stekjes_render_header(['title' => 'Aanbod verlengen', 'canonical_path' => '/account/aanbod-verlengen.php']);
?>
<section class="section wrap">
  <h1>Aanbod verlengen</h1>
  <?php if (!empty($result['ok'])): ?>
    <p role="status">Gelukt — ik toon je aanbod weer 60 dagen in zoeken en op de kaart.</p>
    <p><a class="btn" href="<?= stekjes_e(stekjes_path('/account/aanbod.php')) ?>">Naar mijn aanbod</a></p>
  <?php else: ?>
    <p class="error" role="alert"><?= stekjes_e((string) ($result['error'] ?? 'Verlengen mislukt.')) ?></p>
    <p class="muted">Ben je ingelogd? Dan kun je verlopen aanbod ook vanaf <a href="<?= stekjes_e(stekjes_path('/account/aanbod.php')) ?>">mijn aanbod</a> verlengen.</p>
  <?php endif; ?>
</section>
<?php stekjes_render_footer(); ?>
