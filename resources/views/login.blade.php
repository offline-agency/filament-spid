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

    <!-- Footer with AGID logo -->
    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div class="flex justify-center items-center">
            <!-- AGID Logo as image -->
            <div class="h-16 w-auto opacity-70 mx-auto flex items-center justify-center" style="max-width: 200px; height: 64px;">
                <img src="{{ asset('vendor/filament-spid/images/spid-agid-logo.png') }}" 
                     alt="AGID Logo" 
                     class="h-full w-auto object-contain">
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
