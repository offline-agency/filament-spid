<?php

return [
    'login_with_spid' => 'Login with SPID',
    'select_provider' => 'Select your SPID provider',
    'provider_required' => 'You must select a SPID provider',
    'login_error' => 'Error during SPID login',
    'authentication_failed' => 'SPID authentication failed',
    'acs_error' => 'Error handling SPID response',
    'standard_login' => 'Login with credentials',
    'info_text' => 'Login with your SPID digital identity',
    'more_info' => 'More information about SPID',
    'no_spid' => 'Don\'t have SPID?',
    'need_help' => 'Need help?',
    'logout' => 'Logout from SPID',

    // SPID standard anomaly codes (19–25)
    'error_19' => 'Authentication canceled by the user.',
    'error_20' => 'Authentication canceled due to a technical error.',
    'error_21' => 'Authentication timed out. Please try again.',
    'error_22' => 'Your SPID identity is not yet active.',
    'error_23' => 'Your SPID identity is suspended or locked.',
    'error_25' => 'This operation is not allowed.',

    // SAML-level errors
    'saml_validation_error'           => 'The authentication response is invalid.',
    'saml_response_already_processed' => 'This authentication response has already been used.',
    'saml_authentication_error'       => 'The identity provider rejected the authentication request.',
    'saml_request_id_missing'         => 'Authentication session lost. Please try again.',
    'saml_malformed_idp'              => 'Invalid identity provider selected.',
    'saml_nonexistent_idp'            => 'The selected identity provider does not exist.',
];
