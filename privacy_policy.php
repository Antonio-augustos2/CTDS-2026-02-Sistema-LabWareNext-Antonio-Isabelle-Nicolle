<?php
$pageTitle = 'Política de Privacidade | LabWare';
require_once 'config.php';
require_once 'db_functions.php';
?>
<?php include 'header.php'; ?>

<main class="privacy-page">
  <section class="privacy-hero">
    <div class="container">
      <span class="section-tag">Proteção de Dados</span>
      <h1>Política de Privacidade e Proteção de Dados</h1>
      <p>Última atualização: 15 de Setembro de 2026</p>
    </div>
  </section>

  <section class="privacy-timeline-section">
    <div class="container">
      <div class="privacy-timeline">
        <article class="privacy-item">
          <span class="privacy-node">01</span>
          <div class="privacy-card">
            <h3>1. Quais dados coletamos?</h3>
            <p>
              A LabWare valoriza a sua privacidade e a segurança dos seus dados pessoais. Esta Política de Privacidade explica
              como coletamos, utilizamos, armazenamos e protegemos as informações fornecidas por você por meio do nosso
              formulário de candidatura.
            </p>
            <ul>
              <li><strong>Dados Pessoais:</strong> nome completo e endereço de e-mail.</li>
              <li><strong>Documentos:</strong> o arquivo do seu currículo enviado em anexo, contendo seu histórico profissional, acadêmico e eventuais dados adicionais fornecidos por você.</li>
            </ul>
          </div>
        </article>

        <article class="privacy-item">
          <span class="privacy-node">02</span>
          <div class="privacy-card">
            <h3>2. Para qual finalidade utilizamos os seus dados?</h3>
            <ul>
              <li><strong>Triagem Automatizada e Análise de Perfil:</strong> utilizamos ferramentas de inteligência artificial (API do Google Gemini) para extrair e sintetizar as informações do seu currículo, facilitando o cruzamento de competências com as vagas abertas.</li>
              <li><strong>Avaliação pelo Time de RH:</strong> as informações tratadas são organizadas para análise direta pela nossa equipe de Recursos Humanos.</li>
              <li><strong>Contato:</strong> utilizaremos seu e-mail para comunicação referente ao processo seletivo, como agendamento de entrevistas, retornos e convites para futuras oportunidades.</li>
            </ul>
          </div>
        </article>

        <article class="privacy-item">
          <span class="privacy-node">03</span>
          <div class="privacy-card">
            <h3>3. Uso de Inteligência Artificial e Processamento por Terceiros</h3>
            <p>
              Para realizar a triagem do seu currículo, os dados do arquivo em anexo são processados via <strong>API do Google Gemini</strong>.
            </p>
            <ul>
              <li>Os dados enviados via API do Google Gemini são utilizados <strong>estritamente para o processamento e extração das informações</strong> do seu currículo.</li>
              <li>Seus dados <strong>são</strong> utilizados pelo provedor de IA para treinamento de modelos públicos.</li>
            </ul>
          </div>
        </article>

        <article class="privacy-item">
          <span class="privacy-node">04</span>
          <div class="privacy-card">
            <h3>4. Como e onde seus dados são armazenados?</h3>
            <p>
              Todos os dados coletados (nome, e-mail, currículo e a análise gerada pela IA) são armazenados em um banco de dados
              seguro, com acesso restrito apenas aos profissionais de Recursos Humanos e administradores do sistema autorizados.
            </p>
          </div>
        </article>

        <article class="privacy-item">
          <span class="privacy-node">05</span>
          <div class="privacy-card">
            <h3>5. Por quanto tempo mantemos seus dados?</h3>
            <p>
              Manteremos seus dados pessoais armazenados em nosso banco de dados pelo período necessário para a condução do processo
              seletivo ou pelo prazo máximo de <strong>12 meses</strong> para futuras oportunidades. Após esse período, os dados serão excluídos com segurança,
              salvo se houver obrigação legal para sua manutenção.
            </p>
          </div>
        </article>

        <article class="privacy-item">
          <span class="privacy-node">06</span>
          <div class="privacy-card">
            <h3>6. Quais são os seus direitos?</h3>
            <p>Nos termos da LGPD, você possui os seguintes direitos em relação aos seus dados pessoais:</p>
            <ul>
              <li>Confirmar a existência de tratamento e acessar seus dados.</li>
              <li>Solicitar a correção de dados incompletos, inexatos ou desatualizados.</li>
              <li>Solicitar a eliminação dos seus dados do nosso banco de dados a qualquer momento.</li>
              <li>Revogar o seu consentimento.</li>
            </ul>
            <p>
              Para exercer qualquer um desses direitos, entre em contato conosco pelo e-mail:
              <strong>suporte@labware.com.br</strong>.
            </p>
          </div>
        </article>

        <article class="privacy-item">
          <span class="privacy-node">07</span>
          <div class="privacy-card">
            <h3>7. Consentimento</h3>
            <p>
              Ao preencher o formulário, anexar seu currículo e clicar em <strong>Enviar</strong>, você declara ter lido esta política e concorda
              expressamente com a coleta, processamento via IA e armazenamento dos seus dados para as finalidades descritas.
            </p>
          </div>
        </article>
      </div>
    </div>
  </section>
</main>

<?php include 'footer.php'; ?>
