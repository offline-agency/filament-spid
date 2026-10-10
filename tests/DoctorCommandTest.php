<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Checks\DependenciesCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\PanelCheck;
use OfflineAgency\FilamentSpid\Doctor\Checks\SpidPatchCheck;
use OfflineAgency\FilamentSpid\Doctor\Doctor;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Tests\Fixtures\GuardedPanelUser;
use OfflineAgency\FilamentSpid\Tests\Fixtures\PanelUser;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;
use OfflineAgency\FilamentSpid\Tests\Fixtures\UserWithoutSpidColumns;

/**
 * A synthetic SP key pair, valid for $days days from now.
 *
 * @return array{certificate: string, key: string}
 */
function doctorKeyPair(int $days = 365): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'doctor.test'], $key);
    $certificate = openssl_csr_sign($csr, null, $key, $days, serial: 1);
    openssl_x509_export($certificate, $pem);
    openssl_pkey_export($key, $keyPem);

    $body = fn (string $pem) => trim(preg_replace('/-----[^-]+-----|\s+/', '', $pem));

    return ['certificate' => $body($pem), 'key' => $body($keyPem)];
}

/**
 * Run the doctor and decode its JSON report.
 *
 * @return array{0: int, 1: array<string, mixed>, 2: string}
 */
function runDoctor(array $options = []): array
{
    $exit = Artisan::call('filament-spid:doctor', array_merge(['--json' => true], $options));
    $output = Artisan::output();

    return [$exit, json_decode($output, true, flags: JSON_THROW_ON_ERROR), $output];
}

/**
 * @param  array<string, mixed>  $report
 * @return array<string, mixed>
 */
function doctorCheck(array $report, string $id): array
{
    foreach ($report['checks'] as $check) {
        if ($check['id'] === $id) {
            return $check;
        }
    }

    throw new RuntimeException("no {$id} check");
}

beforeEach(function () {
    // A healthy installation, all synthetic.
    $this->publicPath = sys_get_temp_dir().'/filament-spid-doctor-'.uniqid();
    foreach ([
        'vendor/spid-auth/js/spid-sp-access-button.min.js',
        'vendor/spid-auth/css/spid-sp-access-button.min.css',
        'vendor/spid-auth/img/spid-ico-circle-bb.svg',
        'vendor/filament-spid/images/spid-agid-logo.png',
        'js/offline-agency/filament-spid/filament-spid-button.js',
        'js/offline-agency/filament-spid/spid-jquery.js',
    ] as $file) {
        File::ensureDirectoryExists(dirname($this->publicPath.'/'.$file));
        File::put($this->publicPath.'/'.$file, '/* synthetic */');
    }
    app()->usePublicPath($this->publicPath);

    $pair = doctorKeyPair();
    Config::set('spid-auth.sp_certificate', $pair['certificate']);
    Config::set('spid-auth.sp_private_key', $pair['key']);
    Config::set('spid-auth.sp_entity_id', 'https://sp.example.test');
    Config::set('spid-auth.sp_base_url', 'https://sp.example.test');
    Config::set('app.url', 'https://sp.example.test');
    Config::set('session.secure', true);
    Config::set('filament-spid.user_model', PanelUser::class);
});

afterEach(function () {
    File::deleteDirectory($this->publicPath);
});

it('passes a healthy installation', function () {
    [$exit, $report] = runDoctor();

    $failing = array_filter($report['checks'], fn ($check) => in_array($check['status'], ['warn', 'fail'], true));

    expect($exit)->toBe(0)
        ->and($failing)->toBe([])
        ->and($report['schema_version'])->toBe(1)
        ->and($report['overall_status'])->toBe('pass');
});

it('reports every check with id, status, message and remediation', function () {
    [, $report] = runDoctor();

    expect($report['checks'])->not->toBeEmpty();
    foreach ($report['checks'] as $check) {
        expect($check)->toHaveKeys(['id', 'status', 'message', 'remediation'])
            ->and($check['status'])->toBeIn(['pass', 'warn', 'fail', 'skip']);
    }
});

