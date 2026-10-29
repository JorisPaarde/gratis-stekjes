<?php

declare(strict_types=1);

function stekjes_flash_set(string $type, string $message): void
{
    stekjes_start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function stekjes_flash_take(): ?array
{
    stekjes_start_session();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

/**
 * @param array{title?:string,description?:string,body_class?:string,canonical_path?:string} $opts
 */
function stekjes_render_header(array $opts = []): void
{
    $site = stekjes_site_name();
    $title = isset($opts['title']) ? $opts['title'] . ' · ' . $site : $site;
    $desc = $opts['description'] ?? 'Gratis stekjes weggeven en ophalen in Nederland. De enige tegenprestatie is een praatje.';
    $canonical = stekjes_abs_url($opts['canonical_path'] ?? '/');
    $user = function_exists('stekjes_current_user') ? stekjes_current_user() : null;
    $formStarted = (string) time();
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= stekjes_e($title) ?></title>
  <meta name="description" content="<?= stekjes_e($desc) ?>">
  <meta name="robots" content="noindex, nofollow">
  <link rel="canonical" href="<?= stekjes_e($canonical) ?>">
  <link rel="stylesheet" href="<?= stekjes_e(stekjes_path('/assets/css/styles.css')) ?>?v=<?= stekjes_e(STEKJES_VERSION) ?>">
  <link rel="manifest" href="<?= stekjes_e(stekjes_path('/manifest.webmanifest')) ?>">
  <meta name="theme-color" content="<?= stekjes_e((string) (stekjes_config()['theme_color'] ?? '#2f6b45')) ?>">
  <link rel="icon" href="<?= stekjes_e(stekjes_path('/assets/icons/icon-192.png')) ?>" sizes="192x192">
  <script src="<?= stekjes_e(stekjes_path('/assets/js/site-config.js')) ?>"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Figtree:wght@400;560;700&display=swap" rel="stylesheet">
</head>
<body class="<?= stekjes_e($opts['body_class'] ?? '') ?>">
  <a class="skip-link" href="#inhoud">Naar inhoud</a>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="brand" href="<?= stekjes_e(stekjes_path('/')) ?>"><?= stekjes_e($site) ?></a>
      <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="hoofdmenu" data-nav-toggle>Menu</button>
      <nav id="hoofdmenu" class="site-nav" data-nav>
        <a href="<?= stekjes_e(stekjes_path('/hoe-werkt-het/')) ?>">Hoe werkt het</a>
        <a href="<?= stekjes_e(stekjes_path('/aanbod/')) ?>">Aanbod</a>
        <a href="<?= stekjes_e(stekjes_path('/stektips/')) ?>">Stektips</a>
        <a href="<?= stekjes_e(stekjes_path('/hulpmiddelen/')) ?>">Hulpmiddelen</a>
        <a href="<?= stekjes_e(stekjes_path('/links/')) ?>">Links</a>
        <a href="<?= stekjes_e(stekjes_path('/over/')) ?>">Over</a>
        <?php if ($user): ?>
          <a href="<?= stekjes_e(stekjes_path('/account/inbox.php')) ?>">Inbox</a>
          <a href="<?= stekjes_e(stekjes_path('/account/')) ?>">Account</a>
        <?php else: ?>
          <a class="nav-cta" href="<?= stekjes_e(stekjes_path('/doe-mee/')) ?>">Doe mee</a>
          <a href="<?= stekjes_e(stekjes_path('/account/inloggen.php')) ?>">Inloggen</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main id="inhoud">
<?php
    $flash = stekjes_flash_take();
    if ($flash): ?>
    <div class="wrap"><p class="flash flash-<?= stekjes_e($flash['type']) ?>" role="status"><?= stekjes_e($flash['message']) ?></p></div>
<?php endif;
}

function stekjes_render_footer(): void
{
    $site = stekjes_site_name();
    ?>
  </main>
  <footer class="site-footer">
    <div class="wrap footer-inner">
      <p><strong><?= stekjes_e($site) ?></strong> — gratis stekjes, een praatje als enige prijs.</p>
      <p class="footer-links">
        <a href="<?= stekjes_e(stekjes_path('/privacy/')) ?>">Privacy</a>
        <a href="<?= stekjes_e(stekjes_path('/over/')) ?>">Over</a>
        <a href="<?= stekjes_e(stekjes_path('/doe-mee/')) ?>">Doe mee</a>
      </p>
      <p class="footer-meta">Versie <?= stekjes_e(STEKJES_VERSION) ?> · noindex</p>
    </div>
  </footer>
  <script src="<?= stekjes_e(stekjes_path('/assets/js/main.js')) ?>?v=<?= stekjes_e(STEKJES_VERSION) ?>" defer></script>
</body>
</html>
<?php
}

function stekjes_honeypot_and_timing_fields(): string
{
    $t = (string) time();
    return '<div class="hp" aria-hidden="true">'
        . '<label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>'
        . '</div>'
        . '<input type="hidden" name="form_started" value="' . stekjes_e($t) . '">';
}
