<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();
$uid = (int) $user['id'];
$cid = (int) ($_GET['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'message');
    if ($action === 'message') {
        $r = stekjes_send_message($uid, $cid, (string) ($_POST['body'] ?? ''), $_POST);
        stekjes_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Bericht verstuurd.' : ($r['error'] ?? 'Mislukt'));
    } elseif ($action === 'contact') {
        $r = stekjes_set_contact_preference($uid, $cid, (string) ($_POST['choice'] ?? ''), $_POST);
        stekjes_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Keuze opgeslagen.' : ($r['error'] ?? 'Mislukt'));
    } elseif ($action === 'report') {
        $view = stekjes_conversation_view($uid, $cid);
        $otherId = (int) ($view['andere']['id'] ?? 0);
        $r = stekjes_report_user($uid, $otherId, $cid, (string) ($_POST['reason'] ?? ''), $_POST);
        stekjes_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Melding opgeslagen.' : ($r['error'] ?? 'Mislukt'));
    } elseif ($action === 'block') {
        $view = stekjes_conversation_view($uid, $cid);
        $otherId = (int) ($view['andere']['id'] ?? 0);
        $r = stekjes_block_user($uid, $otherId, $_POST);
        stekjes_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Gebruiker geblokkeerd.' : ($r['error'] ?? 'Mislukt'));
    }
    stekjes_redirect(stekjes_path('/account/gesprek.php?id=' . $cid));
}

$view = stekjes_conversation_view($uid, $cid);
if (!$view) {
    http_response_code(404);
    stekjes_render_header(['title' => 'Gesprek']);
    echo '<section class="section wrap"><p>Gesprek niet gevonden.</p></section>';
    stekjes_render_footer();
    exit;
}

stekjes_render_header(['title' => 'Gesprek', 'canonical_path' => '/account/gesprek.php']);
$andere = $view['andere'];
?>
<section class="section wrap">
  <h1>Gesprek met <?= stekjes_e((string) $andere['voornaam']) ?></h1>

  <aside class="safety" role="note">
    <strong>Veiligheidstips</strong>
    <p><?= stekjes_e((string) $view['veiligheidstips']) ?></p>
  </aside>

  <div class="contact-box" style="margin:1rem 0">
    <h2>Verder contact?</h2>
    <p class="muted">Alleen als jullie allebei “ja” kiezen, zien jullie elkaars e-mail<?= !empty($andere['contact_gedeeld']) ? ' (en telefoon als die is ingevuld)' : '' ?>.</p>
    <?php if (!empty($view['wederzijds_contact'])): ?>
      <p><strong>Contact gedeeld</strong></p>
      <p>E-mail: <?= stekjes_e((string) ($andere['email'] ?? '')) ?></p>
      <?php if (!empty($andere['telefoon'])): ?>
        <p>Telefoon: <?= stekjes_e((string) $andere['telefoon']) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <p>Jouw keuze nu: <?= stekjes_e((string) ($view['mijn_contactkeuze'] ?? 'nog niet gekozen')) ?></p>
      <form method="post" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <?= stekjes_csrf_field() ?>
        <input type="hidden" name="action" value="contact">
        <button class="btn" name="choice" value="ja" type="submit">Ik wil wel verder contact</button>
        <button class="btn btn-secondary" name="choice" value="nee" type="submit">Ik wil niet</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="chat">
    <?php foreach ($view['berichten'] as $m): ?>
      <div class="bubble <?= ((int) $m['sender_id'] === $uid) ? 'me' : '' ?>">
        <p><?= nl2br(stekjes_e((string) $m['body'])) ?></p>
        <p class="muted" style="margin:0;font-size:.85rem"><?= stekjes_e((string) $m['created_at']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <form class="form" method="post" style="margin-top:1rem">
    <?= stekjes_csrf_field() ?>
    <?= stekjes_honeypot_and_timing_fields() ?>
    <input type="hidden" name="action" value="message">
    <label>Nieuw bericht
      <textarea name="body" required maxlength="4000"></textarea>
    </label>
    <button class="btn" type="submit">Verstuur</button>
  </form>

  <details style="margin-top:1.5rem">
    <summary>Melden of blokkeren</summary>
    <form class="form" method="post">
      <?= stekjes_csrf_field() ?>
      <input type="hidden" name="action" value="report">
      <label>Reden melding
        <textarea name="reason" required maxlength="1000"></textarea>
      </label>
      <button class="btn btn-secondary" type="submit">Melden</button>
    </form>
    <form method="post" style="margin-top:.75rem">
      <?= stekjes_csrf_field() ?>
      <input type="hidden" name="action" value="block">
      <button class="btn btn-secondary" type="submit">Blokkeer deze gebruiker</button>
    </form>
  </details>
  <p><a href="<?= stekjes_e(stekjes_path('/account/inbox.php')) ?>">Terug naar inbox</a></p>
</section>
<?php stekjes_render_footer(); ?>
