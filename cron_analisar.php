<?php
/**
 * CRON - Processamento Assíncrono de Análise de Currículos
 * 
 * Este script deve ser executado periodicamente (a cada 5-15 minutos) via CRON.
 * Responsabilidades:
 * - Buscar candidatos com status 'Em analise'
 * - Processar análise de TODAS as candidaturas destes candidatos com Gemini
 * - Atualizar banco de dados com resultados
 * - Registrar logs detalhados
 * - Marcar candidatos como 'Pendente' após processamento completo
 * 
 * Configuração CRON sugerida:
 * curl -s "https://seu-dominio.com/cron_analisar.php?token=SEU_TOKEN_URL_SAFE" > /dev/null 2>&1
 */

$cronLogFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cron_analisar.log';
const MAX_TENTATIVAS_ANALISE = 3;

function cronLog($message, $level = 'INFO') {
    global $cronLogFile;

    $line = sprintf(
        "[%s] [%s] [PID %s] %s%s",
        date('Y-m-d H:i:s'),
        $level,
        getmypid(),
        $message,
        PHP_EOL
    );

    @file_put_contents($cronLogFile, $line, FILE_APPEND | LOCK_EX);
}

// Direciona também os erros nativos do PHP para o log dedicado.
@ini_set('log_errors', '1');
@ini_set('error_log', $cronLogFile);
$requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: 'CLI';
cronLog('Execução iniciada. Método: ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . '; Caminho: ' . $requestPath);

$cronLockHandle = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cron.lock', 'c');
if ($cronLockHandle === false) {
    cronLog('Não foi possível abrir o lock do cron.', 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível iniciar o lock do cron', 'processadas' => 0]);
    exit;
}

if (!flock($cronLockHandle, LOCK_EX | LOCK_NB)) {
    cronLog('Já existe execução de cron em andamento.', 'WARNING');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Execução já em andamento',
        'processadas' => 0
    ]);
    fclose($cronLockHandle);
    exit;
}

register_shutdown_function(function () use (&$cronLockHandle) {
    if (is_resource($cronLockHandle) || $cronLockHandle instanceof \SplFileObject) {
        flock($cronLockHandle, LOCK_UN);
        fclose($cronLockHandle);
    }
});

// Registra erros fatais que não passam pelo try/catch.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        cronLog(sprintf('Erro fatal [%d]: %s em %s:%d', $error['type'], $error['message'], $error['file'], $error['line']), 'FATAL');
    }
});

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

// Apenas JSON será retornado
header('Content-Type: application/json; charset=utf-8');

// O token deve ser URL-safe (sem #, &, ?, espaços ou acentos).
$CRON_TOKEN = defined('CRON_TOKEN') ? (string) CRON_TOKEN : '';

