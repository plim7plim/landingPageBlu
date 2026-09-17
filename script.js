// Edite somente estes campos quando os canais oficiais estiverem disponíveis.
const CONFIG = {
  whatsapp: "5562998406502", // Código do país + DDD + número. Apenas dígitos.
  videoUrl: "", // URL de incorporação: https://www.youtube.com/embed/ID
};

const menuBtn = document.getElementById('menuBtn');
const mobileMenu = document.getElementById('mobileMenu');
const setMenu = (open, restoreFocus = false) => {
  mobileMenu.classList.toggle('open', open);
  if (open && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    mobileMenu.animate([{ opacity: 0, transform: 'translateY(-8px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 240, easing: 'ease-out' });
  }
  menuBtn.setAttribute('aria-expanded', String(open));
  menuBtn.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
  if (restoreFocus) menuBtn.focus();
};
menuBtn.addEventListener('click', () => setMenu(menuBtn.getAttribute('aria-expanded') !== 'true'));
mobileMenu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && menuBtn.getAttribute('aria-expanded') === 'true') setMenu(false, true);
});
document.addEventListener('click', event => {
  if (!event.target.closest('.header')) setMenu(false);
});
matchMedia('(min-width: 901px)').addEventListener('change', event => { if (event.matches) setMenu(false); });

const navLinks = [...document.querySelectorAll('.desktop-nav a')];
const sections = navLinks
  .map(link => link.getAttribute('href'))
  .filter(href => href && href.startsWith('#'))
  .map(href => document.querySelector(href))
  .filter(Boolean);
let scheduled = false;
function updateNavigation() {
  let current = sections[0]?.id;
  for (const section of sections) {
    if (section.getBoundingClientRect().top <= 160) current = section.id;
  }
  navLinks.forEach(link => {
    const active = link.hash === '#' + current;
    link.classList.toggle('active', active);
    if (active) link.setAttribute('aria-current', 'location');
    else link.removeAttribute('aria-current');
  });
  scheduled = false;
}
window.addEventListener('scroll', () => {
  if (!scheduled) { scheduled = true; requestAnimationFrame(updateNavigation); }
}, { passive: true });
window.addEventListener('resize', updateNavigation);
updateNavigation();

document.querySelectorAll('.solution-card').forEach(card => {
  const link = card.querySelector('a');
  const product = card.querySelector('h3').textContent;
  link.setAttribute('aria-label', 'Conversar sobre ' + product);
});
const validPhone = /^[1-9]\d{9,14}$/.test(CONFIG.whatsapp);
const whatsappUrl = message => 'https://wa.me/' + CONFIG.whatsapp + '?text=' + encodeURIComponent(message);
if (CONFIG.videoUrl) {
  try {
    const url = new URL(CONFIG.videoUrl);
    if (url.protocol === 'https:' && ['www.youtube.com', 'www.youtube-nocookie.com'].includes(url.hostname) && /^\/embed\/[\w-]+$/.test(url.pathname)) {
      const frame = document.createElement('iframe');
      frame.src = url.href;
      frame.title = 'Vídeo institucional SOU + BLU';
      frame.loading = 'lazy';
      frame.allowFullscreen = true;
      frame.className = 'configured-video';
      document.querySelector('.about-mark').replaceWith(frame);
    }
  } catch { /* Mantém a apresentação da marca se a URL estiver incompleta. */ }
}

