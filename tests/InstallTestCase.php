<?php

namespace OfflineAgency\FilamentSpid\Tests;

use Illuminate\Filesystem\Filesystem;

/**
 * Runs the installer against a throwaway copy of the application skeleton,
 * so publish targets, config files, migrations and providers it writes land
 * in a directory deleted after each test.
 */
class InstallTestCase extends TestCase
{
    protected string $skeleton;

    protected function getApplicationBasePath()
    {
        $source = parent::getApplicationBasePath();
        $files = new Filesystem;
        $this->skeleton = sys_get_temp_dir().'/filament-spid-install-'.bin2hex(random_bytes(6));
        $files->ensureDirectoryExists($this->skeleton);

        foreach ($files->files($source, true) as $file) {
            $files->copy($file->getPathname(), $this->skeleton.'/'.$file->getFilename());
        }
        foreach ($files->directories($source) as $directory) {
            if (basename($directory) !== 'vendor') {
                $files->copyDirectory($directory, $this->skeleton.'/'.basename($directory));
            }
        }
        symlink(dirname(__DIR__).'/vendor', $this->skeleton.'/vendor');

        // What a Laravel 12/13 application ships, which the installer edits.
        $files->put($this->skeleton.'/bootstrap/app.php', (string) file_get_contents(__DIR__.'/Install/stubs/bootstrap-app.php'));
        $files->ensureDirectoryExists($this->skeleton.'/app/Providers/Filament');
        $files->put($this->skeleton.'/app/Providers/Filament/AdminPanelProvider.php', (string) file_get_contents(__DIR__.'/Install/stubs/AdminPanelProvider.php'));
        $files->put($this->skeleton.'/.env', "APP_NAME=Laravel\nAPP_ENV=testing\n");
        $files->deleteDirectory($this->skeleton.'/database/migrations');
        $files->ensureDirectoryExists($this->skeleton.'/database/migrations');
        foreach (['spid-auth.php', 'spid-idps.php', 'filament-spid.php'] as $config) {
            if ($files->exists($this->skeleton.'/config/'.$config)) {
                $files->delete($this->skeleton.'/config/'.$config);
            }
        }

        return $this->skeleton;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem)->deleteDirectory($this->skeleton);
    }
}