// Validar token
$tokenRecebido = $_GET['token'] ?? '';
if ($CRON_TOKEN === '' || !is_string($tokenRecebido) || !hash_equals($CRON_TOKEN, $tokenRecebido)) {
    cronLog('Autenticação recusada: token ausente ou inválido.', 'ERROR');
    http_response_code(401);
    echo json_encode([
        'error' => 'Token inválido ou não fornecido',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

cronLog('Autenticação validada. Iniciando processamento.');

// Log de erro customizado
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    cronLog("Erro PHP [$errno]: $errstr em $errfile:$errline", 'ERROR');
});

try {
    require_once 'db_functions.php';
    require_once 'gemini_service.php';

    ensureCandidaturaStatusColumn($conn);
    ensureCandidaturaTentativasColumn($conn);
    ensureCandidatoStatusColumn($conn);

    $finalizarStatusCandidato = function ($idCandidato) use ($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM candidatura WHERE id_candidato = ? AND status IN ('Pendente Análise', 'Processando')");
        if (!$stmt) {
            error_log('❌ Erro ao preparar verificação de pendências do candidato: ' . $conn->error);
            return false;
        }

        $stmt->bind_param('i', $idCandidato);
        $stmt->execute();
        $total = 0;
        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = (int) ($resultado['total'] ?? 0);
        if ($total === 0) {
            return updateCandidatoStatus($conn, $idCandidato, 'Pendente');
        }

        return true;
    };

    cronLog('========================================');
    cronLog('Iniciando processamento de análises');

    $candidaturasPendentes = claimCandidaturasPendentes($conn, 20);

    if (empty($candidaturasPendentes)) {
        cronLog('Nenhuma candidatura pendente no momento');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Nenhuma candidatura pendente',
            'processadas' => 0,
            'sucesso' => 0,
            'erros' => 0,
            'em_erro' => 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    cronLog('Encontrados ' . count($candidaturasPendentes) . ' candidatura(s) pendentes para processamento');

    $resultados = [
        'total_processadas' => 0,
        'total_sucesso' => 0,
        'total_erros' => 0,
        'total_em_erro' => 0,
        'detalhes' => []
    ];
    $avisoConfiguracao = null;

    foreach ($candidaturasPendentes as $candidatura) {
        $idCandidatura = (int) ($candidatura['id_candidatura'] ?? 0);
        $idCandidato = (int) ($candidatura['id_candidato'] ?? 0);
        $arquivoPath = (string) ($candidatura['arquivo_path'] ?? '');
        $caminhoArquivo = $arquivoPath !== '' ? __DIR__ . DIRECTORY_SEPARATOR . $arquivoPath : '';
        $descricaoVaga = $candidatura['vaga_descricao'] ?? '';

        if ($idCandidatura <= 0 || $caminhoArquivo === '') {
            cronLog('Candidatura ignorada por caminho de arquivo vazio: #' . $idCandidatura, 'WARNING');
            continue;
        }

        cronLog("Processando candidatura #$idCandidatura (Candidato: {$candidatura['candidato_nome']}, Vaga: {$candidatura['vaga_titulo']})");

        $resultadoAnalise = analizarComApi($conn, $caminhoArquivo, $descricaoVaga, $idCandidatura);
        $resultados['total_processadas']++;

        if ($resultadoAnalise['success']) {
            $stmt = $conn->prepare('UPDATE candidatura SET tentativas_analise = 0 WHERE id_candidatura = ?');
            if ($stmt) {
                $stmt->bind_param('i', $idCandidatura);
                $stmt->execute();
                $stmt->close();
            }

            $resultados['total_sucesso']++;
            $resultados['detalhes'][] = [
                'id_candidatura' => $idCandidatura,
                'candidato' => $candidatura['candidato_nome'],
                'vaga' => $candidatura['vaga_titulo'],
                'status' => 'Sucesso',
                'assertividade' => $resultadoAnalise['assertividade'] ?? 0,
                'feedback_preview' => mb_substr($resultadoAnalise['feedback'] ?? '', 0, 100) . '...'
            ];
        } else {
            $erroConfiguracaoGemini = in_array($resultadoAnalise['error_code'] ?? '', ['gemini_auth', 'missing_api_key', 'invalid_api_key_format'], true);
            if ($erroConfiguracaoGemini) {
                updateCandidaturaStatus($conn, $idCandidatura, 'Pendente Análise');
                $avisoConfiguracao = $resultadoAnalise['error'] ?? 'Corrija GEMINI_API_KEY antes da próxima execução.';
                $resultados['total_erros']++;
                $resultados['detalhes'][] = [
                    'id_candidatura' => $idCandidatura,
                    'candidato' => $candidatura['candidato_nome'],
                    'vaga' => $candidatura['vaga_titulo'],
                    'status' => 'Pendente Análise',
                    'erro' => $resultadoAnalise['error']
                ];
                cronLog('Falha de configuração/autenticação Gemini; candidatura devolvida à fila sem consumir tentativa. Corrija GEMINI_API_KEY antes da próxima execução.', 'ERROR');
                $finalizarStatusCandidato($idCandidato);
                continue;
            }

            $stmt = $conn->prepare('UPDATE candidatura SET tentativas_analise = tentativas_analise + 1 WHERE id_candidatura = ?');
            if ($stmt) {
                $stmt->bind_param('i', $idCandidatura);
                $stmt->execute();
                $stmt->close();
            }

            $tentaStmt = $conn->prepare('SELECT tentativas_analise FROM candidatura WHERE id_candidatura = ? LIMIT 1');
            $tentativas = 0;
            if ($tentaStmt) {
                $tentaStmt->bind_param('i', $idCandidatura);
                $tentaStmt->execute();
                $linhaTentativas = $tentaStmt->get_result()->fetch_assoc();
                $tentaStmt->close();
                $tentativas = (int) ($linhaTentativas['tentativas_analise'] ?? 0);
            }

            if ($tentativas >= MAX_TENTATIVAS_ANALISE) {
                updateCandidaturaStatus($conn, $idCandidatura, 'Análise Erro');
                $resultados['total_erros']++;
                $resultados['total_em_erro']++;
                cronLog("Candidatura #$idCandidatura esgotou as tentativas de análise e foi marcada como Análise Erro.", 'ERROR');
            } else {
                updateCandidaturaStatus($conn, $idCandidatura, 'Pendente Análise');
                $resultados['total_erros']++;
                cronLog("Candidatura #$idCandidatura falhou e voltou para a fila (tentativa $tentativas/" . MAX_TENTATIVAS_ANALISE . ')', 'WARNING');
            }

            $resultados['detalhes'][] = [
                'id_candidatura' => $idCandidatura,
                'candidato' => $candidatura['candidato_nome'],
                'vaga' => $candidatura['vaga_titulo'],
                'status' => $tentativas >= MAX_TENTATIVAS_ANALISE ? 'Erro' : 'Pendente Análise',
                'erro' => $resultadoAnalise['erro'] ?? 'Erro desconhecido',
                'tentativas' => $tentativas
            ];
        }

        $finalizarStatusCandidato($idCandidato);
    }

    cronLog('========================================');
    cronLog('Processamento concluído!');
    cronLog('Total processadas: ' . $resultados['total_processadas']);
    cronLog('Sucesso: ' . $resultados['total_sucesso']);
    cronLog('Erros: ' . $resultados['total_erros']);
    cronLog('Análise Erro: ' . $resultados['total_em_erro']);
    cronLog('========================================');

    http_response_code(200);
    $respostaCron = [
        'success' => true,
        'message' => 'Processamento de análises concluído',
        'processadas' => $resultados['total_processadas'],
        'sucesso' => $resultados['total_sucesso'],
        'erros' => $resultados['total_erros'],
        'em_erro' => $resultados['total_em_erro'],
        'detalhes' => $resultados['detalhes'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($avisoConfiguracao !== null) {
        $respostaCron['aviso_configuracao'] = $avisoConfiguracao;
    }

    echo json_encode($respostaCron);

} catch (Throwable $e) {
    cronLog('Exceção: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine(), 'FATAL');
    http_response_code(500);
    echo json_encode([
        'error' => 'Não foi possível concluir o processamento das análises.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
