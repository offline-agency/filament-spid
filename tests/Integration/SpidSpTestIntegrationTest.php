<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

/*
 * Runs the real spid_sp_test (pinned in tools/spid/versions.env) on the
 * metadata this package's stack emits. Skipped, never passed, when
 * SPID_SP_TEST_BIN is not set: the spid-validation workflow sets it to
 * tools/spid/bin/spid_sp_test, the pinned container.
 */

function spidSpTestBinary(): ?string
{
    $binary = getenv('SPID_SP_TEST_BIN') ?: null;

    // A missing xmlsec1 is not a reason to skip: the adapter reports it as an
    // error, so the tests fail.
    return $binary !== null && is_executable($binary) ? $binary : null;
}

/**
 * Configure a synthetic public SP with a fresh 3072-bit key pair (valid from
 * now, so the test never expires) and return the metadata the app serves.
 */
function servedMetadata(TestCase $test, ?string $publishedCertificate = null): string
{
    $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $dn = ['commonName' => 'sp.example.test', 'organizationName' => 'Comune di Esempio', 'countryName' => 'IT', 'localityName' => 'Roma'];
    $certificate = openssl_csr_sign(openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']), null, $key, 365, ['digest_alg' => 'sha256']);
    openssl_x509_export($certificate, $pem);
    openssl_pkey_export($key, $keyPem);
    $body = fn (string $pem) => preg_replace('/-----[^-]+-----|\s+/', '', $pem);

    Config::set('spid-auth.sp_certificate', $publishedCertificate ?? $body($pem));
    Config::set('spid-auth.sp_private_key', $body($keyPem));
    Config::set('spid-auth.sp_entity_id', 'https://sp.example.test');
    Config::set('spid-auth.sp_base_url', 'https://sp.example.test');
    Config::set('spid-auth.sp_organization_url', 'https://www.example.test');
    Config::set('spid-auth.sp_contact_persons', ['other' => ['Public' => true, 'IPACode' => 'c_x000', 'EmailAddress' => 'spid@example.test']]);

    $response = $test->get('/spid/metadata');
    expect($response->status())->toBe(200);

    return (string) $response->getContent();
}

/**
 * @return array{0: int, 1: array<string, mixed>}
 */
function validateWithRealTool(string $metadata, string $dir): array
{
    File::put($dir.'/metadata.xml', $metadata);
    $exit = Artisan::call('filament-spid:metadata:validate', [
        '--json' => true,
        '--file' => $dir.'/metadata.xml',
        '--profile' => 'spid-sp-public',
        '--tool' => spidSpTestBinary(),
    ]);

    return [$exit, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

beforeEach(function () {
    if (spidSpTestBinary() === null) {
        $this->markTestSkipped('SPID_SP_TEST_BIN is not set (see tools/spid).');
    }
    $this->dir = sys_get_temp_dir().'/filament-spid-integration-'.uniqid();
    File::ensureDirectoryExists($this->dir);
});

afterEach(function () {
    if (isset($this->dir)) {
        File::deleteDirectory($this->dir);
    }
});

it('validates the metadata the plugin serves with the real spid_sp_test', function () {
    [$exit, $report] = validateWithRealTool(servedMetadata($this), $this->dir);

    $failed = array_values(array_filter($report['checks'], fn ($check) => $check['status'] === 'fail'));

    expect($failed)->toBe([])
        ->and($exit)->toBe(0)
        ->and($report['tool'])->toMatchArray(['version' => '1.2.18', 'status' => 'ok'])
        ->and($report['summary']['executed'])->toBeGreaterThan(50)
        ->and($report['evidence'][0])->toBe('LOCAL_METADATA_CHECK_PASSED');
})->group('spid-sp-test');

it('fails metadata altered after signing', function () {
    $tampered = str_replace('entityID="https://sp.example.test"', 'entityID="https://evil.example.test"', servedMetadata($this));

    [$exit, $report] = validateWithRealTool($tampered, $this->dir);

    expect($exit)->toBe(1)->and($report['overall_status'])->toBe('fail');
})->group('spid-sp-test');

it('fails a public SP metadata stripped of its IPA code', function () {
    $stripped = preg_replace('#<spid:IPACode>[^<]*</spid:IPACode>#', '', servedMetadata($this));

    [$exit, $report] = validateWithRealTool($stripped, $this->dir);

    expect($exit)->toBe(1)->and($report['overall_status'])->toBe('fail');
})->group('spid-sp-test');

it('fails metadata signed with a key that is not the published certificate\'s', function () {
    $other = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_x509_export(openssl_csr_sign(openssl_csr_new(['commonName' => 'other.test'], $other), null, $other, 365), $pem);

    [$exit, $report] = validateWithRealTool(servedMetadata($this, preg_replace('/-----[^-]+-----|\s+/', '', $pem)), $this->dir);

    expect($exit)->toBe(1)->and($report['overall_status'])->toBe('fail');
})->group('spid-sp-test');
