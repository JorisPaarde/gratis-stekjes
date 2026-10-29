<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
stekjes_render_header(['title' => 'Bevestig je e-mail', 'canonical_path' => '/account/bevestiging-nodig.php']);
?>
<section class="section wrap prose">
  <h1>Check je inbox</h1>
  <p>We hebben je een bevestigingslink gestuurd. Klik die link om verder te gaan. Geen mail? Kijk in je spamfolder.</p>
  <p><a class="btn" href="<?= stekjes_e(stekjes_path('/')) ?>">Naar home</a></p>
</section>
<?php stekjes_render_footer(); ?>
