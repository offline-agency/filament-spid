<?php

use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use OfflineAgency\FilamentSpid\Install\PanelLocator;

it('refuses to run without a registered panel and points at the Filament installer', function () {
    app()->bind(PanelLocator::class, fn () => new PanelLocator(fn () => []));
    $input = writeInstallInput(installInput());
    $before = applicationFiles();

    [$exit, $output] = runInstall(['--input' => $input]);

    expect($exit)->toBe(1)
        ->and($output)->toContain('php artisan filament:install --panels')
        ->and(applicationFiles())->toBe($before);
});

it('writes nothing with --dry-run and prints the plan', function () {
    $input = writeInstallInput(installInput());
    $before = applicationFiles();

    [$exit, $output] = runInstall(['--input' => $input, '--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(applicationFiles())->toBe($before)
        ->and($output)->toContain('Dry run')
        ->and($output)->toContain('config/spid-auth.php')
        ->and($output)->toContain('->plugin(SpidPlugin::make())')
        ->and($output)->toContain("validateCsrfTokens(except: ['spid/acs'])");
});

it('installs and configures from an input file', function () {
    $input = installInput();

    [$exit, $output] = runInstall(['--input' => writeInstallInput($input)]);

    $spidAuth = require config_path('spid-auth.php');
    expect($exit)->toBe(0)
        ->and($spidAuth['sp_entity_id'])->toBe('https://sp.example.test')
        ->and($spidAuth['sp_base_url'])->toBe('https://sp.example.test')
        ->and($spidAuth['sp_certificate'])->toBe('')
        ->and($spidAuth['sp_private_key'])->toBe('')
        ->and($spidAuth['sp_certificate_file'])->toBe($input['sp']['certificate_file'])
        ->and($spidAuth['sp_private_key_file'])->toBe($input['sp']['private_key_file'])
        ->and($spidAuth['sp_spid_level'])->toBe('https://www.spid.gov.it/SpidL2')
        ->and($spidAuth['sp_requested_attributes'])->toBe(['fiscalNumber', 'name', 'familyName', 'email'])
        ->and($spidAuth['sp_contact_persons'])->toBe(['other' => ['Public' => true, 'IPACode' => 'c_x000', 'EmailAddress' => 'spid@example.test']])
        ->and(File::exists(config_path('filament-spid.php')))->toBeTrue()
        ->and(File::exists(config_path('spid-idps.php')))->toBeTrue()
        ->and(File::glob(database_path('migrations/*_add_spid_fields_to_users_table.php')))->toHaveCount(1)
        ->and(File::exists(public_path('vendor/spid-auth/js/spid-sp-access-button.min.js')))->toBeTrue()
        ->and(File::exists(public_path('vendor/filament-spid/images/spid-agid-logo.png')))->toBeTrue()
        ->and(File::get(base_path('.env')))->toContain("FILAMENT_SPID_PANEL=admin\n")
        ->and(File::get(base_path('.env')))->toContain("SPID_AUTO_CREATE_USERS=false\n")
        ->and(File::get(base_path('.env')))->toContain("FILAMENT_SPID_SP_PROFILE=public\n")
        ->and(File::get(base_path('.env')))->toContain("FILAMENT_SPID_DEPLOYMENT=local\n")
        ->and(File::get(base_path('.env')))->not->toContain('PRIVATE KEY');

    expect(panelProvider())->toContain("->login()\n            ->plugin(SpidPlugin::make())")
        ->and(panelProvider())->toContain('use OfflineAgency\FilamentSpid\SpidPlugin;')
        ->and(File::get(base_path('bootstrap/app.php')))->toContain("\$middleware->validateCsrfTokens(except: ['spid/acs']);");

    expect($output)->toContain(route('spid-auth_metadata'))
        ->and($output)->toContain(route('spid-auth_acs'))
        ->and($output)->toContain('php artisan migrate')
        ->and($output)->toContain('php artisan filament-spid:doctor');
});

it('changes nothing when run again with the same input', function () {
    $input = writeInstallInput(installInput());
    runInstall(['--input' => $input]);
    $keyHash = hash_file('sha256', storage_path('app/private/spid/sp.key'));
    $after = applicationFiles();

    [$exit, $output] = runInstall(['--input' => $input]);

    expect($exit)->toBe(0)
        ->and(applicationFiles())->toBe($after)
        ->and(substr_count(panelProvider(), 'SpidPlugin::make()'))->toBe(1)
        ->and(substr_count(File::get(base_path('.env')), 'FILAMENT_SPID_PANEL='))->toBe(1)
        ->and(substr_count(File::get(base_path('bootstrap/app.php')), 'validateCsrfTokens'))->toBe(1)
        ->and(File::glob(database_path('migrations/*_add_spid_fields_to_users_table.php')))->toHaveCount(1)
        ->and(hash_file('sha256', storage_path('app/private/spid/sp.key')))->toBe($keyHash)
        ->and($output)->toContain('unchanged');
});

it('updates the values it wrote when the input changes', function () {
    runInstall(['--input' => writeInstallInput(installInput())]);

    runInstall(['--input' => writeInstallInput(installInput(['sp' => ['service_name' => 'Renamed service']]))]);

    expect((require config_path('spid-auth.php'))['sp_service_name'])->toBe('Renamed service');
});

it('keeps a hand-edited spid-auth.php and prints the values to change instead', function () {
    runInstall(['--input' => writeInstallInput(installInput())]);
    File::put(config_path('spid-auth.php'), str_replace('Example service', 'Edited by hand', File::get(config_path('spid-auth.php'))));
    $edited = File::get(config_path('spid-auth.php'));

    [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput(['sp' => ['service_name' => 'From the installer']]))]);

    expect($exit)->toBe(1)
        ->and(File::get(config_path('spid-auth.php')))->toBe($edited)
        ->and($output)->toContain('Manual steps')
        ->and($output)->toContain("'sp_service_name' => 'From the installer'");
});

