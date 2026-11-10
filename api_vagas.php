<?php
/**
 * API de Vagas - LabWare
 * Retorna as vagas em formato JSON
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'db_functions.php';

$vagas = getAllVagas($conn);

// Formatar resposta
$response = array_map(function ($vaga) {
    return [
        'id' => $vaga['id'],
        'title' => $vaga['title'],
        'type' => $vaga['type'],
        'location' => $vaga['location'],
        'description' => $vaga['description']
    ];
}, $vagas);

echo json_encode($response);
