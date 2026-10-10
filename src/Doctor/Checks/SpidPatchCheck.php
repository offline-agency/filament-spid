<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use Closure;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Metadata;

/**
 * Whether the SPID patch reached php-saml, judged by what php-saml builds.
 *
 * Unpatched, php-saml hard-codes index="1" on the AttributeConsumingService
 * and never marks the AssertionConsumerService as default; SPID IdPs reject
 * such metadata. A PATCHES.txt file only says a patch was attempted.
 */
class SpidPatchCheck implements Check
{
    /** @var Closure(): string */
    protected Closure $metadata;

    /**
     * @param  (Closure(): string)|null  $metadata  builds SP metadata; null uses php-saml
     */
    public function __construct(?Closure $metadata = null)
    {
        $this->metadata = $metadata ?? fn (): string => Metadata::builder([
            'entityId' => 'https://doctor.invalid',
            'assertionConsumerService' => ['url' => 'https://doctor.invalid/acs', 'binding' => Constants::BINDING_HTTP_POST, 'index' => 0],
            'attributeConsumingService' => [
                'index' => 0,
                'serviceName' => 'filament-spid doctor',
                'serviceDescription' => '',
                'requestedAttributes' => [['name' => 'fiscalNumber', 'isRequired' => true]],
            ],
            'NameIDFormat' => Constants::NAMEID_TRANSIENT,
        ]);
    }

    public function run(): Result
    {
        try {
            $xml = ($this->metadata)();
        } catch (\Throwable $e) {
            return Result::warn('spid_patch', 'The SPID patch could not be verified: building metadata threw '.$e::class.'.', 'Check that vendor/onelogin/php-saml/PATCHES.txt lists "Compatibility with SPID" and that the root composer.json sets extra.enable-patching.');
        }

        if (str_contains($xml, 'AttributeConsumingService index="0"') && str_contains($xml, 'isDefault="true"')) {
            return Result::pass('spid_patch', 'php-saml builds SPID metadata: the SPID patch is applied.');
        }

        return Result::fail('spid_patch', 'php-saml builds metadata without the SPID changes: the SPID patch is not applied.', 'Set extra.enable-patching to true and allow cweagans/composer-patches in the root composer.json, then reinstall onelogin/php-saml (composer reinstall onelogin/php-saml).');
    }
}
