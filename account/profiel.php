<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_update_profile((int) $user['id'], [
        'voornaam' => (string) ($_POST['voornaam'] ?? ''),
        'telefoon' => (string) ($_POST['telefoon'] ?? ''),
        'locatie' => (string) ($_POST['locatie'] ?? ''),
    ], $_POST, true);
    if (!empty($result['ok'])) {
        stekjes_flash_set('ok', 'Profiel opgeslagen.');
        stekjes_redirect(stekjes_path('/account/profiel.php'));
    }
    $error = $result['error'] ?? 'Opslaan mislukt.';
    $user = stekjes_require_user();
}

stekjes_render_header(['title' => 'Profiel', 'canonical_path' => '/account/profiel.php']);
?>
<section class="section wrap">
  <h1>Profiel</h1>
  <p class="section-intro">Publiek: voornaam/bijnaam en benaderde locatie (postcode-4 of plaats). Telefoon alleen zichtbaar bij wederzijds “ja” in een gesprek.</p>
  <?php if ($error): ?><p class="error" role="alert"><?= stekjes_e($error) ?></p><?php endif; ?>
  <form class="form" method="post">
    <?= stekjes_csrf_field() ?>
    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" value="<?= stekjes_e((string) $user['voornaam']) ?>">
    </label>
    <label>Postcode (4 cijfers) of plaats *
      <input name="locatie" required maxlength="120" value="<?= stekjes_e((string) $user['locatie_label']) ?>" placeholder="Bijv. 2011 of Haarlem">
      <span class="hint">We slaan alleen een afgeronde plek op, geen straatadres.</span>
    </label>
    <label>Telefoon (optioneel)
      <input name="telefoon" maxlength="40" value="<?= stekjes_e((string) $user['telefoon']) ?>" autocomplete="tel">
    </label>
    <button class="btn" type="submit">Opslaan</button>
  </form>
  <p><a href="<?= stekjes_e(stekjes_path('/account/')) ?>">Terug naar account</a></p>
</section>
<?php stekjes_render_footer(); ?>
