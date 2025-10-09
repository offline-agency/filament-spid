@props(['size' => 'l'])

<div class="spid-button-wrapper" style="display: flex; justify-content: center; align-items: center; width: 100%;">
    <!-- Official SPID Button Styles -->
    <link rel="stylesheet" href="{{ asset('/vendor/spid-auth/css/spid-sp-access-button.min.css') }}">
    
    <form id="spid_idp_access" name="spid_idp_access" action="{{ route('spid-auth_do-login') }}" method="post" style="display: flex; justify-content: center; align-items: center; flex-direction: column;">
        @csrf
        <input id="spid_idp_access_provider" type="hidden" name="provider" value="" />
        
        <a href="#" class="italia-it-button italia-it-button-size-{{ $size }} button-spid" spid-idp-button="#spid-idp-button-{{ $size }}-post" aria-haspopup="true" aria-expanded="false" style="margin: 0 auto; display: inline-block;">
            <span class="italia-it-button-icon">
                <img src="{{ asset('/vendor/spid-auth/img/spid-ico-circle-bb.svg') }}" 
                     onerror="this.src='{{ asset('/vendor/spid-auth/img/spid-ico-circle-bb.png') }}'; this.onerror=null;" 
                     alt="" />
            </span>
            <span class="italia-it-button-text">Entra con SPID</span>
        </a>
        
        <div id="spid-idp-button-{{ $size }}-post" class="spid-idp-button spid-idp-button-tip spid-idp-button-relative">
            <ul id="spid-idp-list-{{ $size }}-root-post" class="spid-idp-button-menu">
                @unless(config('spid-auth.hide_real_idps'))
                @foreach (config('spid-idps') as $idp => $idpData)
                @if ($idpData['real'] && $idpData['isActive'])
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
                             onerror="this.src='{{ asset('/vendor/spid-auth/img/spid-idp-test.png') }}'; this.onerror=null;" 
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
                             onerror="this.src='{{ asset('/vendor/spid-auth/img/spid-validator.png') }}'; this.onerror=null;" 
                             alt="Validator" />
                    </button>
                </li>
                @endif
                
                <li class="spid-idp-support-link" data-spidlink="info">
                    <a href="https://www.spid.gov.it">Maggiori informazioni</a>
                </li>
                <li class="spid-idp-support-link" data-spidlink="rich">
                    <a href="https://www.spid.gov.it/richiedi-spid">Non hai SPID?</a>
                </li>
                <li class="spid-idp-support-link" data-spidlink="help">
                    <a href="https://www.spid.gov.it/serve-aiuto">Serve aiuto?</a>
                </li>
            </ul>
        </div>
    </form>
    
    <!-- Load jQuery if not already loaded -->
    <script>
        if (typeof jQuery === 'undefined') {
            document.write('<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>');
        }
    </script>
    
    <!-- Official SPID Button Scripts -->
    <script src="{{ asset('/vendor/spid-auth/js/spid-sp-access-button.min.js') }}"></script>
    <script type="text/javascript">
        (function() {
            // Wait for jQuery to be available
            function initSpid() {
                if (typeof jQuery === 'undefined') {
                    setTimeout(initSpid, 100);
                    return;
                }
                
                jQuery('.spid-idp-button-link').click(function(event) {
                    jQuery('#spid_idp_access_provider').val(jQuery(event.currentTarget).data('idp'));
                });

                jQuery(document).ready(function(){
                    var rootList = jQuery(".spid-idp-button-menu").first();
                    var idpList = rootList.children(".spid-idp-button-link").get();
                    var lnkList = rootList.children(".spid-idp-support-link");
                    
                    // Shuffle IdPs
                    for (var i = idpList.length - 1; i > 0; i--) {
                        var j = Math.floor(Math.random() * (i + 1));
                        rootList.append(idpList[j]);
                    }
                    rootList.append(lnkList);
                });
            }
            
            initSpid();
        })();
    </script>
</div>

