<?php
/**
 * Registra um clique no botão "Saiba mais" de uma vaga.
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'db_functions.php';

$jobId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
if ($jobId === false || $jobId === null || $jobId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vaga inválida.']);
    exit;
}

$job = getVagaById($conn, $jobId);
if (!$job) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Vaga não encontrada.']);
    exit;
}

if (!registerSaibaMaisClick($conn, $jobId, $job['title'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não foi possível registrar o acesso.']);
    exit;
}

echo json_encode(['success' => true]);