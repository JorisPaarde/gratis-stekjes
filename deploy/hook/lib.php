<?php

declare(strict_types=1);

/**
 * Standalone deploy-hook helpers (geen Composer / Laravel).
 * Wordt door deploy-hook.php geladen en door tests/Unit/DeployHookTest.php hergebruikt.
 */

// Blokkeer directe HTTP-toegang tot lib.php (alleen include).
if (PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}

const DEPLOY_HOOK_CHUNK_SIZE = 5_242_880; // 5 MiB
const DEPLOY_HOOK_RATE_MAX_AUTH_FAIL = 10;
const DEPLOY_HOOK_RATE_MAX_INIT = 20;
const DEPLOY_HOOK_RATE_WINDOW = 3600;
const DEPLOY_HOOK_RELEASES_KEEP = 3;

/**
 * Home-dir voor LiteSpeed-herstartmarker (.lsphp_restart.txt).
 * Optionele config `home_dir`, anders dirname(app_dir) — bv.
 * /home/user/app-staging → /home/user.
 */
function deploy_hook_resolve_home_dir(string $appDir, ?string $configuredHome = null): string
{
    if (is_string($configuredHome) && trim($configuredHome) !== '') {
        return rtrim($configuredHome, '/');
    }

    return rtrim(dirname($appDir), '/');
}

/**
 * Kandidaat-homes voor ~/.lsphp_restart.txt (account-home, niet release-map).
 * Uniek: geconfigureerde home, dirname(app_dir), dirname(docroot), $HOME.
 *
 * @return list<string>
 */
function deploy_hook_lsphp_restart_homes(string $appDir, string $docroot, ?string $configuredHome = null): array
{
    $homes = [];
    foreach ([
        deploy_hook_resolve_home_dir($appDir, $configuredHome),
        rtrim(dirname(rtrim($docroot, '/')), '/'),
        rtrim(dirname(rtrim($appDir, '/')), '/'),
    ] as $candidate) {
        if ($candidate !== '' && $candidate !== '/') {
            $homes[$candidate] = true;
        }
    }
    $envHome = getenv('HOME');
    if (is_string($envHome) && trim($envHome) !== '') {
        $homes[rtrim($envHome, '/')] = true;
    }

    return array_keys($homes);
}

/**
 * Herschrijf /home/<user>/.lsphp_restart.txt (inhoud wijzigen, niet alleen mtime).
 * LiteSpeed Detached PHP herstart workers bij een verse marker; pure touch()
 * sloeg op deze hosting meermaals niet aan, handmatig herschrijven wel.
 * Robuust als de map ontbreekt; logt geen secrets.
 */
function deploy_hook_write_lsphp_restart(string $homeDir): bool
{
    $homeDir = rtrim($homeDir, '/');
    if ($homeDir === '' || $homeDir === '/') {
        return false;
    }
    if (! is_dir($homeDir)) {
        return false;
    }
    $marker = $homeDir.'/.lsphp_restart.txt';
    $payload = 'restart '.gmdate('c').' '.bin2hex(random_bytes(8))."\n";
    // Nieuw inode + verse inhoud: betrouwbaarder dan touch() op bestaande file.
    if (is_file($marker) || is_link($marker)) {
        @unlink($marker);
    }
    if (@file_put_contents($marker, $payload, LOCK_EX) === false) {
        return false;
    }
    @chmod($marker, 0644);

    return is_file($marker) && @file_get_contents($marker) === $payload;
}

/**
 * @deprecated Alias — gebruik deploy_hook_write_lsphp_restart (herschrijf, niet touch).
 */
function deploy_hook_touch_lsphp_restart(string $homeDir): bool
{
    return deploy_hook_write_lsphp_restart($homeDir);
}

/**
 * Schrijf herstartmarkers in alle kandidaat-homes. True als minstens één lukte.
 *
 * @return array{ok:bool,homes:list<string>}
 */
function deploy_hook_write_lsphp_restart_markers(string $appDir, string $docroot, ?string $configuredHome = null): array
{
    $written = [];
    foreach (deploy_hook_lsphp_restart_homes($appDir, $docroot, $configuredHome) as $home) {
        if (deploy_hook_write_lsphp_restart($home)) {
            $written[] = $home;
        }
    }

    return ['ok' => $written !== [], 'homes' => $written];
}

/**
 * Bump mtime van docroot-entrypoint (index.php) en .htaccess zonder
 * rewrite-regels inhoudelijk te wijzigen — LiteSpeed/lsphp-signaal.
 */
function deploy_hook_bump_entrypoint_mtimes(string $docroot): bool
{
    $docroot = rtrim($docroot, '/');
    $ok = false;
    foreach (['index.php', '.htaccess'] as $name) {
        $path = $docroot.'/'.$name;
        if (! is_file($path)) {
            continue;
        }
        if (@touch($path) !== false) {
            $ok = true;
        }
    }

    return $ok;
}

/**
 * Kopieer deploy-hook.php + lib.php uit de nieuwe release naar de docroot,
 * zodat volgende deploys de bijgewerkte hook draaien (hook staat buiten de zip-sync).
 */
function deploy_hook_self_update_from_release(string $releaseDir, string $docroot): bool
{
    $srcDir = rtrim($releaseDir, '/').'/deploy/hook';
    $docroot = rtrim($docroot, '/');
    $ok = true;
    foreach (['deploy-hook.php', 'lib.php'] as $name) {
        $src = $srcDir.'/'.$name;
        $dst = $docroot.'/'.$name;
        if (! is_file($src)) {
            $ok = false;

            continue;
        }
        if (! @copy($src, $dst)) {
            $ok = false;
        }
    }

    return $ok;
}

/**
 * @return array{token_hash:string,app_dir:string,docroot:string,env:string,home_dir:string}|null
 */
