<?php

declare(strict_types=1);

/**
 * Simpele admin: helperslijst, meldingen, basis voor partnermarkers (later).
 * Geen moderatiewachtrij / regiomoderators.
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

stekjes_start_session();
$config = stekjes_config();
$adminPass = (string) ($config['admin_password'] ?? '');

if ($adminPass === '') {
    http_response_code(503);
    echo 'Admin niet geconfigureerd.';
    exit;
}

if (isset($_POST['logout'])) {
    unset($_SESSION['admin_ok']);
    stekjes_redirect(stekjes_path('/beheer/'));
}

if (empty($_SESSION['admin_ok'])) {
    $err = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $pw = (string) ($_POST['password'] ?? '');
        if (!stekjes_verify_csrf($_POST['csrf'] ?? null)) {
            $err = 'Sessie verlopen.';
        } elseif (hash_equals($adminPass, $pw)) {
            $_SESSION['admin_ok'] = true;
            stekjes_redirect(stekjes_path('/beheer/'));
        } else {
            $err = 'Wachtwoord klopt niet.';
        }
    }
    stekjes_render_header(['title' => 'Beheer']);
    echo '<section class="section wrap"><h1>Beheer</h1>';
    if ($err) {
        echo '<p class="error">' . stekjes_e($err) . '</p>';
    }
    echo '<form class="form" method="post">' . stekjes_csrf_field() .
        '<label>Wachtwoord<input type="password" name="password" required autocomplete="current-password"></label>' .
        '<button class="btn" type="submit">Inloggen</button></form></section>';
    stekjes_render_footer();
    exit;
}

$pdo = stekjes_db();
$helpers = $pdo->query('SELECT id, naam, email, wat, confirmed_at, created_at FROM helpers ORDER BY created_at DESC')->fetchAll();
$reports = $pdo->query('SELECT id, reporter_id, reported_user_id, conversation_id, reason, created_at, handled FROM reports ORDER BY created_at DESC LIMIT 100')->fetchAll();
$markers = $pdo->query('SELECT id, naam, lat, lon, url, bron, visible FROM partner_markers ORDER BY id')->fetchAll();

stekjes_render_header(['title' => 'Beheer']);
?>
<section class="section wrap">
  <h1>Beheer</h1>
  <form method="post" style="margin-bottom:1rem"><button class="btn btn-secondary" name="logout" value="1" type="submit">Uitloggen</button></form>

  <h2>Helpers</h2>
  <table class="table">
    <thead><tr><th>Naam</th><th>E-mail</th><th>Wat</th><th>Bevestigd</th></tr></thead>
    <tbody>
    <?php foreach ($helpers as $h): ?>
      <tr>
        <td><?= stekjes_e((string) $h['naam']) ?></td>
        <td><?= stekjes_e((string) $h['email']) ?></td>
        <td><?= stekjes_e((string) $h['wat']) ?></td>
        <td><?= stekjes_e((string) ($h['confirmed_at'] ?? 'nee')) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$helpers): ?><tr><td colspan="4">Nog geen helpers.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <h2>Meldingen</h2>
  <table class="table">
    <thead><tr><th>Id</th><th>Van→over</th><th>Reden</th><th>Datum</th></tr></thead>
    <tbody>
    <?php foreach ($reports as $r): ?>
      <tr>
        <td><?= (int) $r['id'] ?></td>
        <td><?= (int) $r['reporter_id'] ?> → <?= (int) $r['reported_user_id'] ?></td>
        <td><?= stekjes_e((string) $r['reason']) ?></td>
        <td><?= stekjes_e((string) $r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$reports): ?><tr><td colspan="4">Geen meldingen.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <h2>Partnermarkers</h2>
  <p class="muted">Basis voor markers op de kaart. Beheer/CRUD volgt van een andere agent. Nu alleen overzicht.</p>
  <table class="table">
    <thead><tr><th>Naam</th><th>Lat/Lon</th><th>Bron</th><th>Zichtbaar</th></tr></thead>
    <tbody>
    <?php foreach ($markers as $m): ?>
      <tr>
        <td><?= stekjes_e((string) $m['naam']) ?></td>
        <td><?= stekjes_e((string) $m['lat']) ?>, <?= stekjes_e((string) $m['lon']) ?></td>
        <td><?= stekjes_e((string) $m['bron']) ?></td>
        <td><?= !empty($m['visible']) ? 'ja' : 'nee' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$markers): ?><tr><td colspan="4">Nog geen markers. Stichting Voedselbosbouw en MeerBomen Nu staan op de <a href="<?= stekjes_e(stekjes_path('/links/')) ?>">partnerspagina</a> (geen bezoeklocatie gevonden voor markers).</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php stekjes_render_footer(); ?>
