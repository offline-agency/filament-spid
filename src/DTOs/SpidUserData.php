<?php
declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class SpidUserData implements Arrayable, Jsonable
{
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
    ) {
    }

    public static function fromSpidAuth(array $spidUser): self
    {
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