function deploy_hook_load_config(string $docroot): ?array
{
    $docrootReal = realpath($docroot) ?: $docroot;
    $home = dirname($docrootReal);
    $dir = $home.'/.deploy';
    if (! is_dir($dir)) {
        return null;
    }

    $files = glob($dir.'/*.php') ?: [];
    foreach ($files as $file) {
        if (! is_file($file) || basename($file) === 'ratelimit.php') {
            continue;
        }
        /** @var mixed $cfg */
        $cfg = include $file;
        if (! is_array($cfg)) {
            continue;
        }
        $cfgDoc = isset($cfg['docroot']) ? (string) $cfg['docroot'] : '';
        if ($cfgDoc === '') {
            continue;
        }
        $cfgDocReal = realpath($cfgDoc) ?: $cfgDoc;
        if (rtrim($cfgDocReal, '/') === rtrim($docrootReal, '/')) {
            if (empty($cfg['token_hash']) || empty($cfg['app_dir']) || empty($cfg['env'])) {
                return null;
            }

            $appDir = (string) $cfg['app_dir'];
            $configuredHome = isset($cfg['home_dir']) ? (string) $cfg['home_dir'] : null;

            return [
                'token_hash' => (string) $cfg['token_hash'],
                'app_dir' => $appDir,
                'docroot' => $cfgDocReal,
                'env' => (string) $cfg['env'],
                'home_dir' => deploy_hook_resolve_home_dir($appDir, $configuredHome),
            ];
        }
    }

    return null;
}

function deploy_hook_json(int $status, array $payload): void
{
    if (! headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

function deploy_hook_is_https(): bool
{
    if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($fwd === 'https') {
        return true;
    }
    // Tests: sta http toe via env.
    if (getenv('DEPLOY_HOOK_ALLOW_HTTP') === '1') {
        return true;
    }

    return false;
}

function deploy_hook_bearer_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
        return $m[1];
    }

    return '';
}

function deploy_hook_verify_token(string $tokenHash, string $given): bool
{
    if ($tokenHash === '' || $given === '') {
        return false;
    }
    // password_hash / password_verify
    if (str_starts_with($tokenHash, '$2y$') || str_starts_with($tokenHash, '$2a$') || str_starts_with($tokenHash, '$argon')) {
        return password_verify($given, $tokenHash);
    }
    // sha256 hex (64) of raw token
    if (preg_match('/^[a-f0-9]{64}$/i', $tokenHash)) {
        $calc = hash('sha256', $given);

        return hash_equals(strtolower($tokenHash), strtolower($calc));
    }

    return false;
}

function deploy_hook_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }

    return '0.0.0.0';
}

function deploy_hook_rate_dir(string $home): string
{
    return $home.'/.deploy/ratelimit';
}

/**
 * File-based rate limit. Returns false when limited.
 */
function deploy_hook_rate_allow(string $rateDir, string $bucket, string $ip, int $max, int $window): bool
{
    if (! is_dir($rateDir) && ! @mkdir($rateDir, 0700, true) && ! is_dir($rateDir)) {
        return false;
    }
    $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip) ?: 'unknown';
    $file = $rateDir.'/'.$bucket.'_'.$safeIp;
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if (is_int($ts) && ($now - $ts) < $window) {
                        $hits[] = $ts;
                    }
                }
            }
        }
    }
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    @chmod($file, 0600);

    return true;
}

function deploy_hook_uploads_dir(string $appDir): string
{
    return rtrim($appDir, '/').'/.deploy-uploads';
}

/**
 * @return array{ok:bool,error?:string,upload_id?:string}
 */
function deploy_hook_action_init(string $appDir, string $release, int $totalSize, string $totalSha256, int $chunkCount): array
{
    if ($release === '' || ! preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $release)) {
        return ['ok' => false, 'error' => 'bad_release'];
    }
    if ($totalSize < 1 || $totalSize > 512 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'bad_size'];
    }
    if (! preg_match('/^[a-f0-9]{64}$/i', $totalSha256)) {
        return ['ok' => false, 'error' => 'bad_sha'];
    }
    if ($chunkCount < 1 || $chunkCount > 10_000) {
        return ['ok' => false, 'error' => 'bad_chunks'];
    }

    $uploadRoot = deploy_hook_uploads_dir($appDir);
    if (! is_dir($uploadRoot) && ! @mkdir($uploadRoot, 0700, true) && ! is_dir($uploadRoot)) {
        return ['ok' => false, 'error' => 'mkdir_failed'];
    }

    $uploadId = bin2hex(random_bytes(16));
    $dir = $uploadRoot.'/'.$uploadId;
    if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
        return ['ok' => false, 'error' => 'mkdir_failed'];
    }

    $meta = [
        'release' => $release,
        'total_size' => $totalSize,
        'total_sha256' => strtolower($totalSha256),
        'chunk_count' => $chunkCount,
        'received' => [],
        'created' => time(),
    ];
    if (@file_put_contents($dir.'/meta.json', json_encode($meta), LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'meta_failed'];
    }
    @chmod($dir.'/meta.json', 0600);

    return ['ok' => true, 'upload_id' => $uploadId];
}

/**
 * @return array{ok:bool,error?:string}
 */
function deploy_hook_action_chunk(string $appDir, string $uploadId, int $index, string $chunkSha256, string $chunkPath): array
{
    if (! preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
        return ['ok' => false, 'error' => 'bad_upload_id'];
    }
    if ($index < 0 || $index > 10_000) {
        return ['ok' => false, 'error' => 'bad_index'];
    }
    if (! preg_match('/^[a-f0-9]{64}$/i', $chunkSha256)) {
        return ['ok' => false, 'error' => 'bad_sha'];
    }
    if (! is_file($chunkPath)) {
        return ['ok' => false, 'error' => 'missing_chunk'];
    }

    $dir = deploy_hook_uploads_dir($appDir).'/'.$uploadId;
    $metaFile = $dir.'/meta.json';
    if (! is_file($metaFile)) {
        return ['ok' => false, 'error' => 'unknown_upload'];
    }
    $meta = json_decode((string) file_get_contents($metaFile), true);
    if (! is_array($meta)) {
        return ['ok' => false, 'error' => 'bad_meta'];
    }
    if ($index >= (int) $meta['chunk_count']) {
        return ['ok' => false, 'error' => 'index_oob'];
    }

    $size = filesize($chunkPath);
    if ($size === false || $size < 1 || $size > DEPLOY_HOOK_CHUNK_SIZE + 1024) {
        return ['ok' => false, 'error' => 'chunk_size'];
    }

    $calc = hash_file('sha256', $chunkPath);
    if ($calc === false || ! hash_equals(strtolower($chunkSha256), strtolower($calc))) {
        return ['ok' => false, 'error' => 'chunk_checksum'];
    }

    $dest = $dir.'/chunk_'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);
    if (! @rename($chunkPath, $dest) && ! @copy($chunkPath, $dest)) {
        return ['ok' => false, 'error' => 'chunk_store'];
    }
    @chmod($dest, 0600);

    $received = isset($meta['received']) && is_array($meta['received']) ? $meta['received'] : [];
    $received[(string) $index] = strtolower($chunkSha256);
    $meta['received'] = $received;
    @file_put_contents($metaFile, json_encode($meta), LOCK_EX);

    return ['ok' => true];
}

