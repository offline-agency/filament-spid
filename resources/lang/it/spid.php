<?php

return [
    'login_with_spid' => 'Entra con SPID',
    'select_provider' => 'Seleziona il tuo provider SPID',
    'provider_required' => 'Devi selezionare un provider SPID',
    'login_error' => 'Errore durante il login SPID',
    'authentication_failed' => 'Autenticazione SPID fallita',
    'acs_error' => 'Errore durante la gestione della risposta SPID',
    'standard_login' => 'Accedi con credenziali',
    'info_text' => 'Accedi con la tua identità digitale SPID',
    'more_info' => 'Maggiori informazioni su SPID',
    'no_spid' => 'Non hai SPID?',
    'need_help' => 'Serve aiuto?',
    'logout' => 'Esci da SPID',

    // Codici anomalia standard SPID (19–25)
    'error_19' => 'Autenticazione annullata dall\'utente.',
    'error_20' => 'Autenticazione annullata per un errore tecnico.',
    'error_21' => 'Timeout dell\'autenticazione. Riprova.',
    'error_22' => 'La tua identità SPID non è ancora attiva.',
    'error_23' => 'La tua identità SPID è sospesa o bloccata.',
    'error_25' => 'Operazione non consentita.',

    // Errori a livello SAML
    'saml_validation_error'           => 'La risposta di autenticazione non è valida.',
    'saml_response_already_processed' => 'Questa risposta di autenticazione è già stata utilizzata.',
    'saml_authentication_error'       => 'Il provider di identità ha rifiutato la richiesta.',
    'saml_request_id_missing'         => 'Sessione di autenticazione persa. Riprova.',
    'saml_malformed_idp'              => 'Provider di identità non valido.',
    'saml_nonexistent_idp'            => 'Il provider di identità selezionato non esiste.',
];
