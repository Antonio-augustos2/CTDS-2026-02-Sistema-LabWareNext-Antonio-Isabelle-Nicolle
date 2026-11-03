<?php
session_start();

require_once 'config.php';
require_once 'db_functions.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$csrfToken = getCsrfToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_candidatura') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token de segurança inválido.');
    }

    $idCandidaturaExcluir = filter_input(INPUT_POST, 'id_candidatura', FILTER_VALIDATE_INT);
    $resultadoExclusao = $idCandidaturaExcluir ? deleteCandidatura($conn, $idCandidaturaExcluir) : false;
    header('Location: candidaturas.php?' . ($resultadoExclusao ? 'excluida=1' : 'erro_exclusao=1'));
    exit;
}

ensureCandidaturaArquivoColumn($conn);
ensureCandidatoStatusColumn($conn);
$candidaturas = getCandidaturasComAnalise($conn);
$idsCandidaturasComEntrevista = getCandidaturasComEntrevista($conn);
$filtroNome = trim($_GET['nome'] ?? '');
$filtroAssertividade = $_GET['assertividade'] ?? '';
$filtroVaga = filter_input(INPUT_GET, 'vaga', FILTER_VALIDATE_INT);
$filtroStatus = trim($_GET['status'] ?? '');
$vagasDisponiveis = $candidaturas;

$candidaturas = array_filter($candidaturas, function ($candidatura) use ($filtroNome, $filtroAssertividade, $filtroVaga, $filtroStatus) {
    $status = $candidatura['candidatura_status'] ?? $candidatura['candidato_status'] ?? 'Pendente';
    $nome = (string) ($candidatura['candidato_nome'] ?? '');
    $assertividade = $candidatura['assertividade'];

    if ($filtroNome !== '' && mb_stripos($nome, $filtroNome) === false) {
        return false;
    }

    if ($filtroVaga && (int) $candidatura['id_vaga'] !== (int) $filtroVaga) {
        return false;
    }

    if ($filtroStatus !== '' && $status !== $filtroStatus) {
        return false;
    }

    if ($filtroAssertividade === 'nao_analisada' && $assertividade !== null) {
        return false;
    }

    if ($filtroAssertividade !== '' && $filtroAssertividade !== 'nao_analisada' && $assertividade !== null) {
        [$minAssertividade, $maxAssertividade] = array_map('floatval', explode('-', $filtroAssertividade));
        if ((float) $assertividade < $minAssertividade || (float) $assertividade > $maxAssertividade) {
            return false;
        }
    } elseif ($filtroAssertividade !== '' && $filtroAssertividade !== 'nao_analisada' && $assertividade === null) {
        return false;
    }

    return true;
});

usort($candidaturas, static function ($a, $b) {
    return strcasecmp((string) $a['candidato_nome'], (string) $b['candidato_nome']);
});

$totalCandidaturas = count($candidaturas);
$totalPendentes = count(array_filter($candidaturas, function($c) {
    $status = $c['candidato_status'] ?? $c['candidatura_status'] ?? 'Pendente';
    return $status === 'Pendente';
}));
$totalAnalisadas = $totalCandidaturas - $totalPendentes;

