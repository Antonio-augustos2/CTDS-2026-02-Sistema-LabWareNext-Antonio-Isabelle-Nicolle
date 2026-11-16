<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . DIRECTORY_SEPARATOR . 'config_helpers.php';

$localEnv = loadLocalEnv($projectRoot . DIRECTORY_SEPARATOR . '.env');
$tokenSetting = resolveConfigSetting('CRON_TOKEN', $localEnv, getenv('CRON_TOKEN'));
$cronToken = $tokenSetting['value'];
if ($cronToken === '') {
    fwrite(STDERR, "CRON_TOKEN is not configured.\n");
    exit(1);
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP cURL extension is not available.\n");
    exit(1);
}

$url = 'http://localhost/TCC-labware/cron_analisar.php';
$curl = curl_init($url);
if ($curl === false) {
    fwrite(STDERR, "Could not initialize the cron HTTP request.\n");
    exit(1);
}

curl_setopt_array($curl, [
    CURLOPT_HTTPGET => true,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_HTTPHEADER => ['X-Cron-Token: ' . $cronToken],
    CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) {
        return strlen($chunk);
    },
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 180
]);

$requestSucceeded = curl_exec($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$status = $requestSucceeded !== false && $httpCode >= 200 && $httpCode < 300 ? 'ok' : 'failed';
$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'cron_runner.log';
$logLine = sprintf("[%s] HTTP %d; runner=%s%s", date('Y-m-d H:i:s'), $httpCode, $status, PHP_EOL);

if (file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
    fwrite(STDERR, "Could not write the cron runner status log.\n");
    exit(1);
}

if ($status !== 'ok') {
    fwrite(STDERR, "Cron HTTP request failed (HTTP " . $httpCode . ").\n");
    exit(1);
}
