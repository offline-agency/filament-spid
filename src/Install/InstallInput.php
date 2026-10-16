<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install;

use OfflineAgency\FilamentSpid\Constants\SpidLevel;

/**
 * What filament-spid:install needs to know, validated as a whole before
 * anything is written. Secrets are only ever paths: the input never carries
 * key material.
 *
 * Schema (schema_version 1), as in examples/setup.public.json:
 * panel, deployment (local|acceptance|production), sp.profile (public|private),
 * sp.entity_id, sp.base_url, sp.service_name, sp.organization.{name,
 * display_name, url}, sp.contact.{email, phone?, ipa_code (public),
 * vat_number (private), fiscal_code?}, sp.attributes, sp.level?,
 * sp.certificate_file, sp.private_key_file, provisioning.auto_create_users.
 */
final class InstallInput
{
    public const SCHEMA_VERSION = 1;

    public const DEPLOYMENTS = ['local', 'acceptance', 'production'];

    public const PROFILES = ['public', 'private'];

    /** SPID attributes an SP can request (SPID technical rules). */
    public const ATTRIBUTES = [
        'spidCode', 'name', 'familyName', 'placeOfBirth', 'countyOfBirth', 'dateOfBirth', 'gender',
        'companyName', 'registeredOffice', 'fiscalNumber', 'ivaCode', 'idCard', 'mobilePhone',
        'email', 'address', 'expirationDate', 'digitalAddress',
    ];

    /**
     * @param  list<string>  $attributes
     */
    public function __construct(
        public readonly string $panel,
        public readonly string $deployment,
        public readonly string $profile,
        public readonly string $entityId,
        public readonly string $baseUrl,
        public readonly string $serviceName,
        public readonly string $organizationName,
        public readonly string $organizationDisplayName,
        public readonly string $organizationUrl,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $ipaCode,
        public readonly ?string $vatNumber,
        public readonly ?string $fiscalCode,
        public readonly array $attributes,
        public readonly string $level,
        public readonly string $certificateFile,
        public readonly string $privateKeyFile,
        public readonly bool $autoCreateUsers,
    ) {}

    /**
     * @param  array<mixed>  $data  decoded JSON, or answers in the same shape
     *
     * @throws InvalidInstallInput
     */
    public static function fromArray(array $data): self
    {
        $errors = [];
        $get = function (string $path) use ($data): mixed {
            $value = $data;
            foreach (explode('.', $path) as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }

            return $value;
        };
        $string = function (string $path, bool $required = true) use ($get, &$errors): ?string {
            $value = $get($path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if ($required) {
                $errors[] = "{$path} is required.";
            }

            return null;
        };
        $https = function (string $path) use ($string, &$errors): string {
            $value = $string($path) ?? '';
            if ($value !== '' && ! preg_match('#^https://[^/\s]+#', $value)) {
                $errors[] = "{$path} must be an https:// URL.";
            }

            return $value;
        };

        if ($get('schema_version') !== self::SCHEMA_VERSION) {
            $errors[] = 'schema_version must be '.self::SCHEMA_VERSION.'.';
        }

        $panel = $string('panel') ?? '';
        $deployment = $string('deployment') ?? '';
        if ($deployment !== '' && ! in_array($deployment, self::DEPLOYMENTS, true)) {
            $errors[] = 'deployment must be one of '.implode(', ', self::DEPLOYMENTS).'.';
        }

        $profile = $string('sp.profile') ?? '';
        if ($profile !== '' && ! in_array($profile, self::PROFILES, true)) {
            $errors[] = 'sp.profile must be public or private: aggregators and operators (gestori) are not supported by this installer.';
        }

        $ipaCode = $string('sp.contact.ipa_code', $profile === 'public');
        $vatNumber = $string('sp.contact.vat_number', $profile === 'private');

        $attributes = $get('sp.attributes') ?? ['fiscalNumber', 'name', 'familyName', 'email'];
        if (! is_array($attributes) || ! array_is_list($attributes) || $attributes === []) {
            $errors[] = 'sp.attributes must be a list of SPID attribute names.';
            $attributes = [];
        }
        $attributes = array_map(fn (mixed $attribute): string => is_string($attribute) ? $attribute : '', $attributes);
        foreach (array_diff($attributes, self::ATTRIBUTES) as $unknown) {
            $errors[] = "sp.attributes: {$unknown} is not a SPID attribute.";
        }
        if ($attributes !== [] && ! in_array('fiscalNumber', $attributes, true)) {
            $errors[] = 'sp.attributes must include fiscalNumber: accounts are matched by fiscal code.';
        }

        $level = $string('sp.level', false) ?? SpidLevel::LEVEL_2->value;
        $minimum = SpidLevel::minimum();
        $requested = SpidLevel::tryFrom($level);
        if ($requested === null || ($minimum !== null && ! $requested->meets($minimum))) {
            $errors[] = 'sp.level must be a SPID level URI at least filament-spid.minimum_level (SpidL2 by default).';
        }

        $autoCreate = $get('provisioning.auto_create_users') ?? false;
        if (! is_bool($autoCreate)) {
            $errors[] = 'provisioning.auto_create_users must be true or false.';
            $autoCreate = false;
        }

        $certificateFile = $string('sp.certificate_file') ?? '';
        $privateKeyFile = $string('sp.private_key_file') ?? '';
        $errors = array_merge($errors, KeyPairFiles::validate($certificateFile, $privateKeyFile));

        $input = new self(
            panel: $panel,
            deployment: $deployment,
            profile: $profile,
            entityId: $https('sp.entity_id'),
            baseUrl: $https('sp.base_url'),
            serviceName: $string('sp.service_name') ?? '',
            organizationName: $string('sp.organization.name') ?? '',
            organizationDisplayName: $string('sp.organization.display_name') ?? '',
            organizationUrl: $https('sp.organization.url'),
            email: $string('sp.contact.email') ?? '',
            phone: $string('sp.contact.phone', false),
            ipaCode: $ipaCode,
            vatNumber: $vatNumber,
            fiscalCode: $string('sp.contact.fiscal_code', false),
            attributes: $attributes,
            level: $level,
            certificateFile: $certificateFile,
            privateKeyFile: $privateKeyFile,
            autoCreateUsers: $autoCreate,
        );

        if ($errors !== []) {
            throw new InvalidInstallInput($errors);
        }

        return $input;
    }

    /**
     * The contact persons italia/spid-laravel validates: IPACode for public
     * SPs, VATNumber for private ones, an email for each.
     *
     * @return array<string, array<string, bool|string>>
     */
    public function contactPersons(): array
    {
        $contact = $this->profile === 'public'
            ? ['Public' => true, 'IPACode' => (string) $this->ipaCode]
            : array_filter(['Private' => true, 'VATNumber' => (string) $this->vatNumber, 'FiscalCode' => $this->fiscalCode], fn ($value) => $value !== null);

        $contact['EmailAddress'] = $this->email;
        if ($this->phone !== null) {
            $contact['TelephoneNumber'] = $this->phone;
        }

        return ['other' => $contact];
    }
}
