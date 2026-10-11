<?php

use Filament\Panel;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\File;
use OfflineAgency\FilamentSpid\Install\Editors\CsrfExceptionEditor;
use OfflineAgency\FilamentSpid\Install\Editors\PanelProviderEditor;
use OfflineAgency\FilamentSpid\Install\Editors\SpidAuthConfig;
use OfflineAgency\FilamentSpid\Install\Editors\Tokens;
use OfflineAgency\FilamentSpid\Install\InstallInput;
use OfflineAgency\FilamentSpid\Install\InvalidInstallInput;

describe('PanelProviderEditor', function () {
    it('leaves a provider it cannot read safely alone', function (string $source, string $reason) {
        $edit = (new PanelProviderEditor)->edit($source, 'admin');

        expect($edit->recognised)->toBeFalse()
            ->and($edit->source)->toBe($source)
            ->and($edit->reason)->toContain($reason);
    })->with([
        'two ->login() calls' => ["<?php\nnamespace A;\nreturn \$panel->id('admin')->login()->login();", 'more than one'],
        'an unclosed call' => ["<?php\nnamespace A;\nreturn \$panel->id('admin')->login(", 'unbalanced'],
        'no namespace' => ["<?php\nreturn \$panel->id('admin')->login();", 'namespace'],
        'an id that is not a literal' => ["<?php\nnamespace A;\nreturn \$panel->id(\$id)->login();", "->id('admin')"],
    ]);

    it('keeps a one-line chain valid', function () {
        $edit = (new PanelProviderEditor)->edit("<?php\nnamespace A;\nreturn \$panel->id('admin')->login();", 'admin');

        expect($edit->recognised)->toBeTrue()
            ->and(Tokens::parses($edit->source))->toBeTrue()
            ->and($edit->source)->toContain("->login()\n->plugin(SpidPlugin::make())")
            ->and($edit->source)->toContain("namespace A;\nuse OfflineAgency\\FilamentSpid\\SpidPlugin;");
    });
});

describe('CsrfExceptionEditor', function () {
    it('leaves a bootstrap/app.php it cannot read safely alone', function (string $source, string $reason) {
        $edit = (new CsrfExceptionEditor)->edit($source, 'spid/acs');

        expect($edit->recognised)->toBeFalse()->and($edit->reason)->toContain($reason);
    })->with([
        'no withMiddleware()' => ["<?php\nreturn \$app->create();", 'withMiddleware'],
        'an arrow function' => ["<?php\nreturn \$app->withMiddleware(fn (\$m) => null)->create();", 'closure'],
        'a closure without parameter' => ["<?php\nreturn \$app->withMiddleware(function () {\n    //\n})->create();", 'parameter'],
    ]);
});

describe('Tokens', function () {
    it('tells source that does not parse', function () {
        expect(Tokens::parses('<?php if ('))->toBeFalse();
    });
});

describe('SpidAuthConfig', function () {
    it('does not manage a file it did not write', function () {
        expect((new SpidAuthConfig)->isManaged("<?php\n\nreturn ['sp_entity_id' => 'https://mine.test'];\n"))->toBeFalse();
    });

    it('exports empty lists', function () {
        expect(SpidAuthConfig::snippet(['sp_requested_attributes' => []]))->toBe("    'sp_requested_attributes' => [],");
    });
});

describe('InstallInput', function () {
    function inputErrors(array $input): array
    {
        try {
            InstallInput::fromArray($input);
        } catch (InvalidInstallInput $e) {
            return $e->errors;
        }

        return [];
    }

    it('rejects attributes that are not a list', function () {
        expect(implode(' ', inputErrors(installInput(['sp' => ['attributes' => 'fiscalNumber']]))))->toContain('sp.attributes must be a list');
    });

    it('rejects a provisioning flag that is not a boolean', function () {
        expect(implode(' ', inputErrors(installInput(['provisioning' => ['auto_create_users' => 'yes']]))))->toContain('true or false');
    });

    it('requires both key pair paths', function () {
        $input = installInput();
        unset($input['sp']['certificate_file']);

        expect(implode(' ', inputErrors($input)))->toContain('sp.certificate_file is required');
    });

    it('rejects files that are not PEM', function (string $field, string $error) {
        $input = installInput();
        File::put(storage_path('app/private/spid/garbage.pem'), 'garbage');
        $input['sp'][$field] = storage_path('app/private/spid/garbage.pem');

        expect(implode(' ', inputErrors($input)))->toContain($error);
    })->with([
        'certificate' => ['certificate_file', 'not a PEM X.509 certificate'],
        'key' => ['private_key_file', 'not a PEM private key'],
    ]);

    it('adds the phone to the contact person when given', function () {
        $input = InstallInput::fromArray(installInput(['sp' => ['contact' => ['phone' => '+39 06 0000000']]]));

        expect($input->contactPersons()['other']['TelephoneNumber'])->toBe('+39 06 0000000');
    });
});

