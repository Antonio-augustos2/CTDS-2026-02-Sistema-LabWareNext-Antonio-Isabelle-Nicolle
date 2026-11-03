console.log('script.js carregado!');

// FunÃ§Ã£o auxiliar para escapar HTML
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

function formatJobDescription(description) {
  const safeDescription = escapeHtml(description || '');
  return safeDescription
    .replace(/\*\*(DescriÃ§Ã£o do trabalho|Responsabilidades|Requisitos DesejÃ¡veis|RemuneraÃ§Ã£o e BenefÃ­cios|InformaÃ§Ãµes Adicionais)\*\*/g, '<strong class="job-description-heading">$1</strong>')
    .replace(/\n/g, '<br>');
}

function getPrimaryJobDescription(description) {
  const text = String(description || '');
  const structuredMatch = text.match(/\*\*DescriÃ§Ã£o do trabalho\*\*\s*([\s\S]*?)(?=\n\s*\*\*(?:Responsabilidades|Requisitos DesejÃ¡veis|RemuneraÃ§Ã£o e BenefÃ­cios|InformaÃ§Ãµes Adicionais)\*\*|$)/i);
  return structuredMatch ? structuredMatch[1].trim() : text;
}

document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM pronto!');

  initThemeToggle();
  initTestimonialsCarousel();

  // Funcionalidade de upload de arquivo
  const fileInput = document.getElementById('anexoDocumento');
  const fileButton = document.querySelector('.file-upload-button');

  console.log('fileInput:', fileInput);
  console.log('fileButton:', fileButton);

  if (fileInput && fileButton) {
    fileButton.addEventListener('click', function (event) {
      event.preventDefault();
      fileInput.click();
    });

    // Detectar quando um arquivo Ã© selecionado
    fileInput.addEventListener('change', function () {
      console.log('Change event disparado', this.files);
      
      const fileStatusContainer = document.getElementById('fileUploadStatus');
      console.log('fileStatusContainer:', fileStatusContainer);
      
      if (!fileStatusContainer) {
        console.error('fileUploadStatus container nÃ£o encontrado!');
        return;
      }

      if (this.files && this.files.length > 0) {
        const file = this.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024).toFixed(2); // Converter para KB
        
        console.log('Arquivo selecionado:', fileName, fileSize, 'KB');
        
        // Mostrar status do arquivo
        fileStatusContainer.innerHTML = `
          <div class="file-attached">
            <span class="file-icon">ðŸ“„</span>
            <div class="file-info">
              <div class="file-name">${escapeHtml(fileName)}</div>
              <div class="file-size">${fileSize} KB</div>
            </div>
            <button type="button" class="file-remove-btn" aria-label="Remover arquivo">
              <i class="fas fa-times"></i>
            </button>
          </div>
        `;
        
        // Adicionar evento para remover arquivo
        const removeBtn = fileStatusContainer.querySelector('.file-remove-btn');
        if (removeBtn) {
          removeBtn.addEventListener('click', function (event) {
            event.preventDefault();
            fileInput.value = '';
            fileStatusContainer.innerHTML = '';
          });
        }
      } else {
        fileStatusContainer.innerHTML = '';
      }
    });
  } else {
    console.error('fileInput ou fileButton nÃ£o encontrados!');
  }

  // Carregar e renderizar as vagas dinamicamente
  loadJobs();

  // Recarregar vagas quando a pÃ¡gina fica visÃ­vel (usuÃ¡rio volta Ã  aba)
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      loadJobs();
    }
  });

  // Funcionalidade para os links "Enviar currÃ­culo" genÃ©rico
  const enviarCurriculoLinks = document.querySelectorAll('a[href="#formulario"]');
  enviarCurriculoLinks.forEach(link => {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      
      const vagaSelect = document.getElementById('vaga');
      const formSection = document.getElementById('formulario');
      
      // Limpar o select para candidatura genÃ©rica
      if (vagaSelect) {
        vagaSelect.value = '';
      }
      
      // Mostrar o formulÃ¡rio
      if (formSection) {
        formSection.classList.remove('hidden');
        // Scroll suave para o formulÃ¡rio
        formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  initZoomSections();
});

function initTestimonialsCarousel() {
  const carousel = document.getElementById('testimonial-carousel');
  const indicators = document.getElementById('testimonial-indicators');
  const slides = carousel ? Array.from(carousel.querySelectorAll('.testimonial-slide')) : [];
  if (!carousel || !indicators || slides.length === 0) return;

  let currentSlide = 0;
  indicators.innerHTML = '';
  slides.forEach((slide, index) => {
    const indicator = document.createElement('button');
    indicator.type = 'button';
    indicator.className = 'indicator' + (index === 0 ? ' active' : '');
    indicator.setAttribute('aria-label', `Mostrar feedback ${index + 1}`);
    indicator.setAttribute('aria-current', index === 0 ? 'true' : 'false');
    indicator.addEventListener('click', () => showTestimonial(index));
    indicators.appendChild(indicator);
  });

  const showTestimonial = (index) => {
    currentSlide = (index + slides.length) % slides.length;
    slides.forEach((slide, slideIndex) => slide.classList.toggle('active', slideIndex === currentSlide));
    indicators.querySelectorAll('.indicator').forEach((indicator, indicatorIndex) => {
      indicator.classList.toggle('active', indicatorIndex === currentSlide);
      indicator.setAttribute('aria-current', indicatorIndex === currentSlide ? 'true' : 'false');
    });
  };

  const previousButton = document.querySelector('.testimonial-prev');
  const nextButton = document.querySelector('.testimonial-next');
  if (previousButton) previousButton.onclick = () => showTestimonial(currentSlide - 1);
  if (nextButton) nextButton.onclick = () => showTestimonial(currentSlide + 1);
  showTestimonial(0);
}

function initZoomSections() {
  const zoomSections = document.querySelectorAll('section');

  if (!zoomSections.length) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) return;

  zoomSections.forEach((section) => {
    section.classList.add('zoom-section');

    const rect = section.getBoundingClientRect();
    if (rect.top > window.innerHeight * 0.9) {
      section.classList.add('zoom-pre');
    }
  });

  const zoomObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const section = entry.target;

      if (entry.isIntersecting) {
        section.classList.remove('zoom-pre');
        section.classList.remove('zoom-out');
      } else if (entry.boundingClientRect.top < 0) {
        section.classList.add('zoom-out');
        section.classList.remove('zoom-pre');
      } else {
        section.classList.add('zoom-pre');
        section.classList.remove('zoom-out');
      }
    });
  }, {
    threshold: [0, 0.15, 0.5],
    rootMargin: '0px 0px -8% 0px'
  });

  zoomSections.forEach((section) => zoomObserver.observe(section));
}

