<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$result = stekjes_confirm_helper((string) ($_GET['token'] ?? ''));
stekjes_render_header(['title' => 'Helper bevestigen']);
?>
<section class="section wrap prose">
  <?php if (!empty($result['ok'])): ?>
    <h1>Bedankt</h1>
    <p>Je helper-aanmelding is bevestigd. Joris ziet je in de lijst.</p>
  <?php else: ?>
    <h1>Bevestigen mislukt</h1>
    <p><?= stekjes_e($result['error'] ?? 'Ongeldige link.') ?></p>
  <?php endif; ?>
  <p><a class="btn" href="<?= stekjes_e(stekjes_path('/')) ?>">Naar home</a></p>
</section>
<?php stekjes_render_footer(); ?>