it('marks what it cannot observe as skipped, not passed', function () {
    [, $report] = runDoctor();

    expect(doctorCheck($report, 'csrf')['status'])->toBe('skip')
        ->and(doctorCheck($report, 'csp')['status'])->toBe('skip');
});

it('prints only JSON on stdout with --json', function () {
    [, , $output] = runDoctor();

    expect(json_decode($output))->not->toBeNull()
        ->and(trim($output))->toStartWith('{')
        ->and(trim($output))->toEndWith('}');
});

it('prints a readable report without --json', function () {
    $exit = Artisan::call('filament-spid:doctor');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('[PASS] sp_certificate')
        ->and($output)->toContain('[SKIP] csrf')
        ->and($output)->toContain('Overall: PASS');
});

it('fails when the published assets are missing', function () {
    File::delete($this->publicPath.'/vendor/spid-auth/js/spid-sp-access-button.min.js');

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(1)
        ->and($report['overall_status'])->toBe('fail')
        ->and(doctorCheck($report, 'assets')['status'])->toBe('fail')
        ->and(doctorCheck($report, 'assets')['remediation'])->toContain('vendor:publish --tag=spid-assets');
});

it('fails when the configured panel does not exist', function () {
    Config::set('filament-spid.panel', 'does-not-exist');

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(1)
        ->and(doctorCheck($report, 'panel')['status'])->toBe('fail');
});

it('fails when the SPID patch is missing from php-saml', function () {
    app()->bind(SpidPatchCheck::class, fn () => new SpidPatchCheck(
        fn () => '<md:AttributeConsumingService index="1"><md:AssertionConsumerService index="1" />'
    ));

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(1)
        ->and(doctorCheck($report, 'spid_patch')['status'])->toBe('fail');
});

it('warns when the SPID patch cannot be verified', function () {
    app()->bind(SpidPatchCheck::class, fn () => new SpidPatchCheck(fn () => throw new RuntimeException('no metadata')));

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(0)
        ->and(doctorCheck($report, 'spid_patch')['status'])->toBe('warn')
        ->and($report['overall_status'])->toBe('warn');
});

it('verifies the SPID patch against the installed php-saml', function () {
    [, $report] = runDoctor();

    expect(doctorCheck($report, 'spid_patch')['status'])->toBe('pass');
});

it('fails on an expired SP certificate', function () {
    // Valid for a day, checked three days later.
    $pair = doctorKeyPair(1);
    Config::set('spid-auth.sp_certificate', $pair['certificate']);
    Config::set('spid-auth.sp_private_key', $pair['key']);
    $this->travel(3)->days();

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(1)
        ->and(doctorCheck($report, 'sp_certificate')['status'])->toBe('fail')
        ->and(doctorCheck($report, 'sp_certificate')['message'])->toContain('expired');
});

it('warns on an SP certificate expiring within 30 days', function () {
    $pair = doctorKeyPair(10);
    Config::set('spid-auth.sp_certificate', $pair['certificate']);
    Config::set('spid-auth.sp_private_key', $pair['key']);

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(0)
        ->and(doctorCheck($report, 'sp_certificate')['status'])->toBe('warn');
});

it('fails when the private key does not match the certificate', function () {
    Config::set('spid-auth.sp_private_key', doctorKeyPair()['key']);

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'sp_certificate')['status'])->toBe('fail');
});

it('fails on an unreadable certificate', function () {
    Config::set('spid-auth.sp_certificate', 'not a certificate');

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'sp_certificate')['status'])->toBe('fail');
});

it('reads the certificate and key from files', function () {
    $pair = doctorKeyPair();
    $dir = $this->publicPath.'/../certs-'.uniqid();
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/sp.crt", "-----BEGIN CERTIFICATE-----\n".chunk_split($pair['certificate'], 64)."-----END CERTIFICATE-----\n");
    File::put("{$dir}/sp.key", "-----BEGIN PRIVATE KEY-----\n".chunk_split($pair['key'], 64)."-----END PRIVATE KEY-----\n");
    Config::set('spid-auth.sp_certificate', null);
    Config::set('spid-auth.sp_private_key', null);
    Config::set('spid-auth.sp_certificate_file', "{$dir}/sp.crt");
    Config::set('spid-auth.sp_private_key_file', "{$dir}/sp.key");

    [, $report] = runDoctor();

    File::deleteDirectory($dir);
    expect(doctorCheck($report, 'sp_certificate')['status'])->toBe('pass');
});

