<?php

function loadLocalEnv($path) {
    $values = [];
    if (!is_readable($path)) {
        return $values;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim(trim($value), "\"'");
        if ($name !== '') {
            $values[$name] = $value;
        }
    }

    return $values;
}

function resolveConfigSetting($name, array $localEnv, $environmentValue, $allowEmpty = false, $allowMissing = false) {
    $hasEnvironmentValue = is_string($environmentValue);
    $environmentValue = $hasEnvironmentValue ? trim($environmentValue) : '';
    if ($environmentValue !== '') {
        return [
            'value' => $environmentValue,
            'source' => 'server environment'
        ];
    }

    if ($allowEmpty && $hasEnvironmentValue) {
        return [
            'value' => '',
            'source' => 'server environment'
        ];
    }

    $localValue = trim((string) ($localEnv[$name] ?? ''));
    if ($localValue !== '' || array_key_exists($name, $localEnv)) {
        return [
            'value' => $localValue,
            'source' => 'project .env'
        ];
    }

    if ($allowMissing) {
        return [
            'value' => '',
            'source' => 'default empty'
        ];
    }

    return [
        'value' => '',
        'source' => 'unset'
    ];
}

function isLocalDatabaseHost($host) {
    $host = strtolower(rtrim(trim((string) $host), '.'));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}
