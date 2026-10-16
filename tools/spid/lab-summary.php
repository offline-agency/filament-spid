<?php

/*
 * Lab only: summarise the spid_sp_test reports of tools/spid/validate.sh as
 * evidence with precise, local statuses. Never "validated" or "certified".
 *
 * Usage: php lab-summary.php <evidence dir> <authn exit> <responses exit> <gate sentinel exit> <tool version>
 */

[, $dir, $authnExit, $responsesExit, $gateExit, $version] = $argv;

$read = static function (string $file): ?array {
    if (! is_file($file)) {
        return null;
    }
    try {
        $value = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return is_array($value) ? $value : null;
    } catch (JsonException) {
        return null;
    }
};
$count = static function (?array $data): array {
    $counts = ['executed' => 0, 'passed' => 0, 'failed' => 0, 'warnings' => 0, 'failures' => [], 'scenarios' => [], 'valid' => true];
    if (! is_array($data['test']['sp'] ?? null) || $data['test']['sp'] === []) {
        $counts['valid'] = false;

        return $counts;
    }
    foreach ($data['test']['sp'] as $category => $classes) {
        if (! is_array($classes) || $classes === []) {
            $counts['valid'] = false;

            continue;
        }
        foreach ($classes as $entries) {
            if (! is_array($entries) || $entries === []) {
                $counts['valid'] = false;

                continue;
            }
            foreach ($entries as $entry) {
                if (! is_array($entry) || (! is_string($entry['test'] ?? null) && (! is_array($entry['test'] ?? null) || $entry['test'] === [] || array_filter($entry['test'], 'is_string') !== $entry['test'])) || ! in_array($entry['result'] ?? null, ['success', 'failure', 'warning'], true)) {
                    $counts['valid'] = false;

                    continue;
                }
                $counts['executed']++;
                match ($entry['result']) {
                    'success' => $counts['passed']++,
                    'warning' => $counts['warnings']++,
                    default => [$counts['failed']++, $counts['failures'][] = "{$category}: ".(is_array($entry['test']) ? implode('; ', $entry['test']) : $entry['test'])],
                };
                if ($category === 'response') {
                    if (! is_string($entry['test']) || ! preg_match('/^Test \[([^]]+)\]/', $entry['test'], $match) || isset($counts['scenarios'][$match[1]])) {
                        $counts['valid'] = false;
                    } else {
                        $counts['scenarios'][$match[1]] = $entry['result'];
                    }
                }
            }
        }
    }

    return $counts;
};
$complete = static fn (array $counts, int $total): bool => $counts['valid'] && $counts['executed'] === $total && $counts['passed'] === $total && $counts['failed'] === 0 && $counts['warnings'] === 0;
$expected = $read(__DIR__.'/scenario-ids.json');
$metadata = $read("{$dir}/../metadata-report.json");
$authn = $count($read("{$dir}/authn-request.json"));
$responses = $count($read("{$dir}/responses.json"));
$gate = $count($read("{$dir}/sentinel-gate.json"));
$diagnostics = $read("{$dir}/response-diagnostics.json");
$denial = $read("{$dir}/sentinel-gate-diagnostics.json");
$diagnosticsPassed = is_array($expected) && is_array($diagnostics) && array_column($diagnostics, 'scenario') === $expected;
foreach ($diagnostics ?? [] as $diagnostic) {
    $positive = in_array($diagnostic['scenario'] ?? null, ['1', '31', '95', '96', '109', '110'], true);
    // Scenario 103 explicitly permits either acceptance or refusal upstream.
    $accepted = ($diagnostic['authenticated'] ?? null) === true && ($diagnostic['panel_status'] ?? null) === 200;
    $refused = ($diagnostic['authenticated'] ?? null) === false && ($diagnostic['panel_login_redirect'] ?? null) === true
        && is_string($diagnostic['error'] ?? null)
        && (in_array($diagnostic['scenario'], ['104', '105', '106', '107', '108', '111'], true)
            ? $diagnostic['error'] === 'SPID user-generated anomaly'
            : str_starts_with($diagnostic['error'], 'SAML response validation error: '))
        && ! preg_match('/X.509 Certificate cannot be retrieved|Unable to extract public key/', $diagnostic['error']);
    $diagnosticsPassed = $diagnosticsPassed && ($diagnostic['isolated'] ?? null) === true
        && ($positive ? $accepted : ($diagnostic['scenario'] === '103' ? $accepted || $refused : $refused));
}
$metadataPassed = ($metadata['evidence'][0] ?? null) === 'LOCAL_METADATA_CHECK_PASSED'
    && ($metadata['summary']['executed'] ?? null) === 104 && ($metadata['summary']['passed'] ?? null) === 104
    && ($metadata['summary']['failed'] ?? null) === 0 && ($metadata['summary']['warnings'] ?? null) === 0;
