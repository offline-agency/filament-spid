# Run in the root of a Laravel 12 or 13 application with a Filament panel.

# 1. italia/spid-laravel with the SPID patch for php-saml ^4.3.1: until
#    upstream tags a release, the offline-agency fork at a pinned commit.
composer config repositories.spid-laravel vcs https://github.com/offline-agency/spid-laravel
composer config allow-plugins.cweagans/composer-patches true
composer config extra.enable-patching true --json
composer require "italia/spid-laravel:dev-feat/laravel-13#b8dd1a484d8e6551a1a84e90220c2e9dc0d0a0fe as 2.1.0-beta" --no-update

# 2. The plugin.
composer require offline-agency/filament-spid:^1.0

# 3. Install and configure from setup.json (copy examples/setup.public.json or
#    setup.private.json from the package and fill in your SP's real data and
#    key pair paths). --dry-run shows the plan and writes nothing.
php artisan filament-spid:install --panel=admin --input=setup.json --dry-run
php artisan filament-spid:install --panel=admin --input=setup.json

# 4. Check.
php artisan migrate
php artisan filament-spid:doctor
