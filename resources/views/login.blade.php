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

        <!-- SPID Information -->
        <div class="text-center w-full">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('filament-spid::spid.info_text') }}
            </p>
        </div>
    </div>
</x-filament-panels::page.simple>
