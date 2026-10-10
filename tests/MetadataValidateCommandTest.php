<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use OfflineAgency\FilamentSpid\Validation\ToolReport;

const FAKE_SPID_SP_TEST = __DIR__.'/Fixtures/bin/fake-spid-sp-test';

/** A metadata document as the SP would publish it; the fake tool does not read it. */
function fakeMetadata(): string
{
    return '<?xml version="1.0"?>'."\n".'<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="https://sp.example.test" ID="pfx1"><md:SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol"/></md:EntityDescriptor>';
}

/**
 * Set or clear a variable for the fake tool's process: Symfony Process
 * passes on what is in $_SERVER as well as getenv().
 */
function fakeToolEnv(string $name, ?string $value): void
{
    if ($value === null) {
        putenv($name);
        unset($_SERVER[$name], $_ENV[$name]);

        return;
    }

    putenv("{$name}={$value}");
    $_SERVER[$name] = $_ENV[$name] = $value;
}

/**
 * @return array{0: int, 1: array<string, mixed>, 2: string}
 */
function runMetadataValidate(array $options, string $mode = 'pass'): array
{
    fakeToolEnv('FAKE_SPID_SP_TEST', $mode);
    $exit = Artisan::call('filament-spid:metadata:validate', array_merge(['--json' => true, '--tool' => FAKE_SPID_SP_TEST], $options));
    fakeToolEnv('FAKE_SPID_SP_TEST', null);
    $output = Artisan::output();

    return [$exit, json_decode($output, true) ?? [], $output];
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/filament-spid-validate-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    $this->metadata = $this->dir.'/metadata.xml';
    File::put($this->metadata, fakeMetadata());
    Config::set('filament-spid.sp_profile', 'public');
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('passes when the tool passes, recording what was validated', function () {
    [$exit, $report] = runMetadataValidate(['--file' => $this->metadata, '--report' => $this->dir.'/report.json']);

    expect($exit)->toBe(0)
        ->and($report['overall_status'])->toBe('warn')
        ->and($report['evidence'])->toBe(['LOCAL_METADATA_CHECK_PASSED', 'HOSTED_VALIDATOR_NOT_RUN', 'FORMAL_AGID_ACCEPTANCE_NOT_OBTAINED'])
        ->and($report['metadata']['sha256'])->toBe(hash('sha256', fakeMetadata()))
        ->and($report['profile'])->toBe('spid-sp-public')
        ->and($report['tool'])->toMatchArray(['name' => 'spid_sp_test', 'version' => '1.2.18', 'exit_code' => 0, 'status' => 'ok'])
        ->and($report['summary'])->toBe(['executed' => 2, 'passed' => 1, 'failed' => 0, 'warnings' => 1])
        ->and($report['checks'][1])->toMatchArray(['id' => 'test_Organization', 'status' => 'warn'])
        ->and($report)->toHaveKeys(['schema_version', 'validated_at', 'plugin'])
        ->and(json_decode(File::get($this->dir.'/report.json'), true)['metadata']['sha256'])->toBe($report['metadata']['sha256']);
});

it('passes the exact bytes to the tool, with the profile, --extra and a JSON report', function () {
    fakeToolEnv('FAKE_SPID_SP_TEST_ARGS', $this->dir.'/args');

    runMetadataValidate(['--file' => $this->metadata, '--profile' => 'spid-sp-private']);

    fakeToolEnv('FAKE_SPID_SP_TEST_ARGS', null);
    $args = file($this->dir.'/args', FILE_IGNORE_NEW_LINES);
    $copied = $this->dir.'/args.metadata';

    expect($args)->toContain('--profile', 'spid-sp-private', '--extra', '-rf', 'json')
        ->and($args)->not->toContain('--exit-zero')
        ->and(hash_file('sha256', $copied))->toBe(hash('sha256', fakeMetadata()));
});

it('turns warnings into a failing exit code with --strict', function () {
    [$exit] = runMetadataValidate(['--file' => $this->metadata, '--strict' => true]);

    expect($exit)->toBe(1);
});

it('fails when a check fails', function () {
    [$exit, $report] = runMetadataValidate(['--file' => $this->metadata], 'fail');

    expect($exit)->toBe(1)
        ->and($report['overall_status'])->toBe('fail')
        ->and($report['evidence'][0])->toBe('LOCAL_METADATA_CHECK_FAILED')
        ->and($report['summary']['failed'])->toBe(1);
});

it('fails on a failing check even when the tool exits with 0', function () {
    [$exit, $report] = runMetadataValidate(['--file' => $this->metadata], 'lying');

    expect($exit)->toBe(1)->and($report['overall_status'])->toBe('fail');
});

it('reports an error, never a pass, when the tool cannot judge', function (string $mode, string $status) {
    [$exit, $report] = runMetadataValidate(['--file' => $this->metadata], $mode);

    expect($exit)->toBe(2)
        ->and($report['overall_status'])->toBe('error')
        ->and($report['evidence'][0])->toBe('LOCAL_METADATA_CHECK_ERROR')
        ->and($report['tool']['status'])->toBe($status);
})->with([
    'a missing tool dependency' => ['dependency', 'dependency_missing'],
    'zero checks run' => ['empty', 'no_checks'],
    'a malformed report' => ['malformed', 'unparseable'],
    'no report' => ['noreport', 'unparseable'],
    'a crash' => ['crash', 'crashed'],
    'an unsupported version' => ['old', 'unsupported_version'],
]);

it('reports a timeout as an error', function () {
    [$exit, $report] = runMetadataValidate(['--file' => $this->metadata, '--timeout' => 1], 'sleep');

    expect($exit)->toBe(2)->and($report['tool']['status'])->toBe('timeout');
});

it('reports a missing tool as an error with the install command', function () {
    fakeToolEnv('FAKE_SPID_SP_TEST', 'pass');
    $exit = Artisan::call('filament-spid:metadata:validate', ['--json' => true, '--file' => $this->metadata, '--tool' => $this->dir.'/no-such-tool']);
    $report = json_decode(Artisan::output(), true);
    fakeToolEnv('FAKE_SPID_SP_TEST', null);

    expect($exit)->toBe(2)
        ->and($report['tool']['status'])->toBe('missing')
        ->and($report['checks'][0]['remediation'])->toContain('spid-sp-test==1.2.18');
});

it('downloads the metadata from an HTTPS URL without following redirects', function () {
    Http::fake(['https://sp.example.test/spid/metadata' => Http::response(fakeMetadata(), 200, ['Content-Type' => 'application/xml'])]);

    [$exit, $report] = runMetadataValidate(['--url' => 'https://sp.example.test/spid/metadata']);

    expect($exit)->toBe(0)
        ->and($report['metadata']['source'])->toBe('https://sp.example.test/spid/metadata')
        ->and($report['metadata']['sha256'])->toBe(hash('sha256', fakeMetadata()));
    Http::assertSent(fn ($request) => $request->url() === 'https://sp.example.test/spid/metadata');
});

it('refuses what is not the metadata document', function (Closure $response, string $message) {
    Http::fake(['*' => $response()]);

    [$exit, $report] = runMetadataValidate(['--url' => 'https://sp.example.test/spid/metadata']);

    expect($exit)->toBe(2)
        ->and($report['overall_status'])->toBe('error')
        ->and($report['checks'][0]['message'])->toContain($message);
})->with([
    'a redirect to the login page' => [fn () => fn () => Http::response('', 302, ['Location' => 'https://sp.example.test/admin/login']), 'HTTP 302'],
    'an error page' => [fn () => fn () => Http::response('Not found', 404), 'HTTP 404'],
    'an HTML page' => [fn () => fn () => Http::response('<!DOCTYPE html><html><body>Login</body></html>', 200, ['Content-Type' => 'text/html']), 'not SAML metadata'],
    'malformed XML' => [fn () => fn () => Http::response('<md:EntityDescriptor', 200), 'not SAML metadata'],
    'a document over the size limit' => [fn () => fn () => Http::response(str_repeat('a', 2 * 1024 * 1024), 200), 'larger than'],
]);

it('refuses unreachable URLs as an error', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    [$exit, $report] = runMetadataValidate(['--url' => 'https://sp.example.test/spid/metadata']);

    expect($exit)->toBe(2)->and($report['checks'][0]['message'])->toContain('could not be downloaded');
});