it('runs the migrations only with --migrate', function () {
    $input = writeInstallInput(installInput());

    runInstall(['--input' => $input]);
    $ranWithout = Schema::hasTable('migrations')
        && DB::table('migrations')->where('migration', 'like', '%add_spid_fields_to_users_table')->exists();

    runInstall(['--input' => $input, '--migrate' => true]);

    expect($ranWithout)->toBeFalse()
        ->and(DB::table('migrations')->where('migration', 'like', '%add_spid_fields_to_users_table')->exists())->toBeTrue();
});

it('exempts the ACS under a custom routes prefix only', function () {
    runInstall(['--input' => writeInstallInput(installInput())]);
    config()->set('spid-auth.routes_prefix', 'auth/spid');
    File::put(base_path('bootstrap/app.php'), File::get(__DIR__.'/stubs/bootstrap-app.php'));

    runInstall(['--input' => writeInstallInput(installInput()), '--dry-run' => false]);

    expect(File::get(base_path('bootstrap/app.php')))->toContain("validateCsrfTokens(except: ['auth/spid/acs'])")
        ->and(File::get(base_path('bootstrap/app.php')))->not->toContain("'spid/*'");
});

it('leaves an existing CSRF exception list alone and asks for the entry by hand', function () {
    File::put(base_path('bootstrap/app.php'), str_replace('        //', "        \$middleware->validateCsrfTokens(except: ['stripe/*']);", File::get(base_path('bootstrap/app.php'))));
    $before = File::get(base_path('bootstrap/app.php'));

    [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput())]);

    expect($exit)->toBe(1)
        ->and(File::get(base_path('bootstrap/app.php')))->toBe($before)
        ->and($output)->toContain("'spid/acs'");
});

it('prints the plugin snippet for a panel provider it does not recognise', function () {
    File::put(app_path('Providers/Filament/AdminPanelProvider.php'), "<?php\n\nnamespace App\\Providers\\Filament;\n\nclass AdminPanelProvider {}\n");

    [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput())]);

    expect($exit)->toBe(1)
        ->and($output)->toContain('->plugin(\OfflineAgency\FilamentSpid\SpidPlugin::make())')
        ->and(panelProvider())->not->toContain('SpidPlugin');
});

it('adds the plugin after ->id() when the panel has no ->login()', function () {
    File::put(app_path('Providers/Filament/AdminPanelProvider.php'), str_replace("            ->login()\n", '', panelProvider()));

    runInstall(['--input' => writeInstallInput(installInput())]);

    expect(panelProvider())->toContain("->id('admin')\n            ->plugin(SpidPlugin::make())");
});