function loadJobs() {
  // Carregar vagas da API
  console.log('Iniciando carregamento de vagas...');
  
  fetch('api_vagas.php')
    .then(response => {
      console.log('Resposta recebida:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP Error: ${response.status}`);
      }
      return response.json();
    })
    .then(jobs => {
      console.log('Vagas carregadas:', jobs);
      if (jobs.length === 0) {
        console.warn('Nenhuma vaga retornada pela API');
      }
      renderJobs(jobs);
      populateJobSelect(jobs);
    })
    .catch(error => {
      console.error('Erro ao carregar vagas:', error);
      console.error('Stack:', error.stack);
    });
}

function renderJobs(jobs) {
  const carousel = document.getElementById('jobs-carousel');
  
  console.log('Renderizando vagas. Carousel encontrado:', !!carousel);
  
  if (!carousel) return;

  // Limpar o container
  carousel.innerHTML = '';

  // Se nÃ£o hÃ¡ vagas
  if (jobs.length === 0) {
    document.getElementById('carousel-indicators')?.replaceChildren();
    const previousButton = document.getElementById('carousel-prev');
    const nextButton = document.getElementById('carousel-next');
    if (previousButton) previousButton.disabled = true;
    if (nextButton) nextButton.disabled = true;
    carousel.innerHTML = '<p style="text-align: center; grid-column: 1/-1;">Nenhuma vaga disponÃ­vel no momento.</p>';
    return;
  }

  // Renderizar cada vaga como slide do carrosel
  jobs.forEach((job, index) => {
    const jobSlide = document.createElement('div');
    jobSlide.className = 'carousel-slide' + (index === 0 ? ' active' : '');
    jobSlide.innerHTML = `
      <div class="job-card-carousel">
        <div class="job-card-summary">
          <h3>${escapeHtml(job.title)}</h3>
          <span class="job-tag">${escapeHtml(job.type)}</span>
          <p class="job-location">Local: ${escapeHtml(job.location || 'NÃ£o informado')}</p>
          <div class="job-description-block">
            <strong class="job-description-heading">DescriÃ§Ã£o do trabalho</strong>
            <span class="job-description-content">${formatJobDescription(getPrimaryJobDescription(job.description))}</span>
          </div>
          <button type="button" class="btn btn-more" data-job-id="${job.id}">Saiba mais</button>
        </div>
        <div class="job-card-full hidden">
          <h3>${escapeHtml(job.title)}</h3>
          <span class="job-tag">${escapeHtml(job.type)}</span>
          <p class="job-location">Local: ${escapeHtml(job.location || 'NÃ£o informado')}</p>
          <div class="job-description-block">${formatJobDescription(job.description)}</div>
          <div class="job-card-actions">
            <button type="button" class="btn btn-apply" data-job-id="${job.id}">Candidatar-se</button>
            <button type="button" class="btn btn-outline btn-back-summary">Voltar</button>
          </div>
        </div>
      </div>
    `;
    
    carousel.appendChild(jobSlide);
  });

  console.log('Slides criados:', jobs.length);

  // Criar indicadores
  createCarouselIndicators(jobs.length);

  // Configurar os estados resumido/completo e os botÃµes de candidatura
  setupJobDetailsButtons();
  setupApplyButtons();
  
  // Configurar navegaÃ§Ã£o do carrosel
  setupCarouselNavigation(jobs.length);
}