$samlPassed = $authnExit === '0' && $responsesExit === '0' && $complete($authn, 151) && $complete($responses, 246)
    && array_map('strval', array_keys($responses['scenarios'])) === $expected && $diagnosticsPassed;
$positivePasses = ($responses['scenarios'][1] ?? null) === 'success';
$gateSentinel = $positivePasses && $gateExit === '1' && $gate['valid'] && $gate['executed'] === 136 && $gate['failed'] === 1
    && $gate['warnings'] === 0 && $gate['scenarios'] === [1 => 'failure'] && count($denial ?? []) === 1
    && ($denial[0]['scenario'] ?? null) === '1' && ($denial[0]['isolated'] ?? null) === true
    && ($denial[0]['error'] ?? null) === 'the user may not access the panel'
    && ($denial[0]['authenticated'] ?? null) === false && ($denial[0]['spid_session'] ?? null) === false
    && ($denial[0]['panel_login_redirect'] ?? null) === true;

$provenance = $read("{$dir}/tool-provenance.json");
$environment = $read("{$dir}/environment.json");
$isHash = static fn ($value, int $length): bool => is_string($value) && preg_match('/^[a-f0-9]{'.$length.'}$/D', $value) === 1;
$provenancePassed = $version === '1.2.18'
    && ($provenance['upstream_version'] ?? null) === $version
    && ($provenance['upstream_sha'] ?? null) === '32ac4e7ee819d140bca69e91d8626d6f7d98d364'
    && ($provenance['patch'] ?? null) === 'keyinfo-certificate-v1'
    && ($provenance['patch_sha256'] ?? null) === hash_file('sha256', __DIR__.'/patches/apply.py')
    && ($provenance['original_source_sha256'] ?? null) === '612a03221884386d4a67667de070b8c64eab4e7db7df6e0adf4cb0dd9c1f56eb'
    && ($provenance['patched_source_sha256'] ?? null) === '8e94ffac6c2794bc1c680c474924978c7f8c514cd81295ceea6c97c82252c3b5'
    && $isHash($environment['sha'] ?? null, 40)
    && is_int($environment['dirty_files'] ?? null) && $environment['dirty_files'] >= 0
    && is_string($environment['php'] ?? null) && $environment['php'] !== '';
foreach (['italia/spid-laravel', 'onelogin/php-saml', 'robrichards/xmlseclibs', 'laravel/framework', 'filament/filament'] as $dependency) {
    $package = $environment['packages'][$dependency] ?? [];
    $provenancePassed = $provenancePassed && is_string($package['version'] ?? null) && $package['version'] !== '' && $isHash($package['reference'] ?? null, 40);
}
$passed = $metadataPassed && $samlPassed && $gateSentinel && $provenancePassed;

echo json_encode([
    'schema_version' => 2,
    'generated_at' => gmdate('c'),
    'tool' => ['name' => 'spid_sp_test', 'version' => $version, 'lab_patch' => $provenance],
    'evidence' => [
        $metadataPassed ? 'LOCAL_METADATA_CHECK_PASSED' : 'LOCAL_METADATA_CHECK_FAILED',
        $samlPassed ? 'LOCAL_SAML_TESTS_PASSED' : 'LOCAL_SAML_TESTS_FAILED',
        'HOSTED_VALIDATOR_NOT_RUN',
        'FORMAL_AGID_ACCEPTANCE_NOT_OBTAINED',
    ],
    'source' => $environment,
    'provenance_valid' => $provenancePassed,
    'diagnostics_passed' => $diagnosticsPassed,
    'metadata' => $metadata['summary'] ?? null,
    'authn_request' => $authn,
    'responses' => $responses,
    'gate_sentinel' => [
        'refused_citizen_rejected' => $gateSentinel,
        'note' => $gateSentinel ? null : 'authorization refusal or admitted positive not established',
        'exit_code' => (int) $gateExit,
    ],
    'gate' => $passed ? 'pass' : 'fail',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

exit($passed ? 0 : 1);
