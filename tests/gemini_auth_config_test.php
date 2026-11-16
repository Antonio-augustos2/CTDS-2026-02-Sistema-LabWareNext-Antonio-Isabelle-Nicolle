<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config_helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'gemini_diagnostics.php';

function expectSameValue($expected, $actual, $case) {
    if ($expected !== $actual) {
        throw new RuntimeException('Failed test: ' . $case);
    }
}

$dotenvValues = ['GEMINI_API_KEY' => 'fake-dotenv-key'];
$resolved = resolveConfigSetting('GEMINI_API_KEY', $dotenvValues, 'fake-server-key');
expectSameValue('fake-server-key', $resolved['value'], 'server environment has precedence');
expectSameValue('server environment', $resolved['source'], 'server environment source is reported');

$resolved = resolveConfigSetting('GEMINI_API_KEY', $dotenvValues, false);
expectSameValue('fake-dotenv-key', $resolved['value'], 'dotenv is the local fallback');
expectSameValue('project .env', $resolved['source'], 'dotenv source is reported');

$resolved = resolveConfigSetting('DB_PASS', ['DB_PASS' => ''], false, true);
expectSameValue('', $resolved['value'], 'an empty local database password is preserved');
expectSameValue('project .env', $resolved['source'], 'an explicitly empty local password is configured');

$resolved = resolveConfigSetting('DB_PASS', [], false, true);
expectSameValue('unset', $resolved['source'], 'an unset database password remains distinguishable');

$resolved = resolveConfigSetting('DB_PASS', [], '', true);
expectSameValue('server environment', $resolved['source'], 'an explicitly empty environment password is supported');

$resolved = resolveConfigSetting('DB_PASS', [], false, true, true);
expectSameValue('', $resolved['value'], 'an omitted local database password defaults to empty');
expectSameValue('default empty', $resolved['source'], 'the default empty password source is reported');
if (!isLocalDatabaseHost('localhost') || !isLocalDatabaseHost('127.0.0.1') || isLocalDatabaseHost('db.example.com')) {
    throw new RuntimeException('Failed test: local database hosts are identified safely');
}

$generic403 = diagnoseGeminiAuthResponse(
    403,
    json_encode(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'Request was denied.']])
);
expectSameValue('permission_denied_unclassified', $generic403['classification'], 'generic 403 is not called a revoked key');

$typeMismatch401 = diagnoseGeminiAuthResponse(
    401,
    json_encode([
        'error' => [
            'code' => 401,
            'status' => 'UNAUTHENTICATED',
            'message' => 'ACCESS_TOKEN_TYPE_UNSUPPORTED'
        ]
    ])
);
expectSameValue('credential_type_mismatch', $typeMismatch401['classification'], 'credential type requires explicit evidence');
expectSameValue(401, $typeMismatch401['google_code'], 'Google error code is retained safely');

$verification403 = diagnoseGeminiAuthResponse(
    403,
    json_encode([
        'error' => [
            'status' => 'PERMISSION_DENIED',
            'message' => 'Project verification is required.',
            'details' => [['reason' => 'FREE_TIER_VERIFICATION_REQUIRED']]
        ]
    ])
);
expectSameValue('project_verification_required', $verification403['classification'], 'verification error uses explicit reason');

$billing403 = diagnoseGeminiAuthResponse(
    403,
    json_encode(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'Payment needed for this project.']])
);
expectSameValue('billing_required', $billing403['classification'], 'billing error requires message evidence');

$suspended403 = diagnoseGeminiAuthResponse(
    403,
    json_encode([
        'error' => [
            'status' => 'PERMISSION_DENIED',
            'message' => "Permission denied: Consumer 'projects/12345' has been suspended.",
            'details' => [['reason' => 'CONSUMER_SUSPENDED']]
        ]
    ])
);
expectSameValue('project_suspended', $suspended403['classification'], 'suspended Google project is distinguished from invalid key');

$fakeKey = 'AIza' . str_repeat('A', 35);
$blocked403 = diagnoseGeminiAuthResponse(
    403,
    json_encode([
        'error' => [
            'status' => 'PERMISSION_DENIED',
            'message' => 'Your API key was reported as leaked: ' . $fakeKey
        ]
    ]),
    $fakeKey
);
expectSameValue('api_key_blocked', $blocked403['classification'], 'explicit leaked-key message is classified');
if (strpos(json_encode($blocked403), $fakeKey) !== false) {
    throw new RuntimeException('Failed test: API key is redacted from diagnostics');
}

$restriction403 = diagnoseGeminiAuthResponse(
    403,
    json_encode([
        'error' => [
            'status' => 'PERMISSION_DENIED',
            'message' => 'Request blocked.',
            'details' => [['reason' => 'API_KEY_IP_ADDRESS_BLOCKED']]
        ]
    ])
);
expectSameValue('api_key_restricted', $restriction403['classification'], 'explicit API key restriction is classified');

echo "Gemini configuration and auth diagnostics tests passed.\n";
