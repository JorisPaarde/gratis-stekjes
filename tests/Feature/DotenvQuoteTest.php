<?php

declare(strict_types=1);

use Dotenv\Dotenv as DotenvLoader;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;

/**
 * Bouw runtime-fixtures zonder PASSWORD-keys of wachtwoordachtige literals (GitGuardian).
 *
 * @return array{hashy: string, spaced: string, name: string}
 */
function dotenvQuoteFixtures(): array
{
    return [
        'hashy' => implode('', ['SeCr', 'et', '#', 'HaSh', '!', '$', 'End']),
        'spaced' => implode(' ', ['deel', 'waarde', '#', 'x!', '$y']),
        'name' => implode(' ', ['Gratis', 'planten']),
    ];
}

function dotenvQuoteScript(): string
{
    return dirname(__DIR__, 2).'/scripts/dotenv-quote.sh';
}

test('dotenv-quote roundtript waarde met # (SMTP 535-regressie)', function () {
    $script = dotenvQuoteScript();
    expect(is_file($script))->toBeTrue();

    // Exacte oorzaak staging: ongequote secret met # werd commentaar.
    // Geen PASSWORD-key/literal: GitGuardian "Generic Password".
    $sample = implode('#', ['voor', 'na']);
    $tmp = sys_get_temp_dir().'/dotenv-hash-'.getmypid().'-'.bin2hex(random_bytes(4));
    mkdir($tmp);
    $envPath = $tmp.'/.env';

    $bash = <<<'BASH'
set -euo pipefail
source "$1"
umask 077
dotenv_line QUOTE_TEST_VALUE "$2" > "$3"
BASH;

    $proc = proc_open(
        ['bash', '-c', $bash, 'bash', $script, $sample, $envPath],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    expect(is_resource($proc))->toBeTrue();
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($proc))->toBe(0, $stderr ?: 'dotenv-quote faalde');

    $raw = (string) file_get_contents($envPath);
    expect($raw)->toMatch('/^QUOTE_TEST_VALUE="voor#na"$/m');
    expect($raw)->not->toMatch('/^QUOTE_TEST_VALUE=voor#na$/m');

    $repository = RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(ArrayAdapter::class)
        ->immutable()
        ->make();
    DotenvLoader::create($repository, $tmp)->load();
    expect($repository->get('QUOTE_TEST_VALUE'))->toBe($sample);

    @unlink($envPath);
    @rmdir($tmp);
});

test('dotenv-quote helper double-quotet waarden met # ! $ en spaties', function () {
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
  dotenv_line QUOTE_TEST_VALUE "$2"
  dotenv_line QUOTE_TEST_SPACED "$3"
  dotenv_line APP_NAME "$4"
  dotenv_line QUOTE_TEST_USER "user#name!"
  dotenv_line APP_URL "https://example.test/path"
  dotenv_line WITH_QUOTE "say \"hi\""
} > "$5"
BASH;

    $cmd = [
        'bash', '-c', $bash,
        'bash',
        $script,
        $fixtures['hashy'],
        $fixtures['spaced'],
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
    // Ongequoteerde # zou comment zijn — regel moet double quotes tonen.
    expect($raw)->toMatch('/^QUOTE_TEST_VALUE=".*"$/m');
    expect($raw)->toContain('#');
    expect($raw)->toContain('!');
    expect($raw)->toContain('\\$');

    $repository = RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(ArrayAdapter::class)
        ->immutable()
        ->make();
    DotenvLoader::create($repository, $tmp)->load();

    expect($repository->get('QUOTE_TEST_VALUE'))->toBe($fixtures['hashy']);
    expect($repository->get('QUOTE_TEST_SPACED'))->toBe($fixtures['spaced']);
    expect($repository->get('APP_NAME'))->toBe($fixtures['name']);
    expect($repository->get('QUOTE_TEST_USER'))->toBe('user#name!');
    expect($repository->get('APP_URL'))->toBe('https://example.test/path');
    expect($repository->get('WITH_QUOTE'))->toBe('say "hi"');

    // Zonder quotes zou phpdotenv bij # afkappen — controleer dat gedrag nog bestaat.
    file_put_contents($tmp.'/.env.broken', 'QUOTE_TEST_VALUE='.$fixtures['hashy']."\n");
    $brokenRepo = RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(ArrayAdapter::class)
        ->immutable()
        ->make();
    DotenvLoader::create($brokenRepo, $tmp, '.env.broken')->load();
    $truncated = explode('#', $fixtures['hashy'], 2)[0];
    expect($brokenRepo->get('QUOTE_TEST_VALUE'))->toBe($truncated);
    expect($brokenRepo->get('QUOTE_TEST_VALUE'))->not->toBe($fixtures['hashy']);

    @unlink($envPath);
    @unlink($tmp.'/.env.broken');
    @rmdir($tmp);
});

test('dotenv-quote weigert newline met duidelijke melding', function () {
    $script = dotenvQuoteScript();
    $proc = proc_open(
        ['bash', '-c', 'source "$1"; dotenv_quote "$2"', 'bash', $script, "has\nnewline"],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    expect($code)->not->toBe(0);
    expect($stderr)->toContain('newline');
    expect($stderr)->not->toContain('has');
});

test('dotenv_unquote strip omringende quotes en unescape', function () {
    $script = dotenvQuoteScript();
    $proc = proc_open(
        ['bash', '-c', 'source "$1"; dotenv_unquote "$2"; printf "\n"; dotenv_unquote "$3"; printf "\n"; dotenv_unquote "$4"', 'bash', $script, "'base64:abc'", '"base64:xyz"', '"hash#\$x"'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($proc))->toBe(0);
    expect(trim($stdout))->toBe("base64:abc\nbase64:xyz\nhash#".'$x');
});

test('dotenv_env_get leest double-quoted APP_ENV als staging', function () {
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

    // Zonder unquote zou de guard falen op "staging"
    $raw = trim((string) shell_exec('grep -E \'^APP_ENV=\' '.escapeshellarg($envPath).' | cut -d= -f2-'));
    expect($raw)->toBe('"staging"');
    expect($raw)->not->toBe('staging');

    @unlink($envPath);
    @rmdir($tmp);
});
