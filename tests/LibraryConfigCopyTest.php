<?php

use Illuminate\Filesystem\Filesystem;
use OfflineAgency\FilamentSpid\FilamentSpidServiceProvider;

/**
 * FilamentSpidServiceProvider copies italia/spid-laravel's config into the
 * application on the first artisan run. These tests point the application
 * at a scratch directory holding a fake library config.
 */
beforeEach(function () {
    $this->root = sys_get_temp_dir().'/filament-spid-'.bin2hex(random_bytes(4));
    $library = $this->root.'/vendor/italia/spid-laravel/config';
    mkdir($library, 0777, true);
    mkdir($this->root.'/config');
    file_put_contents($library.'/spid-auth.php', "<?php return ['from' => 'library'];");
    file_put_contents($library.'/spid-idps.php', "<?php return ['from' => 'library'];");

    $this->app->setBasePath($this->root);
    $this->app->useConfigPath($this->root.'/config');
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

function bootSpidProvider(): void
{
    app()->getProvider(FilamentSpidServiceProvider::class)->packageBooted();
}

function runningInConsole(bool $console): void
{
    $property = new ReflectionProperty(app(), 'isRunningInConsole');
    $property->setValue(app(), $console);
}

it('copies the library config on an artisan run', function () {
    runningInConsole(true);

    bootSpidProvider();

    expect($this->root.'/config/spid-auth.php')->toBeFile()
        ->and($this->root.'/config/spid-idps.php')->toBeFile();
});

it('never overwrites a config the application already has', function () {
    runningInConsole(true);
    file_put_contents($this->root.'/config/spid-idps.php', "<?php return ['from' => 'app'];");

    bootSpidProvider();

    expect(file_get_contents($this->root.'/config/spid-idps.php'))->toContain("'app'");
});

it('does not write into config_path() while serving requests', function () {
    runningInConsole(false);

    bootSpidProvider();

    expect($this->root.'/config/spid-auth.php')->not->toBeFile()
        ->and($this->root.'/config/spid-idps.php')->not->toBeFile();
});

it('boots even when the copy fails', function () {
    // A read-only filesystem must not take the application down.
    runningInConsole(true);
    $this->app->instance(Filesystem::class, Mockery::mock(Filesystem::class, function ($mock) {
        $mock->shouldReceive('exists')->andThrow(new RuntimeException('read-only filesystem'));
    }));

    bootSpidProvider();

    expect($this->root.'/config/spid-auth.php')->not->toBeFile();
});
