<?php
/**
 * API de Candidaturas - LabWare (REFATORADA)
 * 
 * NOVO FLUXO ASSÍNCRONO:
 * 1. Valida dados do formulário e arquivo
 * 2. Grava candidatura no banco com status 'Pendente Análise'
 * 3. Salva arquivo em disco
 * 4. Retorna resposta imediata ao usuário
 * 5. O processamento com Gemini é feito via CRON (cron_analisar.php)
 */
header('Content-Type: application/json; charset=utf-8');

// Garantir que erros sejam retornados como JSON
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("Erro PHP [$errno]: $errstr em $errfile:$errline");
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível processar a candidatura.']);
    exit;
});

try {
    require_once 'config.php';
    require_once 'db_functions.php';
    require_once 'gemini_service.php';

    // Garantir que as colunas necessárias existem no banco de dados
    ensureCandidaturaArquivoColumn($conn);
    ensureCandidatoStatusColumn($conn);
    ensureCandidatoCurriculoColumn($conn);
    ensureCandidaturaStatusColumn($conn); // Coluna de status para processamento assíncrono

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        exit;
    }

    // Obter dados do formulário
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    if ($job_id === false || $job_id === null || $job_id < 1) {
        $job_id = getOrCreateBancoTalentos($conn);
    }

    if (empty($nome) || empty($email) || !$job_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos: nome e email são obrigatórios']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email inválido']);
        exit;
    }

    $vaga = getVagaById($conn, (int)$job_id);
    if (!$vaga) {
        http_response_code(400);
        echo json_encode(['error' => 'A vaga selecionada não foi encontrada.']);
        exit;
    }

    $tituloVaga = trim((string) ($vaga['title'] ?? ''));
    if (strcasecmp($tituloVaga, 'Banco de Talentos') !== 0 && strcasecmp(trim((string) ($vaga['status'] ?? '')), 'Aberta') !== 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Esta vaga está encerrada e não aceita novas candidaturas.']);
        exit;
    }

    // Validar o arquivo no servidor antes de qualquer gravação no banco.
    $arquivo = $_FILES['formulario'] ?? null;
    if (!$arquivo) {
        http_response_code(400);
        echo json_encode(['error' => 'Arquivo não foi enviado']);
        exit;
    }

    $arquivoInfo = validateResumeFile($arquivo);
    if (!$arquivoInfo['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $arquivoInfo['error']]);
        exit;
    }

    // Ler o conteúdo do currículo para armazenar no banco de dados (BLOB)
    $curriculoConteudo = file_get_contents($arquivo['tmp_name']);
    if ($curriculoConteudo === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao ler o arquivo de currículo']);
        exit;
    }

    // Preparar dados para salvar no sistema de arquivos
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'candidatos';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log('Erro ao criar diretório de upload: ' . $uploadDir);
        }
    }

    $nomeArquivoSalvo = 'candidatura_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $arquivoInfo['extension'];
    $caminhoArquivo = $uploadDir . DIRECTORY_SEPARATOR . $nomeArquivoSalvo;
    $caminhoRelativo = 'uploads/candidatos/' . $nomeArquivoSalvo;

    // ✅ 1️⃣ REGISTRAR CANDIDATURA COM STATUS 'Em analise'
    // O candidato é marcado como 'Em analise' automaticamente para processamento do CRON
    $idCandidatura = registerCandidatura($conn, $nome, $email, $job_id, $curriculoConteudo, $caminhoRelativo);
    if (!$idCandidatura) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao registrar candidatura no banco de dados']);
        exit;
    }

    registerCandidaturaClick($conn, $job_id, $vaga['title']);

    // ✅ 2️⃣ SALVAR ARQUIVO NO DISCO (BACKUP)
    if (copy($arquivo['tmp_name'], $caminhoArquivo)) {
        error_log('✓ Currículo ID ' . $idCandidatura . ' salvo em: ' . $caminhoArquivo);
    } else {
        error_log('⚠ Arquivo não salvo no sistema para ID ' . $idCandidatura . ', mas currículo está no banco de dados');
    }

    $tamanhoArquivo = strlen($curriculoConteudo);
    error_log("📄 [API] Arquivo processado: " . $arquivo['name'] . " - Tamanho: " . number_format($tamanhoArquivo) . " bytes");
    error_log("✅ [API] Candidatura #$idCandidatura registrada com candidato marcado para análise");

    // ✅ 3️⃣ RETORNAR RESPOSTA IMEDIATA AO USUÁRIO
    // A análise com Gemini será colocada em fila e processada via CRON
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Candidatura enviada com sucesso! Sua candidatura está em fila de análise.',
        'candidatura_id' => $idCandidatura
    ]);

} catch (Exception $e) {
    error_log('Exceção em api_candidatura.php: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível processar a candidatura.']);
}
