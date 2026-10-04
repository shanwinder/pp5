<?php
declare(strict_types=1);

// Run: php tests/Browser/gradebook-behavior-registry-check.php
// No runtime package is needed. This checks traceability, not browser behavior.
$root = dirname(__DIR__, 2);
$registryFile = $root.'/docs/superpowers/specs/2026-10-02-pp5-gradebook-behavior-registry.json';
$json = file_get_contents($registryFile);
$registry = $json === false ? null : json_decode($json, true);
$errors = [];
if (!is_array($registry) || json_last_error() !== JSON_ERROR_NONE || !isset($registry['behaviors']) || !is_array($registry['behaviors'])) {
    fwrite(STDERR, "Invalid Gradebook behavior registry JSON: ".json_last_error_msg()."\n");
    exit(1);
}
if (!is_array($registry['system_rules'] ?? null)
    || !in_array('GB-COMMAND-001', array_column($registry['system_rules'], 'id'), true)
    || !is_string($registry['system_rules'][0]['rule'] ?? null)
    || trim($registry['system_rules'][0]['rule']) === '') {
    $errors[] = 'GB-COMMAND-001 safe serialization policy is missing';
}

$required = [
    'SEL' => range(1, 6), 'NAV' => range(1, 7), 'DIRECT' => range(1, 10),
    'EDIT' => range(1, 10), 'RANGE' => array_merge(range(1, 8), range(101, 105)),
    'COPY' => range(1, 6), 'PASTE' => range(1, 11), 'CLEAR' => range(1, 12),
    'FILL' => range(1, 5), 'SERVER' => range(1, 11), 'RO' => range(1, 6),
    'HIST' => range(1, 4), 'VIS' => range(1, 10), 'RUNTIME' => range(1, 6),
    'SCALE' => range(1, 7),
];
$requiredIds = [];
foreach ($required as $group => $numbers) {
    foreach ($numbers as $number) $requiredIds[] = sprintf('GB-%s-%03d', $group, $number);
}
$seen = [];
$statuses = [];
$golden = file_get_contents(__DIR__.'/gradebook-golden-journeys.js');
if ($golden === false) $errors[] = 'Golden journey suite is missing';
$validJourneys = $golden !== false && preg_match("/const valid = '([A-Z]+)'/", $golden, $match)
    ? str_split($match[1]) : [];
foreach ($registry['behaviors'] as $index => $behavior) {
    if (!is_array($behavior)) { $errors[] = "Record $index is not an object"; continue; }
    $id = $behavior['id'] ?? null;
    if (!is_string($id) || !preg_match('/\AGB-[A-Z]+-[0-9]{3}\z/', $id)) {
        $errors[] = "Record $index has an invalid ID";
        continue;
    }
    if (isset($seen[$id])) $errors[] = "Duplicate behavior ID: $id";
    $seen[$id] = true;
    $statuses[$id] = $behavior['status'] ?? null;
    foreach (['title','state','trigger','expected','permission_mode','status'] as $field) {
        if (!isset($behavior[$field]) || !is_string($behavior[$field]) || trim($behavior[$field]) === '') {
            $errors[] = "$id has no $field";
        }
    }
    if (!isset($behavior['mutates']) || !is_bool($behavior['mutates'])) $errors[] = "$id has no boolean mutates";
    if (!isset($behavior['required_evidence']) || !is_array($behavior['required_evidence']) || $behavior['required_evidence'] === []) {
        $errors[] = "$id has no required_evidence array";
        continue;
    }
    foreach ($behavior['required_evidence'] as $evidence) {
        if (!in_array($evidence, ['synthetic', 'real_browser', 'mamp_if_safe'], true)) {
            $errors[] = "$id has unknown evidence type";
        }
    }
    if (($behavior['status'] ?? null) !== 'accepted') continue;
    $tests = $behavior['automated_tests'] ?? null;
    if (!is_array($tests) || $tests === []) $errors[] = "$id has no automated test mapping";
    else foreach ($tests as $path) {
        if (!is_string($path) || !preg_match('~\Atests/Browser/[A-Za-z0-9._-]+\.(?:js|php)\z~', $path)) {
            $errors[] = "$id maps to an invalid test path";
            continue;
        }
        $source = file_get_contents($root.'/'.$path);
        if ($source === false) $errors[] = "$id maps to a missing test: $path";
        elseif (!str_contains($source, $id)) $errors[] = "$id is absent from mapped test source: $path";
    }
    if (in_array('real_browser', $behavior['required_evidence'], true)) {
        $journeys = $behavior['real_browser_journeys'] ?? null;
        if (!is_array($journeys) || $journeys === []) $errors[] = "$id has no real-browser journey mapping";
        elseif ($golden !== false && !str_contains($golden, $id)) $errors[] = "$id has no assertion in golden journey source";
        else foreach ($journeys as $journey) {
            if (!is_string($journey) || !in_array($journey, $validJourneys, true)) {
                $errors[] = "$id maps to an unknown real-browser journey";
            }
        }
    } elseif (($behavior['real_browser_journeys'] ?? []) !== []) {
        $errors[] = "$id claims a real-browser journey without requiring that evidence";
    }
}
foreach ($requiredIds as $id) {
    if (!isset($seen[$id])) $errors[] = "Required accepted behavior missing: $id";
    elseif ($statuses[$id] !== 'accepted') $errors[] = "Required behavior is no longer accepted without an explicit gate change: $id";
}
// GB-RUNTIME-005: renderer assets must remain the audited local Tabulator 6.6.0 files.
$vendorHashes = [
    'htdocs/assets/vendor/tabulator/tabulator.min.js' => 'b8c69d7e6b82b01979a6630fad847c3a303b6a59b74bfd04c27fa644c4721332',
    'htdocs/assets/vendor/tabulator/tabulator.min.css' => 'ff598d8e961398e09a8e52ab21740e5ed952c84feebbdacec8f8a362dd3f73db',
    'htdocs/assets/vendor/tabulator/LICENSE' => '191a2ee554684e1064c897b432f0e1bc6dfa714ca045d3f6ea2cf692cbd398b7',
];
foreach ($vendorHashes as $path => $expectedHash) {
    if (!is_file($root.'/'.$path) || hash_file('sha256', $root.'/'.$path) !== $expectedHash) {
        $errors[] = "GB-RUNTIME-005 local pinned vendor mismatch: $path";
    }
}
if ($errors !== []) {
    foreach ($errors as $error) fwrite(STDERR, $error."\n");
    fwrite(STDERR, 'FAIL: '.count($errors)." registry coverage errors\n");
    exit(1);
}
echo 'PASS: '.count($seen)." accepted Gradebook behaviors have unique IDs and test mappings\n";
