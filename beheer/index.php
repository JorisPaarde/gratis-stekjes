<?php

declare(strict_types=1);

/**
 * Simpele admin: helperslijst, meldingen, CRUD voor partnermarkers.
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
$markerError = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = $editId > 0 ? stekjes_admin_get_partner_marker($editId) : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['marker_action'])) {
    if (!stekjes_verify_csrf($_POST['csrf'] ?? null)) {
        $markerError = 'Sessie verlopen. Probeer opnieuw.';
    } else {
        $action = (string) $_POST['marker_action'];
        if ($action === 'delete') {
            $delId = (int) ($_POST['id'] ?? 0);
            $res = stekjes_delete_partner_marker($delId);
            if (!empty($res['ok'])) {
                stekjes_flash_set('ok', 'Marker verwijderd.');
                stekjes_redirect(stekjes_path('/beheer/'));
            }
            $markerError = $res['error'] ?? 'Verwijderen mislukt.';
        } elseif ($action === 'save') {
            $saveId = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
            $res = stekjes_save_partner_marker($saveId, [
                'naam' => (string) ($_POST['naam'] ?? ''),
                'type' => (string) ($_POST['type'] ?? ''),
                'plaats' => (string) ($_POST['plaats'] ?? ''),
                'beschrijving' => (string) ($_POST['beschrijving'] ?? ''),
                'url' => (string) ($_POST['url'] ?? ''),
                'bron' => (string) ($_POST['bron'] ?? ''),
                'visible' => !empty($_POST['visible']),
            ], true);
            if (!empty($res['ok'])) {
                stekjes_flash_set('ok', $saveId ? 'Marker bijgewerkt.' : 'Marker toegevoegd.');
                stekjes_redirect(stekjes_path('/beheer/'));
            }
            $markerError = $res['error'] ?? 'Opslaan mislukt.';
            $editing = [
                'id' => $saveId ?? 0,
                'naam' => (string) ($_POST['naam'] ?? ''),
                'type' => (string) ($_POST['type'] ?? 'partner'),
                'plaats' => (string) ($_POST['plaats'] ?? ''),
                'beschrijving' => (string) ($_POST['beschrijving'] ?? ''),
                'url' => (string) ($_POST['url'] ?? ''),
                'bron' => (string) ($_POST['bron'] ?? ''),
                'visible' => !empty($_POST['visible']) ? 1 : 0,
                'lat' => $editing['lat'] ?? '',
                'lon' => $editing['lon'] ?? '',
            ];
        }
    }
}

$helpers = $pdo->query('SELECT id, naam, email, wat, confirmed_at, created_at FROM helpers ORDER BY created_at DESC')->fetchAll();
$reports = $pdo->query('SELECT id, reporter_id, reported_user_id, conversation_id, reason, created_at, handled FROM reports ORDER BY created_at DESC LIMIT 100')->fetchAll();
$markers = stekjes_admin_list_partner_markers();
$typeLabels = stekjes_partner_type_labels();

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

  <h2 id="markers">Partnermarkers</h2>
  <p class="muted">Voeg plekken toe die op de aanbodkaart verschijnen: partner, voedselbos, plantenasiel, plantenbieb of ruilkast. Coördinaten komen benaderd via PDOK op basis van plaats of postcode-4. Organisaties zonder bezoeklocatie horen op de <a href="<?= stekjes_e(stekjes_path('/links/')) ?>">partnerspagina</a>, niet als marker.</p>

  <?php if ($markerError): ?>
    <p class="error" role="alert"><?= stekjes_e($markerError) ?></p>
  <?php endif; ?>

  <form class="form" method="post" style="max-width:36rem;margin-bottom:1.5rem">
    <?= stekjes_csrf_field() ?>
    <input type="hidden" name="marker_action" value="save">
    <?php if ($editing && !empty($editing['id'])): ?>
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <p><strong>Marker bewerken #<?= (int) $editing['id'] ?></strong>
        · <a href="<?= stekjes_e(stekjes_path('/beheer/')) ?>">Annuleren</a></p>
    <?php else: ?>
      <p><strong>Nieuwe marker</strong></p>
    <?php endif; ?>
    <label>Naam *
      <input name="naam" required maxlength="160" value="<?= stekjes_e((string) ($editing['naam'] ?? '')) ?>">
    </label>
    <label>Type *
      <select name="type" required>
        <?php foreach ($typeLabels as $val => $label): ?>
          <option value="<?= stekjes_e($val) ?>"<?= (($editing['type'] ?? 'partner') === $val) ? ' selected' : '' ?>><?= stekjes_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Plaats of postcode-4 *
      <input name="plaats" required maxlength="120" value="<?= stekjes_e((string) ($editing['plaats'] ?? '')) ?>" placeholder="Bijv. 2011 of Haarlem">
      <span class="hint">We geocoderen via PDOK; op de kaart staat alleen een benaderd punt.</span>
    </label>
    <label>Website (optioneel)
      <input name="url" type="url" maxlength="500" value="<?= stekjes_e((string) ($editing['url'] ?? '')) ?>" placeholder="https://…">
    </label>
    <label>Korte beschrijving
      <textarea name="beschrijving" maxlength="500" rows="3"><?= stekjes_e((string) ($editing['beschrijving'] ?? '')) ?></textarea>
    </label>
    <label>Bron / notitie (intern, optioneel)
      <input name="bron" maxlength="500" value="<?= stekjes_e((string) ($editing['bron'] ?? '')) ?>">
    </label>
    <label class="checkbox">
      <input type="checkbox" name="visible" value="1"<?= !isset($editing['visible']) || !empty($editing['visible']) ? ' checked' : '' ?>>
      Zichtbaar op de kaart
    </label>
    <?php if ($editing && isset($editing['lat'], $editing['lon']) && $editing['lat'] !== '' && $editing['lon'] !== ''): ?>
      <p class="muted">Huidige benaderde coördinaten: <?= stekjes_e((string) $editing['lat']) ?>, <?= stekjes_e((string) $editing['lon']) ?></p>
    <?php endif; ?>
    <button class="btn" type="submit"><?= ($editing && !empty($editing['id'])) ? 'Opslaan' : 'Toevoegen' ?></button>
  </form>

  <table class="table">
    <thead><tr><th>Naam</th><th>Type</th><th>Plaats</th><th>Lat/Lon</th><th>Zichtbaar</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($markers as $m): ?>
      <tr>
        <td><?= stekjes_e((string) $m['naam']) ?></td>
        <td><?= stekjes_e($typeLabels[(string) $m['type']] ?? (string) $m['type']) ?></td>
        <td><?= stekjes_e((string) $m['plaats']) ?></td>
        <td><?= stekjes_e((string) $m['lat']) ?>, <?= stekjes_e((string) $m['lon']) ?></td>
        <td><?= !empty($m['visible']) ? 'ja' : 'nee' ?></td>
        <td style="white-space:nowrap">
          <a href="<?= stekjes_e(stekjes_path('/beheer/?edit=' . (int) $m['id'])) ?>">Bewerken</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Marker verwijderen?');">
            <?= stekjes_csrf_field() ?>
            <input type="hidden" name="marker_action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-secondary" type="submit" style="padding:.2rem .5rem;font-size:.85rem">Verwijderen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$markers): ?>
      <tr><td colspan="6">Nog geen markers. Stichting Voedselbosbouw en MeerBomen Nu staan op de <a href="<?= stekjes_e(stekjes_path('/links/')) ?>">partnerspagina</a> (geen bezoeklocatie gevonden voor markers).</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php stekjes_render_footer(); ?>
