<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/*
 * Lab only: configure the throwaway application built by install-check.sh to
 * trust spid_sp_test's test IdP, and give it one synthetic citizen the panel
 * admits through an explicit gate (spid_lab_allowed), never `return true`.
 *
 * Usage (from the application root): php lab-configure.php <idp-metadata.xml> <sp base URL>
 */

[$script, $idpMetadata, $baseUrl] = $argv + [null, null, null];
if (! is_string($idpMetadata) || ! is_string($baseUrl)) {
    fwrite(STDERR, "usage: php lab-configure.php <idp-metadata.xml> <sp base URL>\n");
    exit(2);
}

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// The IdP, derived from its metadata: entityID, SSO/SLO endpoints, signing certificate.
$xml = new DOMDocument;
$xml->loadXML((string) file_get_contents($idpMetadata), LIBXML_NONET);
$xpath = new DOMXPath($xml);
$xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');
$xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$redirect = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
$value = fn (string $query) => trim((string) ($xpath->query($query)?->item(0)?->nodeValue ?? ''));
$idp = [
    'entityId' => $value('/md:EntityDescriptor/@entityID'),
    'sso_endpoint' => $value("//md:IDPSSODescriptor/md:SingleSignOnService[@Binding='{$redirect}']/@Location"),
    'slo_endpoint' => $value("//md:IDPSSODescriptor/md:SingleLogoutService[@Binding='{$redirect}']/@Location"),
    'x509cert' => preg_replace('/\s+/', '', $value("//md:IDPSSODescriptor/md:KeyDescriptor[not(@use) or @use='signing']//ds:X509Certificate")),
];
foreach ($idp as $key => $item) {
    if ($item === '') {
        fwrite(STDERR, "the IdP metadata has no {$key}\n");
        exit(1);
    }
}

$config = require config_path('spid-auth.php');
$config['test_idp'] = $idp;
$config['sp_base_url'] = rtrim($baseUrl, '/');
file_put_contents(config_path('spid-auth.php'), "<?php\n\n// Lab configuration (tools/spid/lab-configure.php).\n\nreturn ".var_export($config, true).";\n");

// Behind the lab's TLS proxy: php-saml compares the Response Destination
// with the URL it was received at, so it must read X-Forwarded-*; Laravel
// generates https:// URLs. Only the proxy can reach the app in the lab.
$provider = app_path('Providers/AppServiceProvider.php');
$source = (string) file_get_contents($provider);
if (! str_contains($source, 'setProxyVars')) {
    $source = preg_replace('/public function boot\(\): void\s*\{/', "public function boot(): void\n    {\n        // Lab TLS proxy (tools/spid/lab-configure.php).\n        \\OneLogin\\Saml2\\Utils::setProxyVars(true);\n        \\Illuminate\\Support\\Facades\\URL::forceScheme('https');\n", $source, 1);
    file_put_contents($provider, $source);
}

// An explicit gate for the lab citizen.
$model = app_path('Models/User.php');
$source = (string) file_get_contents($model);
if (! str_contains($source, 'spid_lab_allowed')) {
    $source = str_replace('class User extends Authenticatable', 'class User extends Authenticatable implements \Filament\Models\Contracts\FilamentUser', $source);
    $source = substr($source, 0, (int) strrpos($source, '}'))."\n    public function canAccessPanel(\\Filament\\Panel \$panel): bool\n    {\n        // Lab gate: only citizens flagged for the lab.\n        return (bool) \$this->spid_lab_allowed;\n    }\n}\n";
    file_put_contents($model, $source);
}

$schema = Schema::getFacadeRoot();
if (! $schema->hasColumn('users', 'spid_lab_allowed')) {
    $schema->table('users', fn ($table) => $table->boolean('spid_lab_allowed')->default(true));
}

// spid_sp_test's default user (synthetic AgID test identity, see spid_sp_test -pas).
DB::table('users')->updateOrInsert(
    ['fiscal_code' => 'GDASDV00A01H501J'],
    ['name' => 'SpidValidator AgID', 'email' => 'lab-citizen@example.test', 'password' => Hash::make(bin2hex(random_bytes(16))), 'spid_lab_allowed' => true, 'created_at' => now(), 'updated_at' => now()],
);

echo "lab: trusting test IdP {$idp['entityId']}, SP base URL {$config['sp_base_url']}\n";
