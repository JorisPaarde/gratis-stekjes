<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();

stekjes_render_header(['title' => 'Account', 'canonical_path' => '/account/']);
?>
<section class="section wrap">
  <h1>Hoi <?= stekjes_e($user['voornaam']) ?></h1>
  <div class="grid-cards">
    <a class="soft-card" href="<?= stekjes_e(stekjes_path('/account/profiel.php')) ?>"><h3>Profiel</h3><p>Naam, locatie, telefoon (optioneel).</p></a>
    <a class="soft-card" href="<?= stekjes_e(stekjes_path('/account/aanbod.php')) ?>"><h3>Mijn aanbod</h3><p>Wat je uitdeelt of zoekt.</p></a>
    <a class="soft-card" href="<?= stekjes_e(stekjes_path('/account/inbox.php')) ?>"><h3>Inbox</h3><p>Berichten met anderen.</p></a>
    <a class="soft-card" href="<?= stekjes_e(stekjes_path('/aanbod/')) ?>"><h3>Zoeken</h3><p>Aanbod in de buurt.</p></a>
  </div>
  <p style="margin-top:1.5rem"><a href="<?= stekjes_e(stekjes_path('/account/uitloggen.php')) ?>">Uitloggen</a></p>
</section>
<?php stekjes_render_footer(); ?>
