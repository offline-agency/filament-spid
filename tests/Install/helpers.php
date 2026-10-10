<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * A synthetic SP key pair under storage/, as the guide recommends.
 *
 * @return array{certificate: string, key: string}
 */
function installKeyPair(string $name = 'sp'): array
{
    $dir = storage_path('app/private/spid');
    File::ensureDirectoryExists($dir);
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $certificate = openssl_csr_sign(openssl_csr_new(['commonName' => 'sp.example.test'], $key), null, $key, 365);
    openssl_x509_export($certificate, $pem);
    openssl_pkey_export($key, $keyPem);
    File::put("{$dir}/{$name}.crt", $pem);
    File::put("{$dir}/{$name}.key", $keyPem);
    chmod("{$dir}/{$name}.key", 0600);

    return ['certificate' => "{$dir}/{$name}.crt", 'key' => "{$dir}/{$name}.key"];
}

/**
 * A complete installer input for a public-sector SP, overridable per test.
 *
 * @return array<string, mixed>
 */
function installInput(array $overrides = []): array
{
    $pair = installKeyPair();

    return array_replace_recursive([
        'schema_version' => 1,
        'panel' => 'admin',
        'deployment' => 'local',
        'sp' => [
            'profile' => 'public',
            'entity_id' => 'https://sp.example.test',
            'base_url' => 'https://sp.example.test',
            'service_name' => 'Example service',
            'organization' => [
                'name' => 'Comune di Esempio',
                'display_name' => 'Comune di Esempio',
                'url' => 'https://www.example.test',
            ],
            'contact' => [
                'email' => 'spid@example.test',
                'ipa_code' => 'c_x000',
            ],
            'attributes' => ['fiscalNumber', 'name', 'familyName', 'email'],
            'certificate_file' => $pair['certificate'],
            'private_key_file' => $pair['key'],
        ],
        'provisioning' => ['auto_create_users' => false],
    ], $overrides);
}

function writeInstallInput(array $input): string
{
    $path = base_path('setup.json');
    File::put($path, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $path;
}

/**
 * @return array{0: int, 1: string}
 */
function runInstall(array $options = []): array
{
    $exit = Artisan::call('filament-spid:install', array_merge(['--no-interaction' => true], $options));

    return [$exit, Artisan::output()];
}

/**
 * Every file under the application, with its hash, to prove nothing changed.
 *
 * @return array<string, string>
 */
function applicationFiles(): array
{
    $hashes = [];
    foreach (['app', 'bootstrap', 'config', 'database', 'public', 'routes'] as $dir) {
        foreach (File::allFiles(base_path($dir)) as $file) {
            $hashes[$file->getPathname()] = hash_file('sha256', $file->getPathname());
        }
    }
    $hashes['.env'] = hash_file('sha256', base_path('.env'));

    return $hashes;
}

function panelProvider(): string
{
    return File::get(app_path('Providers/Filament/AdminPanelProvider.php'));
}
