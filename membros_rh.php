<?php
session_start();

require_once 'config.php';
require_once 'db_functions.php';

ensureRhCargoColumn($conn);

$csrfToken = getCsrfToken();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$errors = [];
$success = $_GET['sucesso'] ?? '';
$nome = '';
$email = '';
$cargo = '';
$passwordsMustBeReentered = false;
$loggedInMemberId = (int) ($_SESSION['admin_id'] ?? 0);
$editingId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
$searchQuery = trim($_GET['busca'] ?? '');

if ($editingId > 0) {
    if ($editingId !== $loggedInMemberId) {
        $editingId = 0;
        $errors[] = 'Você só pode alterar os dados do próprio acesso.';
    } else {
        $editQuery = $conn->prepare('SELECT nome, login_email, cargo FROM rh WHERE id_rh = ? LIMIT 1');
        $editQuery->bind_param('i', $editingId);
        $editQuery->execute();
        $editingMember = $editQuery->get_result()->fetch_assoc();
        $editQuery->close();

        if (!$editingMember) {
            $editingId = 0;
            $errors[] = 'Membro não encontrado.';
        } else {
            $nome = $editingMember['nome'];
            $email = $editingMember['login_email'];
            $cargo = $editingMember['cargo'] ?? '';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    $action = $_POST['action'] ?? '';
    $memberId = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT) ?: 0;

    if ($action === 'delete') {
        if ($memberId !== $loggedInMemberId) {
            header('Location: membros_rh.php?sucesso=nao_autorizado');
            exit;
        }

        $delete = $conn->prepare('DELETE FROM rh WHERE id_rh = ?');
        $delete->bind_param('i', $memberId);
        $delete->execute();
        $delete->close();
        session_unset();
        session_destroy();
        header('Location: admin.php?sucesso=conta_excluida');
        exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';

    $isEditing = $action === 'update' && $memberId > 0;
    $editingId = $isEditing ? $memberId : 0;

    if ($isEditing && $memberId !== $loggedInMemberId) {
        $errors[] = 'Você só pode alterar os dados do próprio acesso.';
    }

    if ($nome === '' || mb_strlen($nome) < 2) {
        $errors[] = 'Informe um nome válido.';
    }

    if ($cargo === '' || mb_strlen($cargo) < 2) {
        $errors[] = 'Informe o cargo da pessoa.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }

    if (!$isEditing && strlen($password) < 8) {
        $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    }

    if (($password !== '' || $passwordConfirmation !== '') && $password !== $passwordConfirmation) {
        $errors[] = 'A confirmação da senha não confere.';
        $passwordsMustBeReentered = true;
        $password = '';
        $passwordConfirmation = '';
    }

    if ($isEditing && $password !== '' && strlen($password) < 8) {
        $errors[] = 'A nova senha deve ter pelo menos 8 caracteres.';
    }

    if (!$errors) {
        $check = $conn->prepare('SELECT id_rh FROM rh WHERE login_email = ? AND id_rh <> ? LIMIT 1');
        $check->bind_param('si', $email, $memberId);
        $check->execute();
        $existingMember = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existingMember) {
            $errors[] = 'Já existe um membro cadastrado com este e-mail.';
        }
    }

    if (!$errors && $isEditing) {
        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE rh SET nome = ?, cargo = ?, login_email = ?, senha = ? WHERE id_rh = ?');
            $update->bind_param('ssssi', $nome, $cargo, $email, $passwordHash, $memberId);
        } else {
            $update = $conn->prepare('UPDATE rh SET nome = ?, cargo = ?, login_email = ? WHERE id_rh = ?');
            $update->bind_param('sssi', $nome, $cargo, $email, $memberId);
        }

        if ($update->execute()) {
            $update->close();
            header('Location: membros_rh.php?sucesso=atualizado');
            exit;
        }

        $errors[] = 'Não foi possível atualizar o membro. Tente novamente.';
        $update->close();
    }

    if (!$errors && !$isEditing) {
        $companyResult = $conn->query('SELECT id_empresa FROM empresa ORDER BY id_empresa LIMIT 1');
        $company = $companyResult ? $companyResult->fetch_assoc() : null;

        if (!$company) {
            $errors[] = 'Nenhuma empresa está cadastrada para vincular o membro.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare('INSERT INTO rh (nome, cargo, login_email, id_empresa, senha) VALUES (?, ?, ?, ?, ?)');
            $companyId = (int) $company['id_empresa'];
            $insert->bind_param('sssis', $nome, $cargo, $email, $companyId, $passwordHash);

            if ($insert->execute()) {
                $insert->close();
                header('Location: membros_rh.php?sucesso=cadastrado');
                exit;
            }

            $errors[] = 'Não foi possível cadastrar o membro. Tente novamente.';
            $insert->close();
        }
    }
}

$members = [];
$searchPattern = '%' . $searchQuery . '%';
$listSql = 'SELECT id_rh, nome, cargo, login_email FROM rh WHERE nome LIKE ? OR cargo LIKE ? OR login_email LIKE ?';
if ($searchQuery === '') {
    $listSql .= ' ORDER BY CASE WHEN id_rh = ? THEN 0 ELSE 1 END, nome ASC';
    $listQuery = $conn->prepare($listSql);
    $listQuery->bind_param('sssi', $searchPattern, $searchPattern, $searchPattern, $loggedInMemberId);
} else {
    $listSql .= ' ORDER BY nome ASC';
    $listQuery = $conn->prepare($listSql);
    $listQuery->bind_param('sss', $searchPattern, $searchPattern, $searchPattern);
}
$listQuery->execute();
$result = $listQuery->get_result();
if ($result) {
    while ($member = $result->fetch_assoc()) {
        $members[] = $member;
    }
}
$listQuery->close();

$pageTitle = 'Membros do RH - LabWare';
include 'header.php';
?>
<main class="container admin-page">
    <section class="admin-header analysis-header">
        <span class="analysis-eyebrow"><i class="fas fa-user-plus"></i> Gestão de acessos</span>
        <h2>Membros do RH</h2>
        <p>Cadastre pessoas autorizadas a acessar o painel administrativo.</p>
    </section>

    <?php if ($success === 'atualizado'): ?>
        <div class="alert alert-success" role="status">Membro do RH atualizado com sucesso.</div>
    <?php elseif ($success === 'nao_autorizado'): ?>
        <div class="alert alert-error" role="alert">Você só pode excluir o próprio acesso.</div>
    <?php elseif ($success === 'cadastrado'): ?>
        <div class="alert alert-success" role="status">Membro do RH cadastrado com sucesso.</div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="admin-grid member-management-grid">
        <section class="admin-card admin-form-card" aria-labelledby="new-member-title">
            <span class="card-eyebrow">Novo acesso</span>
            <h3 id="new-member-title"><?= $editingId ? 'Editar membro' : 'Cadastrar membro' ?></h3>
            <form method="post" action="membros_rh.php" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="<?= $editingId ? 'update' : 'create' ?>">
                <input type="hidden" name="member_id" value="<?= (int) $editingId ?>">
                <div class="form-group">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($nome) ?>" required autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="cargo">Cargo</label>
                    <input type="text" id="cargo" name="cargo" value="<?= htmlspecialchars($cargo) ?>" required placeholder="Ex.: Analista de RH" autocomplete="organization-title">
                </div>
                <div class="form-group">
                    <label for="email">E-mail de acesso</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" <?= $editingId ? '' : 'required' ?> minlength="8" autocomplete="new-password" <?= $passwordsMustBeReentered ? 'autofocus' : '' ?> value="">
                    <small class="form-help"><?= $editingId ? 'Deixe em branco para manter a senha atual.' : 'Use pelo menos 8 caracteres.' ?></small>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirmar senha</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password" value="">
                </div>
                <div class="member-form-actions">
                    <button type="submit" class="btn"><i class="fas <?= $editingId ? 'fa-save' : 'fa-user-plus' ?>"></i> <?= $editingId ? 'Salvar alterações' : 'Cadastrar membro' ?></button>
                    <?php if ($editingId): ?>
                        <a href="membros_rh.php" class="btn btn-outline">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="admin-card admin-list-card" aria-labelledby="members-title">
            <span class="card-eyebrow">Acessos cadastrados</span>
            <h3 id="members-title">Membros do RH</h3>
            <form method="get" action="membros_rh.php" class="search-bar">
                <input type="search" name="busca" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Pesquisar por nome, cargo ou e-mail" aria-label="Pesquisar membros do RH">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Pesquisar</button>
                <?php if ($searchQuery !== ''): ?>
                    <a href="membros_rh.php" class="btn btn-outline">Limpar</a>
                <?php endif; ?>
            </form>
            <?php if (!$members): ?>
                <p class="empty-state">Nenhum membro cadastrado.</p>
            <?php else: ?>
                <div class="analysis-table-wrapper member-table-wrapper">
                    <table class="analysis-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Cargo</th>
                                <th>E-mail</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['nome']) ?></td>
                                    <td><?= htmlspecialchars($member['cargo'] ?? 'RH') ?></td>
                                    <td><?= htmlspecialchars($member['login_email']) ?></td>
                                    <td class="actions-row">
                                        <?php if ((int) $member['id_rh'] === $loggedInMemberId): ?>
                                            <a href="membros_rh.php?editar=<?= (int) $member['id_rh'] ?>" class="btn btn-outline action-button" title="Editar seu acesso" aria-label="Editar seu acesso"><i class="fas fa-pen"></i></a>
                                        <?php endif; ?>
                                        <?php if ((int) $member['id_rh'] === $loggedInMemberId): ?>
                                            <form method="post" action="membros_rh.php" onsubmit="return confirm('Excluir este membro do RH?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="member_id" value="<?= (int) $member['id_rh'] ?>">
                                                <button type="submit" class="btn btn-danger action-button" title="Excluir membro" aria-label="Excluir membro"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php include 'footer.php'; ?>