function setupJobDetailsButtons() {
  document.querySelectorAll('.btn-more').forEach(button => {
    button.addEventListener('click', function () {
      const card = this.closest('.job-card-carousel');
      if (!card) return;

      const jobId = this.getAttribute('data-job-id');
      fetch('api_registrar_saiba_mais.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `job_id=${encodeURIComponent(jobId)}`
      }).catch(error => console.error('NÃ£o foi possÃ­vel registrar o clique:', error));

      card.querySelector('.job-card-summary')?.classList.add('hidden');
      card.querySelector('.job-card-full')?.classList.remove('hidden');
    });
  });

  document.querySelectorAll('.btn-back-summary').forEach(button => {
    button.addEventListener('click', function () {
      const card = this.closest('.job-card-carousel');
      if (!card) return;

      card.querySelector('.job-card-full')?.classList.add('hidden');
      card.querySelector('.job-card-summary')?.classList.remove('hidden');

      // Reposiciona a tela no inÃ­cio do card enquanto ele volta ao resumo.
      const headerOffset = 96;
      const cardTop = card.getBoundingClientRect().top + window.scrollY - headerOffset;
      window.scrollTo({
        top: Math.max(0, cardTop),
        behavior: 'smooth'
      });
    });
  });
}

function createCarouselIndicators(total) {
  const indicators = document.getElementById('carousel-indicators');
  
  if (!indicators) return;

  indicators.innerHTML = '';
  
  for (let i = 0; i < total; i++) {
    const dot = document.createElement('button');
    dot.type = 'button';
    dot.className = 'indicator' + (i === 0 ? ' active' : '');
    dot.setAttribute('aria-label', `Mostrar vaga ${i + 1}`);
    dot.setAttribute('aria-current', i === 0 ? 'true' : 'false');
    dot.addEventListener('click', () => goToSlide(i));
    indicators.appendChild(dot);
  }
}

function setupCarouselNavigation(total) {
  const prevBtn = document.getElementById('carousel-prev');
  const nextBtn = document.getElementById('carousel-next');
  const carousel = document.getElementById('jobs-carousel');

  if (prevBtn) prevBtn.disabled = total <= 1;
  if (nextBtn) nextBtn.disabled = total <= 1;

  if (prevBtn) {
    prevBtn.onclick = () => {
      const currentSlide = Number(carousel?.dataset.currentSlide || 0);
      goToSlide((currentSlide - 1 + total) % total);
    };
  }

  if (nextBtn) {
    nextBtn.onclick = () => {
      const currentSlide = Number(carousel?.dataset.currentSlide || 0);
      goToSlide((currentSlide + 1) % total);
    };
  }
}

