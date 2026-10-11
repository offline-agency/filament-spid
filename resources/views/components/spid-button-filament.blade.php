@props(['size' => 'l'])

@php
    $plugin = \OfflineAgency\FilamentSpid\SpidPlugin::resolve();
    $buttonLabel = $plugin?->getSpidButtonLabel() ?? __('filament-spid::spid.login_with_spid');
    $buttonIcon = $plugin?->getSpidButtonIcon();
    $allowedProviders = $plugin?->getProviders() ?? [];
    // Unique per instance: several buttons can share a page.
    $uid = 'spid-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10));
@endphp

<div class="spid-button-wrapper"
     data-spid-button
     data-jquery-src="{{ \Filament\Support\Facades\FilamentAsset::getScriptSrc('spid-jquery', 'offline-agency/filament-spid') }}"
     data-button-src="{{ asset('vendor/spid-auth/js/spid-sp-access-button.min.js') }}">
    <!-- Official SPID Button Styles -->
    <link rel="stylesheet" href="{{ asset('/vendor/spid-auth/css/spid-sp-access-button.min.css') }}">
    
    <form id="{{ $uid }}-form" action="{{ route('spid-auth_do-login') }}" method="post">
        @csrf
        <input type="hidden" name="provider" value="" />

        <a href="#" role="button" class="italia-it-button italia-it-button-size-{{ $size }} button-spid" spid-idp-button="#{{ $uid }}-menu" aria-haspopup="true" aria-expanded="false" aria-controls="{{ $uid }}-menu">
            <span class="italia-it-button-icon">
                @if ($buttonIcon)
                    {{-- @svg comes from blade-icons, which every supported
                         Filament version depends on, unlike the x-filament::icon
                         component whose props changed between majors. --}}
                    @svg($buttonIcon, 'fi-spid-icon')
                @else
                    <img src="{{ asset('/vendor/spid-auth/img/spid-ico-circle-bb.svg') }}" alt="" />
                @endif
            </span>
            <span class="italia-it-button-text">{{ $buttonLabel }}</span>
        </a>
        
        <div id="{{ $uid }}-menu" class="spid-idp-button spid-idp-button-tip spid-idp-button-relative">
            <ul id="{{ $uid }}-list" class="spid-idp-button-menu" aria-label="{{ __('filament-spid::spid.select_provider') }}">
                @unless(config('spid-auth.hide_real_idps'))
                @foreach (config('spid-idps') as $idp => $idpData)
                @if ($idpData['real'] && $idpData['isActive'] && (empty($allowedProviders) || in_array($idp, $allowedProviders, true)))
                <li class="spid-idp-button-link" data-idp="{{ $idp }}">
                    <button class="idp-button-idp-logo" name="{{ $idpData['entityName'] }}" type="submit">
                        <span class="spid-sr-only">{{ $idpData['entityName'] }}</span>
                        <img class="spid-idp-button-logo" 
                             src="{{ asset('/vendor/spid-auth/img/' . $idpData['logo']) }}" 
                             alt="{{ $idpData['entityName'] }}" />
                    </button>
                </li>
                @endif
                @endforeach
                @endunless
                
                @if (config('spid-auth.test_idp'))
                <li class="spid-idp-button-link" data-idp="test">
                    <button class="idp-button-idp-logo" name="test" type="submit">
                        <span class="spid-sr-only">Test IdP</span>
                        <img class="spid-idp-button-logo" 
                             src="{{ asset('/vendor/spid-auth/img/spid-idp-test.svg') }}"
                             alt="Test IdP" />
                    </button>
                </li>
                @endif
                
                @if (config('spid-auth.validator_idp'))
                <li class="spid-idp-button-link" data-idp="validator">
                    <button class="idp-button-idp-logo" name="validator" type="submit">
                        <span class="spid-sr-only">Validator IdP</span>
                        <img class="spid-idp-button-logo" 
                             src="{{ asset('/vendor/spid-auth/img/spid-validator.svg') }}"
                             alt="Validator" />
                    </button>
                </li>
                @endif
                
                <li class="spid-idp-support-link" data-spidlink="info">
                    <a href="https://www.spid.gov.it">{{ __('filament-spid::spid.more_info') }}</a>
                </li>
                <li class="spid-idp-support-link" data-spidlink="rich">
                    <a href="https://www.spid.gov.it/richiedi-spid">{{ __('filament-spid::spid.no_spid') }}</a>
                </li>
                <li class="spid-idp-support-link" data-spidlink="help">
                    <a href="https://www.spid.gov.it/serve-aiuto">{{ __('filament-spid::spid.need_help') }}</a>
                </li>
            </ul>
        </div>
    </form>
    
    <p class="fi-spid-load-error" data-spid-load-error hidden>
        {{ __('filament-spid::spid.button_unavailable') }}
    </p>

    {{-- Behaviour (shuffle, provider choice, keyboard) and the jQuery/AgID
         script loading live in resources/dist/filament-spid-button.js, a
         Filament asset loaded on request: no inline script for a CSP to allow.
         It runs once per page and initialises each button once. --}}
    <script src="{{ \Filament\Support\Facades\FilamentAsset::getScriptSrc('filament-spid-button', 'offline-agency/filament-spid') }}" defer></script>
</div>
