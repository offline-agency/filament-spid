<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use OfflineAgency\FilamentSpid\Install\Change;
use OfflineAgency\FilamentSpid\Install\Editors\SpidAuthConfig;
use OfflineAgency\FilamentSpid\Install\InstallInput;
use OfflineAgency\FilamentSpid\Install\InvalidInstallInput;
use OfflineAgency\FilamentSpid\Install\PanelLocator;
use OfflineAgency\FilamentSpid\Install\Planner;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

/**
 * Installs and configures filament-spid in one pass.
 *
 * Every input is validated before the first write; the plan is shown (and
 * confirmed when interactive) before it is applied. Running it again with
 * the same input changes nothing.
 */
class InstallCommand extends Command
{
    protected $signature = 'filament-spid:install
        {--panel= : Id of the Filament panel SPID users sign in to}
        {--input= : JSON file with the answers (see examples/setup.public.json)}
        {--dry-run : Show the plan without writing anything}
        {--migrate : Also run php artisan migrate}';

    protected $description = 'Install and configure SPID login for a Filament panel';

    public function handle(PanelLocator $panels, Planner $planner): int
    {
        try {
            $data = $this->answers($panels);
            $input = InstallInput::fromArray($data);
        } catch (InvalidInstallInput $e) {
            $this->error('Nothing was written. Fix the input:');
            foreach ($e->errors as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        $plan = $planner->plan($input, (bool) $this->option('migrate'), fn (string $command, array $arguments): int => $this->callSilently($command, $arguments));
        $this->renderPlan($plan);

        if ($this->option('dry-run')) {
            $this->renderManual($plan);
            $this->newLine();
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm('Apply these changes?', true)) {
            $this->warn('Nothing was applied.');

            return self::FAILURE;
        }

        $applied = [];
        foreach ($plan as $change) {
            if (! $change->writes()) {
                continue;
            }

            try {
                $change->apply();
                $applied[] = $change;
            } catch (\Throwable $e) {
                $this->error("Failed: {$change->target}, {$change->description} (".$e::class.').');
                $this->line('Applied before the failure: '.($applied === [] ? 'nothing' : implode(', ', array_map(fn (Change $c) => $c->target, $applied))).'.');
                $this->line('Fix the cause and run the same command again: completed steps are detected and skipped. Migrations and other external effects are not rolled back.');

                return self::FAILURE;
            }
        }

        return $this->summary($input, $plan);
    }

    /**
     * The answers, from --input or the prompts, with the panel resolved.
     *
     * @return array<mixed>
     *
     * @throws InvalidInstallInput
     */
    protected function answers(PanelLocator $panels): array
    {
        $file = $this->option('input');

        if (is_string($file) && $file !== '') {
            if (! is_file($file) || ! is_readable($file)) {
                throw new InvalidInstallInput(["--input: {$file} is not a readable file."]);
            }
            $data = json_decode((string) file_get_contents($file), true);
            if (! is_array($data)) {
                throw new InvalidInstallInput(["--input: {$file} is not a JSON object."]);
            }
        } elseif ($this->input->isInteractive()) {
            $data = $this->promptAnswers();
        } else {
            throw new InvalidInstallInput(['Pass --input=setup.json (see examples/setup.public.json), or run without --no-interaction to answer the questions.']);
        }

        $data['panel'] = $this->panel($panels, $data['panel'] ?? null);

        return $data;
    }

    /**
     * @throws InvalidInstallInput
     */
    protected function panel(PanelLocator $panels, mixed $fromInput): string
    {
        $ids = $panels->ids();
        $option = $this->option('panel');
        $wanted = is_string($option) && $option !== '' ? $option : (is_string($fromInput) ? $fromInput : null);

        if ($ids === []) {
            throw new InvalidInstallInput(['No Filament panel is registered. Create one with php artisan filament:install --panels, then run this command again.']);
        }

        if (is_string($wanted)) {
            if (! in_array($wanted, $ids, true)) {
                throw new InvalidInstallInput(["Panel [{$wanted}] is not registered. Registered: ".implode(', ', $ids).'.']);
            }

            return $wanted;
        }

        if (count($ids) === 1) {
            return $ids[0];
        }

        if (! $this->input->isInteractive()) {
            throw new InvalidInstallInput(['Several panels are registered ('.implode(', ', $ids).'): choose one with --panel.']);
        }

        return $this->text($this->choice('Panel SPID users sign in to', $ids));
    }

    /**
     * The interactive questions, offering what is already configured.
     *
     * @return array<string, mixed>
     */
    protected function promptAnswers(): array
    {
        $configured = fn (string $key): ?string => $this->configured($key);
        $profile = $this->text($this->choice('SP profile', InstallInput::PROFILES, config('filament-spid.sp_profile') === 'private' ? 1 : 0));
        $deployment = $this->text($this->choice('Deployment', InstallInput::DEPLOYMENTS, (int) array_search(config('filament-spid.deployment') ?? 'local', InstallInput::DEPLOYMENTS, true)));

        $contact = [];
        $sp = [
            'profile' => $profile,
            'entity_id' => $this->ask('SP entityID (an HTTPS URL that identifies your service)', $configured('sp_entity_id')),
            'base_url' => $this->ask('Public base URL of the application (HTTPS)', $configured('sp_base_url')),
            'service_name' => $this->ask('Service name shown to citizens', $configured('sp_service_name')),
            'organization' => [
                'name' => $this->ask('Legal name of the organisation (the SP, not the developer)', $configured('sp_organization_name')),
                'display_name' => $this->ask('Display name of the organisation', $configured('sp_organization_display_name')),
                'url' => $this->ask('Organisation website (HTTPS)', $configured('sp_organization_url')),
            ],
        ];
        $contact['email'] = $this->ask('Contact email of the SP');
        if ($profile === 'public') {
            $contact['ipa_code'] = $this->ask('IPA code of the public administration');
        } else {
            $contact['vat_number'] = $this->ask('VAT number of the SP (e.g. IT12345678901)');
            $contact['fiscal_code'] = $this->ask('Fiscal code of the SP (optional)');
        }
        $sp['contact'] = $contact;
        $sp['attributes'] = array_values(array_filter(array_map('trim', explode(',', $this->text($this->ask('SPID attributes to request (comma separated)', 'fiscalNumber,name,familyName,email'))))));
        $sp['certificate_file'] = $this->ask('Path of the SP signing certificate (PEM)', $configured('sp_certificate_file'));
        $sp['private_key_file'] = $this->ask('Path of the SP private key (PEM, kept outside public/)', $configured('sp_private_key_file'));

        return [
            'schema_version' => InstallInput::SCHEMA_VERSION,
            'deployment' => $deployment,
            'sp' => $sp,
            'provisioning' => [
                'auto_create_users' => $this->confirm('Create an account for every SPID citizen without one (auto_create_users)?', false),
            ],
        ];
    }

    protected function text(mixed $answer): string
    {
        return is_string($answer) ? $answer : '';
    }

    /**
     * A spid-auth value the application set, ignoring the library's examples.
     */
    protected function configured(string $key): ?string
    {
        $value = TypedConfig::string("spid-auth.{$key}");
        $default = SpidAuthConfig::libraryDefaults()[$key] ?? null;

        return $value !== null && $value !== '' && $value !== $default ? $value : null;
    }

    /**
     * @param  list<Change>  $plan
     */
    protected function renderPlan(array $plan): void
    {
        $this->line('Plan:');
        foreach ($plan as $change) {
            if ($change->status === Change::MANUAL) {
                continue;
            }
            $this->line(sprintf('  [%s] %s: %s', $change->status, $change->target, $change->description));
            if ($change->detail !== null) {
                $this->line('      + '.str_replace("\n", "\n      + ", $change->detail));
            }
        }
    }

    /**
     * @param  list<Change>  $plan
     */
    protected function summary(InstallInput $input, array $plan): int
    {
        $manual = array_values(array_filter($plan, fn (Change $change) => $change->status === Change::MANUAL));
        $panel = filament()->getPanels()[$input->panel] ?? null;

        $this->newLine();
        $this->info('Done.');
        $this->line('  Login:    '.($panel?->getLoginUrl() ?? 'panel not resolved'));
        $this->line('  Metadata: '.(Route::has('spid-auth_metadata') ? route('spid-auth_metadata') : 'route spid-auth_metadata not registered'));
        $this->line('  ACS:      '.(Route::has('spid-auth_acs') ? route('spid-auth_acs') : 'route spid-auth_acs not registered'));
        $this->line('  Profile:  '.$input->profile.', deployment '.$input->deployment.', auto_create_users '.($input->autoCreateUsers ? 'on' : 'off'));
        $this->newLine();
        $this->line('Next:');
        if (! $this->option('migrate')) {
            $this->line('  php artisan migrate');
        }
        $this->line('  php artisan filament-spid:doctor');
        $this->line('  php artisan filament-spid:metadata:validate --url='.(Route::has('spid-auth_metadata') ? route('spid-auth_metadata') : '<metadata URL>'));

        $this->renderManual($plan);

        return $manual === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<Change>  $plan
     */
    protected function renderManual(array $plan): void
    {
        $manual = array_values(array_filter($plan, fn (Change $change) => $change->status === Change::MANUAL));
        if ($manual === []) {
            return;
        }

        $this->newLine();
        $this->warn('Manual steps left (the installation is not complete until they are done):');
        foreach ($manual as $change) {
            $this->line("  - {$change->target}: {$change->description}");
            if ($change->detail !== null) {
                $this->line('      '.str_replace("\n", "\n      ", $change->detail));
            }
        }
    }
}