function goToSlide(index) {
  const carousel = document.getElementById('jobs-carousel');
  const indicatorsContainer = document.getElementById('carousel-indicators');
  const slides = carousel ? carousel.querySelectorAll('.carousel-slide') : [];
  const indicators = indicatorsContainer ? indicatorsContainer.querySelectorAll('.indicator') : [];
  if (carousel) carousel.dataset.currentSlide = String(index);

  slides.forEach(slide => slide.classList.remove('active'));
  indicators.forEach(indicator => indicator.classList.remove('active'));

  if (slides[index]) {
    slides[index].classList.add('active');
  }
  
  if (indicators[index]) {
    indicators[index].classList.add('active');
  }

  indicators.forEach((indicator, indicatorIndex) => {
    indicator.setAttribute('aria-current', indicatorIndex === index ? 'true' : 'false');
  });
}

function populateJobSelect(jobs) {
  const vagaSelect = document.getElementById('vaga');
  
  if (!vagaSelect) return;

  // Preservar a vaga escolhida quando as opÃ§Ãµes forem recarregadas.
  // Isso evita que o select volte para a opÃ§Ã£o inicial ao anexar um currÃ­culo
  // ou quando a pÃ¡gina dispara o recarregamento das vagas.
  const selectedJobId = vagaSelect.value;

  // Limpar opÃ§Ãµes existentes (mantendo a primeira)
  while (vagaSelect.options.length > 1) {
    vagaSelect.remove(1);
  }

  // Adicionar opÃ§Ãµes das vagas
  jobs.forEach(job => {
    const option = document.createElement('option');
    option.value = job.id;
    option.textContent = job.title;
    vagaSelect.appendChild(option);
  });

  if (selectedJobId && jobs.some(job => String(job.id) === String(selectedJobId))) {
    vagaSelect.value = selectedJobId;
  }
}

function setupApplyButtons() {
  const applyButtons = document.querySelectorAll('.btn-apply');
  const formSection = document.getElementById('formulario');
  const vagaSelect = document.getElementById('vaga');

  applyButtons.forEach(button => {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      const jobId = this.getAttribute('data-job-id');
      
      // Preencher o select da vaga
      if (vagaSelect) {
        vagaSelect.value = jobId;
      }
      
      // Mostrar o formulÃ¡rio
      if (formSection) {
        formSection.classList.remove('hidden');
        // Scroll suave para o formulÃ¡rio
        formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
}

// Funcionalidade do formulÃ¡rio de candidatura
document.addEventListener('DOMContentLoaded', function () {
  const contactForm = document.querySelector('.contact-form');
  
  if (contactForm) {
    const submitButton = contactForm.querySelector('button[type="submit"]');

    contactForm.addEventListener('submit', function (event) {
      event.preventDefault();
      
      const formData = new FormData(contactForm);
      console.log('FormulÃ¡rio enviado. Dados:', Array.from(formData.entries()));
      const vagaSelect = document.getElementById('vaga');
      const jobId = formData.get('job_id');
      const resumeFile = formData.get('formulario');
      const privacyAccepted = contactForm.querySelector('input[name="privacy_policy_accept"]');
      const temVagaSelecionada = Boolean(jobId && String(jobId).trim() !== '');

      if (!privacyAccepted || !privacyAccepted.checked) {
        alert('VocÃª precisa aceitar a PolÃ­tica de Privacidade para continuar com a candidatura.');
        privacyAccepted && privacyAccepted.focus();
        return;
      }

      if (!resumeFile || resumeFile.size === 0) {
        alert('Anexe seu currÃ­culo em formato PDF ou DOCX.');
        return;
      }

      const fileName = resumeFile.name.toLowerCase();
      if (!fileName.endsWith('.pdf') && !fileName.endsWith('.docx')) {
        alert('Formato invÃ¡lido. Envie somente arquivos .pdf ou .docx.');
        return;
      }

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.dataset.originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
      }
      
      // Enviar candidatura via API
      fetch('api_candidatura.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Resposta da API:', response.status, response.statusText);
        
        // Tentar fazer parse do JSON
        return response.json().then(data => {
          // Retornar tanto os dados quanto o status da resposta
          return { status: response.status, ok: response.ok, data: data };
        }).catch(error => {
          // Se falhar ao fazer parse do JSON
          console.error('Erro ao fazer parse do JSON:', error);
          return { 
            status: response.status, 
            ok: response.ok, 
            data: { error: 'Resposta invÃ¡lida do servidor' } 
          };
        });
      })
      .then(result => {
        console.log('Resultado processado:', result);
        const { status, ok, data } = result;
        
        // Verificar se a requisiÃ§Ã£o foi bem-sucedida
        if (ok && data.success) {
          const mensagemSucesso = temVagaSelecionada
            ? 'Candidatura enviada com sucesso! Agradecemos sua participaÃ§Ã£o.'
            : 'Candidatura cadastrada no banco de talentos com sucesso!';
          alert(mensagemSucesso);
          contactForm.reset();
          if (vagaSelect) {
            vagaSelect.value = '';
          }
          document.getElementById('formulario').classList.add('hidden');
        } else {
          // Mostrar erro especÃ­fico
          const errorMsg = data.error || data.message || `Erro do servidor (HTTP ${status})`;
          alert('Erro: ' + errorMsg);
          console.error('Erro na resposta:', data);
        }
      })
      .catch(error => {
        console.error('Erro na requisiÃ§Ã£o:', error);
        console.error('Stack:', error.stack);
        alert('Erro ao enviar candidatura. Verifique sua conexÃ£o e tente novamente.');
      })
      .finally(() => {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.innerHTML = submitButton.dataset.originalText;
        }
      });
    });
  }
});