it('requires --panel when several panels are registered', function () {
    filament()->registerPanel(Panel::make()->id('staff')->path('staff'));
    $input = installInput();
    unset($input['panel']);
    $path = writeInstallInput($input);
    $before = applicationFiles();

    [$exit, $output] = runInstall(['--input' => $path]);

    expect($exit)->toBe(1)
        ->and($output)->toContain('--panel')
        ->and(applicationFiles())->toBe($before);
});

it('takes the panel from --panel', function () {
    $input = installInput();
    unset($input['panel']);

    [$exit] = runInstall(['--input' => writeInstallInput($input), '--panel' => 'admin']);

    expect($exit)->toBe(0)
        ->and(File::get(base_path('.env')))->toContain('FILAMENT_SPID_PANEL=admin');
});

it('rejects an unknown panel', function () {
    [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput()), '--panel' => 'nope']);

    expect($exit)->toBe(1)->and($output)->toContain('nope');
});

it('refuses invalid input before writing anything', function (Closure $mutate, string $error) {
    $input = installInput();
    $mutate($input);
    $path = writeInstallInput($input);
    $before = applicationFiles();

    [$exit, $output] = runInstall(['--input' => $path]);

    expect($exit)->toBe(1)
        ->and($output)->toContain($error)
        ->and(applicationFiles())->toBe($before);
})->with([
    'an embedded private key' => [function (array &$input) {
        $input['sp']['private_key_file'] = "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----";
    }, 'path'],
    'a public SP without IPA code' => [function (array &$input) {
        unset($input['sp']['contact']['ipa_code']);
    }, 'sp.contact.ipa_code'],
    'a private SP without VAT number' => [function (array &$input) {
        $input['sp']['profile'] = 'private';
        unset($input['sp']['contact']['ipa_code']);
    }, 'sp.contact.vat_number'],
    'a missing entity id' => [function (array &$input) {
        unset($input['sp']['entity_id']);
    }, 'sp.entity_id'],
    'an http base URL' => [function (array &$input) {
        $input['sp']['base_url'] = 'http://sp.example.test';
    }, 'https'],
    'an unknown profile' => [function (array &$input) {
        $input['sp']['profile'] = 'aggregator';
    }, 'sp.profile'],
    'an unknown deployment' => [function (array &$input) {
        $input['deployment'] = 'staging';
    }, 'deployment'],
    'a level below SpidL2' => [function (array &$input) {
        $input['sp']['level'] = 'https://www.spid.gov.it/SpidL1';
    }, 'sp.level'],
    'attributes without fiscalNumber' => [function (array &$input) {
        $input['sp']['attributes'] = ['name'];
    }, 'fiscalNumber'],
    'an unknown attribute' => [function (array &$input) {
        $input['sp']['attributes'] = ['fiscalNumber', 'shoeSize'];
    }, 'shoeSize'],
    'a missing key file' => [function (array &$input) {
        $input['sp']['private_key_file'] = '/nonexistent/sp.key';
    }, 'sp.private_key_file'],
    'a key under public/' => [function (array &$input) {
        File::ensureDirectoryExists(public_path('keys'));
        File::copy($input['sp']['private_key_file'], public_path('keys/sp.key'));
        $input['sp']['private_key_file'] = public_path('keys/sp.key');
    }, 'public'],
    'a key that does not match the certificate' => [function (array &$input) {
        $input['sp']['private_key_file'] = installKeyPair('other')['key'];
    }, 'does not belong'],
    'an unknown schema version' => [function (array &$input) {
        $input['schema_version'] = 2;
    }, 'schema_version'],
]);

it('refuses an input file that is not JSON', function () {
    File::put(base_path('setup.json'), 'not json');

    [$exit, $output] = runInstall(['--input' => base_path('setup.json')]);

    expect($exit)->toBe(1)->and($output)->toContain('JSON');
});

it('refuses a missing input file', function () {
    [$exit, $output] = runInstall(['--input' => base_path('missing.json')]);

    expect($exit)->toBe(1)->and($output)->toContain('missing.json');
});

it('requires an input file or a prompt for the SP data', function () {
    $before = applicationFiles();

    [$exit, $output] = runInstall();

    expect($exit)->toBe(1)
        ->and($output)->toContain('--input')
        ->and(applicationFiles())->toBe($before);
});

