<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * @implements Arrayable<string, string|null>
 */
class SpidUserData implements Arrayable, Jsonable
{
    /**
     * The SPID attributes the package reads, from a SPIDUser object or a
     * field_mapping entry.
     *
     * @var list<string>
     */
    public const ATTRIBUTES = [
        'fiscalNumber',
        'name',
        'familyName',
        'email',
        'spidCode',
        'placeOfBirth',
        'dateOfBirth',
        'gender',
    ];

    /**
     * @param  array<string, mixed>|null  $rawData
     */
    public function __construct(
        public readonly string $fiscalNumber,
        public readonly string $name,
        public readonly string $familyName,
        public readonly ?string $email = null,
        public readonly ?string $spidCode = null,
        public readonly ?string $placeOfBirth = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $gender = null,
        public readonly ?array $rawData = null,
    ) {}

    /**
     * @param  array<string, mixed>|object  $spidUser  raw attributes, or the SPIDUser
     *                                                 shipped by italia/spid-laravel
     */
    public static function fromSpidAuth(array|object $spidUser): self
    {
        if (is_object($spidUser)) {
            $spidUser = self::attributesFromObject($spidUser);
        }

        return new self(
            fiscalNumber: self::string($spidUser, 'fiscalNumber') ?? throw new \InvalidArgumentException('fiscalNumber is required'),
            name: self::string($spidUser, 'name') ?? '',
            familyName: self::string($spidUser, 'familyName') ?? '',
            email: self::string($spidUser, 'email'),
            spidCode: self::string($spidUser, 'spidCode'),
            placeOfBirth: self::string($spidUser, 'placeOfBirth'),
            dateOfBirth: self::string($spidUser, 'dateOfBirth'),
            gender: self::string($spidUser, 'gender'),
            rawData: $spidUser,
        );
    }

    /**
     * One attribute, or null when it is missing or not a string.
     *
     * @param  array<string, mixed>  $spidUser
     */
    protected static function string(array $spidUser, string $attribute): ?string
    {
        $value = $spidUser[$attribute] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Read the SPID attributes off an object.
     *
     * SPIDUser keeps its attributes in a protected property and exposes them
     * through __get, so get_object_vars() would come back empty: each attribute
     * has to be read by name.
     *
     * @return array<string, mixed>
     */
    protected static function attributesFromObject(object $spidUser): array
    {
        $attributes = [];

        foreach (self::ATTRIBUTES as $attribute) {
            $value = $spidUser->{$attribute} ?? null;

            if ($value !== null) {
                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'fiscalNumber' => $this->fiscalNumber,
            'name' => $this->name,
            'familyName' => $this->familyName,
            'email' => $this->email,
            'spidCode' => $this->spidCode,
            'placeOfBirth' => $this->placeOfBirth,
            'dateOfBirth' => $this->dateOfBirth,
            'gender' => $this->gender,
        ];
    }

    public function toJson($options = 0): string
    {
        // Throw rather than return false, which the Jsonable contract does not allow.
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }
}