function initThemeToggle() {
  const toggles = Array.from(document.querySelectorAll('.theme-toggle'));
  if (!toggles.length) return;

  const body = document.body;
  let savedTheme = null;
  try { savedTheme = localStorage.getItem('labware-theme'); } catch (_) {}
  const darkMode = savedTheme === 'dark';

  const applyTheme = (dark) => {
    body.classList.toggle('dark-mode', dark);
    toggles.forEach((toggle) => {
      toggle.classList.toggle('active', dark);
      toggle.setAttribute('aria-pressed', String(dark));
      toggle.setAttribute('aria-label', dark ? 'Ativar modo claro' : 'Ativar modo escuro');
      toggle.setAttribute('title', dark ? 'Ativar modo claro' : 'Ativar modo escuro');
      const icon = toggle.querySelector('i');
      const label = toggle.querySelector('span');
      if (icon) {
        icon.classList.toggle('fa-moon', !dark);
        icon.classList.toggle('fa-sun', dark);
      }
      if (label) label.textContent = dark ? 'Modo claro' : 'Modo escuro';
    });
    try { localStorage.setItem('labware-theme', dark ? 'dark' : 'light'); } catch (_) {}
  };

  applyTheme(darkMode);
  toggles.forEach((toggle) => toggle.addEventListener('click', () => {
    applyTheme(!body.classList.contains('dark-mode'));
  }));
}

// InteraÃ§Ãµes visuais do destaque editorial da pÃ¡gina inicial.
document.addEventListener('DOMContentLoaded', function () {
  const strip = document.querySelector('.headline-strip');
  const cards = strip ? Array.from(strip.querySelectorAll('.headline-card')) : [];
  if (!cards.length) return;

  let activeIndex = 0;
  let timer;
  const showHeadline = (index) => {
    activeIndex = (index + cards.length) % cards.length;
    cards.forEach((card, i) => {
      const isActive = i === activeIndex;
      card.classList.toggle('active', isActive);
      card.setAttribute('aria-current', isActive ? 'true' : 'false');
      card.setAttribute('aria-hidden', String(!isActive));
    });
  };
  const startRotation = () => {
    window.clearInterval(timer);
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      timer = window.setInterval(() => showHeadline(activeIndex + 1), 6000);
    }
  };

  strip.querySelector('.headline-prev')?.addEventListener('click', () => { showHeadline(activeIndex - 1); startRotation(); });
  strip.querySelector('.headline-next')?.addEventListener('click', () => { showHeadline(activeIndex + 1); startRotation(); });
  strip.addEventListener('mouseenter', () => window.clearInterval(timer));
  strip.addEventListener('mouseleave', startRotation);
  strip.addEventListener('focusin', () => window.clearInterval(timer));
  strip.addEventListener('focusout', (event) => {
    if (!strip.contains(event.relatedTarget)) startRotation();
  });
  showHeadline(0);
  startRotation();

  const visual = document.querySelector('.editorial-visual');
  if (visual && window.matchMedia('(pointer: fine)').matches && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    visual.addEventListener('pointermove', (event) => {
      const rect = visual.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - .5;
      const y = (event.clientY - rect.top) / rect.height - .5;
      visual.style.transform = `perspective(1000px) rotateY(${x * 3}deg) rotateX(${-y * 3}deg) translateY(-2px)`;
    });
    visual.addEventListener('pointerleave', () => { visual.style.transform = ''; });
  }
});
