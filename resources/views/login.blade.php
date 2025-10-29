<x-filament-panels::page.simple>
    <x-slot name="heading">
        <div class="text-center">
            {{ __('filament-spid::spid.login_with_spid') }}
        </div>
    </x-slot>

    <x-slot name="subheading">
        {{ __('filament-spid::spid.select_provider') }}
    </x-slot>

    @if (session('error'))
        <div class="rounded-md bg-red-50 p-4 mb-6">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">
                        {{ session('error') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-8">
        <!-- Filament-compatible SPID Button -->
        <div class="flex justify-center items-center w-full">
            <div class="mx-auto">
                @include('filament-spid::components.spid-button-filament', ['size' => 'l'])
            </div>
        </div>

        <!-- SPID Information Links -->
        <div class="mt-5 text-center">
            <a href="https://www.spid.gov.it" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 text-sm font-bold">
                {{ __('filament-spid::spid.more_info') }}
            </a>
            <br>
            <a href="https://www.spid.gov.it/cos-e-spid/come-attivare-spid/" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 text-sm font-bold">
                {{ __('filament-spid::spid.no_spid') }}
            </a>
            <br>
            <a href="https://www.spid.gov.it/serve-aiuto" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 text-sm font-bold">
                {{ __('filament-spid::spid.need_help') }}
            </a>
            <br>
            
            <!-- SPID-AGID Logo -->
            <div class="flex justify-center" style="margin-top: 5px;">
                <img 
                    alt="SPID AGID" 
                    class="max-w-full h-auto mx-auto" 
                    src="https://cdn.inpa.gov.it/concorsismart/9.11.1-GA/assets/img/authentication/spid-agid.png"
                    style="max-width: 300px;"
                >
            </div>
        </div>

        <!-- SPID Information -->
        <div class="text-center w-full">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('filament-spid::spid.info_text') }}
            </p>
        </div>
    </div>
</x-filament-panels::page.simple>
