<?php
session_start();

require_once 'config.php';
require_once 'db_functions.php';
require_once 'gemini_service.php';

$csrfToken = getCsrfToken();

$loginError = '';
$editJob = null;
$searchQuery = trim($_GET['search'] ?? '');
$statusSearchQuery = trim($_GET['status_search'] ?? '');
$loggedIn = false;
$filteredJobs = [];
$statusFilteredJobs = [];
$accessLog = [];
$accessCounts = [];
$feedbacksAdmin = [];
$editFeedback = null;
$selectedAccessJobId = filter_input(INPUT_GET, 'access_job_id', FILTER_VALIDATE_INT);
$selectedCandidaturaId = filter_input(INPUT_GET, 'candidatura_id', FILTER_VALIDATE_INT);
$entrevistas = [];
$candidaturasParaEntrevista = [];
$activeTab = $_GET['tab'] ?? 'vagas';

/**
 * Divide a descrição estruturada em campos para edição.
 * Descrições antigas continuam sendo exibidas como "Descrição do trabalho".
 */
function getJobDescriptionSections($description) {
    $sections = [
        'description' => '',
        'responsibilities' => '',
        'requirements' => '',
        'benefits' => '',
        'additional' => ''
    ];

    $labels = [
        'description' => 'Descrição do trabalho',
        'responsibilities' => 'Responsabilidades',
        'requirements' => 'Requisitos Desejáveis',
        'benefits' => 'Remuneração e Benefícios',
        'additional' => 'Informações Adicionais'
    ];

    $labelPattern = implode('|', array_map(static fn($label) => preg_quote($label, '/'), $labels));
    $pattern = '/\*\*(' . $labelPattern . ')\*\*\s*(.*?)(?=\R\s*\*\*(?:' . $labelPattern . ')\*\*|$)/su';
    preg_match_all($pattern, (string) $description, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $key = array_search($match[1], $labels, true);
        if ($key !== false) {
            $sections[$key] = trim($match[2]);
        }
    }

    if (count(array_filter($sections)) === 0) {
        $sections['description'] = trim((string) $description);
    }

    return $sections;
}

function buildJobDescription($sections) {
    $labels = [
        'description' => 'Descrição do trabalho',
        'responsibilities' => 'Responsabilidades',
        'requirements' => 'Requisitos Desejáveis',
        'benefits' => 'Remuneração e Benefícios',
        'additional' => 'Informações Adicionais'
    ];
    $parts = [];

    foreach ($labels as $key => $label) {
        $content = trim((string) ($sections[$key] ?? ''));
        if ($content !== '') {
            $parts[] = '**' . $label . "**\n\n" . $content;
        }
    }

    return implode("\n\n", $parts);
}

