<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Italia\SPIDAuth\ServiceProvider as SpidAuthServiceProvider;
use OfflineAgency\FilamentSpid\FilamentSpidServiceProvider;
use OfflineAgency\FilamentSpid\Install\Editors\CsrfExceptionEditor;
use OfflineAgency\FilamentSpid\Install\Editors\EnvEditor;
use OfflineAgency\FilamentSpid\Install\Editors\PanelProviderEditor;
use OfflineAgency\FilamentSpid\Install\Editors\SpidAuthConfig;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

/**
 * Turns validated input into the list of changes filament-spid:install makes.
 * Planning reads files only; nothing is written until a change is applied.
 */
class Planner
{
    public const VALIDATOR_METADATA = 'https://validator.spid.gov.it/metadata.xml';

    public function __construct(protected Filesystem $files) {}

    /**
     * @param  Closure(string, array<string, mixed>): int  $call  runs an Artisan command
     * @return list<Change>
     */
    public function plan(InstallInput $input, bool $migrate, Closure $call): array
    {
        return array_values(array_filter([
            ...$this->publishMissing(FilamentSpidServiceProvider::class, 'filament-spid-config', fn (string $target) => basename($target) !== 'spid-auth.php'),
            $this->spidAuthConfig($input),
            $this->environment($input),
            $this->migration(),
            ...$this->publishMissing(SpidAuthServiceProvider::class, 'spid-assets'),
            ...$this->publishMissing(FilamentSpidServiceProvider::class, 'filament-spid-images'),
            new Change(Change::RUN, 'public/js, public/css', 'php artisan filament:assets (the plugin\'s button script and jQuery)', null, fn () => $call('filament:assets', [])),
            $this->panelProvider($input),
            $this->csrf(),
            $migrate
                ? new Change(Change::RUN, 'database', 'php artisan migrate --force (asked for with --migrate)', null, fn () => $call('migrate', ['--force' => true]))
                : null,
            ...$this->manualSteps($input),
        ]));
    }

    /**
     * Copy each file of a publish tag that the application does not have yet.
     * Existing files, customised or not, are never overwritten.
     *
     * @param  (callable(string): bool)|null  $only
     * @return list<Change>
     */
    protected function publishMissing(string $provider, string $tag, ?callable $only = null): array
    {
        $changes = [];

        foreach (array_filter(ServiceProvider::pathsToPublish($provider, $tag), is_string(...)) as $source => $target) {
            $source = (string) $source;
            $sources = is_dir($source)
                ? array_map(fn ($file) => [$file->getPathname(), $target.'/'.$file->getRelativePathname()], $this->files->allFiles($source))
                : [[$source, $target]];

            $missing = array_values(array_filter($sources, fn (array $pair) => ! $this->files->exists($pair[1]) && ($only === null || $only($pair[1]))));

            if ($missing === []) {
                if ($only === null || $only($target)) {
                    $changes[] = new Change(Change::UNCHANGED, $this->relative($target), "vendor:publish --tag={$tag}: already published");
                }

                continue;
            }

            $changes[] = new Change(
                Change::CREATE,
                $this->relative($target),
                "vendor:publish --tag={$tag}: ".count($missing).' missing file(s), existing ones kept',
                null,
                function () use ($missing) {
                    foreach ($missing as [$from, $to]) {
                        $this->files->ensureDirectoryExists(dirname($to));
                        $this->files->copy($from, $to);
                    }
                },
            );
        }

        return $changes;
    }