/**
 * Assemble chunks into $destFile. Verifies total sha256.
 *
 * @return array{ok:bool,error?:string}
 */
function deploy_hook_assemble_chunks(string $uploadDir, array $meta, string $destFile): array
{
    $count = (int) $meta['chunk_count'];
    $received = isset($meta['received']) && is_array($meta['received']) ? $meta['received'] : [];
    if (count($received) !== $count) {
        return ['ok' => false, 'error' => 'incomplete'];
    }

    $out = @fopen($destFile, 'wb');
    if ($out === false) {
        return ['ok' => false, 'error' => 'assemble_open'];
    }
    for ($i = 0; $i < $count; $i++) {
        $chunk = $uploadDir.'/chunk_'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
        if (! is_file($chunk)) {
            fclose($out);
            @unlink($destFile);

            return ['ok' => false, 'error' => 'missing_chunk'];
        }
        $in = fopen($chunk, 'rb');
        if ($in === false) {
            fclose($out);
            @unlink($destFile);

            return ['ok' => false, 'error' => 'chunk_read'];
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
    }
    fclose($out);

    $calc = hash_file('sha256', $destFile);
    $expect = strtolower((string) $meta['total_sha256']);
    if ($calc === false || ! hash_equals($expect, strtolower($calc))) {
        @unlink($destFile);

        return ['ok' => false, 'error' => 'total_checksum'];
    }
    $size = filesize($destFile);
    if ($size === false || $size !== (int) $meta['total_size']) {
        @unlink($destFile);

        return ['ok' => false, 'error' => 'total_size'];
    }

    return ['ok' => true];
}

/**
 * Extract zip or tar.gz into $destDir (must exist and be empty-ish).
 *
 * @return array{ok:bool,error?:string,method?:string}
 */
function deploy_hook_extract(string $archive, string $destDir): array
{
    if (! is_dir($destDir) && ! @mkdir($destDir, 0755, true) && ! is_dir($destDir)) {
        return ['ok' => false, 'error' => 'mkdir_failed'];
    }

    $lower = strtolower($archive);
    if (str_ends_with($lower, '.zip') || deploy_hook_is_zip($archive)) {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            if ($zip->open($archive) !== true) {
                return ['ok' => false, 'error' => 'zip_open'];
            }
            if (! $zip->extractTo($destDir)) {
                $zip->close();

                return ['ok' => false, 'error' => 'zip_extract'];
            }
            $zip->close();

            return ['ok' => true, 'method' => 'ZipArchive'];
        }
        if (class_exists(PharData::class)) {
            try {
                $phar = new PharData($archive);
                $phar->extractTo($destDir, null, true);

                return ['ok' => true, 'method' => 'PharData-zip'];
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'phar_zip'];
            }
        }

        return ['ok' => false, 'error' => 'no_zip_support'];
    }

    // tar / tar.gz
    if (class_exists(PharData::class)) {
        try {
            // PharData wil soms eerst .tar; bij .tar.gz decomprimeert het automatisch.
            $phar = new PharData($archive);
            $phar->extractTo($destDir, null, true);

            return ['ok' => true, 'method' => 'PharData'];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'phar_extract'];
        }
    }

    return ['ok' => false, 'error' => 'no_extract_support'];
}

function deploy_hook_is_zip(string $path): bool
{
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return false;
    }
    $magic = fread($fh, 4);
    fclose($fh);

    return $magic === "PK\x03\x04" || $magic === "PK\x05\x06";
}

/**
 * Als het archief één top-level map heeft, geef die terug; anders $extractRoot.
 */
function deploy_hook_normalize_extract_root(string $extractRoot): string
{
    $entries = array_values(array_filter(scandir($extractRoot) ?: [], static function ($e) {
        return $e !== '.' && $e !== '..';
    }));
    if (count($entries) === 1 && is_dir($extractRoot.'/'.$entries[0])) {
        return $extractRoot.'/'.$entries[0];
    }

    return $extractRoot;
}

function deploy_hook_rm_tree(string $path): void
{
    if (! file_exists($path) && ! is_link($path)) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        deploy_hook_rm_tree($path.'/'.$item);
    }
    @rmdir($path);
}

/**
 * Kopieer directory recursively (voor fallback zonder symlink).
 */
function deploy_hook_copy_tree(string $src, string $dst): bool
{
    if (is_link($src) || is_file($src)) {
        $dir = dirname($dst);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        return @copy($src, $dst);
    }
    if (! is_dir($dst) && ! @mkdir($dst, 0755, true) && ! is_dir($dst)) {
        return false;
    }
    $items = scandir($src) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (! deploy_hook_copy_tree($src.'/'.$item, $dst.'/'.$item)) {
            return false;
        }
    }

    return true;
}

function deploy_hook_ensure_storage(string $storageDir): void
{
    $dirs = [
        $storageDir,
        $storageDir.'/app',
        $storageDir.'/app/public',
        $storageDir.'/framework',
        $storageDir.'/framework/cache',
        $storageDir.'/framework/cache/data',
        $storageDir.'/framework/sessions',
        $storageDir.'/framework/views',
        $storageDir.'/logs',
        $storageDir.'/backups',
    ];
    foreach ($dirs as $d) {
        if (! is_dir($d)) {
            @mkdir($d, 0755, true);
        }
    }
    @chmod($storageDir.'/backups', 0700);
}

/**
 * Lees APP_KEY uit een .env-bestand (ongequoted).
 */