// Verificar login
if (!empty($_SESSION['admin_logged_in'])) {
    $loggedIn = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id_rh, nome, senha FROM rh WHERE login_email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $adminUser = $result->fetch_assoc();

    if ($adminUser && password_verify($password, $adminUser['senha'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int) $adminUser['id_rh'];
        $_SESSION['admin_nome'] = $adminUser['nome'];
        header('Location: admin.php');
        exit;
    }

    $loginError = 'E-mail ou senha incorretos.';
}

if ($loggedIn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_gemini_connection') {
        $apiKey = getGeminiApiKey();
        $redirectBase = 'admin.php?tab=vagas&gemini_test=';

        if ($apiKey === '') {
            logApiError('missing_api_key', 'GEMINI_API_KEY não está configurada.', null, null, 30);
            header('Location: ' . $redirectBase . 'erro_revogada&gemini_http=0');
            exit;
        }

        if (defined('GEMINI_API_KEY_FORMAT_OK') && !GEMINI_API_KEY_FORMAT_OK) {
            $error = 'GEMINI_API_KEY com formato não reconhecido. Use uma chave padrão AIza ou uma authorization key AQ. do Gemini.';
            logApiError('invalid_api_key_format', $error, null, null, 30);
            header('Location: ' . $redirectBase . 'erro_tipo&gemini_http=0');
            exit;
        }

        if (!function_exists('curl_init')) {
            header('Location: ' . $redirectBase . 'erro_rede&gemini_http=0');
            exit;
        }

        $curl = curl_init('https://generativelanguage.googleapis.com/v1beta/models');
        if ($curl === false) {
            header('Location: ' . $redirectBase . 'erro_rede&gemini_http=0');
            exit;
        }

        curl_setopt_array($curl, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $curlError !== '') {
            header('Location: ' . $redirectBase . 'erro_rede&gemini_http=' . $httpCode);
            exit;
        }

        if ($httpCode === 200) {
            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['models']) && is_array($decoded['models'])) {
                header('Location: ' . $redirectBase . 'ok&gemini_models=' . count($decoded['models']) . '&gemini_key_len=' . strlen($apiKey));
                exit;
            }

            header('Location: ' . $redirectBase . 'erro_rede&gemini_http=200');
            exit;
        }

        if ($httpCode === 401 || $httpCode === 403) {
            $cause = stripos($response, 'ACCESS_TOKEN_TYPE_UNSUPPORTED') !== false
                ? 'o Google sinalizou incompatibilidade no tipo de credencial. Confirme a API key do AI Studio, o header x-goog-api-key e o endpoint v1beta'
                : 'chave inexistente, revogada ou desabilitada (chaves expostas publicamente podem ser revogadas automaticamente pelo Google)';
            logApiError('API_AUTH_ERROR', "HTTP $httpCode: $cause.", null, null, 30);
            $testResult = stripos($response, 'ACCESS_TOKEN_TYPE_UNSUPPORTED') !== false ? 'erro_tipo' : 'erro_revogada';
            header('Location: ' . $redirectBase . $testResult . '&gemini_http=' . $httpCode);
            exit;
        }

        header('Location: ' . $redirectBase . 'erro_rede&gemini_http=' . $httpCode);
        exit;
    }

    // Obter todas as vagas para o painel administrativo
    $jobs = getAllVagasAdmin($conn);
    $entrevistas = getEntrevistas($conn);
    $candidaturasParaEntrevista = getCandidaturasParaEntrevista($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'schedule_interview') {
        $candidaturaId = filter_input(INPUT_POST, 'candidatura_id', FILTER_VALIDATE_INT);
        $dataHoraInput = trim((string) ($_POST['data_hora'] ?? ''));

        if ($candidaturaId === false || $candidaturaId === null || $candidaturaId <= 0) {
            header('Location: admin.php?tab=entrevistas&candidatura_id=' . (int) ($candidaturaId ?? 0) . '&erro=candidatura_invalida');
            exit;
        }

        if ($dataHoraInput === '') {
            header('Location: admin.php?tab=entrevistas&candidatura_id=' . $candidaturaId . '&erro=data_passada');
            exit;
        }

        try {
            $dataHoraDateTime = new DateTime($dataHoraInput);
            if ($dataHoraDateTime <= new DateTime()) {
                header('Location: admin.php?tab=entrevistas&candidatura_id=' . $candidaturaId . '&erro=data_passada');
                exit;
            }
        } catch (Exception $e) {
            header('Location: admin.php?tab=entrevistas&candidatura_id=' . $candidaturaId . '&erro=data_passada');
            exit;
        }

        $stmt = $conn->prepare('SELECT id_entrevista FROM entrevista WHERE id_candidatura = ? AND status = ? LIMIT 1');
        if ($stmt) {
            $statusAgendada = 'Agendada';
            $stmt->bind_param('is', $candidaturaId, $statusAgendada);
            $stmt->execute();
            $jaExiste = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($jaExiste) {
                header('Location: admin.php?tab=entrevistas&candidatura_id=' . $candidaturaId . '&erro=duplicada');
                exit;
            }
        }

        $idEntrevista = createEntrevista($conn, $candidaturaId, (int) ($_SESSION['admin_id'] ?? 0), $dataHoraDateTime->format('Y-m-d H:i:s'));
        if ($idEntrevista) {
            header('Location: admin.php?tab=entrevistas&sucesso=agendada');
            exit;
        }

        header('Location: admin.php?tab=entrevistas&candidatura_id=' . $candidaturaId . '&erro=falha_salvar');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_interview') {
        $entrevistaId = filter_input(INPUT_POST, 'entrevista_id', FILTER_VALIDATE_INT);
        if ($entrevistaId !== false && $entrevistaId !== null) {
            $resultado = marcarEntrevistaFeita($conn, $entrevistaId, (int) ($_SESSION['admin_id'] ?? 0));
            if ($resultado) {
                header('Location: admin.php?tab=entrevistas&sucesso=feita');
                exit;
            }
            header('Location: admin.php?tab=entrevistas&erro=nao_encontrada');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_interview_feedback') {
        $entrevistaId = filter_input(INPUT_POST, 'entrevista_id', FILTER_VALIDATE_INT);
        $feedback = trim((string) ($_POST['feedback_entrevista'] ?? ''));
        $idRhAtual = (int) ($_SESSION['admin_id'] ?? 0);

        if ($entrevistaId === false || $entrevistaId === null) {
            header('Location: admin.php?tab=entrevistas&erro=sem_permissao');
            exit;
        }

        $resultado = updateEntrevistaFeedback($conn, $entrevistaId, $idRhAtual, $feedback);
        if ($resultado) {
            header('Location: admin.php?tab=entrevistas&sucesso=feedback');
            exit;
        }

        $entrevista = getEntrevistaById($conn, $entrevistaId);
        if (!$entrevista) {
            header('Location: admin.php?tab=entrevistas&erro=nao_feita');
            exit;
        }

        if ((int) ($entrevista['id_rh'] ?? 0) !== $idRhAtual) {
            header('Location: admin.php?tab=entrevistas&erro=sem_permissao');
            exit;
        }

        if (($entrevista['status'] ?? '') !== 'Feita') {
            header('Location: admin.php?tab=entrevistas&erro=nao_feita');
            exit;
        }

        header('Location: admin.php?tab=entrevistas&erro=feedback_invalido');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_interview') {
        $entrevistaId = filter_input(INPUT_POST, 'entrevista_id', FILTER_VALIDATE_INT);
        $idRhAtual = (int) ($_SESSION['admin_id'] ?? 0);

        if ($entrevistaId === false || $entrevistaId === null) {
            header('Location: admin.php?tab=entrevistas&erro=sem_permissao_exclusao');
            exit;
        }

        $resultado = deleteEntrevista($conn, $entrevistaId, $idRhAtual);
        if ($resultado) {
            header('Location: admin.php?tab=entrevistas&sucesso=entrevista_excluida');
            exit;
        }

        header('Location: admin.php?tab=entrevistas&erro=sem_permissao_exclusao');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_feedback') {
        $feedbackId = filter_input(INPUT_POST, 'feedback_id', FILTER_VALIDATE_INT);
        if ($feedbackId !== false && $feedbackId !== null) {
            deleteFeedback($conn, $feedbackId);
            header('Location: admin.php?tab=feedbacks');
            exit;
        }
    }

    if (isset($_GET['edit_feedback'])) {
        $feedbackId = filter_input(INPUT_GET, 'edit_feedback', FILTER_VALIDATE_INT);
        if ($feedbackId !== false && $feedbackId !== null) {
            $editFeedback = getFeedbackById($conn, $feedbackId);
            if (!$editFeedback || (int) ($editFeedback['id_rh_autor'] ?? 0) !== (int) ($_SESSION['admin_id'] ?? 0)) {
                $editFeedback = null;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_feedback') {
        $feedbackAuthor = trim($_POST['feedback_author'] ?? $_SESSION['admin_nome'] ?? '');
        $feedbackRole = trim((string) ($_SESSION['admin_nome'] ?? ''));
        $feedbackMessage = trim($_POST['feedback_message'] ?? '');
        $feedbackHighlight = !empty($_POST['feedback_highlight']);
        $feedbackId = filter_input(INPUT_POST, 'feedback_id', FILTER_VALIDATE_INT);

        if ($feedbackAuthor !== '' && $feedbackMessage !== '') {
            if ($feedbackId) {
                updateFeedback($conn, $feedbackId, $_SESSION['admin_id'], $feedbackAuthor, $feedbackRole, $feedbackMessage, $feedbackHighlight);
            } else {
                createFeedback($conn, $_SESSION['admin_id'], $feedbackAuthor, $feedbackRole, $feedbackMessage, $feedbackHighlight);
            }
            header('Location: admin.php?tab=feedbacks');
            exit;
        }
    }

    // Alterar status da vaga
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
        $statusId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
        $statusValue = trim($_POST['status_value'] ?? 'Aberta');
        $allowedStatus = ['Aberta', 'Encerrada'];

        if ($statusId !== false && $statusId !== null && in_array($statusValue, $allowedStatus, true)) {
            setVagaStatus($conn, $statusId, $statusValue);
            header('Location: admin.php?tab=status');
            exit;
        }
    }
    
    // Deletar vaga
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_job') {
        $deleteId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
        if ($deleteId !== false && $deleteId !== null) {
            deleteVaga($conn, $deleteId);
            header('Location: admin.php');
            exit;
        }
    }

    // Editar vaga
    if (isset($_GET['edit'])) {
        $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
        if ($editId !== false && $editId !== null) {
            $editJob = getVagaById($conn, $editId);
        }
    }

    // Salvar vaga (criar ou atualizar)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $descriptionSections = [
            'description' => trim($_POST['description'] ?? ''),
            'responsibilities' => trim($_POST['responsibilities'] ?? ''),
            'requirements' => trim($_POST['requirements'] ?? ''),
            'benefits' => trim($_POST['benefits'] ?? ''),
            'additional' => trim($_POST['additional'] ?? '')
        ];
        $description = buildJobDescription($descriptionSections);
        $status = trim($_POST['status'] ?? 'Aberta');
        $jobId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);

        $allowedStatus = ['Aberta', 'Encerrada'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'Aberta';
        }

        if ($title !== '' && $type !== '' && $location !== '' && $descriptionSections['description'] !== '') {
            if ($jobId) {
                // Atualizar vaga existente
                updateVaga($conn, $jobId, $title, $description, $status, $type, $location);
            } else {
                // Criar nova vaga
                createVaga($conn, $title, $description, $status, $type, $location);
            }
            header('Location: admin.php');
            exit;
        }
    }

    // Filtrar vagas por busca
    $filteredJobs = $jobs;
    if ($searchQuery !== '') {
        $filteredJobs = array_filter($jobs, function ($job) use ($searchQuery) {
            return stripos($job['title'], $searchQuery) !== false
                || stripos($job['description'], $searchQuery) !== false;
        });
    }

    $statusFilteredJobs = $jobs;
    if ($statusSearchQuery !== '') {
        $statusFilteredJobs = array_filter($jobs, function ($job) use ($statusSearchQuery) {
            $jobTitle = (string) ($job['title'] ?? '');
            $jobStatus = (string) ($job['status'] ?? 'Aberta');

            return stripos($jobTitle, $statusSearchQuery) !== false
                || stripos($jobStatus, $statusSearchQuery) !== false;
        });
    }

    // Obter histórico de acessos
    $accessLog = getAccessLog($conn, 25);
    $accessCounts = getAccessCounts($conn);
    $feedbacksAdmin = getFeedbacksAdmin($conn);

    // Ordenar vagas por número de acessos em ordem decrescente
    usort($jobs, function($a, $b) use ($accessCounts) {
        $aCount = (int) ($accessCounts[(int) $a['id']] ?? 0);
        $bCount = (int) ($accessCounts[(int) $b['id']] ?? 0);
        return $bCount - $aCount; // Decrescente: maior para menor
    });

    $ultimoErroGemini = getUltimoErroGemini($conn);
}

