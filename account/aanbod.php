<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = stekjes_create_aanbod(
        (int) $user['id'],
        (string) ($_POST['type'] ?? ''),
        (string) ($_POST['tekst'] ?? ''),
        $_POST
    );
    if (!empty($result['ok'])) {
        stekjes_flash_set('ok', 'Aanbod geplaatst.');
        stekjes_redirect(stekjes_path('/account/aanbod.php'));
    }
    $error = $result['error'] ?? 'Plaatsen mislukt.';
}

$pdo = stekjes_db();
$stmt = $pdo->prepare('SELECT * FROM aanbod WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([(int) $user['id']]);
$mine = $stmt->fetchAll();

stekjes_render_header(['title' => 'Mijn aanbod', 'canonical_path' => '/account/aanbod.php']);
?>
<section class="section wrap">
  <h1>Mijn aanbod</h1>
  <p class="section-intro">Vrije tekst over wat je hebt of zoekt. Geen soortenlijst in deze versie — stektips staan apart.</p>
  <?php if ($error): ?><p class="error" role="alert"><?= stekjes_e($error) ?></p><?php endif; ?>
  <form class="form" method="post">
    <?= stekjes_csrf_field() ?>
    <?= stekjes_honeypot_and_timing_fields() ?>
    <label>Type *
      <select name="type" required>
        <option value="uitdelen">Ik deel uit</option>
        <option value="ophalen">Ik zoek / wil ophalen</option>
      </select>
    </label>
    <label>Wat heb je of zoek je? *
      <textarea name="tekst" required maxlength="2000" placeholder="Bijv. zwarte bessenstekken, vanaf november, zaterdagochtend"></textarea>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required>
      <span>Alleen gratis, geen verkoop.</span>
    </label>
    <button class="btn" type="submit">Plaatsen</button>
  </form>
  <h2>Jouw items</h2>
  <?php if (!$mine): ?>
    <p class="muted">Nog niets geplaatst.</p>
  <?php else: ?>
    <ul class="zoek-lijst">
      <?php foreach ($mine as $row): ?>
        <li class="zoek-item">
          <span class="badge"><?= stekjes_e((string) $row['type']) ?></span>
          <?= !empty($row['visible']) ? '' : ' (verborgen)' ?>
          <p><?= stekjes_e((string) $row['tekst']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php stekjes_render_footer(); ?>