function deploy_hook_env_get(string $envPath, string $key): string
{
    if (! is_file($envPath)) {
        return '';
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $line) {
        if ($line === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }
        if (! str_starts_with($line, $key.'=')) {
            continue;
        }
        $raw = substr($line, strlen($key) + 1);
        if (
            (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
            || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
        ) {
            $raw = substr($raw, 1, -1);
            $raw = str_replace(['\\$', '\\"', '\\\\'], ['$', '"', '\\'], $raw);
        }

        return $raw;
    }

    return '';
}

/**
 * Zorg dat APP_KEY in $envContent staat: upload > bestaande > genereer.
 */
function deploy_hook_resolve_app_key(string $envContent, string $existingEnvPath): string
{
    $uploaded = '';
    foreach (preg_split("/\r\n|\n|\r/", $envContent) ?: [] as $line) {
        if (str_starts_with($line, 'APP_KEY=')) {
            $raw = substr($line, 8);
            if (
                (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
                || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
            ) {
                $raw = substr($raw, 1, -1);
                $raw = str_replace(['\\$', '\\"', '\\\\'], ['$', '"', '\\'], $raw);
            }
            $uploaded = $raw;
            break;
        }
    }

    $key = $uploaded;
    if ($key === '') {
        $key = deploy_hook_env_get($existingEnvPath, 'APP_KEY');
    }
    if ($key === '') {
        $key = 'base64:'.base64_encode(random_bytes(32));
    }

    // Vervang of voeg APP_KEY toe.
    $out = [];
    $seen = false;
    foreach (preg_split("/\r\n|\n|\r/", $envContent) ?: [] as $line) {
        if (str_starts_with($line, 'APP_KEY=')) {
            $out[] = 'APP_KEY="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $key).'"';
            $seen = true;
        } else {
            $out[] = $line;
        }
    }
    if (! $seen) {
        $out[] = 'APP_KEY="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $key).'"';
    }

    return implode("\n", $out).(str_ends_with(implode("\n", $out), "\n") ? '' : "\n");
}

/**
 * Behoud $key uit bestaande .env als de upload hem leeg/ontbrekend heeft.
 * Gebruikt o.a. voor BACKUP_ENCRYPTION_KEY (nooit genereren; alleen bewaren).
 */
function deploy_hook_preserve_env_key(string $envContent, string $existingEnvPath, string $key): string
{
    $uploaded = '';
    $prefix = $key.'=';
    foreach (preg_split("/\r\n|\n|\r/", $envContent) ?: [] as $line) {
        if (! str_starts_with($line, $prefix)) {
            continue;
        }
        $raw = substr($line, strlen($prefix));
        if (
            (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
            || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
        ) {
            $raw = substr($raw, 1, -1);
            $raw = str_replace(['\\$', '\\"', '\\\\'], ['$', '"', '\\'], $raw);
        }
        $uploaded = $raw;
        break;
    }

    if ($uploaded !== '') {
        return $envContent;
    }

    $existing = deploy_hook_env_get($existingEnvPath, $key);
    if ($existing === '') {
        return $envContent;
    }

    $escaped = $key.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $existing).'"';
    $out = [];
    $seen = false;
    foreach (preg_split("/\r\n|\n|\r/", $envContent) ?: [] as $line) {
        if (str_starts_with($line, $prefix)) {
            $out[] = $escaped;
            $seen = true;
        } else {
            $out[] = $line;
        }
    }
    if (! $seen) {
        $out[] = $escaped;
    }

    $joined = implode("\n", $out);

    return $joined.(str_ends_with($joined, "\n") ? '' : "\n");
}

/**
 * Schrijf .env naar $path met mode 0600.
 */
function deploy_hook_write_env(string $path, string $content): bool
{
    $dir = dirname($path);
    if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
        return false;
    }
    if (@file_put_contents($path, $content, LOCK_EX) === false) {
        return false;
    }
    @chmod($path, 0600);

    return true;
}

/**
 * Relatief pad van $fromDir naar $toDir (alleen .. / basename-segmenten).
 */
function deploy_hook_relative_path(string $fromDir, string $toDir): string
{
    $from = explode('/', trim(str_replace('\\', '/', $fromDir), '/'));
    $to = explode('/', trim(str_replace('\\', '/', $toDir), '/'));
    while ($from && $to && $from[0] === $to[0]) {
        array_shift($from);
        array_shift($to);
    }
    $rel = str_repeat('../', count($from)).implode('/', $to);

    return $rel === '' ? '.' : $rel;
}

/**
 * Is docroot/index.html een cPanel-placeholder (niet uit public/)?
 * Alleen dan mag sync die weghalen — nooit andere bestanden (.user.ini, php.ini, …).
 */
function deploy_hook_is_cpanel_placeholder_index_html(string $publicDir, string $docroot): bool
{
    $path = $docroot.'/index.html';
    if (! is_file($path)) {
        return false;
    }
    // Onderdeel van de release public/ → niet wissen (sync kopieert die zelf).
    if (is_file($publicDir.'/index.html')) {
        return false;
    }
    $raw = (string) @file_get_contents($path);

    // cPanel-default ("Domein gereserveerd"), of restant dat niet uit public/ komt.
    return stripos($raw, 'Domein gereserveerd') !== false
        || ! is_file($publicDir.'/index.html');
}

/**
 * Verwijder een lege stray public/-map in de docroot (nooit als er inhoud in zit).
 */
function deploy_hook_remove_empty_docroot_public(string $docroot): void
{
    $stray = $docroot.'/public';
    if (! is_dir($stray) || is_link($stray)) {
        return;
    }
    $entries = array_values(array_filter(scandir($stray) ?: [], static function ($e) {
        return $e !== '.' && $e !== '..';
    }));
    if ($entries === []) {
        @rmdir($stray);
    }
}

/**
 * Sync public/ naar docroot; behoud .well-known, cgi-bin, uploads, deploy-hook.php,
 * cPanel .user.ini/php.ini. robots.txt uit de release-public/ wordt wél gekopieerd
 * (build-time, overrult hosting-fallback).
 *
 * @return array{ok:bool,error?:string}
 */
function deploy_hook_sync_docroot(string $publicDir, string $docroot): array
{
    if (! is_dir($publicDir)) {
        return ['ok' => false, 'error' => 'no_public'];
    }
    if (! is_dir($docroot) && ! @mkdir($docroot, 0755, true) && ! is_dir($docroot)) {
        return ['ok' => false, 'error' => 'docroot_mkdir'];
    }

    // Nooit overschrijven/wissen: ACME, cgi-bin, uploads, de hook zelf, storage-symlink,
    // cPanel PHP-ini's. robots.txt hoort NIET hier — die komt uit public/ van de release.
    $preserveNames = [
        '.well-known' => true,
        'cgi-bin' => true,
        'uploads' => true,
        'deploy-hook.php' => true,
        'lib.php' => true,
        'storage' => true,
        '.user.ini' => true,
        'php.ini' => true,
    ];

    // Vernieuw Vite-build atomisch-ish: oude build weg, nieuwe komt uit public/.
    if (is_dir($docroot.'/build')) {
        deploy_hook_rm_tree($docroot.'/build');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $rel = substr($item->getPathname(), strlen($publicDir) + 1);
        $rel = str_replace('\\', '/', $rel);
        $top = explode('/', $rel, 2)[0];
        if (isset($preserveNames[$top]) || isset($preserveNames[$rel])) {
            continue;
        }
        $target = $docroot.'/'.$rel;
        if ($item->isDir()) {
            if (! is_dir($target)) {
                @mkdir($target, 0755, true);
            }
        } else {
            $tdir = dirname($target);
            if (! is_dir($tdir)) {
                @mkdir($tdir, 0755, true);
            }
            if (! @copy($item->getPathname(), $target)) {
                return ['ok' => false, 'error' => 'copy_public'];
            }
        }
    }

    // Zorg dat .well-known/acme-challenge bestaat.
    if (! is_dir($docroot.'/.well-known/acme-challenge')) {
        @mkdir($docroot.'/.well-known/acme-challenge', 0755, true);
    }

    // cPanel DirectoryIndex: leftover index.html ("Domein gereserveerd") wint van index.php.
    // Alleen die placeholder weg; .user.ini / php.ini / cgi-bin blijven onaangeroerd.
    if (deploy_hook_is_cpanel_placeholder_index_html($publicDir, $docroot)) {
        @unlink($docroot.'/index.html');
    }

    // Lege stray public/ in docroot (verwarrend t.o.v. release-public/); alleen als leeg.
    deploy_hook_remove_empty_docroot_public($docroot);

    return ['ok' => true];
}

function deploy_hook_write_index(string $docroot, string $appLivePath): bool
{
    $rel = deploy_hook_relative_path($docroot, $appLivePath);
    $code = "<?php\n"
        ."define('LARAVEL_START', microtime(true));\n"
        ."require __DIR__ . '/{$rel}/vendor/autoload.php';\n"
        ."\$app = require_once __DIR__ . '/{$rel}/bootstrap/app.php';\n"
        ."\$app->usePublicPath(__DIR__);\n"
        ."\$app->handleRequest(Illuminate\\Http\\Request::capture());\n";

    return @file_put_contents($docroot.'/index.php', $code) !== false;
}

/**
 * Prune oude releases; behoud $keep nieuwste + eventuele current target.
 */
function deploy_hook_prune_releases(string $releasesDir, string $currentTarget, int $keep = DEPLOY_HOOK_RELEASES_KEEP): void
{
    if (! is_dir($releasesDir)) {
        return;
    }
    $dirs = [];
    foreach (scandir($releasesDir) ?: [] as $e) {
        if ($e === '.' || $e === '..') {
            continue;
        }
        $p = $releasesDir.'/'.$e;
        if (is_dir($p)) {
            $dirs[$p] = filemtime($p) ?: 0;
        }
    }
    arsort($dirs);
    $i = 0;
    foreach ($dirs as $path => $_mtime) {
        $i++;
        if ($i <= $keep) {
            continue;
        }
        if (realpath($path) === realpath($currentTarget)) {
            continue;
        }
        deploy_hook_rm_tree($path);
    }
}

/**
 * Wis Laravel config-/route-cache in bootstrap/cache.
 *
 * Doel: een stale lsphp-proces dat nog een vorige release serveert (opcache /
 * realpath via symlink `current`) valt terug op de gedeelde `.env` i.p.v. een
 * stale `config:cache` met oud DEPLOY_TOKEN.
 *
 * Laat packages.php / services.php staan (composer package discovery).
 * Robuust als bestanden of de map ontbreken; logt geen paden met secrets.
 */
function deploy_hook_clear_bootstrap_cache_files(string $appRoot): void
{
    $cacheDir = rtrim($appRoot, '/').'/bootstrap/cache';
    if (! is_dir($cacheDir)) {
        return;
    }

    foreach (['config.php', 'routes.php'] as $name) {
        $path = $cacheDir.'/'.$name;
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }

    foreach (glob($cacheDir.'/routes-*.php') ?: [] as $path) {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }
}

/**
 * Wis config-/route-cache in alle releases behalve de zojuist geactiveerde.
 *
 * @param  ?string  $keepReleaseDir  Pad van de nieuwe release (wordt overgeslagen)
 */
function deploy_hook_clear_previous_releases_config_cache(string $releasesDir, ?string $keepReleaseDir = null): void
{
    if (! is_dir($releasesDir)) {
        return;
    }

    $keepReal = null;
    if ($keepReleaseDir !== null && $keepReleaseDir !== '') {
        $keepReal = realpath($keepReleaseDir) ?: rtrim($keepReleaseDir, '/');
    }

    foreach (scandir($releasesDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }
        $path = $releasesDir.'/'.$entry;
        if (! is_dir($path)) {
            continue;
        }
        $real = realpath($path) ?: $path;
        if ($keepReal !== null && $real === $keepReal) {
            continue;
        }
        deploy_hook_clear_bootstrap_cache_files($path);
    }
}

/**
 * Best-effort: realpath-cache + opcache na symlink-swap (lsphp kan anders
 * de oude release blijven serveren). Werkt alleen in het huidige PHP-proces.
 */
function deploy_hook_invalidate_runtime_caches(): void
{
    clearstatcache(true);
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
}

/**
 * Zoek een PHP-CLI binary (cPanel ea-php / lsphp / php). Geen secrets in output.
 */
function deploy_hook_resolve_php_cli(): ?string
{
    $candidates = [];
    if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
        $candidates[] = PHP_BINARY;
    }
    $candidates = array_merge($candidates, [
        'php',
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/usr/local/bin/lsphp',
        '/opt/cpanel/ea-php84/root/usr/bin/php',
        '/opt/cpanel/ea-php83/root/usr/bin/php',
        '/opt/cpanel/ea-php82/root/usr/bin/php',
        '/opt/cpanel/ea-php81/root/usr/bin/php',
        '/usr/local/bin/ea-php84',
        '/usr/local/bin/ea-php83',
        '/usr/local/bin/ea-php82',
        '/usr/local/bin/ea-php81',
    ]);

    foreach ($candidates as $bin) {
        if ($bin !== 'php' && ! is_executable($bin)) {
            continue;
        }
        $out = [];
        $code = 1;
        @exec(escapeshellarg($bin).' -v 2>/dev/null', $out, $code);
        if ($code === 0 && $out !== []) {
            return $bin;
        }
    }

    return null;
}

