<?php

namespace OfflineAgency\FilamentSpid\Services;

use DOMDocument;
use DOMElement;
use Italia\SPIDAuth\Exceptions\SPIDLoginException;
use Italia\SPIDAuth\SPIDAuth as BaseSpidAuth;
use OneLogin\Saml2\Utils;

/**
 * Complete missing Issuer and subject-expiry checks in spid-laravel 2.1.0-beta.
 * The parent runs after php-saml's signature, schema and protocol validation.
 */
class SpidAuth extends BaseSpidAuth
{
    protected function validateLoginResponse(string $lastResponseXML, string $lastRequestIssueInstant): void
    {
        $document = new DOMDocument;
        Utils::loadXML($document, $lastResponseXML);
        if (Utils::query($document, '/samlp:Response/saml:Issuer')->length === 0) {
            throw new SPIDLoginException('SAML response validation error: missing Response Issuer element', SPIDLoginException::SAML_VALIDATION_ERROR);
        }

        parent::validateLoginResponse($lastResponseXML, $lastRequestIssueInstant);

        /** @var DOMElement $confirmation */
        $confirmation = Utils::query($document, '/samlp:Response/saml:Assertion/saml:Subject/saml:SubjectConfirmation/saml:SubjectConfirmationData')->item(0);
        if (trim($confirmation->getAttribute('NotOnOrAfter')) === '') {
            throw new SPIDLoginException('SAML response validation error: empty or missing SubjectConfirmationData NotOnOrAfter attribute', SPIDLoginException::SAML_VALIDATION_ERROR);
        }
    }
}