describe('the plan', function () {
    it('reports a hand-edited spid-auth.php that already holds the values as unchanged', function () {
        $input = installInput();
        runInstall(['--input' => writeInstallInput($input)]);
        File::put(config_path('spid-auth.php'), str_replace(SpidAuthConfig::MARKER, '// mine ', File::get(config_path('spid-auth.php'))));
        foreach (require config_path('spid-auth.php') as $key => $value) {
            config()->set("spid-auth.{$key}", $value);
        }

        [, $output] = runInstall(['--input' => writeInstallInput($input)]);

        expect($output)->toContain('edited by hand; already holds these values');
    });

    it('skips application files that are not panel providers', function () {
        File::ensureDirectoryExists(app_path('Models'));
        File::put(app_path('Models/Post.php'), "<?php\n\nnamespace App\\Models;\n\nclass Post {}\n");

        [$exit] = runInstall(['--input' => writeInstallInput(installInput())]);

        expect($exit)->toBe(0);
    });

    it('asks for the CSRF exemption when there is no bootstrap/app.php', function () {
        File::delete(base_path('bootstrap/app.php'));

        [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput())]);

        expect($exit)->toBe(1)->and($output)->toContain(CsrfExceptionEditor::method()."(except: ['spid/acs'])");
    });

    it('flags a private key other users can read', function () {
        $input = installInput();
        chmod($input['sp']['private_key_file'], 0644);

        [$exit, $output] = runInstall(['--input' => writeInstallInput($input)]);

        expect($exit)->toBe(1)->and($output)->toContain('chmod 600');
    });

    it('reminds to rebuild a cached configuration', function () {
        // As when the configuration was loaded from bootstrap/cache/config.php.
        app()->instance('config_loaded_from_cache', true);

        [, $output] = runInstall(['--input' => writeInstallInput(installInput())]);

        expect($output)->toContain('php artisan config:cache');
    });

    it('asks for the plugin snippet when the application has no app directory', function () {
        app()->useAppPath(base_path('no-such-directory'));

        [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput())]);

        expect($exit)->toBe(1)->and($output)->toContain(PanelProviderEditor::SNIPPET);
    });

    it('says what was applied when a step fails, and how to resume', function () {
        chmod(base_path('bootstrap/app.php'), 0444);

        [$exit, $output] = runInstall(['--input' => writeInstallInput(installInput())]);

        chmod(base_path('bootstrap/app.php'), 0644);
        expect($exit)->toBe(1)
            ->and($output)->toContain('Failed: bootstrap/app.php')
            ->and($output)->toContain('Applied before the failure: ')
            ->and($output)->toContain('config/spid-auth.php')
            ->and($output)->toContain('run the same command again');
    });
});

describe('interactive', function () {
    it('asks which panel when several are registered', function () {
        filament()->registerPanel(Panel::make()->id('staff')->path('staff'));

        $this->artisan('filament-spid:install', ['--input' => writeInstallInput(array_diff_key(installInput(), ['panel' => true])), '--dry-run' => true])
            ->expectsChoice('Panel SPID users sign in to', 'staff', ['admin', 'staff'])
            ->expectsOutputToContain('no panel provider this installer can edit safely')
            ->assertExitCode(0);
    });

    it('asks a private SP for its VAT number', function () {
        $pair = installKeyPair();

        $this->artisan('filament-spid:install', ['--dry-run' => true])
            ->expectsChoice('SP profile', 'private', ['public', 'private'])
            ->expectsChoice('Deployment', 'local', ['local', 'acceptance', 'production'])
            ->expectsQuestion('SP entityID (an HTTPS URL that identifies your service)', 'https://sp.example.test')
            ->expectsQuestion('Public base URL of the application (HTTPS)', 'https://sp.example.test')
            ->expectsQuestion('Service name shown to citizens', 'Example service')
            ->expectsQuestion('Legal name of the organisation (the SP, not the developer)', 'Esempio S.r.l.')
            ->expectsQuestion('Display name of the organisation', 'Esempio')
            ->expectsQuestion('Organisation website (HTTPS)', 'https://www.example.test')
            ->expectsQuestion('Contact email of the SP', 'spid@example.test')
            ->expectsQuestion('VAT number of the SP (e.g. IT12345678901)', 'IT12345678901')
            ->expectsQuestion('Fiscal code of the SP (optional)', '')
            ->expectsQuestion('SPID attributes to request (comma separated)', 'fiscalNumber')
            ->expectsQuestion('Path of the SP signing certificate (PEM)', $pair['certificate'])
            ->expectsQuestion('Path of the SP private key (PEM, kept outside public/)', $pair['key'])
            ->expectsConfirmation('Create an account for every SPID citizen without one (auto_create_users)?', 'no')
            ->expectsOutputToContain('billing contact')
            ->assertExitCode(0);
    });
});

it('ships examples that only need real key pair paths', function (string $example) {
    $data = json_decode(File::get(__DIR__.'/../../examples/'.$example), true, flags: JSON_THROW_ON_ERROR);
    $pair = installKeyPair();
    $data['sp']['certificate_file'] = $pair['certificate'];
    $data['sp']['private_key_file'] = $pair['key'];

    expect(InstallInput::fromArray($data))->toBeInstanceOf(InstallInput::class);
})->with(['setup.public.json', 'setup.private.json']);

describe('the CSRF API of the installed Laravel', function () {
    it('uses preventRequestForgery() where it exists, validateCsrfTokens() otherwise', function () {
        $expected = method_exists(Middleware::class, 'preventRequestForgery') ? 'preventRequestForgery' : 'validateCsrfTokens';

        expect(CsrfExceptionEditor::method())->toBe($expected);
    });

    it('writes the call it is told to', function (string $method) {
        $source = File::get(__DIR__.'/stubs/bootstrap-app.php');

        expect((new CsrfExceptionEditor)->edit($source, 'spid/acs', $method)->source)->toContain("\$middleware->{$method}(except: ['spid/acs']);");
    })->with(['preventRequestForgery', 'validateCsrfTokens']);

    it('recognises an exemption made with either call', function (string $method) {
        $source = str_replace('        //', "        \$middleware->{$method}(except: ['spid/acs']);", File::get(__DIR__.'/stubs/bootstrap-app.php'));

        $edit = (new CsrfExceptionEditor)->edit($source, 'spid/acs', 'preventRequestForgery');

        expect($edit->recognised)->toBeTrue()->and($edit->source)->toBe($source);
    })->with(['preventRequestForgery', 'validateCsrfTokens']);
});