/**
 * Absolute app-root van de live release (nooit een stale realpath via alleen `current`
 * zonder clearstatcache). Optioneel expliciete release-id.
 */
function deploy_hook_resolve_live_app_root(string $appDir, ?string $release = null): ?string
{
    clearstatcache(true);
    $appDir = rtrim($appDir, '/');

    if (is_string($release) && $release !== '' && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $release)) {
        $path = $appDir.'/releases/'.$release;
        if (is_file($path.'/artisan') && is_file($path.'/vendor/autoload.php')) {
            return $path;
        }
    }

    $current = $appDir.'/current';
    if (is_link($current) || is_dir($current)) {
        $real = realpath($current);
        if (is_string($real) && is_file($real.'/artisan') && is_file($real.'/vendor/autoload.php')) {
            return $real;
        }
    }

    // Nieuwste release-map als current ontbreekt.
    $releasesDir = $appDir.'/releases';
    if (is_dir($releasesDir)) {
        $best = null;
        $bestMtime = -1;
        foreach (scandir($releasesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $path = $releasesDir.'/'.$entry;
            if (! is_dir($path) || ! is_file($path.'/artisan')) {
                continue;
            }
            $mtime = filemtime($path) ?: 0;
            if ($mtime >= $bestMtime) {
                $bestMtime = $mtime;
                $best = $path;
            }
        }
        if ($best !== null) {
            return $best;
        }
    }

    if (is_file($appDir.'/artisan') && is_file($appDir.'/vendor/autoload.php')) {
        return $appDir;
    }

    return null;
}

