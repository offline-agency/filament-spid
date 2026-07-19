<x-filament-panels::page.simple>
    <x-slot name="heading">
        <div class="text-center">
            {{ __('filament-spid::spid.login_with_spid') }}
        </div>
    </x-slot>

    <x-slot name="subheading">
        {{ __('filament-spid::spid.select_provider') }}
    </x-slot>

    @if (session('spid_error'))
        <div class="rounded-md bg-danger-50 p-4 mb-4 border border-danger-200 dark:bg-danger-950 dark:border-danger-800">
            <p class="text-sm font-medium text-danger-800 dark:text-danger-200">
                {{ session('spid_error') }}
            </p>
        </div>
    @endif

    <div class="space-y-8">
        <!-- Filament-compatible SPID Button -->
        @if (\OfflineAgency\FilamentSpid\SpidPlugin::resolve()?->getShowSpidButton() ?? true)
        <div class="flex justify-center items-center w-full">
            <div class="mx-auto">
                @include('filament-spid::components.spid-button-filament', ['size' => 'l'])
            </div>
        </div>
        @endif

        <!-- SPID Information -->
        <div class="text-center w-full">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('filament-spid::spid.info_text') }}
            </p>
        </div>

        <!-- SPID-AGID Logo, served from the published package assets -->
        <div class="spid-agid-logo flex justify-center">
            <img
                alt="SPID AGID"
                class="max-w-full h-auto mx-auto"
                src="{{ asset('vendor/filament-spid/images/spid-agid-logo.png') }}"
            >
        </div>

        <!-- Standard login fallback -->
        @if (filament()->hasLogin())
            <div class="text-center">
                <a href="{{ filament()->getLoginUrl() }}"
                   class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    {{ __('filament-spid::spid.standard_login') }}
                </a>
            </div>
        @endif
    </div>
</x-filament-panels::page.simple>
