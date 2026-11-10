<?php
$pageTitle = 'LabWare - Transforme a Gestão Laboratorial';
require_once 'config.php';
require_once 'db_functions.php';
$feedbacksPublicos = getFeedbacksPublicos($conn);
?>
<?php include 'header.php'; ?>

  <!-- Destaques editoriais e chamada de carreira -->
  <section class="hero hero-editorial" id="home" aria-label="Destaques LabWare">
    <div class="hero-atmosphere" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
    <div class="container editorial-grid">
      <div class="editorial-copy">
        <span class="editorial-eyebrow"><i class="fas fa-sparkles"></i> CIÊNCIA, TECNOLOGIA E PESSOAS</span>
        <p class="editorial-kicker">Seu talento pode mover o futuro.</p>
        <h1>Ideias grandes começam com <em>gente curiosa.</em></h1>
        <p class="editorial-intro">Na LabWare, pessoas e tecnologia transformam a rotina dos laboratórios. Traga sua curiosidade e construa o próximo capítulo com a gente.</p>
        <div class="editorial-actions"><a class="btn" href="#vagas">Encontre sua oportunidade <i class="fas fa-arrow-right"></i></a><a class="editorial-secondary" href="#sobre">Conheça a LabWare <i class="fas fa-arrow-down"></i></a></div>
        <div class="editorial-note"><span><i class="fas fa-flask"></i></span><p>Inovação com propósito<br><strong>e pessoas no centro</strong></p></div>
      </div>
      <div class="editorial-visual" data-tilt>
        <img src="data/banner-carreira-labware.png" alt="Equipe colaborando em um ambiente de tecnologia e inovação">
        <div class="visual-shade"></div>
        <div class="visual-caption"><span><i class="fas fa-circle"></i> IDEIAS EM MOVIMENTO</span><h2>O futuro começa com pessoas.</h2><a href="#carreira">Descubra sua jornada <i class="fas fa-arrow-up-right-from-square"></i></a></div>
        
      </div>
    </div>
    <div class="container headline-strip" role="region" aria-label="Manchetes em destaque" aria-roledescription="carrossel">
      <div class="headline-heading"><span>EM PAUTA</span><i class="fas fa-arrow-right"></i></div>
      <a class="headline-card active" href="https://www.labware.com/pt/lims/ml-ai" target="_blank" rel="noopener noreferrer">
        <span class="headline-number">01</span>
        <span><small>TECNOLOGIA & CIÊNCIA</small><strong>Inteligência artificial está transformando os laboratórios</strong></span>
        <i class="fas fa-arrow-up-right-from-square"></i>
      </a>
      <a class="headline-card" href="https://www.meioemensagem.com.br/videos/geracao-z-os-jovens-estao-remodelando-o-trabalho" target="_blank" rel="noopener noreferrer">
        <span class="headline-number">02</span>
        <span><small>TRABALHO & CULTURA</small><strong>Geração Z está remodelando o jeito de trabalhar</strong></span>
        <i class="fas fa-arrow-up-right-from-square"></i>
      </a>
      
      <div class="headline-controls"><button type="button" class="headline-prev" aria-label="Manchete anterior"><i class="fas fa-arrow-left"></i></button><button type="button" class="headline-next" aria-label="Próxima manchete"><i class="fas fa-arrow-right"></i></button></div>
    </div>
  </section>

  <!-- HERO anterior -->
  <section class="hero legacy-hero" id="legacy-home">
    <div class="container hero-grid">
      <div class="hero-text">
        <h1>Transforme a Gestão Laboratorial</h1>
        <p>Na LabWare, desenvolvemos soluções tecnológicas que revolucionam a gestão
           de laboratórios clínicos e de análises, tornando os processos mais eficientes, 
           organizados e seguros. Nosso objetivo é utilizar a tecnologia para otimizar 
           o dia a dia dos profissionais, facilitando o gerenciamento de informações, o 
           acompanhamento de processos e a tomada de decisões. Por meio de sistemas modernos 
           e soluções personalizadas, buscamos atender às necessidades de diferentes laboratórios, 
           contribuindo para a melhoria contínua de seus resultados, 
           produtividade e qualidade dos serviços prestados.</p>
        <a href="#vagas" class="btn">Conheça nossas vagas</a>
      </div>
      <div class="hero-image">
        <img src="data/banner-carreira-labware.png" alt="Imagem de destaque da LabWare" style="width: 100%; max-width: 520px; border-radius: 18px; object-fit: cover; display: block; margin: 0 auto;">
      </div>
    </div>
  </section>

  <!-- SOBRE -->
  <section id="sobre" class="about">
    <div class="container">
      <h2>Sobre a LabWare</h2>
      <div class="about-content">
        <p>A LabWare é uma empresa que combina tecnologia, inovação e conhecimento para transformar a gestão laboratorial. 
          Atuamos junto a laboratórios clínicos, empresas de análises químicas e centros de pesquisa, desenvolvendo soluções
           alinhadas às necessidades de cada operação.</p>
        <p>Valorizamos a inovação, a qualidade e, principalmente, as pessoas que fazem parte da nossa equipe. 
          Acreditamos que um ambiente colaborativo e o desenvolvimento constante de talentos são fundamentais 
          para criar soluções cada vez mais eficientes e contribuir para a evolução do setor.</p>
      </div>
    </div>
  </section>

  <!-- VALORES -->
  <section id="valores" class="values">
    <div class="container">
      <h2>Cultura & Valores</h2>
      <p class="section-subtitle">Nossos princípios guiam cada decisão e projeto</p>
      <div class="values-grid">
        <div class="value-card">
          <i class="fas fa-people-arrows"></i>
          <h3>Colaboração</h3>
          <p>Trabalhamos em equipe, valorizando cada perspectiva e contribuição para a inovação laboratorial.</p>
        </div>
        <div class="value-card">
          <i class="fas fa-bullseye"></i>
          <h3>Precisão</h3>
          <p>Desenvolvemos soluções com o rigor e exatidão que o ambiente laboratorial exige.</p>
        </div>
        <div class="value-card">
          <i class="fas fa-chart-line"></i>
          <h3>Crescimento</h3>
          <p>Investimos no desenvolvimento contínuo de nossos colaboradores através de treinamentos especializados.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- BENEFÍCIOS -->
  <section class="benefits">
    <div class="container">
      <h2>Por Que Trabalhar Aqui?</h2>
      <div class="benefits-grid">
        <div class="benefit-item"><i class="fas fa-home"></i> Home office flexível</div>
        <div class="benefit-item"><i class="fas fa-heartbeat"></i> Plano de saúde e odontológico completo</div>
        <div class="benefit-item"><i class="fas fa-utensils"></i> Vale refeição e alimentação</div>
        <div class="benefit-item"><i class="fas fa-graduation-cap"></i> Auxílio educação e certificações</div>
        <div class="benefit-item"><i class="fas fa-lightbulb"></i> Ambiente inovador e tecnológico</div>
        <div class="benefit-item"><i class="fas fa-microscope"></i> Equipamentos de última geração</div>
        <div class="benefit-item"><i class="fas fa-flask"></i> Participação em eventos científicos</div>
        <div class="benefit-item"><i class="fas fa-arrow-up"></i> Plano de carreira estruturado</div>
      </div>
    </div>
  </section>

  <!-- DEPOIMENTOS -->
  <section class="testimonials">
    <div class="container">
      <h2>O Que Dizem Nossos Colaboradores</h2>
      <?php if (count($feedbacksPublicos) > 0): ?>
      <div class="testimonial-carousel-container">
        <button type="button" class="carousel-btn testimonial-prev" aria-label="Feedback anterior"><i class="fas fa-chevron-left"></i></button>
        <div class="testimonial-carousel-wrapper">
          <div class="testimonial-carousel" id="testimonial-carousel">
        <?php foreach ($feedbacksPublicos as $feedback): ?>
          <div class="testimonial-slide">
            <div class="testimonial-card <?= $feedback['highlight'] ? 'testimonial-highlight' : '' ?>">
              <p><i class="fas fa-quote-left" style="margin-right: 8px; opacity: 0.7;"></i> <?= nl2br(htmlspecialchars($feedback['message'])) ?></p>
              <div class="testimonial-author"><?= htmlspecialchars($feedback['author']) ?></div>
              <?php if (!empty($feedback['role'])): ?>
                <div class="testimonial-role"><?= htmlspecialchars($feedback['role']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
          </div>
        </div>
        <button type="button" class="carousel-btn testimonial-next" aria-label="Próximo feedback"><i class="fas fa-chevron-right"></i></button>
      </div>
      <div class="testimonial-indicators" id="testimonial-indicators"></div>
      <?php else: ?>
        <p class="empty-state">Nenhum feedback publicado ainda.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- CARREIRA -->
  <section id="carreira" class="career">
    <div class="container">
      <h2>Trilha de Carreira</h2>
      <p class="section-subtitle">Acompanhe sua evolução profissional com total transparência</p>
      <div class="career-grid">
        <div class="career-level">
          <h3>Júnior</h3>
          <p style="color: #475569; margin-bottom: 0.8rem;">Início da jornada com mentoria e projetos práticos em sistemas de gestão laboratorial</p>
          <ul>
            <li><i class="fas fa-check-circle"></i> Mentoria dedicada</li>
            <li><i class="fas fa-check-circle"></i> Treinamentos em sistemas laboratoriais</li>
            <li><i class="fas fa-check-circle"></i> Projetos supervisionados</li>
            <li><i class="fas fa-check-circle"></i> Certificações técnicas</li>
          </ul>
        </div>
        <div class="career-level">
          <h3>Pleno</h3>
          <p style="color: #475569; margin-bottom: 0.8rem;">Autonomia para liderar projetos e contribuir ativamente nas decisões de arquitetura</p>
          <ul>
            <li><i class="fas fa-check-circle"></i> Liderança de projetos</li>
            <li><i class="fas fa-check-circle"></i> Decisões de arquitetura</li>
            <li><i class="fas fa-check-circle"></i> Mentoria de desenvolvedores júnior</li>
            <li><i class="fas fa-check-circle"></i> Especialização em domínios laboratoriais</li>
          </ul>
        </div>
        <div class="career-level">
          <h3>Sênior</h3>
          <p style="color: #475569; margin-bottom: 0.8rem;">Referência técnica com impacto estratégico na inovação e cultura da empresa</p>
          <ul>
            <li><i class="fas fa-check-circle"></i> Arquitetura de soluções enterprise</li>
            <li><i class="fas fa-check-circle"></i> Visão estratégica de produto</li>
            <li><i class="fas fa-check-circle"></i> Liderança técnica e cultural</li>
            <li><i class="fas fa-check-circle"></i> Inovação em gestão laboratorial</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- VAGAS -->
  <section id="vagas" class="jobs">
    <div class="container">
      <h2>Vagas Abertas</h2>
      <p class="section-subtitle">Encontre a oportunidade que combina com seu talento</p>
      
      <div class="carousel-container">
        <button type="button" class="carousel-btn carousel-prev" id="carousel-prev" aria-label="Vaga anterior">
          <i class="fas fa-chevron-left"></i>
        </button>
        
        <div class="carousel-wrapper">
          <div class="carousel" id="jobs-carousel">
            <!-- Vagas carregadas dinamicamente via JavaScript -->
          </div>
        </div>
        
        <button type="button" class="carousel-btn carousel-next" id="carousel-next" aria-label="Próxima vaga">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
      
      <div class="carousel-indicators" id="carousel-indicators">
        <!-- Indicadores carregados dinamicamente -->
      </div>
      
      <div style="text-align: center; margin-top: 3rem;">
        <p style="color: #475569; margin-bottom: 1rem;">Não encontrou sua vaga? Cadastre seu currículo no Banco de Talentos e entraremos em contato quando houver oportunidade.</p>
        <a href="#formulario" class="btn btn-outline">Enviar currículo</a>
      </div>
    </div>
  </section>

  <!-- FORMULÁRIO -->
  <section class="contact-form-section hidden" id="formulario">
    <div class="container">
      <h2>Candidate-se agora</h2>
      <p class="section-subtitle">Envie seus dados e anexe seu currículo para participar do processo seletivo.</p>
      <form class="contact-form" enctype="multipart/form-data">
        <div class="form-group">
          <label for="vaga">Selecione a vaga *</label>
          <select id="vaga" name="job_id" required>
            <option value="">-- Selecionar uma vaga --</option>
            <!-- Opções carregadas dinamicamente -->
          </select>
        </div>
        <div class="form-group">
          <label for="nome">Nome completo</label>
          <input type="text" id="nome" name="nome" placeholder="Digite seu nome completo" required>
        </div>
        <div class="form-group">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" placeholder="seu@email.com" required>
        </div>
        <div class="form-group">
          <label for="anexoDocumento">Anexo de documento</label>
          <div class="file-upload-wrapper">
            <input type="file" id="anexoDocumento" name="formulario" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required style="color: white !important;">
            <label for="anexoDocumento" class="file-upload-button" style="color: white !important;">Anexar documento</label>
            <div class="file-upload-status" id="fileUploadStatus"></div>
          </div>
        </div>
        <div class="form-group checkbox-row">
          <label class="checkbox-label" for="privacy-policy-checkbox">
            <input type="checkbox" id="privacy-policy-checkbox" name="privacy_policy_accept" value="1" required>
            Li e concordo com a <a href="privacy_policy.php" target="_blank" rel="noopener noreferrer">Política de Privacidade e Proteção de Dados</a>.
          </label>
        </div>
        <button type="submit" class="btn">Enviar candidatura</button>
      </form>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta">
    <div class="container">
      <h2>Pronto para fazer parte do nosso time?</h2>
      <p>Junte-se a nós e construa uma carreira extraordinária na vanguarda da tecnologia em saúde.</p>
      <a href="#vagas" class="btn">Ver todas as vagas</a>
    </div>
  </section>

<?php include 'footer.php'; ?>