/**
 * Draai artisan in $appRoot via verse CLI-PHP (niet via web-lsphp/opcache).
 *
 * @param  list<string>  $argv  Argumenten ná artisan (bv. ['migrate','--force'])
 * @return array{ok:bool,exit_code:int,error?:string}
 */
function deploy_hook_run_artisan(string $appRoot, array $argv): array
{
    $appRoot = rtrim($appRoot, '/');
    $artisan = $appRoot.'/artisan';
    if (! is_file($artisan)) {
        return ['ok' => false, 'exit_code' => 127, 'error' => 'no_artisan'];
    }

    $php = deploy_hook_resolve_php_cli();
    if ($php === null) {
        return ['ok' => false, 'exit_code' => 127, 'error' => 'no_php_cli'];
    }

    $cmd = array_merge([$php, $artisan], $argv);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open(
        $cmd,
        $descriptors,
        $pipes,
        $appRoot,
        null,
    );
    if (! is_resource($proc)) {
        return ['ok' => false, 'exit_code' => 127, 'error' => 'proc_open'];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], true);
    stream_set_blocking($pipes[2], true);
    // Lees output weg (voorkomt blokkeren); gooi weg — mag secrets bevatten.
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    if ($exit !== 0) {
        return ['ok' => false, 'exit_code' => $exit, 'error' => 'artisan_exit'];
    }

    return ['ok' => true, 'exit_code' => 0];
}

/**
 * Migrate + DeelplekkenSeeder + caches via CLI in de nieuwe release-map.
 *
 * @return array{ok:bool,error?:string,release_path?:string,steps?:list<string>}
 */
function deploy_hook_action_migrate(string $appDir, ?string $release = null): array
{
    $root = deploy_hook_resolve_live_app_root($appDir, $release);
    if ($root === null) {
        return ['ok' => false, 'error' => 'no_release'];
    }

    $steps = [
        ['migrate', '--force'],
        ['db:seed', '--class=DeelplekkenSeeder', '--force'],
        ['config:clear'],
        ['config:cache'],
        ['route:cache'],
        ['view:cache'],
    ];
    $done = [];
    foreach ($steps as $argv) {
        $result = deploy_hook_run_artisan($root, $argv);
        if (! ($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => (string) ($result['error'] ?? 'artisan_failed'),
                'failed_step' => $argv[0],
                'exit_code' => $result['exit_code'] ?? 1,
                'steps' => $done,
            ];
        }
        $done[] = $argv[0];
    }

    return [
        'ok' => true,
        // Geen absoluut pad in response (hosting-layout); wel relative release-id.
        'release' => basename($root),
        'steps' => $done,
        'via' => 'cli',
    ];
}

/**
 * Volledige finish: assemble → extract → swap → env → docroot.
 *
 * @return array{ok:bool,error?:string,method?:string,release?:string,lsphp_restart?:bool}
 */
