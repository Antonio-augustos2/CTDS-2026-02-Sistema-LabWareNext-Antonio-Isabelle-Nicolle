<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'LabWare - Transforme a GestÃ£o Laboratorial'; ?></title>
  
  <!-- Fontes e Ã­cones -->
  <link rel="shortcut icon" href="data/favicon.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=20261103-2">
  <link rel="stylesheet" href="redesign.css?v=20261103-6">
</head>
<body>
  <!-- HEADER -->
  <header>
    <div class="container header-content">
      <a href="index.php#home"><div class="logo">Lab<span>Ware</span></div></a>
      <nav>
        <?php 
          // Determina as links de navegaÃ§Ã£o baseado no arquivo atual
          $currentPage = basename($_SERVER['PHP_SELF']);
          
          if ($currentPage === 'index.php'): 
        ?>
          <a href="#sobre">Sobre</a>
          <a href="#valores">Valores</a>
          <a href="#carreira">Carreira</a>
          <a href="#vagas">Vagas</a>
          <a href="privacy_policy.php">Privacidade</a>
          <a href="admin.php" class="btn btn-outline">Login admin</a>
          <button type="button" class="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro">
            <i class="fas fa-moon"></i>
            <span>Dark</span>
          </button>
          <a href="#formulario" class="btn">Candidatar-se</a>
        <?php 
          elseif ($currentPage === 'job.php'): 
        ?>
          <a href="index.php">Voltar</a>
          <a href="privacy_policy.php">Privacidade</a>
          <a href="admin.php" class="btn btn-outline">Login admin</a>
        <?php 
          elseif ($currentPage === 'admin.php'): 
        ?>
          <a href="index.php">Voltar ao site</a>
          <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
            <a href="candidaturas.php" class="btn btn-outline">Candidaturas</a>
            <a href="membros_rh.php" class="btn btn-outline">Membros do RH</a>
            <button type="button" class="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro">
              <i class="fas fa-moon"></i>
              <span>Dark</span>
            </button>
            <form method="post" action="admin.php" class="inline-action-form">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="logout">
              <button type="submit" class="btn btn-outline">Sair</button>
            </form>
          <?php else: ?>
            <button type="button" class="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro">
              <i class="fas fa-moon"></i>
              <span>Dark</span>
            </button>
          <?php endif; ?>
        <?php 
          elseif ($currentPage === 'candidaturas.php'): 
        ?>
          <a href="index.php">Voltar ao site</a>
          <a href="admin.php" class="btn btn-outline">Painel admin</a>
          <a href="membros_rh.php" class="btn btn-outline">Membros do RH</a>
          <button type="button" class="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro">
            <i class="fas fa-moon"></i>
            <span>Dark</span>
          </button>
          <form method="post" action="admin.php" class="inline-action-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn-outline">Sair</button>
          </form>
        <?php 
          elseif ($currentPage === 'membros_rh.php'): 
        ?>
          <a href="index.php">Voltar ao site</a>
          <a href="admin.php" class="btn btn-outline">Painel admin</a>
          <a href="candidaturas.php" class="btn btn-outline">Candidaturas</a>
          <button type="button" class="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro">
            <i class="fas fa-moon"></i>
            <span>Dark</span>
          </button>
          <form method="post" action="admin.php" class="inline-action-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn-outline">Sair</button>
          </form>
        <?php 
          elseif ($currentPage === 'privacy_policy.php'): 
        ?>
          <a href="index.php">Voltar ao site</a>
          <button type="button" class="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro">
            <i class="fas fa-moon"></i>
            <span>Dark</span>
          </button>
          <a href="admin.php" class="btn btn-outline">Login admin</a>
        <?php 
          endif; 
        ?>
      </nav>
    </div>
  </header>
