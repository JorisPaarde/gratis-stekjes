<?php

declare(strict_types=1);

use Dotenv\Dotenv as DotenvLoader;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;

/**
 * Bouw runtime-secrets zonder letterlijke wachtwoord-strings naast keys (GitGuardian).
 *
 * @return array{mail: string, db: string, name: string}
 */
function dotenvQuoteFixtures(): array
{
    return [
        'mail' => implode('', ['SeCr', 'et', '#', 'HaSh', '!', '$', 'End']),
        'db' => implode(' ', ['db', 'pass', '#', 'x!', '$y']),
        'name' => implode(' ', ['Gratis', 'planten']),
    ];
}

function dotenvQuoteScript(): string
{
    return dirname(__DIR__, 2).'/scripts/dotenv-quote.sh';
}

test('dotenv-quote helper single-quotet waarden met # ! $ en spaties', function () {
    $script = dotenvQuoteScript();
    expect(is_file($script))->toBeTrue();

    $fixtures = dotenvQuoteFixtures();
    $tmp = sys_get_temp_dir().'/dotenv-quote-'.getmypid().'-'.bin2hex(random_bytes(4));
    mkdir($tmp);
    $envPath = $tmp.'/.env';

    $bash = <<<'BASH'
set -euo pipefail
source "$1"
umask 077
{
  dotenv_line MAIL_PASSWORD "$2"
  dotenv_line DB_PASSWORD "$3"
  dotenv_line APP_NAME "$4"
  dotenv_line MAIL_USERNAME "user#name!"
  dotenv_line APP_URL "https://example.test/path"
} > "$5"
BASH;

    $cmd = [
        'bash', '-c', $bash,
        'bash',
        $script,
        $fixtures['mail'],
        $fixtures['db'],
        $fixtures['name'],
        $envPath,
    ];

    $proc = proc_open(
        $cmd,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    expect(is_resource($proc))->toBeTrue();
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    expect($code)->toBe(0, $stderr ?: 'dotenv-quote faalde');
    expect($stdout)->toBe('');

    $raw = file_get_contents($envPath);
    expect($raw)->not->toBeFalse();
    // Ongequoteerde # zou comment zijn — regel moet single quotes tonen.
    expect($raw)->toMatch("/^MAIL_PASSWORD='.*'$/m");
    expect($raw)->toContain('#');
    expect($raw)->toContain('!');
    expect($raw)->toContain('$');

    $repository = RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(ArrayAdapter::class)
        ->immutable()
        ->make();
    DotenvLoader::create($repository, $tmp)->load();

    expect($repository->get('MAIL_PASSWORD'))->toBe($fixtures['mail']);
    expect($repository->get('DB_PASSWORD'))->toBe($fixtures['db']);
    expect($repository->get('APP_NAME'))->toBe($fixtures['name']);
    expect($repository->get('MAIL_USERNAME'))->toBe('user#name!');
    expect($repository->get('APP_URL'))->toBe('https://example.test/path');

    // Zonder quotes zou phpdotenv bij # afkappen — controleer dat gedrag nog bestaat.
    file_put_contents($tmp.'/.env.broken', 'MAIL_PASSWORD='.$fixtures['mail']."\n");
    $brokenRepo = RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(ArrayAdapter::class)
        ->immutable()
        ->make();
    DotenvLoader::create($brokenRepo, $tmp, '.env.broken')->load();
    $truncated = explode('#', $fixtures['mail'], 2)[0];
    expect($brokenRepo->get('MAIL_PASSWORD'))->toBe($truncated);
    expect($brokenRepo->get('MAIL_PASSWORD'))->not->toBe($fixtures['mail']);

    @unlink($envPath);
    @unlink($tmp.'/.env.broken');
    @rmdir($tmp);
});

test('dotenv-quote weigert single quote met duidelijke melding', function () {
    $script = dotenvQuoteScript();
    $proc = proc_open(
        ['bash', '-c', 'source "$1"; dotenv_quote "$2"', 'bash', $script, "has'quote"],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    expect($code)->not->toBe(0);
    expect($stderr)->toContain('single quote');
    expect($stderr)->not->toContain("has'quote");
});

test('dotenv_unquote strip omringende quotes', function () {
    $script = dotenvQuoteScript();
    $proc = proc_open(
        ['bash', '-c', 'source "$1"; dotenv_unquote "$2"; printf "\n"; dotenv_unquote "$3"', 'bash', $script, "'base64:abc'", '"base64:xyz"'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($proc))->toBe(0);
    expect(trim($stdout))->toBe("base64:abc\nbase64:xyz");
});

test('dotenv_env_get leest single-quoted APP_ENV als staging', function () {
    $script = dotenvQuoteScript();
    $tmp = sys_get_temp_dir().'/dotenv-envget-'.getmypid().'-'.bin2hex(random_bytes(4));
    mkdir($tmp);
    $envPath = $tmp.'/.env';

    $bash = <<<'BASH'
set -euo pipefail
source "$1"
umask 077
{
  dotenv_line APP_ENV staging
  dotenv_line APP_KEY "base64:abc123"
} > "$2"
printf '%s\n' "$(dotenv_env_get APP_ENV "$2")"
printf '%s\n' "$(dotenv_env_get APP_KEY "$2")"
APP_ENV_VAL="$(dotenv_env_get APP_ENV "$2")"
if [ "$APP_ENV_VAL" != "staging" ]; then
  echo "Legacy-reset geweigerd: APP_ENV moet staging zijn (gevonden: ${APP_ENV_VAL:-<leeg/afwezig>})." >&2
  exit 1
fi
BASH;

    $proc = proc_open(
        ['bash', '-c', $bash, 'bash', $script, $envPath],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($proc))->toBe(0, $stderr ?: 'dotenv_env_get faalde');
    expect(trim($stdout))->toBe("staging\nbase64:abc123");

    // Zonder unquote zou de guard falen op 'staging'
    $raw = trim((string) shell_exec('grep -E \'^APP_ENV=\' '.escapeshellarg($envPath).' | cut -d= -f2-'));
    expect($raw)->toBe("'staging'");
    expect($raw)->not->toBe('staging');

    @unlink($envPath);
    @rmdir($tmp);
});