it('rejects invalid invocations', function (array $options, string $message) {
    $exit = Artisan::call('filament-spid:metadata:validate', array_merge(['--tool' => FAKE_SPID_SP_TEST], $options));

    expect($exit)->toBe(2)->and(Artisan::output())->toContain($message);
})->with([
    'neither URL nor file' => [[], '--url or --file'],
    'both URL and file' => [['--url' => 'https://sp.example.test/m', '--file' => '/tmp/m.xml'], 'not both'],
    'a plain HTTP URL' => [['--url' => 'http://sp.example.test/m'], 'https://'],
    'an unsupported profile' => [['--file' => '/tmp/m.xml', '--profile' => 'saml2-sp'], 'spid-sp-public or spid-sp-private'],
    'a missing file' => [['--file' => '/nonexistent/m.xml'], 'not a readable file'],
    'an unknown option' => [['--exit-zero' => true], 'exit-zero'],
]);

it('takes the profile from filament-spid.sp_profile, and refuses to guess', function () {
    Config::set('filament-spid.sp_profile', 'private');
    [, $report] = runMetadataValidate(['--file' => $this->metadata]);

    Config::set('filament-spid.sp_profile', null);
    $exit = Artisan::call('filament-spid:metadata:validate', ['--file' => $this->metadata, '--tool' => FAKE_SPID_SP_TEST]);

    expect($report['profile'])->toBe('spid-sp-private')
        ->and($exit)->toBe(2)
        ->and(Artisan::output())->toContain('--profile');
});

