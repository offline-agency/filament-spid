<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Validation;

use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Doctor\Status;

/**
 * The checks in a spid_sp_test JSON report:
 * {"test": {"sp": {<category>: {<class>: [{test_id, test, value, references, method, result}]}}}}
 */
final class ToolReport
{
    /**
     * @param  list<Result>  $checks
     */
    public function __construct(public readonly array $checks, public readonly bool $dependencyMissing) {}

    /**
     * @return self|null null when the report is not what spid_sp_test writes
     */
    public static function parse(string $json): ?self
    {
        $data = json_decode($json, true);
        $categories = is_array($data) && is_array($data['test'] ?? null) && is_array($data['test']['sp'] ?? null) ? $data['test']['sp'] : null;

        if ($categories === null) {
            return null;
        }

        $checks = [];
        $dependencyMissing = false;

        foreach ($categories as $classes) {
            foreach (is_array($classes) ? $classes : [] as $entries) {
                foreach (is_array($entries) ? $entries : [] as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $value = is_string($entry['value'] ?? null) ? $entry['value'] : '';
                    // The tool reports a missing system binary (xmlsec1) as
                    // a failed check: that is the environment, not the SP.
                    $dependencyMissing = $dependencyMissing || str_contains($value, 'command not found');
                    $references = is_array($entry['references'] ?? null) ? implode('; ', array_filter($entry['references'], is_string(...))) : '';

                    $checks[] = new Result(
                        self::text($entry['test_id'] ?? null) ?: self::text($entry['method'] ?? null) ?: 'unnamed',
                        match ($entry['result'] ?? null) {
                            'success' => Status::Pass,
                            'warning' => Status::Warn,
                            default => Status::Fail,
                        },
                        self::text($entry['test'] ?? null).($value !== '' ? ': '.$value : ''),
                        $references !== '' ? 'See the SPID technical rules: '.$references : null,
                    );
                }
            }
        }

        return new self($checks, $dependencyMissing);
    }

    /**
     * @return array{executed: int, passed: int, failed: int, warnings: int}
     */
    public function summary(): array
    {
        $count = fn (Status $status) => count(array_filter($this->checks, fn (Result $check) => $check->status === $status));

        return [
            'executed' => count($this->checks),
            'passed' => $count(Status::Pass),
            'failed' => $count(Status::Fail),
            'warnings' => $count(Status::Warn),
        ];
    }

    protected static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
