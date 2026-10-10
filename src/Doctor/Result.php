<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor;

/**
 * The outcome of one doctor check. Messages name config keys, versions and
 * dates only: never key material, assertions or citizens' data.
 */
final class Result
{
    public function __construct(
        public readonly string $id,
        public readonly Status $status,
        public readonly string $message,
        public readonly ?string $remediation = null,
    ) {}

    public static function pass(string $id, string $message): self
    {
        return new self($id, Status::Pass, $message);
    }

    public static function warn(string $id, string $message, string $remediation): self
    {
        return new self($id, Status::Warn, $message, $remediation);
    }

    public static function fail(string $id, string $message, string $remediation): self
    {
        return new self($id, Status::Fail, $message, $remediation);
    }

    public static function skip(string $id, string $message, string $remediation): self
    {
        return new self($id, Status::Skip, $message, $remediation);
    }

    /**
     * @return array{id: string, status: string, message: string, remediation: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'message' => $this->message,
            'remediation' => $this->remediation,
        ];
    }
}
