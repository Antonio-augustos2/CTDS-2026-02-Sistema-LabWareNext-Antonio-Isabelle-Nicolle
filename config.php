<?php
/**
 * Configuração de Banco de Dados - LabWare
 * Conexão com MySQL através do phpMyAdmin
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config_helpers.php';

// Server environment values take precedence; the project .env is a local-development fallback.
$localEnvPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
$localEnv = loadLocalEnv($localEnvPath);

$requiredEnv = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'CRON_TOKEN'];
$resolvedEnv = [];
foreach ($requiredEnv as $envName) {
    $allowEmpty = $envName === 'DB_PASS';
    $allowMissing = $envName === 'DB_PASS'
        && isset($resolvedEnv['DB_HOST']['value'])
        && isLocalDatabaseHost($resolvedEnv['DB_HOST']['value']);
    $resolvedEnv[$envName] = resolveConfigSetting(
        $envName,
        $localEnv,
        getenv($envName),
        $allowEmpty,
        $allowMissing
    );
    if (
        $resolvedEnv[$envName]['source'] === 'unset'
        || (!$allowEmpty && $resolvedEnv[$envName]['value'] === '')
    ) {
        error_log('Configuração obrigatória ausente: ' . $envName);
        http_response_code(500);
        die('Serviço temporariamente indisponível.');
    }
}

define('DB_HOST', $resolvedEnv['DB_HOST']['value']);
define('DB_USER', $resolvedEnv['DB_USER']['value']);
define('DB_PASS', $resolvedEnv['DB_PASS']['value']);
define('DB_NAME', $resolvedEnv['DB_NAME']['value']);
$geminiSetting = resolveConfigSetting('GEMINI_API_KEY', $localEnv, getenv('GEMINI_API_KEY'));
$geminiApiKey = $geminiSetting['value'];
define('GEMINI_API_KEY', $geminiApiKey);
define('GEMINI_API_KEY_SOURCE', $geminiSetting['source']);
$geminiStandardKeyFormatOk = preg_match('/^AIza[0-9A-Za-z_\-]{35}$/', $geminiApiKey) === 1;
$geminiAuthorizationKeyFormatOk = preg_match('/^AQ\.[0-9A-Za-z_\-]{50}$/', $geminiApiKey) === 1;
$geminiApiKeyFormatOk = $geminiApiKey !== '' && ($geminiStandardKeyFormatOk || $geminiAuthorizationKeyFormatOk);
define('GEMINI_API_KEY_FORMAT_OK', $geminiApiKeyFormatOk);

if ($geminiApiKey !== '' && !$geminiApiKeyFormatOk) {
    error_log('GEMINI_API_KEY definida, mas com formato não reconhecido. Use uma API key do Gemini AI Studio.');
}

// Criar conexão sem expor warnings ou detalhes da conexão ao cliente.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexão
if ($conn->connect_error) {
    error_log('Erro ao conectar ao banco de dados: ' . $conn->connect_error);
    http_response_code(500);
    die('Serviço temporariamente indisponível.');
}

// Definir charset para UTF-8 somente após uma conexão válida.
$conn->set_charset('utf8mb4');


// Use only URL-safe characters because this value is sent in ?token=...
define('CRON_TOKEN', $resolvedEnv['CRON_TOKEN']['value']);

function getCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
