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
 * @return array{token_hash:string,app_dir:string,docroot:string,env:string}|null
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

            return [
                'token_hash' => (string) $cfg['token_hash'],
                'app_dir' => (string) $cfg['app_dir'],
                'docroot' => $cfgDocReal,
                'env' => (string) $cfg['env'],
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
 * Sync public/ naar docroot; behoud .well-known, cgi-bin, uploads, deploy-hook.php.
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

    // Nooit overschrijven/wissen: ACME, cgi-bin, uploads, de hook zelf, storage-symlink.
    $preserveNames = [
        '.well-known' => true,
        'cgi-bin' => true,
        'uploads' => true,
        'deploy-hook.php' => true,
        'lib.php' => true,
        'storage' => true,
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
 * Volledige finish: assemble → extract → swap → env → docroot.
 *
 * @return array{ok:bool,error?:string,method?:string,release?:string}
 */
function deploy_hook_action_finish(
    string $appDir,
    string $docroot,
    string $uploadId,
    string $envContent,
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
    }

    // Docroot: public/ + index.php; behoud deploy-hook.php
    $publicDir = $releaseDir.'/public';
    $sync = deploy_hook_sync_docroot($publicDir, $docroot);
    if (! ($sync['ok'] ?? false)) {
        return $sync;
    }
    if (! deploy_hook_write_index($docroot, $livePath === $current ? $current : $livePath)) {
        return ['ok' => false, 'error' => 'index_write'];
    }

    // Zorg dat deploy-hook.php niet per ongeluk is weggevaagd (sync slaat hem over als source).
    // Hook leeft alleen in docroot, niet in public/ van het archief — sync overschrijft hem niet.

    deploy_hook_prune_releases($releasesDir, $releaseDir);

    // Ruim upload op
    deploy_hook_rm_tree($uploadDir);

    // Wis gevoelige env-string uit geheugen-pad (best-effort).
    $finalEnv = '';
    $envContent = '';

    return [
        'ok' => true,
        'method' => $method,
        'release' => $release,
    ];
}

/**
 * HTTP request afhandelen. Returnt ['status'=>int,'body'=>array].
 *
 * @param  array{token_hash:string,app_dir:string,docroot:string,env:string}  $config
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
        );
        if (! ($result['ok'] ?? false)) {
            return ['status' => 400, 'body' => $result];
        }

        return ['status' => 200, 'body' => $result];
    }

    return ['status' => 400, 'body' => ['ok' => false, 'error' => 'bad_action']];
}