const maskCpfCnpj = value => {
  const digits = value.replace(/\D/g, '').slice(0, 14);
  if (digits.length <= 11) {
    return digits
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }
  return digits
    .replace(/(\d{2})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
};

const maskPhone = value => {
  const digits = value.replace(/\D/g, '').slice(0, 11);
  if (digits.length <= 10) {
    return digits
      .replace(/(\d{2})(\d)/, '($1) $2')
      .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
  }
  return digits
    .replace(/(\d{2})(\d)/, '($1) $2')
    .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
};

document.getElementById('partnerDocument')?.addEventListener('input', function () {
  this.value = maskCpfCnpj(this.value);
});
document.getElementById('partnerCommercialPhone')?.addEventListener('input', function () {
  this.value = maskPhone(this.value);
});
document.getElementById('partnerWhatsappPhone')?.addEventListener('input', function () {
  this.value = maskPhone(this.value);
});

// CONSULTA DE CNPJ (FonteData): pendente de backend.
// A API da FonteData é paga e exige uma chave secreta (header X-API-Key) que
// nunca pode ficar no código do site (front-end). Essa consulta deve rodar em
// uma function/servidor que guarde a chave em segredo e nunca devolva o
// resultado para o navegador de quem preenche o formulário — só para os
// canais internos (ex: anexado à notificação enviada à equipe).
// Falta: (1) hospedagem com suporte a backend/serverless e (2) chave de API.

const partnerForm = document.getElementById('partnerForm');
const partnerFormStatus = document.getElementById('partnerFormStatus');
if (partnerForm && partnerFormStatus) {
  partnerForm.addEventListener('submit', event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(partnerForm).entries());
    const name = (data.name || '').trim();
    const document_ = (data.document || '').trim();
    const commercialPhone = (data.commercialPhone || '').trim();
    const commercialEmail = (data.commercialEmail || '').trim();
    const termsAccepted = partnerForm.elements.terms.checked;

    if (!name || !document_ || !commercialPhone || !commercialEmail) {
      partnerFormStatus.textContent = 'Preencha nome, CNPJ/CPF, e-mail comercial e contato comercial para continuar.';
      partnerFormStatus.className = 'is-error';
      return;
    }
    if (!termsAccepted) {
      partnerFormStatus.textContent = 'É necessário aceitar os Termos e Condições de Uso para continuar.';
      partnerFormStatus.className = 'is-error';
      return;
    }

    const record = {
      id: Date.now(),
      createdAt: new Date().toISOString(),
      name,
      document: document_,
      commercialPhone,
      whatsappPhone: (data.whatsappPhone || '').trim(),
      commercialEmail,
      financialEmail: (data.financialEmail || '').trim(),
      city: (data.city || '').trim(),
      contactTime: data.contactTime || '',
      worksCommercial: data.worksCommercial || '',
      hasAddress: data.hasAddress || '',
      creditExperience: data.creditExperience || '',
      message: (data.message || '').trim(),
    };
    const storageKey = 'blu-partner-registrations';
    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
    saved.push(record);
    localStorage.setItem(storageKey, JSON.stringify(saved));

    // Envia o cadastro para a API (guardado no servidor, visível na página
    // de administração). Não bloqueia a confirmação para quem preencheu.
    fetch('/api/parceiros.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(record),
    }).catch(() => {});

    // Consulta interna de CNPJ (FonteData) - roda em segundo plano, no
    // servidor. Não bloqueia o cadastro e nada disso volta para esta página.
    if (document_.replace(/\D/g, '').length === 14) {
      fetch('/api/consulta-cnpj.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: record.id, cnpj: document_, partnerName: name }),
      }).catch(() => {});
    }

    const summary = [
      'Novo cadastro de parceiro:',
      `Nome: ${record.name}`,
      `CNPJ/CPF: ${record.document}`,
      `Contato comercial: ${record.commercialPhone}`,
      record.whatsappPhone && `Contato WhatsApp: ${record.whatsappPhone}`,
      `E-mail comercial: ${record.commercialEmail}`,
      record.financialEmail && `E-mail financeiro: ${record.financialEmail}`,
      record.city && `Cidade/UF: ${record.city}`,
      record.contactTime && `Melhor horário para contato: ${record.contactTime}`,
      record.worksCommercial && `Trabalha na área comercial: ${record.worksCommercial}`,
      record.hasAddress && `Possui endereço físico: ${record.hasAddress}`,
      record.creditExperience && `Já trabalhou no mercado de crédito: ${record.creditExperience}`,
      record.message && `Observações: ${record.message}`,
    ].filter(Boolean).join('\n');

    if (validPhone) {
      window.open(whatsappUrl(summary), '_blank', 'noopener');
      partnerFormStatus.textContent = 'Cadastro salvo! Você será direcionado ao WhatsApp para concluir o contato.';
    } else {
      partnerFormStatus.textContent = 'Cadastro salvo por aqui! Nosso WhatsApp estará disponível em breve — entraremos em contato pelos dados informados.';
    }
    partnerFormStatus.className = 'is-success';
    partnerForm.reset();
  });
}

// Revelação progressiva por seção ao rolar: cada bloco aparece inteiro,
// sem escalonar item por item. O conteúdo continua visível sem JavaScript.
const motionPreference = matchMedia('(prefers-reduced-motion: reduce)');
let revealObserver;
function configureReveals() {
  revealObserver?.disconnect();
  document.querySelectorAll('.reveal-pending').forEach(element => element.classList.remove('reveal-pending'));
  if (motionPreference.matches || !('IntersectionObserver' in window)) return;
  revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.remove('reveal-pending');
      revealObserver.unobserve(entry.target);
    });
  }, { threshold: 0.08 });
  document.querySelectorAll('.section-heading, .about-mark, .video-heading, .solutions-grid, .benefits, .steps-grid, .accordion, .partners-form').forEach(element => {
    element.classList.add('reveal-item');
    // Não ocultar conteúdo que já está na tela ou acima dela.
    if (element.getBoundingClientRect().top < innerHeight) return;
    element.classList.add('reveal-pending');
    revealObserver.observe(element);
  });
}
configureReveals();
motionPreference.addEventListener('change', configureReveals);

// A navegação por teclado nunca deixa o foco em um elemento oculto.
document.addEventListener('focusin', event => {
  const element = event.target.closest('.reveal-pending');
  if (element) {
    element.classList.remove('reveal-pending');
    revealObserver?.unobserve(element);
  }
});

const footerLegal = document.getElementById('footerLegal');
const footerLegalToggle = document.getElementById('footerLegalToggle');
if (footerLegal && footerLegalToggle) {
  const mobileLegal = matchMedia('(max-width: 700px)');
  const applyLegalState = () => {
    if (mobileLegal.matches) {
      footerLegalToggle.hidden = false;
      footerLegal.classList.add('is-collapsed');
      footerLegalToggle.textContent = 'Ler mais';
      footerLegalToggle.setAttribute('aria-expanded', 'false');
    } else {
      footerLegalToggle.hidden = true;
      footerLegal.classList.remove('is-collapsed');
    }
  };
  applyLegalState();
  mobileLegal.addEventListener('change', applyLegalState);
  footerLegalToggle.addEventListener('click', () => {
    const collapsed = footerLegal.classList.toggle('is-collapsed');
    footerLegalToggle.textContent = collapsed ? 'Ler mais' : 'Ler menos';
    footerLegalToggle.setAttribute('aria-expanded', String(!collapsed));
  });
}
