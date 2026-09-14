// Edite somente estes campos quando os canais oficiais estiverem disponíveis.
const CONFIG = {
  whatsapp: "", // Código do país + DDD + número. Apenas dígitos.
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
const sections = navLinks.map(link => document.querySelector(link.getAttribute('href'))).filter(Boolean);
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
  link.addEventListener('click', () => {
    if (validPhone) whatsappLink.href = whatsappUrl('Olá! Gostaria de saber mais sobre ' + product + '.');
  });
});
const whatsappLink = document.getElementById('whatsappLink');
const contactStatus = document.getElementById('contact-status');
const validPhone = /^[1-9]\d{9,14}$/.test(CONFIG.whatsapp);
const whatsappUrl = message => 'https://wa.me/' + CONFIG.whatsapp + '?text=' + encodeURIComponent(message);
if (validPhone) {
  whatsappLink.href = whatsappUrl('Olá! Gostaria de conhecer as opções de crédito da SOU + BLU.');
  whatsappLink.target = '_blank';
  whatsappLink.rel = 'noopener noreferrer';
  contactStatus.textContent = 'Você será direcionado ao WhatsApp.';
} else {
  whatsappLink.setAttribute('aria-disabled', 'true');
  whatsappLink.addEventListener('click', event => {
    event.preventDefault();
    contactStatus.textContent = 'Nosso WhatsApp estará disponível em breve. Agradecemos seu interesse!';
  });
}
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

// Entradas progressivas: o conteúdo continua visível sem JavaScript.
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
  document.querySelectorAll('.section-heading, .solution-card, .benefit, .step, .why-copy, .about-mark, .video-heading, .accordion details, .contact-box').forEach(element => {
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