    protected function spidAuthConfig(InstallInput $input): Change
    {
        $path = config_path('spid-auth.php');
        $current = $this->files->exists($path) ? $this->files->get($path) : null;
        $config = new SpidAuthConfig;
        $values = $this->spidAuthValues($input);

        if (! $config->isManaged($current)) {
            $changed = array_filter($values, fn (mixed $value, string $key) => config("spid-auth.{$key}") !== $value, ARRAY_FILTER_USE_BOTH);

            return $changed === []
                ? new Change(Change::UNCHANGED, 'config/spid-auth.php', 'edited by hand; already holds these values')
                : new Change(Change::MANUAL, 'config/spid-auth.php', 'edited by hand, so it is not rewritten: set these values in it', SpidAuthConfig::snippet($changed));
        }

        $rendered = $config->render($values);

        return $current === $rendered
            ? new Change(Change::UNCHANGED, 'config/spid-auth.php', 'SP identity, contacts, attributes, level and key pair paths')
            : new Change($current === null ? Change::CREATE : Change::UPDATE, 'config/spid-auth.php', 'SP identity, contacts, attributes, level and key pair paths', null, fn () => $this->files->put($path, $rendered));
    }

    /**
     * @return array<string, mixed>
     */
    protected function spidAuthValues(InstallInput $input): array
    {
        $values = [
            'sp_entity_id' => $input->entityId,
            'sp_base_url' => $input->baseUrl,
            'sp_service_name' => $input->serviceName,
            'sp_organization_name' => $input->organizationName,
            'sp_organization_display_name' => $input->organizationDisplayName,
            'sp_organization_url' => $input->organizationUrl,
            'sp_requested_attributes' => $input->attributes,
            // Paths only: the key never lands in a config file.
            'sp_certificate' => '',
            'sp_private_key' => '',
            'sp_certificate_file' => $input->certificateFile,
            'sp_private_key_file' => $input->privateKeyFile,
            'sp_spid_level' => $input->level,
            'sp_contact_persons' => $input->contactPersons(),
            'hide_real_idps' => false,
        ];

        // Outside the local lab no test IdP is trusted. The official
        // Validator is configured by hand, and only for the acceptance window.
        if ($input->deployment !== 'local') {
            $values['test_idp'] = false;
            $values['validator_idp'] = false;
        }

        return $values;
    }

    protected function environment(InstallInput $input): Change
    {
        $variables = [
            'FILAMENT_SPID_PANEL' => $input->panel,
            'SPID_AUTO_CREATE_USERS' => $input->autoCreateUsers ? 'true' : 'false',
            'FILAMENT_SPID_SP_PROFILE' => $input->profile,
            'FILAMENT_SPID_DEPLOYMENT' => $input->deployment,
        ];
        $path = app()->environmentFilePath();
        $lines = implode("\n", array_map(fn (string $key, string $value) => "{$key}={$value}", array_keys($variables), $variables));

        if (! $this->files->exists($path)) {
            return new Change(Change::MANUAL, '.env', 'no .env file: set these environment variables', $lines);
        }

        $current = $this->files->get($path);
        $edited = (new EnvEditor)->edit($current, $variables);

        return $edited === $current
            ? new Change(Change::UNCHANGED, '.env', 'panel, provisioning, SP profile and deployment')
            : new Change(Change::UPDATE, '.env', 'panel, provisioning, SP profile and deployment', $lines, fn () => $this->files->put($path, $edited));
    }

    protected function migration(): Change
    {
        $existing = $this->files->glob(database_path('migrations/*_add_spid_fields_to_users_table.php'));

        if ($existing !== []) {
            return new Change(Change::UNCHANGED, 'database/migrations', 'add_spid_fields_to_users_table already published');
        }

        $paths = array_filter(ServiceProvider::pathsToPublish(FilamentSpidServiceProvider::class, 'filament-spid-migrations'), is_string(...));

        return new Change(Change::CREATE, 'database/migrations', 'add_spid_fields_to_users_table (fiscal_code, spid_data; existing columns are kept)', null, function () use ($paths) {
            foreach ($paths as $from => $to) {
                $this->files->ensureDirectoryExists(dirname($to));
                $this->files->copy((string) $from, $to);
            }
        });
    }