$pageTitle = 'Assertividade e Feedback - LabWare';
include 'header.php';
?>
<main class="container analysis-page">
    <section class="admin-header analysis-header">
        <span class="analysis-eyebrow"><i class="fas fa-chart-line"></i> Gestão de talentos</span>
        <h2>Assertividade e feedback das candidaturas</h2>
        <p>Consulte a relação entre cada currículo recebido, a vaga escolhida, a assertividade da análise e o feedback gerado.</p>
    </section>

    <section class="analysis-summary" aria-label="Resumo das candidaturas">
        <div class="summary-card summary-total">
            <span class="summary-icon"><i class="fas fa-users"></i></span>
            <div><strong><?= $totalCandidaturas ?></strong><span>Total de candidaturas</span></div>
        </div>
        <div class="summary-card summary-ready">
            <span class="summary-icon"><i class="fas fa-circle-check"></i></span>
            <div><strong><?= $totalAnalisadas ?></strong><span>Em análise</span></div>
        </div>
        <div class="summary-card summary-pending">
            <span class="summary-icon"><i class="fas fa-clock"></i></span>
            <div><strong><?= $totalPendentes ?></strong><span>Pendentes</span></div>
        </div>
    </section>

    <section class="admin-card analysis-card" aria-labelledby="analysis-title">
        <div class="analysis-header-toolbar">
            <div>
                <span class="card-eyebrow">Painel de análise</span>
                <h3 id="analysis-title">Currículos recebidos</h3>
            </div>
        </div>
        <?php if (isset($_GET['excluida'])): ?>
            <p class="alert-success">Candidatura excluída com sucesso.</p>
        <?php elseif (isset($_GET['erro_exclusao'])): ?>
            <p class="alert">Não foi possível excluir a candidatura.</p>
        <?php endif; ?>
        <form method="get" action="candidaturas.php" class="analysis-filters">
            <div class="analysis-filter-field">
                <label for="filtro-nome">Nome</label>
                <input type="search" id="filtro-nome" name="nome" value="<?= htmlspecialchars($filtroNome, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por nome">
            </div>
            <div class="analysis-filter-field">
                <label for="filtro-assertividade">Assertividade</label>
                <select id="filtro-assertividade" name="assertividade">
                    <option value="">Todas</option>
                    <option value="nao_analisada" <?= $filtroAssertividade === 'nao_analisada' ? 'selected' : '' ?>>Não analisada</option>
                    <option value="0-24.99" <?= $filtroAssertividade === '0-24.99' ? 'selected' : '' ?>>0% a 24%</option>
                    <option value="25-49.99" <?= $filtroAssertividade === '25-49.99' ? 'selected' : '' ?>>25% a 49%</option>
                    <option value="50-74.99" <?= $filtroAssertividade === '50-74.99' ? 'selected' : '' ?>>50% a 74%</option>
                    <option value="75-100" <?= $filtroAssertividade === '75-100' ? 'selected' : '' ?>>75% a 100%</option>
                </select>
            </div>
            <div class="analysis-filter-field">
                <label for="filtro-vaga">Vaga</label>
                <select id="filtro-vaga" name="vaga">
                    <option value="">Todas</option>
                    <?php
                    $vagasFiltro = [];
                    foreach ($vagasDisponiveis as $candidatura) {
                        $vagasFiltro[(int) $candidatura['id_vaga']] = $candidatura['vaga_titulo'];
                    }
                    asort($vagasFiltro, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach ($vagasFiltro as $idVaga => $tituloVaga):
                    ?>
                        <option value="<?= $idVaga ?>" <?= (int) $filtroVaga === $idVaga ? 'selected' : '' ?>><?= htmlspecialchars($tituloVaga) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="analysis-filter-field">
                <label for="filtro-status">Status</label>
                <select id="filtro-status" name="status">
                    <option value="">Todos</option>
                    <?php foreach (['Pendente', 'Em analise', 'Pendente Análise', 'Processando', 'Análise Completa', 'Análise Erro', 'Entrevista marcada', 'Entrevista feita'] as $statusOpcao): ?>
                        <option value="<?= htmlspecialchars($statusOpcao, ENT_QUOTES, 'UTF-8') ?>" <?= $filtroStatus === $statusOpcao ? 'selected' : '' ?>><?= htmlspecialchars($statusOpcao) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="analysis-filter-actions">
                <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrar</button>
                <?php if ($filtroNome !== '' || $filtroAssertividade !== '' || $filtroVaga || $filtroStatus !== ''): ?>
                    <a href="candidaturas.php" class="btn btn-outline">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if (count($candidaturas) === 0): ?>
            <p class="empty-state">Nenhuma candidatura encontrada.</p>
        <?php else: ?>
            <div class="analysis-table-wrapper">
            <table class="analysis-table">
                <thead>
                    <tr>
                        <th>Currículo / candidato</th>
                        <th>Vaga referente</th>
                        <th>Assertividade</th>
                        <th>Feedback</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidaturas as $candidatura): ?>
                        <?php $statusAtual = $candidatura['candidatura_status'] ?? $candidatura['candidato_status'] ?? 'Pendente'; ?>
                        <?php $statusClass = in_array($statusAtual, ['Entrevista marcada', 'Entrevista feita'], true) ? 'status-entrevista' : ($statusAtual === 'Pendente' ? 'status-pendente' : 'status-em-analise'); ?>
                        <tr>
                            <td>
                                <span class="candidate-name"><?= htmlspecialchars($candidatura['candidato_nome']) ?></span>
                                <span class="candidate-email"><?= htmlspecialchars($candidatura['candidato_email']) ?></span>
                                <span class="application-id">Candidatura #<?= (int)$candidatura['id_candidatura'] ?></span>
                                <?php if (!empty($candidatura['id_candidato'])): ?>
                                    <a href="download_curriculo.php?id=<?= (int)$candidatura['id_candidato'] ?>" class="btn btn-outline curriculum-download-button" title="Baixar currículo" aria-label="Baixar currículo de <?= htmlspecialchars($candidatura['candidato_nome']) ?>">
                                        <i class="fas fa-download"></i> Baixar currículo
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="job-name"><?= htmlspecialchars($candidatura['vaga_titulo']) ?></span>
                                <span class="application-id">Vaga #<?= (int)$candidatura['id_vaga'] ?></span>
                            </td>
                            <td>
                                <?php if ($candidatura['assertividade'] !== null): ?>
                                    <span class="score"><?= number_format((float)$candidatura['assertividade'], 2, ',', '.') ?>%</span>
                                <?php else: ?>
                                    <span class="application-id">Ainda não calculada</span>
                                <?php endif; ?>
                            </td>
                            <td class="feedback-text">
                                <?php if ($candidatura['feedback'] !== null && trim($candidatura['feedback']) !== ''): ?>
                                    <?= htmlspecialchars(trim(preg_replace('/\s+/u', ' ', (string) $candidatura['feedback'])), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span class="application-id">Ainda não disponível</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="candidate-status <?= $statusClass ?>"><?= htmlspecialchars($statusAtual) ?></span></td>
                            <td>
                                <?php $temEntrevista = !empty($idsCandidaturasComEntrevista[(int) $candidatura['id_candidatura']]); ?>
                                <?php if (!$temEntrevista): ?>
                                    <a href="admin.php?tab=entrevistas&candidatura_id=<?= (int) $candidatura['id_candidatura'] ?>" class="btn btn-outline action-button" title="Agendar entrevista" aria-label="Agendar entrevista">
                                        <i class="fas fa-calendar-plus"></i>
                                    </a>
                                <?php endif; ?>
                                <form method="post" action="candidaturas.php" class="inline-action-form" onsubmit="return confirm('Excluir esta candidatura? Essa ação não pode ser desfeita.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete_candidatura">
                                    <input type="hidden" name="id_candidatura" value="<?= (int) $candidatura['id_candidatura'] ?>">
                                    <button type="submit" class="btn btn-danger action-button" title="Excluir candidatura" aria-label="Excluir candidatura">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>
