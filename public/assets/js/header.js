// Cabeçalho: menu móvel (painel de ecrã inteiro), sombra ao fazer scroll e foco preso no menu.
// Eventos: 'festeja:menu' em document com detail.open, para outros módulos (buybar.js) reagirem.

const FOCAVEIS = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

export default function init(root = document) {
  const header = root.querySelector('[data-header]');
  if (!header) {
    return;
  }

  const toggle = header.querySelector('[data-menu-toggle]');
  const menu = document.getElementById(toggle?.getAttribute('aria-controls') ?? '');
  const rotulo = toggle?.querySelector('[data-menu-label]');
  // Fora do menu, o conteúdo fica inerte enquanto o menu está aberto.
  const fora = [document.querySelector('main'), document.querySelector('footer')].filter(Boolean);
  const porDesktop = matchMedia('(min-width: 1024px)');

  // Sombra fina depois de 8px de scroll.
  const aoRolar = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
  window.addEventListener('scroll', aoRolar, { passive: true });
  aoRolar();

  if (!toggle || !menu) {
    return;
  }

  const estaAberto = () => toggle.getAttribute('aria-expanded') === 'true';

  const definir = (aberto) => {
    toggle.setAttribute('aria-expanded', String(aberto));
    menu.hidden = !aberto;
    // Rótulos no idioma da página: data-rotulo-abrir / data-rotulo-fechar (SiteHeader.php).
    rotulo.textContent = (aberto ? rotulo.dataset.rotuloFechar : rotulo.dataset.rotuloAbrir) ?? '';
    document.body.style.overflow = aberto ? 'hidden' : '';
    fora.forEach((el) => el.toggleAttribute('inert', aberto));
    document.dispatchEvent(new CustomEvent('festeja:menu', { detail: { open: aberto } }));
  };

  const abrir = () => {
    definir(true);
    menu.querySelector(FOCAVEIS)?.focus();
  };

  const fechar = ({ devolverFoco = false } = {}) => {
    definir(false);
    if (devolverFoco) {
      toggle.focus();
    }
  };

  toggle.addEventListener('click', () => (estaAberto() ? fechar() : abrir()));

  // Tab e Shift+Tab dão a volta dentro do botão e do menu.
  document.addEventListener('keydown', (event) => {
    if (!estaAberto()) {
      return;
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      fechar({ devolverFoco: true });
      return;
    }
    if (event.key !== 'Tab') {
      return;
    }
    const focaveis = [toggle, ...menu.querySelectorAll(FOCAVEIS)];
    const primeiro = focaveis[0];
    const ultimo = focaveis[focaveis.length - 1];
    if (event.shiftKey && document.activeElement === primeiro) {
      event.preventDefault();
      ultimo.focus();
    } else if (!event.shiftKey && document.activeElement === ultimo) {
      event.preventDefault();
      primeiro.focus();
    }
  });

  // Ao passar para o desktop, o painel fecha sem mexer no foco.
  porDesktop.addEventListener('change', (event) => {
    if (event.matches && estaAberto()) {
      fechar();
    }
  });
}