it('prints a readable summary without --json', function () {
    fakeToolEnv('FAKE_SPID_SP_TEST', 'fail');
    $exit = Artisan::call('filament-spid:metadata:validate', ['--file' => $this->metadata, '--tool' => FAKE_SPID_SP_TEST]);
    $output = Artisan::output();
    fakeToolEnv('FAKE_SPID_SP_TEST', null);

    expect($exit)->toBe(1)
        ->and($output)->toContain('[FAIL] test_Signature')
        ->and($output)->toContain('LOCAL_METADATA_CHECK_FAILED')
        ->and($output)->toContain('HOSTED_VALIDATOR_NOT_RUN');
});

it('passes when every check passes, writing the report into a new private directory', function () {
    $path = $this->dir.'/reports/nested/metadata.json';

    [$exit, $report] = runMetadataValidate(['--file' => $this->metadata, '--report' => $path], 'clean');

    expect($exit)->toBe(0)
        ->and($report['overall_status'])->toBe('pass')
        ->and(File::exists($path))->toBeTrue()
        ->and(substr(sprintf('%o', fileperms(dirname($path))), -3))->toBe('700');
});

it('looks the tool up on the PATH by name', function () {
    $exit = Artisan::call('filament-spid:metadata:validate', ['--json' => true, '--file' => $this->metadata, '--tool' => 'spid-sp-test-not-installed-'.uniqid()]);

    expect($exit)->toBe(2)->and(json_decode(Artisan::output(), true)['tool']['status'])->toBe('missing');
});

it('ignores report entries that are not checks', function () {
    $report = ToolReport::parse('{"test": {"sp": {"metadata": {"Check": ["noise", {"test_id": "t", "test": "x", "result": "success"}]}}}}');

    expect($report?->summary())->toBe(['executed' => 1, 'passed' => 1, 'failed' => 0, 'warnings' => 0]);
});
