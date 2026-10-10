<?php

/*
 * Lab only: summarise the spid_sp_test reports of tools/spid/validate.sh as
 * evidence with precise, local statuses. Never "validated" or "certified".
 *
 * Usage: php lab-summary.php <evidence dir> <authn exit> <responses exit> <gate sentinel exit> <tool version>
 */

[, $dir, $authnExit, $responsesExit, $gateExit, $version] = $argv;

$count = function (string $file): array {
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $counts = ['executed' => 0, 'passed' => 0, 'failed' => 0, 'warnings' => 0, 'failures' => []];
    foreach (($data['test']['sp'] ?? []) as $category => $classes) {
        foreach ((array) $classes as $entries) {
            foreach ((array) $entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $counts['executed']++;
                match ($entry['result'] ?? '') {
                    'success' => $counts['passed']++,
                    'warning' => $counts['warnings']++,
                    default => [$counts['failed']++, $counts['failures'][] = "{$category}: ".($entry['test'] ?? '?')],
                };
            }
        }
    }

    return $counts;
};

$metadata = is_file("{$dir}/../metadata-report.json") ? json_decode((string) file_get_contents("{$dir}/../metadata-report.json"), true) : null;
$authn = $count("{$dir}/authn-request.json");
$responses = $count("{$dir}/responses.json");
$gate = $count("{$dir}/sentinel-gate.json");

$metadataPassed = ($metadata['evidence'][0] ?? null) === 'LOCAL_METADATA_CHECK_PASSED';
$samlPassed = (int) $authnExit === 0 && (int) $responsesExit === 0 && $authn['executed'] > 0 && $responses['executed'] > 0 && $authn['failed'] === 0 && $responses['failed'] === 0;
// The positive Response for a citizen the panel refuses must fail.
$gateSentinel = (int) $gateExit !== 0 && $gate['failed'] > 0;

echo json_encode([
    'schema_version' => 1,
    'generated_at' => gmdate('c'),
    'tool' => ['name' => 'spid_sp_test', 'version' => $version],
    'evidence' => [
        $metadataPassed ? 'LOCAL_METADATA_CHECK_PASSED' : 'LOCAL_METADATA_CHECK_FAILED',
        $samlPassed ? 'LOCAL_SAML_TESTS_PASSED' : 'LOCAL_SAML_TESTS_FAILED',
        'HOSTED_VALIDATOR_NOT_RUN',
        'FORMAL_AGID_ACCEPTANCE_NOT_OBTAINED',
    ],
    'metadata' => $metadata['summary'] ?? null,
    'authn_request' => $authn,
    'responses' => $responses,
    'gate_sentinel' => ['refused_citizen_rejected' => $gateSentinel, 'exit_code' => (int) $gateExit],
    'gate' => $metadataPassed && $samlPassed && $gateSentinel ? 'pass' : 'fail',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
