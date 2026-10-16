<?php

use Italia\SPIDAuth\Exceptions\SPIDLoginException;
use Italia\SPIDAuth\SPIDAuth;

function subjectExpiryResponse(string $expiry): string
{
    return <<<XML
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Destination="https://test.local/spid/acs" InResponseTo="_request" IssueInstant="2026-10-16T10:00:00Z">
<saml:Issuer>https://idp.example.test</saml:Issuer>
<saml:Assertion Version="2.0" IssueInstant="2026-10-16T10:00:00Z">
<saml:Issuer Format="urn:oasis:names:tc:SAML:2.0:nameid-format:entity">https://idp.example.test</saml:Issuer>
<saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:transient" NameQualifier="https://idp.example.test">synthetic</saml:NameID>
<saml:SubjectConfirmation><saml:SubjectConfirmationData InResponseTo="_request" Recipient="https://test.local/spid/acs" {$expiry}/></saml:SubjectConfirmation></saml:Subject>
<saml:Conditions NotBefore="2026-10-16T10:00:00Z" NotOnOrAfter="2026-10-16T10:05:00Z"><saml:AudienceRestriction><saml:Audience>https://test.local</saml:Audience></saml:AudienceRestriction></saml:Conditions>
<saml:AuthnStatement><saml:AuthnContext><saml:AuthnContextClassRef>https://www.spid.gov.it/SpidL2</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement>
</saml:Assertion></samlp:Response>
XML;
}

it('rejects missing or empty subject expiry after the upstream SPID checks', function (string $expiry) {
    $auth = app(SPIDAuth::class);
    $method = new ReflectionMethod($auth, 'validateLoginResponse');
    expect(fn () => $method->invoke($auth, subjectExpiryResponse($expiry), '2026-10-16T10:00:00Z'))
        ->toThrow(SPIDLoginException::class, 'empty or missing SubjectConfirmationData NotOnOrAfter');
})->with(['missing' => '', 'empty' => 'NotOnOrAfter=""']);

it('retains valid subject expiry and the upstream validation', function () {
    $auth = app(SPIDAuth::class);
    expect($auth)->toBe(app('SPIDAuth'));
    $method = new ReflectionMethod($auth, 'validateLoginResponse');
    expect($method->invoke($auth, subjectExpiryResponse('NotOnOrAfter="2026-10-16T10:05:00Z"'), '2026-10-16T10:00:00Z'))->toBeNull();
    expect(fn () => $method->invoke($auth, subjectExpiryResponse('NotOnOrAfter="2026-10-16T10:05:00Z"'), ''))
        ->toThrow(SPIDLoginException::class, 'missing spid_lastRequestIssueInstant');
});

it('reports a missing Response Issuer as a validation refusal', function () {
    $auth = app(SPIDAuth::class);
    $xml = str_replace('<saml:Issuer>https://idp.example.test</saml:Issuer>', '', subjectExpiryResponse('NotOnOrAfter="2026-10-16T10:05:00Z"'));
    $method = new ReflectionMethod($auth, 'validateLoginResponse');
    expect(fn () => $method->invoke($auth, $xml, '2026-10-16T10:00:00Z'))
        ->toThrow(SPIDLoginException::class, 'missing Response Issuer element');
});
