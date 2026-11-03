<?php
/**
 * Download de Currículo BLOB
 * Recupera o arquivo armazenado em BLOB no banco de dados
 */

session_start();

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit('Acesso negado. Faça login para baixar currículos.');
}

require_once 'config.php';
require_once 'db_functions.php';

$idCandidato = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idCandidato) {
    http_response_code(400);
    echo "ID de candidato inválido";
    exit;
}

// Obter candidato e extensão do arquivo
$sql = "SELECT c.nome, c.curriculo, cand.arquivo_path 
        FROM candidato c
        LEFT JOIN candidatura cand ON cand.id_candidato = c.id_candidato
        WHERE c.id_candidato = $idCandidato
        LIMIT 1";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo "Candidato não encontrado";
    exit;
}

$row = $result->fetch_assoc();
$curriculoBlob = $row['curriculo'];
$nomeCandidato = $row['nome'];
$caminhoArquivo = $row['arquivo_path'];

if (empty($curriculoBlob)) {
    http_response_code(404);
    echo "Currículo não encontrado para este candidato";
    exit;
}

// Detectar extensão do arquivo
$extensao = 'pdf'; // padrão
if ($caminhoArquivo && preg_match('/\.(pdf|docx)$/i', $caminhoArquivo, $matches)) {
    $extensao = strtolower($matches[1]);
}

// Definir mime type
$mimeType = $extensao === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf';

// Preparar nome do arquivo para download
$nomeDownload = 'curriculo_' . preg_replace('/[^a-z0-9]/i', '_', $nomeCandidato) . '.' . $extensao;

// Headers para download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
header('Content-Length: ' . strlen($curriculoBlob));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Enviar o arquivo BLOB
echo $curriculoBlob;

error_log("✅ Currículo BLOB enviado - Candidato: $idCandidato ($nomeCandidato), Tamanho: " . number_format(strlen($curriculoBlob)) . " bytes, Extensão: $extensao");
exit;