it('fails on the example key pair shipped by italia/spid-laravel in production', function () {
    $library = require __DIR__.'/../vendor/italia/spid-laravel/config/spid-auth.php';
    Config::set('spid-auth.sp_certificate', $library['sp_certificate']);
    Config::set('spid-auth.sp_private_key', $library['sp_private_key']);
    app()->detectEnvironment(fn () => 'production');

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(1)
        ->and(doctorCheck($report, 'sp_certificate')['message'])->toContain('example');
});

it('fails when test IdPs are enabled in production', function () {
    app()->detectEnvironment(fn () => 'production');
    Config::set('spid-auth.test_idp', ['entityId' => 'spid-testenv']);

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(1)
        ->and(doctorCheck($report, 'test_idps')['status'])->toBe('fail');
});

it('allows test IdPs outside production', function () {
    Config::set('spid-auth.test_idp', ['entityId' => 'spid-testenv']);

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'test_idps')['status'])->toBe('pass');
});

it('turns warnings into a failing exit code with --strict', function () {
    Config::set('session.secure', false);

    [$exit, $report] = runDoctor();
    [$strictExit] = runDoctor(['--strict' => true]);

    expect($report['overall_status'])->toBe('warn')
        ->and($exit)->toBe(0)
        ->and($strictExit)->toBe(1);
});

it('exits with 2 when a check crashes', function () {
    app()->bind(Doctor::class, fn () => new Doctor([
        new class implements Check
        {
            public function run(): Result
            {
                throw new LogicException('secret detail RSSMRA80A01H501U');
            }
        },
    ]));

    [$exit, $report, $output] = runDoctor();

    expect($exit)->toBe(2)
        ->and($report['overall_status'])->toBe('error')
        ->and($output)->toContain('LogicException')
        ->and($output)->not->toContain('RSSMRA80A01H501U');
});

it('exits with 2 on an invalid invocation', function () {
    expect(Artisan::call('filament-spid:doctor', ['--no-such-option' => true]))->toBe(2);
});

it('never prints key material or SPID data, even verbosely', function () {
    $pair = doctorKeyPair();
    Config::set('spid-auth.sp_certificate', $pair['certificate']);
    Config::set('spid-auth.sp_private_key', $pair['key']);

    Artisan::call('filament-spid:doctor', ['-vvv' => true]);
    $text = Artisan::output();
    [, , $json] = runDoctor();

    foreach ([$text, $json] as $output) {
        expect($output)->not->toContain(substr($pair['key'], 20, 40))
            ->and($output)->not->toContain(substr($pair['certificate'], 20, 40));
    }
});

it('fails when the user model is not configured correctly', function () {
    Config::set('filament-spid.user_model', 'App\\Does\\Not\\Exist');

    [$exit, $report] = runDoctor();

    expect($exit)->toBe(1)
        ->and(doctorCheck($report, 'user_model')['status'])->toBe('fail')
        ->and(doctorCheck($report, 'schema')['status'])->toBe('skip');
});

it('warns when the user model has no panel gate', function () {
    Config::set('filament-spid.user_model', User::class);

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'user_model')['status'])->toBe('warn')
        ->and(doctorCheck($report, 'user_model')['remediation'])->toContain('canAccessPanel');
});

it('fails when the users table lacks the SPID columns', function () {
    Schema::create('plain_users', function ($table) {
        $table->id();
        $table->string('name');
    });
    Config::set('filament-spid.user_model', UserWithoutSpidColumns::class);

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'schema')['status'])->toBe('fail')
        ->and(doctorCheck($report, 'schema')['remediation'])->toContain('filament-spid-migrations');
});

