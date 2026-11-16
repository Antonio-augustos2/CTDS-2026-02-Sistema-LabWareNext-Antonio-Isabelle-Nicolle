<?php

function sanitizeGeminiDiagnosticValue($value, $apiKey = '', $maxLength = 500) {
    if (!is_scalar($value)) {
        return '';
    }

    $value = (string) $value;
    if ($apiKey !== '') {
        $value = str_replace($apiKey, '[REDACTED]', $value);
    }
    $value = preg_replace('/\b(?:AIza[0-9A-Za-z_-]{35}|AQ\.[0-9A-Za-z_-]{50})\b/', '[REDACTED]', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

function diagnoseGeminiAuthResponse($httpCode, $responseBody, $apiKey = '') {
    $decoded = json_decode((string) $responseBody, true);
    $error = is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])
        ? $decoded['error']
        : [];
    $status = sanitizeGeminiDiagnosticValue($error['status'] ?? '', $apiKey, 120);
    $message = sanitizeGeminiDiagnosticValue($error['message'] ?? '', $apiKey, 500);
    $googleCode = isset($error['code']) && is_numeric($error['code'])
        ? (int) $error['code']
        : null;
    $reasons = [];
    $metadata = [];
    $safeMetadataKeys = ['service', 'quotaMetric', 'quotaLimit', 'quotaLocation', 'violationReason'];

    foreach (($error['details'] ?? []) as $detail) {
        if (!is_array($detail)) {
            continue;
        }

        if (isset($detail['reason']) && is_scalar($detail['reason'])) {
            $reasons[] = sanitizeGeminiDiagnosticValue($detail['reason'], $apiKey, 120);
        }

        if (!isset($detail['metadata']) || !is_array($detail['metadata'])) {
            continue;
        }

        foreach ($safeMetadataKeys as $key) {
            if (isset($detail['metadata'][$key]) && is_scalar($detail['metadata'][$key])) {
                $metadata[$key] = sanitizeGeminiDiagnosticValue($detail['metadata'][$key], $apiKey, 200);
            }
        }
    }

    $reasons = array_values(array_unique(array_filter($reasons, 'strlen')));
    $evidence = strtoupper(implode(' ', array_merge([$status, $message], $reasons, array_values($metadata))));
    $classification = 'unclassified_auth_failure';

    if (strpos($evidence, 'ACCESS_TOKEN_TYPE_UNSUPPORTED') !== false) {
        $classification = 'credential_type_mismatch';
    } elseif (
        stripos($message, 'reported as leaked') !== false
        || strpos($evidence, 'API_KEY_REVOKED') !== false
    ) {
        $classification = 'api_key_blocked';
    } elseif (
        strpos($evidence, 'CONSUMER_SUSPENDED') !== false
        || strpos($evidence, 'PROJECT_SUSPENDED') !== false
        || stripos($message, 'consumer') !== false && stripos($message, 'has been suspended') !== false
        || stripos($message, 'project') !== false && stripos($message, 'has been suspended') !== false
    ) {
        $classification = 'project_suspended';
    } elseif (
        strpos($evidence, 'API_KEY_HTTP_REFERRER_BLOCKED') !== false
        || strpos($evidence, 'API_KEY_IP_ADDRESS_BLOCKED') !== false
        || strpos($evidence, 'API_KEY_ANDROID_APP_BLOCKED') !== false
        || strpos($evidence, 'API_KEY_IOS_APP_BLOCKED') !== false
        || strpos($evidence, 'API_KEY_SERVICE_BLOCKED') !== false
        || stripos($message, 'requests from this referer are blocked') !== false
    ) {
        $classification = 'api_key_restricted';
    } elseif (
        strpos($evidence, 'API_KEY_INVALID') !== false
        || strpos($evidence, 'API_KEY_EXPIRED') !== false
        || stripos($message, 'API key not valid') !== false
        || stripos($message, 'invalid api key') !== false
    ) {
        $classification = 'api_key_invalid';
    } elseif (strpos($evidence, 'SERVICE_DISABLED') !== false) {
        $classification = 'api_service_disabled';
    } elseif (strpos($evidence, 'FREE_TIER_VERIFICATION_REQUIRED') !== false) {
        $classification = 'project_verification_required';
    } elseif (
        strpos($evidence, 'FREE_TIER_UNAVAILABLE') !== false
        || stripos($message, 'free tier is unavailable') !== false
    ) {
        $classification = 'free_tier_unavailable';
    } elseif (
        strpos($evidence, 'PAYMENT_REQUIRED') !== false
        || strpos($evidence, 'BILLING_DISABLED') !== false
        || strpos($evidence, 'BILLING_NOT_ENABLED') !== false
        || stripos($message, 'payment needed') !== false
        || stripos($message, 'outstanding balance') !== false
        || stripos($message, 'billing account') !== false
    ) {
        $classification = 'billing_required';
    } elseif (
        stripos($message, 'project has been denied access') !== false
        || stripos($message, 'project is restricted') !== false
        || strpos($evidence, 'PROJECT_DISABLED') !== false
    ) {
        $classification = 'project_access_denied';
    } elseif ($status === 'PERMISSION_DENIED') {
        $classification = 'permission_denied_unclassified';
    }

    return [
        'http_code' => (int) $httpCode,
        'google_code' => $googleCode,
        'error_status' => $status !== '' ? $status : null,
        'error_message' => $message !== '' ? $message : 'No diagnostic message was provided.',
        'reasons' => $reasons,
        'metadata' => $metadata,
        'classification' => $classification
    ];
}