    protected function panelProvider(InstallInput $input): Change
    {
        $snippet = "->login()\n".PanelProviderEditor::SNIPPET;

        foreach ($this->phpFiles(app_path()) as $file) {
            $source = $this->files->get($file);
            if (! str_contains($source, 'Panel')) {
                continue;
            }

            $edit = (new PanelProviderEditor)->edit($source, $input->panel);
            if (! $edit->recognised) {
                continue;
            }

            return $edit->source === $source
                ? new Change(Change::UNCHANGED, $this->relative($file), 'SpidPlugin already registered')
                : new Change(Change::UPDATE, $this->relative($file), "register SpidPlugin on panel [{$input->panel}], after ->login()", '->plugin(SpidPlugin::make())', fn () => $this->files->put($file, $edit->source));
        }

        return new Change(Change::MANUAL, "panel [{$input->panel}] provider", 'no panel provider this installer can edit safely: add the plugin after ->login()', $snippet);
    }

    protected function csrf(): Change
    {
        $acs = trim(TypedConfig::string('spid-auth.routes_prefix', 'spid') ?? 'spid', '/').'/acs';
        $path = base_path('bootstrap/app.php');
        $snippet = CsrfExceptionEditor::snippet($acs);

        if (! $this->files->exists($path)) {
            return new Change(Change::MANUAL, 'bootstrap/app.php', 'exempt only the ACS from CSRF verification', $snippet);
        }

        $source = $this->files->get($path);
        $edit = (new CsrfExceptionEditor)->edit($source, $acs);

        if (! $edit->recognised) {
            return new Change(Change::MANUAL, 'bootstrap/app.php', "{$edit->reason}; exempt only the ACS", "'{$acs}'  // in: {$snippet}");
        }

        return $edit->source === $source
            ? new Change(Change::UNCHANGED, 'bootstrap/app.php', "{$acs} exempt from CSRF verification")
            : new Change(Change::UPDATE, 'bootstrap/app.php', "exempt only {$acs} from CSRF verification", $snippet, fn () => $this->files->put($path, $edit->source));
    }

    /**
     * @return list<Change>
     */
    protected function manualSteps(InstallInput $input): array
    {
        $steps = [];

        if ($input->profile === 'private') {
            $steps[] = new Change(Change::MANUAL, 'config/spid-auth.php', 'private SPs also declare a billing contact (CessionarioCommittente) in sp_contact_persons: the installer does not invent invoicing data', "'billing' => ['Private' => true, 'EmailAddress' => '...', 'CessionarioCommittente' => [...]]");
        }

        if ($input->deployment === 'acceptance') {
            $steps[] = new Change(Change::MANUAL, 'config/spid-auth.php', 'for the AgID acceptance test, configure validator_idp from '.self::VALIDATOR_METADATA.' (entityId, SSO/SLO endpoints, certificate), only for the time of the test, then set it back to false', null);
        }

        if (KeyPairFiles::isExposed($input->privateKeyFile)) {
            $steps[] = new Change(Change::MANUAL, $input->privateKeyFile, 'the private key is readable by other users', 'chmod 600 '.$input->privateKeyFile);
        }

        if (app()->configurationIsCached()) {
            $steps[] = new Change(Change::MANUAL, 'bootstrap/cache/config.php', 'the configuration is cached: rebuild it to pick up these changes', 'php artisan config:cache');
        }

        return $steps;
    }

    /**
     * @return list<string>
     */
    protected function phpFiles(string $directory): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $files = array_map(fn ($file) => $file->getPathname(), array_filter($this->files->allFiles($directory), fn ($file) => $file->getExtension() === 'php'));
        sort($files);

        return $files;
    }

    protected function relative(string $path): string
    {
        // A temporary directory may be reached through a symlink
        // (/var -> /private/var on macOS): compare the real paths too.
        $directory = realpath(dirname($path));
        $candidates = array_filter([$path, $directory === false ? null : $directory.'/'.basename($path)]);
        $bases = array_filter([base_path(), realpath(base_path())]);

        foreach ($candidates as $candidate) {
            foreach ($bases as $base) {
                if (str_starts_with($candidate, $base.'/')) {
                    return substr($candidate, strlen($base) + 1);
                }
            }
        }

        return $path;
    }
}