it('fails when the requested SPID level is below the minimum', function () {
    Config::set('spid-auth.sp_spid_level', 'https://www.spid.gov.it/SpidL1');

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'spid_config')['status'])->toBe('fail');
});

it('warns when the SP is not served over HTTPS', function () {
    Config::set('spid-auth.sp_base_url', 'http://sp.example.test');

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'spid_config')['status'])->toBe('warn');
});

it('reports the installed dependency versions', function () {
    [, $report] = runDoctor();

    expect(doctorCheck($report, 'dependencies')['status'])->toBe('pass')
        ->and(doctorCheck($report, 'dependencies')['message'])->toContain('onelogin/php-saml');
});

it('does not write to the database', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    runDoctor();

    foreach ($queries as $sql) {
        expect(strtolower(ltrim($sql)))->not->toMatch('/^(insert|update|delete|create|alter|drop)/');
    }
});

describe('doctor branches', function () {
    it('fails on a vulnerable php-saml and on a missing package', function () {
        app()->bind(DependenciesCheck::class, fn () => new DependenciesCheck(
            fn (string $package) => match ($package) {
                'onelogin/php-saml' => ['pretty' => '4.1.0', 'normalized' => '4.1.0.0'],
                'italia/spid-laravel' => null,
                default => ['pretty' => '1.0.0', 'normalized' => '1.0.0.0'],
            }
        ));

        [, $report] = runDoctor();
        $check = doctorCheck($report, 'dependencies');

        expect($check['status'])->toBe('fail')
            ->and($check['message'])->toContain('onelogin/php-saml is below 4.3.1')
            ->and($check['message'])->toContain('italia/spid-laravel is not installed');
    });

    it('names the publish command of each missing asset group', function () {
        File::delete($this->publicPath.'/vendor/filament-spid/images/spid-agid-logo.png');
        File::delete($this->publicPath.'/js/offline-agency/filament-spid/filament-spid-button.js');

        [, $report] = runDoctor();
        $check = doctorCheck($report, 'assets');

        expect($check['remediation'])->toContain('vendor:publish --tag=filament-spid-images')
            ->and($check['remediation'])->toContain('filament:assets')
            ->and($check['message'])->toContain('filament-spid-button');
    });

    it('fails without a private key', function () {
        Config::set('spid-auth.sp_private_key', '');
        Config::set('spid-auth.sp_private_key_file', null);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'sp_certificate')['message'])->toContain('sp_private_key');
    });

    it('warns about the example key pair outside production', function () {
        $library = require __DIR__.'/../vendor/italia/spid-laravel/config/spid-auth.php';
        Config::set('spid-auth.sp_certificate', $library['sp_certificate']);
        Config::set('spid-auth.sp_private_key', $library['sp_private_key']);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'sp_certificate')['status'])->toBe('warn');
    });

    it('warns when app.url is not HTTPS', function () {
        Config::set('app.url', 'http://sp.example.test');

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'https_session')['message'])->toContain('app.url');
    });

    it('fails without a default panel', function () {
        Filament::getPanel('admin')->default(false);
        app()->bind(PanelCheck::class, fn () => new class extends PanelCheck
        {
            protected function panel(): ?Panel
            {
                return null;
            }
        });

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'panel')['status'])->toBe('fail')
            ->and(doctorCheck($report, 'panel')['remediation'])->toContain('->default()');
    });

    it('warns when the panel login page is not the SPID one', function () {
        Filament::getPanel('admin')->login();

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'panel')['status'])->toBe('warn');
    });

    it('fails when the user table is missing', function () {
        Schema::drop('users');

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'schema')['status'])->toBe('fail')
            ->and(doctorCheck($report, 'schema')['remediation'])->toBe('Run php artisan migrate.');
    });

    it('warns when the database cannot be read', function () {
        Config::set('database.connections.testing.database', '/nonexistent/dir/database.sqlite');
        DB::purge('testing');

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'schema')['status'])->toBe('warn')
            ->and(doctorCheck($report, 'schema')['message'])->not->toContain('/nonexistent');
    });

    it('warns without a unique index on fiscal_code', function () {
        Schema::create('plain_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('fiscal_code')->nullable();
            $table->json('spid_data')->nullable();
        });
        Config::set('filament-spid.user_model', UserWithoutSpidColumns::class);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'schema')['status'])->toBe('warn');
    });

    it('fails without an entity id', function () {
        Config::set('spid-auth.sp_entity_id', '');

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'spid_config')['status'])->toBe('fail');
    });

    it('passes when no test IdP is enabled', function () {
        Config::set('spid-auth.test_idp', false);
        Config::set('spid-auth.validator_idp', false);
        Config::set('spid-auth.hide_real_idps', false);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'test_idps')['message'])->toStartWith('No test');
    });

    it('warns about mapped columns the model cannot fill', function () {
        Config::set('filament-spid.user_model', GuardedPanelUser::class);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'user_model')['status'])->toBe('warn')
            ->and(doctorCheck($report, 'user_model')['message'])->toContain('fiscal_code');
    });
});