function deploy_hook_action_finish(
    string $appDir,
    string $docroot,
    string $uploadId,
    string $envContent,
    ?string $homeDir = null,
): array {
    if (! preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
        return ['ok' => false, 'error' => 'bad_upload_id'];
    }

    $uploadDir = deploy_hook_uploads_dir($appDir).'/'.$uploadId;
    $metaFile = $uploadDir.'/meta.json';
    if (! is_file($metaFile)) {
        return ['ok' => false, 'error' => 'unknown_upload'];
    }
    $meta = json_decode((string) file_get_contents($metaFile), true);
    if (! is_array($meta)) {
        return ['ok' => false, 'error' => 'bad_meta'];
    }

    $release = (string) ($meta['release'] ?? '');
    $archive = $uploadDir.'/artifact.bin';
    $assembled = deploy_hook_assemble_chunks($uploadDir, $meta, $archive);
    if (! ($assembled['ok'] ?? false)) {
        return $assembled;
    }

    // Bepaal extensie voor extract (zip vs tar.gz) via magic.
    $isZip = deploy_hook_is_zip($archive);
    $named = $uploadDir.'/artifact.'.($isZip ? 'zip' : 'tar.gz');
    @rename($archive, $named);
    $archive = $named;

    if (! is_dir($appDir) && ! @mkdir($appDir, 0755, true) && ! is_dir($appDir)) {
        return ['ok' => false, 'error' => 'app_mkdir'];
    }

    // Migreer flat layout → shared storage + releases.
    $sharedStorage = $appDir.'/storage';
    $releasesDir = $appDir.'/releases';
    if (! is_dir($releasesDir)) {
        @mkdir($releasesDir, 0755, true);
    }

    // Als storage nog niet shared is maar wel in flat app zit: al $appDir/storage.
    deploy_hook_ensure_storage($sharedStorage);

    $releaseDir = $releasesDir.'/'.$release;
    if (is_dir($releaseDir) || is_link($releaseDir)) {
        deploy_hook_rm_tree($releaseDir);
    }
    $extractTmp = $releasesDir.'/.tmp-'.$release;
    deploy_hook_rm_tree($extractTmp);
    if (! @mkdir($extractTmp, 0755, true) && ! is_dir($extractTmp)) {
        return ['ok' => false, 'error' => 'extract_mkdir'];
    }

    $extracted = deploy_hook_extract($archive, $extractTmp);
    if (! ($extracted['ok'] ?? false)) {
        deploy_hook_rm_tree($extractTmp);

        return $extracted;
    }
    $method = (string) ($extracted['method'] ?? 'unknown');

    $root = deploy_hook_normalize_extract_root($extractTmp);
    if ($root !== $extractTmp) {
        // Verplaats inhoud van nested root naar releaseDir.
        if (! @rename($root, $releaseDir)) {
            if (! deploy_hook_copy_tree($root, $releaseDir)) {
                deploy_hook_rm_tree($extractTmp);

                return ['ok' => false, 'error' => 'release_move'];
            }
            deploy_hook_rm_tree($extractTmp);
        } else {
            @rmdir($extractTmp);
        }
    } else {
        if (! @rename($extractTmp, $releaseDir)) {
            if (! deploy_hook_copy_tree($extractTmp, $releaseDir)) {
                deploy_hook_rm_tree($extractTmp);

                return ['ok' => false, 'error' => 'release_move'];
            }
            deploy_hook_rm_tree($extractTmp);
        }
    }

    // Storage: vervang release-storage door symlink naar shared (preserve logs/sessions/uploads).
    $relStorage = $releaseDir.'/storage';
    if (file_exists($relStorage) || is_link($relStorage)) {
        deploy_hook_rm_tree($relStorage);
    }
    $linked = @symlink($sharedStorage, $relStorage);
    if (! $linked) {
        // Fallback: kopieer structuur; behoud shared als bron van waarheid via copy-back later — hier merge empty dirs.
        deploy_hook_ensure_storage($relStorage);
        // Gebruik shared door contents niet te wissen: kopieer shared → release storage files that are missing.
        // Voor noshell zonder symlink: laat release/storage een echte map zijn en sync NIET over shared heen.
        // Zet instead live path op appDir flat — zie fallback hieronder.
    }

    // .env resolven en schrijven op app-niveau + in release.
    $existingEnv = $appDir.'/.env';
    // Bij flat layout stond .env al op $appDir/.env; bewaar.
    $finalEnv = deploy_hook_resolve_app_key($envContent, $existingEnv);
    $finalEnv = deploy_hook_preserve_env_key($finalEnv, $existingEnv, 'BACKUP_ENCRYPTION_KEY');
    $finalEnv = deploy_hook_preserve_env_key($finalEnv, $existingEnv, 'ADMIN_DASHBOARD_EMAILS');
    if (! deploy_hook_write_env($existingEnv, $finalEnv)) {
        return ['ok' => false, 'error' => 'env_write'];
    }
    // Symlink .env in release → shared; fallback copy.
    $relEnv = $releaseDir.'/.env';
    if (file_exists($relEnv) || is_link($relEnv)) {
        @unlink($relEnv);
    }
    if (! @symlink($existingEnv, $relEnv)) {
        if (! deploy_hook_write_env($relEnv, $finalEnv)) {
            return ['ok' => false, 'error' => 'env_release'];
        }
    }

    // bootstrap/cache schrijfbaar
    if (! is_dir($releaseDir.'/bootstrap/cache')) {
        @mkdir($releaseDir.'/bootstrap/cache', 0755, true);
    }

    // current-symlink (atomisch via rename van temp-link waar mogelijk)
    $current = $appDir.'/current';
    $livePath = $releaseDir;
    $tmpLink = $appDir.'/current.new-'.bin2hex(random_bytes(4));
    if (@symlink($releaseDir, $tmpLink)) {
        // Vervang current
        if (is_link($current) || file_exists($current)) {
            @unlink($current);
        }
        if (! @rename($tmpLink, $current)) {
            @unlink($tmpLink);
            // probeer direct
            @symlink($releaseDir, $current);
        }
        $livePath = $current;
        // Na swap: stale lsphp kan nog oude release + config:cache serveren.
        deploy_hook_invalidate_runtime_caches();
    } else {
        @unlink($tmpLink);
        // Geen symlink-support: sync release → flat app_dir (preserve storage, .env, releases).
        $skip = ['storage' => true, '.env' => true, 'releases' => true, 'current' => true, '.deploy-uploads' => true];
        foreach (scandir($releaseDir) ?: [] as $item) {
            if ($item === '.' || $item === '..' || isset($skip[$item])) {
                continue;
            }
            $src = $releaseDir.'/'.$item;
            $dst = $appDir.'/'.$item;
            if (is_dir($dst) || is_link($dst) || is_file($dst)) {
                deploy_hook_rm_tree($dst);
            }
            if (is_dir($src)) {
                if (! deploy_hook_copy_tree($src, $dst)) {
                    return ['ok' => false, 'error' => 'flat_copy'];
                }
            } else {
                if (! @copy($src, $dst)) {
                    return ['ok' => false, 'error' => 'flat_copy'];
                }
            }
        }
        $livePath = $appDir;
        deploy_hook_invalidate_runtime_caches();
    }

    // Docroot: public/ + index.php; behoud deploy-hook.php tot self-update hieronder.
    $publicDir = $releaseDir.'/public';
    $sync = deploy_hook_sync_docroot($publicDir, $docroot);
    if (! ($sync['ok'] ?? false)) {
        return $sync;
    }
    // Absolute release-map in index.php (niet via symlink `current`) — minder kans
    // dat stale realpath-cache een vorige release blijft laden.
    if (! deploy_hook_write_index($docroot, $releaseDir)) {
        return ['ok' => false, 'error' => 'index_write'];
    }
    // Pointer buiten docroot (geen secrets; alleen release-pad voor diagnostics).
    @file_put_contents(
        $appDir.'/.deploy-release',
        $releaseDir."\n",
        LOCK_EX
    );

    // Hook + lib uit de nieuwe release → docroot (anders blijft oude touch()-logica staan).
    deploy_hook_self_update_from_release($releaseDir, $docroot);

    deploy_hook_prune_releases($releasesDir, $releaseDir);

    // Ruim upload op
    deploy_hook_rm_tree($uploadDir);

    // Stale lsphp op vorige release(s): config:cache met oud DEPLOY_TOKEN weg,
    // zodat die processen de gedeelde (nieuwe) .env lezen tot ze recyclen.
    deploy_hook_clear_previous_releases_config_cache($releasesDir, $releaseDir);
    deploy_hook_invalidate_runtime_caches();

    // LiteSpeed: NÁ swap + docroot — herschrijf ~/.lsphp_restart.txt in account-home
    // (inhoud, niet alleen mtime). Daarna entrypoint-mtimes bumpen.
    $restart = deploy_hook_write_lsphp_restart_markers($appDir, $docroot, $homeDir);
    deploy_hook_bump_entrypoint_mtimes($docroot);

    // Wis gevoelige env-string uit geheugen-pad (best-effort).
    $finalEnv = '';
    $envContent = '';

    return [
        'ok' => true,
        'method' => $method,
        'release' => $release,
        'lsphp_restart' => $restart['ok'],
    ];
}

