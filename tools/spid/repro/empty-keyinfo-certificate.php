<?php

/*
 * Minimal reproduction (synthetic, no SPID data): php-saml + xmlseclibs reject
 * a correctly signed document whose ds:KeyInfo carries an empty
 * ds:X509Certificate, before they use the IdP certificate the SP configured.
 * spid_sp_test 1.2.18 signs its positive Responses (tests 1, 31, 95, 96, 109,
 * 110) with `xmlsec1 --sign --privkey-pem <key>` and no certificate, which
 * leaves exactly that empty element. See docs/testing/evidence.md.
 *
 * Usage (package root): php tools/spid/repro/empty-keyinfo-certificate.php
 * Exit 0 when the behaviour is reproduced, 1 when it is not.
 */

require __DIR__.'/../../../vendor/autoload.php';

use Composer\InstalledVersions;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$certificate = openssl_csr_sign(openssl_csr_new(['commonName' => 'idp.example.test'], $key), null, $key, 1);
openssl_x509_export($certificate, $certificatePem);
openssl_pkey_export($key, $keyPem);

$sign = function (bool $emptyCertificate) use ($keyPem, $certificatePem): string {
    $document = new DOMDocument;
    $document->loadXML('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ID="_r1" Version="2.0"><samlp:Status/></samlp:Response>');
    $signature = new XMLSecurityDSig;
    $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $signature->addReference($document->documentElement, XMLSecurityDSig::SHA256, ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N], ['id_name' => 'ID', 'overwrite' => false]);
    $privateKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
    $privateKey->loadKey($keyPem);
    $signature->sign($privateKey);
    $signature->add509Cert($emptyCertificate ? '' : $certificatePem, true, false);
    $signature->appendSignature($document->documentElement);

    if ($emptyCertificate) {
        // What xmlsec1 leaves when it signs without a certificate. KeyInfo is
        // outside the signed content, so the signature stays valid.
        $x509Data = $document->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'X509Data')->item(0);
        $x509Data?->appendChild($document->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509Certificate'));
    }

    return $document->saveXML();
};

$validate = function (string $xml) use ($certificatePem): string {
    $document = new DOMDocument;
    $document->loadXML($xml);
    try {
        return Utils::validateSign($document, $certificatePem) ? 'valid' : 'invalid';
    } catch (Throwable $e) {
        return get_class($e).': '.$e->getMessage();
    }
};

printf("onelogin/php-saml %s, robrichards/xmlseclibs %s, PHP %s\n", InstalledVersions::getPrettyVersion('onelogin/php-saml'), InstalledVersions::getPrettyVersion('robrichards/xmlseclibs'), PHP_VERSION);
$withCertificate = $validate($sign(false));
$withEmptyCertificate = @$validate($sign(true));
printf("signed, certificate in KeyInfo:       %s\n", $withCertificate);
printf("signed, empty X509Certificate:        %s\n", $withEmptyCertificate);

exit($withCertificate === 'valid' && $withEmptyCertificate !== 'valid' ? 0 : 1);