it('leaves the session and the authenticated user untouched', function () {
    $user = PanelUser::create(['name' => 'Session', 'email' => 'session@example.com', 'password' => 'x', 'fiscal_code' => 'SSSNNN80A01H501U']);
    Auth::guard('web')->login($user);
    session(['spid_sessionId' => 'ID_1']);
    $before = session()->all();

    runDoctor();

    expect(session()->all())->toBe($before)
        ->and(Auth::guard('web')->id())->toBe($user->id)
        ->and(PanelUser::count())->toBe(1);
});

describe('IdPs during the AgID acceptance test', function () {
    beforeEach(function () {
        Config::set('filament-spid.deployment', 'acceptance');
        Config::set('spid-auth.test_idp', false);
        Config::set('spid-auth.hide_real_idps', false);
    });

    $official = [
        'entityId' => 'https://validator.spid.gov.it',
        'sso_endpoint' => 'https://validator.spid.gov.it/samlsso',
        'slo_endpoint' => 'https://validator.spid.gov.it/samlsso',
        'x509cert' => 'MIIsynthetic',
    ];

    it('flags the official Validator as an acceptance window, never as a pass', function () use ($official) {
        Config::set('spid-auth.validator_idp', $official);

        [$exit, $report] = runDoctor(['--strict' => true]);
        $check = doctorCheck($report, 'test_idps');

        expect($check['status'])->toBe('warn')
            ->and($check['message'])->toContain('acceptance')
            ->and($check['remediation'])->toContain('validator_idp')
            ->and($exit)->toBe(1);
    });

    it('fails on a validator that only looks official', function () use ($official) {
        Config::set('spid-auth.validator_idp', array_merge($official, ['sso_endpoint' => 'https://spid-validator.local/samlsso']));

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'test_idps')['status'])->toBe('fail');
    });

    it('fails on the test IdP', function () {
        Config::set('spid-auth.test_idp', ['entityId' => 'spid-testenv']);
        Config::set('spid-auth.validator_idp', false);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'test_idps')['status'])->toBe('fail');
    });

    it('warns when the Validator is not configured yet', function () {
        Config::set('spid-auth.validator_idp', false);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'test_idps')['status'])->toBe('warn')
            ->and(doctorCheck($report, 'test_idps')['remediation'])->toContain('https://validator.spid.gov.it/metadata.xml');
    });

    it('fails on the official Validator once back in production', function () use ($official) {
        Config::set('filament-spid.deployment', 'production');
        Config::set('spid-auth.validator_idp', $official);

        [, $report] = runDoctor();

        expect(doctorCheck($report, 'test_idps')['status'])->toBe('fail');
    });
});

it('passes a production deployment that trusts no test IdP', function () {
    Config::set('filament-spid.deployment', 'production');
    Config::set('spid-auth.test_idp', false);
    Config::set('spid-auth.validator_idp', false);
    Config::set('spid-auth.hide_real_idps', false);

    [, $report] = runDoctor();

    expect(doctorCheck($report, 'test_idps')['status'])->toBe('pass');
});
