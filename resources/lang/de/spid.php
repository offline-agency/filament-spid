<?php

return [
    'login_with_spid' => 'Mit SPID anmelden',
    'select_provider' => 'Wählen Sie Ihren SPID-Anbieter',
    'provider_required' => 'Sie müssen einen SPID-Anbieter auswählen',
    'login_error' => 'Fehler bei der SPID-Anmeldung',
    'authentication_failed' => 'SPID-Authentifizierung fehlgeschlagen',
    'acs_error' => 'Fehler bei der Verarbeitung der SPID-Antwort',
    'standard_login' => 'Mit Anmeldedaten anmelden',
    'info_text' => 'Melden Sie sich mit Ihrer digitalen SPID-Identität an',
    'more_info' => 'Mehr Informationen über SPID',
    'no_spid' => 'Haben Sie kein SPID?',
    'need_help' => 'Brauchen Sie Hilfe?',
    'logout' => 'Von SPID abmelden',

    // SPID Standard-Anomalie-Codes (19–25)
    'error_19' => 'Authentifizierung vom Benutzer abgebrochen.',
    'error_20' => 'Authentifizierung aufgrund eines technischen Fehlers abgebrochen.',
    'error_21' => 'Authentifizierungs-Timeout. Bitte versuchen Sie es erneut.',
    'error_22' => 'Ihre SPID-Identität ist noch nicht aktiv.',
    'error_23' => 'Ihre SPID-Identität ist gesperrt oder ausgesetzt.',
    'error_25' => 'Dieser Vorgang ist nicht zulässig.',

    // SAML-Fehler
    'saml_validation_error'           => 'Die Authentifizierungsantwort ist ungültig.',
    'saml_response_already_processed' => 'Diese Authentifizierungsantwort wurde bereits verwendet.',
    'saml_authentication_error'       => 'Der Identitätsanbieter hat die Anfrage abgelehnt.',
    'saml_request_id_missing'         => 'Authentifizierungssitzung verloren. Bitte versuchen Sie es erneut.',
    'saml_malformed_idp'              => 'Ungültiger Identitätsanbieter ausgewählt.',
    'saml_nonexistent_idp'            => 'Der ausgewählte Identitätsanbieter existiert nicht.',
];