/**
 * HTTP request afhandelen. Returnt ['status'=>int,'body'=>array].
 *
 * @param  array{token_hash:string,app_dir:string,docroot:string,env:string,home_dir?:string}  $config
 * @return array{status:int,body:array}
 */
function deploy_hook_handle(array $config, ?array $post = null, ?array $files = null, ?string $method = null): array
{
    $post ??= $_POST;
    $files ??= $_FILES;
    $method ??= strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method !== 'POST') {
        return ['status' => 405, 'body' => ['ok' => false, 'error' => 'method']];
    }
    if (! deploy_hook_is_https()) {
        return ['status' => 403, 'body' => ['ok' => false, 'error' => 'https_required']];
    }

    $home = dirname($config['docroot']);
    $rateDir = deploy_hook_rate_dir($home);
    $ip = deploy_hook_client_ip();

    $token = deploy_hook_bearer_token();
    if (! deploy_hook_verify_token($config['token_hash'], $token)) {
        // Eerst gate: te veel eerdere fails → 429; anders registreer fail en 403.
        $failFile = $rateDir.'/authfail_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
        $failCount = 0;
        if (is_file($failFile)) {
            $decoded = json_decode((string) file_get_contents($failFile), true);
            if (is_array($decoded)) {
                $now = time();
                foreach ($decoded as $ts) {
                    if (is_int($ts) && ($now - $ts) < DEPLOY_HOOK_RATE_WINDOW) {
                        $failCount++;
                    }
                }
            }
        }
        if ($failCount >= DEPLOY_HOOK_RATE_MAX_AUTH_FAIL) {
            return ['status' => 429, 'body' => ['ok' => false, 'error' => 'rate_limited']];
        }
        deploy_hook_rate_allow($rateDir, 'authfail', $ip, DEPLOY_HOOK_RATE_MAX_AUTH_FAIL + 1, DEPLOY_HOOK_RATE_WINDOW);

        return ['status' => 403, 'body' => ['ok' => false, 'error' => 'forbidden']];
    }

    $action = (string) ($post['action'] ?? '');

    if ($action === 'init') {
        if (! deploy_hook_rate_allow($rateDir, 'init', $ip, DEPLOY_HOOK_RATE_MAX_INIT, DEPLOY_HOOK_RATE_WINDOW)) {
            return ['status' => 429, 'body' => ['ok' => false, 'error' => 'rate_limited']];
        }
        $result = deploy_hook_action_init(
            $config['app_dir'],
            (string) ($post['release'] ?? ''),
            (int) ($post['total_size'] ?? 0),
            (string) ($post['total_sha256'] ?? ''),
            (int) ($post['chunk_count'] ?? 0),
        );
        if (! ($result['ok'] ?? false)) {
            return ['status' => 400, 'body' => $result];
        }

        return ['status' => 200, 'body' => $result];
    }

    if ($action === 'chunk') {
        $tmp = '';
        if (isset($files['chunk']) && is_array($files['chunk']) && ($files['chunk']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) $files['chunk']['tmp_name'];
        } elseif (! empty($post['chunk_path']) && is_file((string) $post['chunk_path'])) {
            // Alleen voor tests.
            $tmp = (string) $post['chunk_path'];
        } else {
            return ['status' => 400, 'body' => ['ok' => false, 'error' => 'missing_chunk']];
        }
        $result = deploy_hook_action_chunk(
            $config['app_dir'],
            (string) ($post['upload_id'] ?? ''),
            (int) ($post['chunk_index'] ?? -1),
            (string) ($post['chunk_sha256'] ?? ''),
            $tmp,
        );
        if (! ($result['ok'] ?? false)) {
            return ['status' => 400, 'body' => $result];
        }

        return ['status' => 200, 'body' => $result];
    }

    if ($action === 'finish') {
        $envContent = (string) ($post['env'] ?? '');
        if ($envContent === '' && isset($files['env']) && ($files['env']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $envContent = (string) file_get_contents((string) $files['env']['tmp_name']);
        }
        if ($envContent === '') {
            return ['status' => 400, 'body' => ['ok' => false, 'error' => 'missing_env']];
        }
        $result = deploy_hook_action_finish(
            $config['app_dir'],
            $config['docroot'],
            (string) ($post['upload_id'] ?? ''),
            $envContent,
            isset($config['home_dir']) ? (string) $config['home_dir'] : null,
        );
        if (! ($result['ok'] ?? false)) {
            return ['status' => 400, 'body' => $result];
        }

        return ['status' => 200, 'body' => $result];
    }

    // Opnieuw ~/.lsphp_restart.txt herschrijven (smoke-retry / handmatige recycle).
    if ($action === 'lsphp_restart') {
        $configuredHome = isset($config['home_dir']) ? (string) $config['home_dir'] : null;
        $restart = deploy_hook_write_lsphp_restart_markers(
            $config['app_dir'],
            $config['docroot'],
            $configuredHome,
        );
        deploy_hook_bump_entrypoint_mtimes($config['docroot']);
        deploy_hook_invalidate_runtime_caches();

        return [
            'status' => 200,
            'body' => [
                'ok' => $restart['ok'],
                'lsphp_restart' => $restart['ok'],
                // Geen absolute home-paden in response (hosting-layout blijft intern).
            ],
        ];
    }

    // Migrate + seed + caches via CLI in de nieuwe release (ná swap/herstart).
    if ($action === 'migrate') {
        $release = isset($post['release']) ? (string) $post['release'] : null;
        if ($release === '') {
            $release = null;
        }
        $result = deploy_hook_action_migrate($config['app_dir'], $release);
        if (! ($result['ok'] ?? false)) {
            return ['status' => 500, 'body' => $result];
        }

        return ['status' => 200, 'body' => $result];
    }

    return ['status' => 400, 'body' => ['ok' => false, 'error' => 'bad_action']];
}