$editJobSections = getJobDescriptionSections($editJob['description'] ?? '');

$pageTitle = 'Admin LabWare';
include 'header.php';
?>
<main class="container admin-page <?= $loggedIn ? '' : 'admin-login-page' ?>">
    <?php if (!$loggedIn): ?>
        <section class="admin-login">
            <h2>Login de Administrador</h2>
            <p>Entre para gerenciar vagas e visualizar o histórico de acessos.</p>
            <?php if ($loginError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="post" action="admin.php" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required placeholder="admin@labware.com">
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required placeholder="Senha">
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>
        </section>
    <?php else: ?>
        <section class="admin-dashboard">
            <div class="admin-header">
                <h2>Painel Administrativo</h2>
                <p>Gerencie vagas abertas e acompanhe o acesso dos candidatos.</p>
            </div>
            <?php
            $bannerGemini = null;
            if (is_array($ultimoErroGemini ?? null)) {
                $erroGeminiTimestamp = strtotime($ultimoErroGemini['timestamp'] ?? '');
                $idadeErroGemini = $erroGeminiTimestamp !== false ? time() - $erroGeminiTimestamp : null;
                if ($idadeErroGemini !== null && $idadeErroGemini >= 0 && $idadeErroGemini < 86400) {
                    if ($ultimoErroGemini['type'] === 'API_AUTH_ERROR') {
                        $bannerGemini = stripos($ultimoErroGemini['message'], 'incompatibilidade no tipo de credencial') !== false
                            ? 'o Google sinalizou incompatibilidade no tipo de credencial; confirme a API key do AI Studio e o endpoint da integração'
                            : 'a chave Gemini está revogada, inválida ou desabilitada';
                    } elseif ($ultimoErroGemini['type'] === 'missing_api_key') {
                        $bannerGemini = 'a chave Gemini não está configurada';
                    } elseif ($ultimoErroGemini['type'] === 'invalid_api_key_format') {
                        $bannerGemini = 'a chave configurada não tem o formato esperado para uma API key Gemini';
                    }
                }
            }

            $geminiTestResult = $_GET['gemini_test'] ?? '';
            $geminiTestHttp = filter_var($_GET['gemini_http'] ?? null, FILTER_VALIDATE_INT);
            $geminiTestAlert = null;
            $geminiTestAlertClass = 'alert-error';
            if ($geminiTestResult === 'ok') {
                $modelCount = filter_var($_GET['gemini_models'] ?? 0, FILTER_VALIDATE_INT);
                $keyLength = filter_var($_GET['gemini_key_len'] ?? 0, FILTER_VALIDATE_INT);
                $keyPrefix = substr(getGeminiApiKey(), 0, 4);
                $geminiTestAlert = 'Conexão OK — ' . max(0, (int) $modelCount) . ' modelos disponíveis. Chave: ' . $keyPrefix . '*** (' . max(0, (int) $keyLength) . ' caracteres).';
                $geminiTestAlertClass = 'alert-success';
            } elseif ($geminiTestResult === 'erro_tipo') {
                $geminiTestAlert = 'O Google sinalizou incompatibilidade no tipo de credencial. Confirme a API key do AI Studio, o header x-goog-api-key e o endpoint v1beta.';
            } elseif ($geminiTestResult === 'erro_revogada') {
                $geminiTestAlert = GEMINI_API_KEY === ''
                    ? 'A GEMINI_API_KEY está ausente. Configure uma API key do Gemini no .env do servidor.'
                    : 'A chave Gemini é inexistente, revogada ou desabilitada. Gere uma nova e atualize o .env do servidor.';
            } elseif ($geminiTestResult === 'erro_rede') {
                $geminiTestAlert = $geminiTestHttp === 0
                    ? 'Não foi possível conectar à API Gemini (falha de rede ou timeout).'
                    : 'A API Gemini não respondeu com sucesso (HTTP ' . (int) $geminiTestHttp . '). Tente novamente e verifique a disponibilidade do serviço.';
            }
            ?>
            <?php if ($bannerGemini !== null): ?>
                <div class="alert alert-error" role="alert">
                    ⚠️ Triagem automática pausada: <?= htmlspecialchars($bannerGemini) ?>. Atualize a GEMINI_API_KEY no .env do servidor. Último erro: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ultimoErroGemini['timestamp']))) ?>.
                </div>
            <?php endif; ?>
            <?php if ($geminiTestAlert !== null): ?>
                <div class="alert <?= $geminiTestAlertClass ?>" role="<?= $geminiTestAlertClass === 'alert-success' ? 'status' : 'alert' ?>">
                    <?= htmlspecialchars($geminiTestAlert) ?>
                </div>
            <?php endif; ?>
            <div class="admin-card" style="margin-bottom: 1.5rem;">
                <form method="post" action="admin.php?tab=vagas" class="inline-action-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="test_gemini_connection">
                    <button type="submit" class="btn btn-outline">Testar conexão Gemini</button>
                </form>
            </div>
            <div class="admin-tabs">
                <a href="admin.php?tab=vagas" class="tab-button <?= $activeTab === 'vagas' ? 'active' : '' ?>">Vagas</a>
                <a href="admin.php?tab=status" class="tab-button <?= $activeTab === 'status' ? 'active' : '' ?>">Status das vagas</a>
                <a href="admin.php?tab=feedbacks" class="tab-button <?= $activeTab === 'feedbacks' ? 'active' : '' ?>">Feedbacks</a>
                <a href="admin.php?tab=entrevistas" class="tab-button <?= $activeTab === 'entrevistas' ? 'active' : '' ?>">Entrevistas</a>
            </div>

            <?php
            $adminAlertSuccess = null;
            $adminAlertError = null;
            $successCode = $_GET['sucesso'] ?? null;
            $errorCode = $_GET['erro'] ?? null;

            if ($successCode === 'agendada') {
                $adminAlertSuccess = 'Entrevista agendada com sucesso.';
            } elseif ($successCode === 'feita') {
                $adminAlertSuccess = 'Entrevista marcada como feita.';
            } elseif ($successCode === 'feedback') {
                $adminAlertSuccess = 'Feedback salvo com sucesso.';
            } elseif ($successCode === 'entrevista_excluida') {
                $adminAlertSuccess = 'Entrevista excluída com sucesso.';
            }

            if ($errorCode === 'data_passada') {
                $adminAlertError = 'A data da entrevista deve ser futura.';
            } elseif ($errorCode === 'duplicada') {
                $adminAlertError = 'Já existe uma entrevista agendada para essa candidatura.';
            } elseif ($errorCode === 'candidatura_invalida') {
                $adminAlertError = 'Selecione uma candidatura válida.';
            } elseif ($errorCode === 'falha_salvar') {
                $adminAlertError = 'Não foi possível salvar a entrevista.';
            } elseif ($errorCode === 'nao_encontrada') {
                $adminAlertError = 'Entrevista não encontrada ou não está mais agendada.';
            } elseif ($errorCode === 'sem_permissao') {
                $adminAlertError = 'Você não tem permissão para alterar esse feedback.';
            } elseif ($errorCode === 'sem_permissao_exclusao') {
                $adminAlertError = 'Apenas o RH que publicou a entrevista pode excluí-la.';
            } elseif ($errorCode === 'nao_feita') {
                $adminAlertError = 'A entrevista precisa estar marcada como feita antes de registrar o feedback.';
            } elseif ($errorCode === 'feedback_invalido') {
                $adminAlertError = 'O feedback deve ter entre 3 e 5000 caracteres.';
            }
            ?>

            <?php if ($adminAlertSuccess): ?>
                <div class="alert alert-success" role="status"><?= htmlspecialchars($adminAlertSuccess) ?></div>
            <?php endif; ?>
            <?php if ($adminAlertError): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars($adminAlertError) ?></div>
            <?php endif; ?>

            <?php if ($activeTab !== 'feedbacks'): ?>
            <section class="access-summary" aria-labelledby="access-summary-title">
                <div class="access-summary-header">
                    <div>
                        <span class="card-eyebrow">Interesse nas vagas</span>
                        <h3 id="access-summary-title">Interações com as vagas</h3>
                    </div>
                    <form method="get" action="admin.php" class="access-filter-form">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                        <label for="access_job_id">Filtrar vaga</label>
                        <select id="access_job_id" name="access_job_id" onchange="this.form.submit()">
                            <option value="">Todas as vagas</option>
                            <?php foreach ($jobs as $job): ?>
                                <option value="<?= (int) $job['id'] ?>" <?= ((int) $selectedAccessJobId === (int) $job['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($job['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="access-summary-grid">
                    <div class="access-counter access-counter-total">
                        <span class="access-counter-icon"><i class="fas fa-chart-column"></i></span>
                        <div><strong><?= array_sum($accessCounts) ?></strong><span>Total de interações</span></div>
                    </div>
                    <?php foreach ($jobs as $job): ?>
                        <?php if ($selectedAccessJobId === false || $selectedAccessJobId === null || (int) $selectedAccessJobId === (int) $job['id']): ?>
                            <div class="access-counter">
                                <span class="access-counter-icon"><i class="fas fa-eye"></i></span>
                                <div><strong><?= (int) ($accessCounts[(int) $job['id']] ?? 0) ?></strong><span><?= htmlspecialchars($job['title']) ?></span></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php if ($activeTab === 'feedbacks'): ?>
                <div class="admin-grid feedback-admin-grid">
                    <div class="admin-card admin-form-card">
                        <span class="card-eyebrow">Conteúdo do site</span>
                        <h3><?= $editFeedback ? 'Alterar feedback' : 'Publicar feedback' ?></h3>
                        <form method="post" action="admin.php?tab=feedbacks" class="admin-form feedback-admin-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="save_feedback">
                            <input type="hidden" name="feedback_id" value="<?= (int) ($editFeedback['id_feedback'] ?? 0) ?>">
                            <div class="form-group">
                                <label for="feedback_author">Autor do feedback</label>
                                <input type="text" id="feedback_author" name="feedback_author" value="<?= htmlspecialchars($editFeedback['autor'] ?? $_SESSION['admin_nome'] ?? '') ?>" required placeholder="Ex.: Ana Silva">
                            </div>
                            <div class="form-group">
                                <label for="feedback_message">Feedback</label>
                                <textarea id="feedback_message" name="feedback_message" rows="6" required placeholder="Escreva o depoimento que aparecerá no site."><?= htmlspecialchars($editFeedback['mensagem'] ?? '') ?></textarea>
                            </div>
                            <label class="checkbox-label"><input type="checkbox" name="feedback_highlight" value="1" <?= !empty($editFeedback['destaque']) ? 'checked' : '' ?>> Destacar este feedback em azul</label>
                            <div class="feedback-form-actions">
                                <button type="submit" class="btn feedback-submit-button"><i class="fas <?= $editFeedback ? 'fa-save' : 'fa-paper-plane' ?>"></i> <?= $editFeedback ? 'Salvar alterações' : 'Publicar feedback' ?></button>
                                <?php if ($editFeedback): ?>
                                    <a href="admin.php?tab=feedbacks" class="btn btn-outline feedback-cancel-button">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="admin-card admin-list-card">
                        <span class="card-eyebrow">Feedbacks cadastrados</span>
                        <h3>Gerenciar publicações</h3>
                        <?php if (count($feedbacksAdmin) === 0): ?>
                            <p class="empty-state">Nenhum feedback novo cadastrado.</p>
                        <?php else: ?>
                            <div class="feedback-admin-list">
                                <?php foreach ($feedbacksAdmin as $feedback): ?>
                                    <article class="feedback-admin-item">
                                        <div>
                                            <strong><?= htmlspecialchars($feedback['autor']) ?></strong>
                                            <span><?= htmlspecialchars($feedback['cargo']) ?></span>
                                            <p><?= nl2br(htmlspecialchars($feedback['mensagem'])) ?></p>
                                        </div>
                                        <div class="feedback-item-actions">
                                            <?php if ((int) ($feedback['id_rh_autor'] ?? 0) === (int) ($_SESSION['admin_id'] ?? 0)): ?>
                                                <a class="btn btn-outline feedback-edit-button" href="admin.php?tab=feedbacks&edit_feedback=<?= (int) $feedback['id_feedback'] ?>" title="Alterar feedback" aria-label="Alterar feedback"><i class="fas fa-pen"></i></a>
                                            <?php endif; ?>
                                            <form method="post" action="admin.php?tab=feedbacks" class="inline-action-form" onsubmit="return confirm('Excluir este feedback?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="delete_feedback">
                                                <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id_feedback'] ?>">
                                                <button type="submit" class="btn btn-danger feedback-delete-button" title="Excluir feedback" aria-label="Excluir feedback"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($activeTab === 'status'): ?>
                <div class="admin-card admin-list-card" style="margin-top: 1.5rem;">
                    <h3>Gerenciar status das vagas</h3>

                    <div class="search-bar" style="margin-bottom: 1.25rem;">
                        <form method="get" action="admin.php" class="preserve-scroll-form" style="display:flex; width:100%; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                            <input type="hidden" name="tab" value="status">
                            <input type="text" name="status_search" value="<?= htmlspecialchars($statusSearchQuery) ?>" placeholder="Buscar por título ou status">
                            <button type="submit" class="btn">Buscar</button>
                            <?php if ($statusSearchQuery !== ''): ?>
                                <a href="admin.php?tab=status" class="btn btn-outline">Limpar</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vaga</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusFilteredJobs as $job): ?>
                                <tr>
                                    <td><?= $job['id'] ?></td>
                                    <td><?= htmlspecialchars($job['title']) ?></td>
                                    <td>
                                        <span class="status-badge <?= ($job['status'] ?? 'Aberta') === 'Encerrada' ? 'status-closed' : 'status-open' ?>">
                                            <?= htmlspecialchars($job['status'] ?? 'Aberta') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="admin.php?tab=status" class="inline-action-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                            <input type="hidden" name="status_value" value="<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrada' : 'Aberta' ?>">
                                            <button type="submit" class="btn btn-outline">
                                                <?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrar' : 'Reabrir' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (count($statusFilteredJobs) === 0): ?>
                        <p style="margin-top: 1rem; color: #475569;">Nenhuma vaga encontrada para essa busca.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($activeTab === 'entrevistas'): ?>
                <div class="admin-grid interviews-grid">
                    <div class="admin-card admin-form-card">
                        <span class="card-eyebrow">Agendamento</span>
                        <h3>Agendar entrevista</h3>
                        <form method="post" action="admin.php?tab=entrevistas" class="admin-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="schedule_interview">
                            <div class="form-group">
                                <label for="candidatura_id">Candidatura</label>
                                <select id="candidatura_id" name="candidatura_id" required>
                                    <option value="">Selecione uma candidatura</option>
                                    <?php foreach ($candidaturasParaEntrevista as $candidatura): ?>
                                        <?php $selectedOption = ((int) ($selectedCandidaturaId ?? 0) === (int) ($candidatura['id_candidatura'] ?? 0)) ? 'selected' : ''; ?>
                                        <option value="<?= (int) ($candidatura['id_candidatura'] ?? 0) ?>" <?= $selectedOption ?>>
                                            <?= htmlspecialchars((string) ($candidatura['candidato_nome'] ?? '') . ' — ' . (string) ($candidatura['vaga_titulo'] ?? '') . ' (Candidatura #' . ((int) ($candidatura['id_candidatura'] ?? 0)) . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="data_hora">Data e hora</label>
                                <input type="datetime-local" id="data_hora" name="data_hora" value="<?= htmlspecialchars($_GET['data_hora'] ?? '') ?>" min="<?= date('Y-m-d\TH:i') ?>" required>
                                <small class="form-help">Selecione a data e hora em que a entrevista será realizada.</small>
                            </div>
                            <button type="submit" class="btn">Agendar entrevista</button>
                        </form>
                    </div>

                    <div class="admin-card admin-list-card interview-list-card">
                        <span class="card-eyebrow">Entrevistas cadastradas</span>
                        <h3>Gerenciar entrevistas</h3>

                        <?php if (count($entrevistas) === 0): ?>
                            <p class="empty-state">Nenhuma entrevista agendada.</p>
                        <?php else: ?>
                            <div class="interview-cards">
                                <?php foreach ($entrevistas as $entrevista): ?>
                                    <?php
                                    $statusEntrevista = (string) ($entrevista['status'] ?? 'Agendada');
                                    $isRhAutor = (int) ($entrevista['id_rh'] ?? 0) === (int) ($_SESSION['admin_id'] ?? 0);
                                    $feedbackAtual = trim((string) ($entrevista['resultado'] ?? ''));
                                    $entrevistaId = (int) ($entrevista['id_entrevista'] ?? 0);
                                    ?>
                                    <article class="interview-card">
                                        <div class="interview-card-header">
                                            <div>
                                                <span class="interview-card-id">Entrevista #<?= $entrevistaId ?></span>
                                                <h4><?= htmlspecialchars((string) ($entrevista['candidato_nome'] ?? '')) ?></h4>
                                                <p><?= htmlspecialchars((string) ($entrevista['candidato_email'] ?? '')) ?></p>
                                            </div>
                                            <span class="status-badge <?= in_array($statusEntrevista, ['Feita', 'Cancelada'], true) ? 'status-closed' : 'status-open' ?>">
                                                <?= htmlspecialchars($statusEntrevista) ?>
                                            </span>
                                        </div>

                                        <div class="interview-card-details">
                                            <div class="interview-detail">
                                                <span>Vaga</span>
                                                <strong><?= htmlspecialchars((string) ($entrevista['vaga_titulo'] ?? '')) ?></strong>
                                                <small>Vaga #<?= (int) ($entrevista['id_vaga'] ?? 0) ?></small>
                                            </div>
                                            <div class="interview-detail">
                                                <span>Data e hora</span>
                                                <strong><i class="fas fa-calendar-day" aria-hidden="true"></i> <?= date('d/m/Y H:i', strtotime((string) ($entrevista['data_hora'] ?? ''))) ?></strong>
                                            </div>
                                            <div class="interview-detail">
                                                <span>Agendada por</span>
                                                <strong><?= htmlspecialchars((string) ($entrevista['rh_nome'] ?? '')) ?></strong>
                                            </div>
                                        </div>

                                        <div class="interview-card-feedback">
                                            <h5>Feedback</h5>
                                            <?php if ($statusEntrevista === 'Feita' && $isRhAutor): ?>
                                                <form method="post" action="admin.php?tab=entrevistas" class="admin-form interview-feedback-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="save_interview_feedback">
                                                    <input type="hidden" name="entrevista_id" value="<?= $entrevistaId ?>">
                                                    <textarea name="feedback_entrevista" rows="3" class="interview-feedback-textarea" required><?= htmlspecialchars($feedbackAtual) ?></textarea>
                                                    <button type="submit" class="btn btn-outline">Salvar feedback</button>
                                                </form>
                                            <?php elseif ($feedbackAtual !== ''): ?>
                                                <p><?= nl2br(htmlspecialchars($feedbackAtual)) ?></p>
                                            <?php elseif ($statusEntrevista !== 'Feita'): ?>
                                                <p class="interview-feedback-hint">Disponível após marcar a entrevista como feita.</p>
                                            <?php else: ?>
                                                <p class="interview-feedback-hint">Feedback disponível apenas para quem agendou a entrevista.</p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="interview-card-actions">
                                            <?php if ($statusEntrevista === 'Agendada'): ?>
                                                <form method="post" action="admin.php?tab=entrevistas" class="inline-action-form" onsubmit="return confirm('Marcar esta entrevista como feita?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="complete_interview">
                                                    <input type="hidden" name="entrevista_id" value="<?= $entrevistaId ?>">
                                                    <button type="submit" class="btn btn-outline">Marcar como feita</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($isRhAutor): ?>
                                                <form method="post" action="admin.php?tab=entrevistas" class="inline-action-form" onsubmit="return confirm('Excluir esta entrevista?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="delete_interview">
                                                    <input type="hidden" name="entrevista_id" value="<?= $entrevistaId ?>">
                                                    <button type="submit" class="btn btn-danger">Excluir entrevista</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
            <div class="search-bar">
                <form method="get" action="admin.php" class="preserve-scroll-form" style="display:flex; width:100%; gap:0.75rem;">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Buscar vagas por título, tipo ou localidade">
                    <button type="submit" class="btn">Buscar</button>
                </form>
                <?php if ($searchQuery !== ''): ?>
                    <a href="admin.php" class="btn btn-outline">Limpar busca</a>
                <?php endif; ?>
            </div>
            <div class="admin-grid">
                <div class="admin-card admin-form-card">
                    <h3><?= $editJob ? 'Editar vaga' : 'Criar nova vaga' ?></h3>
                    <form method="post" action="admin.php" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="job_id" value="<?= $editJob['id'] ?? '' ?>">
                        <div class="form-group">
                            <label for="title">Título da vaga</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($editJob['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="type">Nível e formato</label>
                            <input type="text" id="type" name="type" value="<?= htmlspecialchars($editJob['type'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Localidade</label>
                            <input type="text" id="location" name="location" value="<?= htmlspecialchars($editJob['location'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Status da vaga</label>
                            <select id="status" name="status">
                                <option value="Aberta" <?= (($editJob['status'] ?? 'Aberta') === 'Aberta') ? 'selected' : '' ?>>Aberta</option>
                                <option value="Encerrada" <?= (($editJob['status'] ?? 'Aberta') === 'Encerrada') ? 'selected' : '' ?>>Encerrada</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="description">Descrição do trabalho</label>
                            <textarea id="description" name="description" rows="5" required placeholder="Apresente a empresa, a posição e o contexto da oportunidade."><?= htmlspecialchars($editJobSections['description']) ?></textarea>
                            <small class="form-help">Explique o propósito da vaga e o contexto da empresa.</small>
                        </div>
                        <div class="form-group">
                            <label for="responsibilities">Responsabilidades</label>
                            <textarea id="responsibilities" name="responsibilities" rows="5" placeholder="Descreva as principais atividades e entregas da posição."><?= htmlspecialchars($editJobSections['responsibilities']) ?></textarea>
                            <small class="form-help">Use parágrafos ou tópicos iniciados por •.</small>
                        </div>
                        <div class="form-group">
                            <label for="requirements">Requisitos desejáveis</label>
                            <textarea id="requirements" name="requirements" rows="4" placeholder="Ex.: Escolaridade, experiência, conhecimentos técnicos e comportamentais."><?= htmlspecialchars($editJobSections['requirements']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="benefits">Remuneração e benefícios</label>
                            <textarea id="benefits" name="benefits" rows="4" placeholder="Ex.: Salário, contratação, benefícios e condições. "><?= htmlspecialchars($editJobSections['benefits']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="additional">Informações adicionais</label>
                            <textarea id="additional" name="additional" rows="4" placeholder="Ex.: Modalidade, jornada, período e observações para candidatos."><?= htmlspecialchars($editJobSections['additional']) ?></textarea>
                        </div>
                        <button type="submit" class="btn"><?= $editJob ? 'Salvar alterações' : 'Criar vaga' ?></button>
                        <?php if ($editJob): ?>
                            <a href="admin.php" class="btn btn-outline" style="margin-left: 1rem;">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="admin-card admin-list-card">
                    <h3>Lista de vagas</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Local</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredJobs as $job): ?>
                                <tr>
                                    <td><?= $job['id'] ?></td>
                                    <td><?= htmlspecialchars($job['title']) ?></td>
                                    <td><?= htmlspecialchars($job['type']) ?></td>
                                    <td><?= htmlspecialchars($job['location']) ?></td>
                                    <td>
                                        <span class="status-badge <?= ($job['status'] ?? 'Aberta') === 'Encerrada' ? 'status-closed' : 'status-open' ?>">
                                            <?= htmlspecialchars($job['status'] ?? 'Aberta') ?>
                                        </span>
                                    </td>
                                    <td class="actions-row">
                                        <a href="admin.php?edit=<?= $job['id'] ?>" class="btn btn-outline action-button" title="Editar vaga" aria-label="Editar vaga">✏️</a>
                                        <form method="post" action="admin.php" class="inline-action-form" onsubmit="return confirm('Excluir esta vaga?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete_job">
                                            <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                            <button type="submit" class="btn btn-danger action-button" title="Excluir vaga" aria-label="Excluir vaga">🗑️</button>
                                        </form>
                                        <form method="post" action="admin.php" class="inline-action-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                            <input type="hidden" name="status_value" value="<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrada' : 'Aberta' ?>">
                                            <button type="submit" class="btn btn-outline action-button" title="<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrar vaga' : 'Reabrir vaga' ?>" aria-label="<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrar vaga' : 'Reabrir vaga' ?>">
                                                <?= (($job['status'] ?? 'Aberta') === 'Aberta') ? '🔒' : '🔓' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($filteredJobs) === 0): ?>
                        <p style="margin-top: 1rem; color: #475569;">Nenhuma vaga encontrada para essa busca.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-card access-card">
                <h3>Histórico de acessos às vagas</h3>
                <?php if (count($accessLog) === 0): ?>
                    <p>Nenhum acesso registrado ainda.</p>
                <?php else: ?>
                    <ul class="access-list">
                        <?php foreach ($accessLog as $entry): ?>
                            <li>
                                <strong><?= htmlspecialchars($entry['job_title']) ?></strong>
                                <span><?= date('d/m/Y H:i', strtotime($entry['timestamp'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script>
// Mantém a posição da lista ao abrir uma edição ou ao enviar filtros de busca.
(function () {
    const scrollStorageKey = 'adminScrollPosition';

    document.querySelectorAll('a[href*="edit="]:not([href*="edit_feedback="]), a[href*="edit_feedback="]').forEach(link => {
        link.addEventListener('click', function () {
            sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
        });
    });

    document.querySelectorAll('form.preserve-scroll-form').forEach(form => {
        form.addEventListener('submit', function () {
            sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
        });
    });

    const savedPosition = sessionStorage.getItem(scrollStorageKey);
    if (savedPosition !== null) {
        sessionStorage.removeItem(scrollStorageKey);
        const restoreScroll = () => window.scrollTo({ top: Number(savedPosition) || 0, behavior: 'auto' });
        requestAnimationFrame(() => {
            restoreScroll();
            requestAnimationFrame(restoreScroll);
        });
    }
})();
</script>
<?php include 'footer.php'; ?>
