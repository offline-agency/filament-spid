<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class SpidUserData implements Arrayable, Jsonable
{
    /**
     * SPID attributes read from a SPIDUser object.
     *
     * @var list<string>
     */
    protected const ATTRIBUTES = [
        'fiscalNumber',
        'name',
        'familyName',
        'email',
        'spidCode',
        'placeOfBirth',
        'dateOfBirth',
        'gender',
    ];

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
            fiscalNumber: $spidUser['fiscalNumber'] ?? throw new \InvalidArgumentException('fiscalNumber is required'),
            name: $spidUser['name'] ?? '',
            familyName: $spidUser['familyName'] ?? '',
            email: $spidUser['email'] ?? null,
            spidCode: $spidUser['spidCode'] ?? null,
            placeOfBirth: $spidUser['placeOfBirth'] ?? null,
            dateOfBirth: $spidUser['dateOfBirth'] ?? null,
            gender: $spidUser['gender'] ?? null,
            rawData: $spidUser,
        );
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
        return json_encode($this->toArray(), $options);
    }
}