it('accepts a private SP and leaves the billing contact as a manual step', function () {
    $input = installInput(['sp' => ['profile' => 'private', 'contact' => ['vat_number' => 'IT12345678901', 'fiscal_code' => '12345678901']]]);
    unset($input['sp']['contact']['ipa_code']);

    [$exit, $output] = runInstall(['--input' => writeInstallInput($input)]);

    expect((require config_path('spid-auth.php'))['sp_contact_persons'])->toBe([
        'other' => ['Private' => true, 'VATNumber' => 'IT12345678901', 'FiscalCode' => '12345678901', 'EmailAddress' => 'spid@example.test'],
    ])
        ->and($exit)->toBe(1)
        ->and($output)->toContain('billing');
});

it('turns test IdPs off for production and leaves the validator as a manual step for acceptance', function (string $deployment) {
    [, $output] = runInstall(['--input' => writeInstallInput(installInput(['deployment' => $deployment]))]);

    $spidAuth = require config_path('spid-auth.php');
    expect($spidAuth['test_idp'])->toBeFalse()
        ->and($spidAuth['validator_idp'])->toBeFalse()
        ->and($spidAuth['hide_real_idps'])->toBeFalse();
    if ($deployment === 'acceptance') {
        expect($output)->toContain('https://validator.spid.gov.it/metadata.xml');
    }
})->with(['production', 'acceptance']);

it('opts into provisioning only when asked', function () {
    runInstall(['--input' => writeInstallInput(installInput(['provisioning' => ['auto_create_users' => true]]))]);

    expect(File::get(base_path('.env')))->toContain("SPID_AUTO_CREATE_USERS=true\n");
});

it('prints the variables when there is no .env file', function () {
    File::delete(base_path('.env'));

    [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput())]);

    expect($exit)->toBe(1)
        ->and($output)->toContain('FILAMENT_SPID_PANEL=admin')
        ->and(File::exists(base_path('.env')))->toBeFalse();
});

it('asks for the SP data interactively, offering what is configured', function () {
    $pair = installKeyPair();

    $this->artisan('filament-spid:install')
        ->expectsChoice('SP profile', 'public', ['public', 'private'])
        ->expectsChoice('Deployment', 'local', ['local', 'acceptance', 'production'])
        ->expectsQuestion('SP entityID (an HTTPS URL that identifies your service)', 'https://sp.example.test')
        ->expectsQuestion('Public base URL of the application (HTTPS)', 'https://sp.example.test')
        ->expectsQuestion('Service name shown to citizens', 'Example service')
        ->expectsQuestion('Legal name of the organisation (the SP, not the developer)', 'Comune di Esempio')
        ->expectsQuestion('Display name of the organisation', 'Comune di Esempio')
        ->expectsQuestion('Organisation website (HTTPS)', 'https://www.example.test')
        ->expectsQuestion('Contact email of the SP', 'spid@example.test')
        ->expectsQuestion('IPA code of the public administration', 'c_x000')
        ->expectsQuestion('SPID attributes to request (comma separated)', 'fiscalNumber,name,familyName')
        ->expectsQuestion('Path of the SP signing certificate (PEM)', $pair['certificate'])
        ->expectsQuestion('Path of the SP private key (PEM, kept outside public/)', $pair['key'])
        ->expectsConfirmation('Create an account for every SPID citizen without one (auto_create_users)?', 'no')
        ->expectsConfirmation('Apply these changes?', 'yes')
        ->assertExitCode(0);

    expect((require config_path('spid-auth.php'))['sp_requested_attributes'])->toBe(['fiscalNumber', 'name', 'familyName']);
});

it('applies nothing when the plan is declined', function () {
    $path = writeInstallInput(installInput());
    $before = applicationFiles();

    $this->artisan('filament-spid:install', ['--input' => $path])
        ->expectsConfirmation('Apply these changes?', 'no')
        ->assertExitCode(1);

    expect(applicationFiles())->toBe($before);
});

it('still runs when the configured panel is unknown', function () {
    putenv('FILAMENT_SPID_PANEL=missing');
    config()->set('filament-spid.panel', 'missing');

    [$exit] = runInstall(['--input' => writeInstallInput(installInput()), '--dry-run' => true]);

    putenv('FILAMENT_SPID_PANEL');
    expect($exit)->toBe(0);
});
