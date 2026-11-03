<?php
/**
 * Limpar Erros da API Gemini
 * Acesse: http://localhost/TCC-labware-main/clear_api_errors.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id']) || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado ou token CSRF inválido']);
    exit;
}

$errorsFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_errors.json';

$emptyData = [
    'total_errors' => 0,
    'last_update' => date('Y-m-d H:i:s'),
    'errors' => []
];

$payload = json_encode($emptyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($payload !== false && file_put_contents($errorsFile, $payload, LOCK_EX) !== false) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Erros limpados com sucesso']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível limpar os erros']);
}
?>
