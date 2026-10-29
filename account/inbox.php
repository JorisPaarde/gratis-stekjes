<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();
$uid = (int) $user['id'];

$stmt = stekjes_db()->prepare(
    'SELECT c.id, c.user_a_id, c.user_b_id, c.created_at,
            CASE WHEN c.user_a_id = ? THEN ub.voornaam ELSE ua.voornaam END AS andere_naam
     FROM conversations c
     JOIN users ua ON ua.id = c.user_a_id
     JOIN users ub ON ub.id = c.user_b_id
     WHERE c.user_a_id = ? OR c.user_b_id = ?
     ORDER BY c.created_at DESC'
);
$stmt->execute([$uid, $uid, $uid]);
$rows = $stmt->fetchAll();

stekjes_render_header(['title' => 'Inbox', 'canonical_path' => '/account/inbox.php']);
?>
<section class="section wrap">
  <h1>Inbox</h1>
  <?php if (!$rows): ?>
    <p class="muted">Nog geen gesprekken. Zoek aanbod en stuur een bericht.</p>
  <?php else: ?>
    <ul class="zoek-lijst">
      <?php foreach ($rows as $r): ?>
        <li class="zoek-item">
          <a href="<?= stekjes_e(stekjes_path('/account/gesprek.php?id=' . (int) $r['id'])) ?>">
            Gesprek met <?= stekjes_e((string) $r['andere_naam']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php stekjes_render_footer(); ?>
