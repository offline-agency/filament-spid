{{-- Styled by resources/dist/filament-spid.css (fi-spid-* classes): Filament 4
     and 5 compile only the Tailwind utilities their own views use. --}}
<x-filament-panels::page.simple>
    <x-slot name="heading">
        <div class="fi-spid-heading">
            {{ __('filament-spid::spid.login_with_spid') }}
        </div>
    </x-slot>

    <x-slot name="subheading">
        {{ __('filament-spid::spid.select_provider') }}
    </x-slot>

    @if (session('spid_error'))
        <div class="fi-spid-error" role="alert">
            <p class="fi-spid-error-text">
                {{ session('spid_error') }}
            </p>
        </div>
    @endif

    <div class="fi-spid-login">
        @if (\OfflineAgency\FilamentSpid\SpidPlugin::resolve()?->getShowSpidButton() ?? true)
        <div class="fi-spid-button">
            @include('filament-spid::components.spid-button-filament', ['size' => 'l'])
        </div>
        @endif

        <div class="fi-spid-info">
            <p class="fi-spid-info-text">
                {{ __('filament-spid::spid.info_text') }}
            </p>
        </div>

        {{-- The SPID AgID logo, served from the published package assets. --}}
        <div class="fi-spid-logo">
            <img
                alt="SPID AGID"
                src="{{ asset('vendor/filament-spid/images/spid-agid-logo.png') }}"
            >
        </div>

        {{-- Login with other credentials, when the plugin names a page for it. --}}
        @if ($credentialsLoginUrl = \OfflineAgency\FilamentSpid\SpidPlugin::resolve()?->getCredentialsLoginUrl())
            <div class="fi-spid-info">
                <a href="{{ $credentialsLoginUrl }}" class="fi-spid-credentials-link">
                    {{ __('filament-spid::spid.standard_login') }}
                </a>
            </div>
        @endif
    </div>
</x-filament-panels::page.simple>
