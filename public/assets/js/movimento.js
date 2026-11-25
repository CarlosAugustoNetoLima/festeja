// Movimento com GSAP (ScrollTrigger e ScrollToPlugin). main.js carrega este módulo depois do load e só sem
// prefers-reduced-motion. Sem GSAP, ou com reduced-motion, o site fica como está: os estados iniciais escondidos são
// aplicados aqui (gsap.set), nunca no CSS, e só a elementos ainda abaixo da dobra no arranque.
// Marcação: data-mov="revelar | escalonar | parallax | palavras | inclinar" e data-mov-* para os parâmetros.
// Ver docs/guia-de-manutencao.md ("Quero animar um bloco ao fazer scroll").

const VENDOR = ['gsap.min.js', 'ScrollTrigger.min.js', 'ScrollToPlugin.min.js'];
const SEM_REDUCAO = '(prefers-reduced-motion: no-preference)';
const DESKTOP = '(min-width: 1024px)';
const PASSO = 0.08; // segundos entre itens de um escalonamento (data-mov-passo)

// Estado de cada efeito: "de" é aplicado com gsap.set antes do scroll; "para" é a animação ao entrar.
const ESTILOS = {
  revelar: { de: { y: 24, opacity: 0 }, para: { y: 0, opacity: 1 } },
  escala: { de: { scale: 0.94, opacity: 0 }, para: { scale: 1, opacity: 1 } },
  girar: { de: { rotation: -90, opacity: 0 }, para: { rotation: 0, opacity: 1 } },
  simbolo: { de: { scale: 0.8, opacity: 0 }, para: { scale: 1, opacity: 1 } },
  mascara: { de: { yPercent: 100, overflow: 'hidden' }, para: { yPercent: 0 } },
  sticker: { de: { y: 24, rotation: -2, opacity: 0 }, para: { y: 0, rotation: 0, opacity: 1 } },
  facto: { de: { y: 16, opacity: 0, '--facts-sep': 0 }, para: { y: 0, opacity: 1, '--facts-sep': 1 } },
};
// Pontos entre nomes do cartaz: aparecem depois de cada nome (variável --ponto, usada em site.css)
const DEPOIS = { mascara: { de: { '--ponto': 0 }, para: { '--ponto': 1 }, atraso: 0.35 } };

const bloqueios = new Set();

export default async function init(root = document) {
  await carregarGsap();
  const { gsap, ScrollTrigger, ScrollToPlugin } = window;
  gsap.registerPlugin(ScrollTrigger, ScrollToPlugin);
  ScrollTrigger.config({ ignoreMobileResize: true });

  // Menu móvel e checkout: enquanto estão abertos, os triggers não disparam (o scroll da página está bloqueado).
  document.addEventListener('festeja:menu', (event) => bloquear('menu', event.detail.open));
  const modal = document.querySelector('[data-checkout]');
  if (modal) {
    new MutationObserver(() => bloquear('checkout', !modal.hidden))
      .observe(modal, { attributes: true, attributeFilter: ['hidden'] });
  }

  // Imagens e fontes mudam a altura da página: recalcular as posições quando chegam.
  const atualizar = () => ScrollTrigger.refresh();
  document.fonts?.ready.then(atualizar);
  root.querySelectorAll('img').forEach((img) => {
    if (!img.complete) img.addEventListener('load', atualizar, { once: true });
  });

  const mm = gsap.matchMedia();

  // Navegação e revelações: em todos os tamanhos (sem reduced-motion).
  mm.add(SEM_REDUCAO, () => {
    const limpar = navegacao();
    revelacoes(root);
    return limpar;
  });

  // Parallax, palavras e faixa: só no desktop (≥ 1024 px) e sem reduced-motion.
  mm.add(`${DESKTOP} and ${SEM_REDUCAO}`, () => {
    root.querySelectorAll('[data-mov="parallax"]').forEach(parallax);
    root.querySelectorAll('[data-mov="palavras"]').forEach(palavras);
    root.querySelectorAll('[data-mov="inclinar"]').forEach(inclinar);
  });
}

// ---------------------------------------------------------------- carregamento

function carregarGsap() {
  if (window.gsap && window.ScrollTrigger && window.ScrollToPlugin) {
    return Promise.resolve();
  }
  // Ficheiros UMD: carregados por ordem (os plugins precisam do gsap já definido)
  return VENDOR.reduce(
    (antes, ficheiro) => antes.then(() => carregarScript(new URL(`../vendor/gsap/${ficheiro}`, import.meta.url).href)),
    Promise.resolve(),
  );
}

function carregarScript(src) {
  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = () => reject(new Error(`GSAP não carregado: ${src}`));
    document.head.append(script);
  });
}

// ---------------------------------------------------------------- utilitários

const dados = (el, nome, padrao) => {
  const valor = Number.parseFloat(el.dataset[nome]);
  return Number.isFinite(valor) ? valor : padrao;
};

// Só elementos ainda abaixo da dobra no arranque ganham estado inicial escondido (nunca esconder o que já está visível)
const abaixoDaDobra = (el) => el.getBoundingClientRect().top >= window.innerHeight;

function ouvir(alvo, tipo, fn, opcoes) {
  alvo.addEventListener(tipo, fn, opcoes);
  return () => alvo.removeEventListener(tipo, fn, opcoes);
}

// Bloqueia os ScrollTrigger enquanto há um motivo ativo (menu aberto, modal do checkout)
function bloquear(motivo, ativo) {
  if (ativo) {
    bloqueios.add(motivo);
  } else {
    bloqueios.delete(motivo);
  }
  if (bloqueios.size > 0) {
    window.ScrollTrigger.disable(false);
  } else {
    window.ScrollTrigger.enable(false, true);
  }
}

// ---------------------------------------------------------------- navegação

function navegacao() {
  const { gsap, ScrollTrigger } = window;
  const header = document.querySelector('[data-header]');
  if (!header) {
    return () => {};
  }

  const limpar = [];
  let escondido = false;
  const esconder = (sim) => {
    if (sim === escondido) {
      return;
    }
    escondido = sim;
    gsap.to(header, { yPercent: sim ? -100 : 0, duration: 0.25, ease: 'power2.out', overwrite: true });
  };

  // Esconde ao descer (depois de 1 ecrã) e volta ao subir. Não esconde com foco dentro ou menu aberto.
  ScrollTrigger.create({
    start: 0,
    end: 'max',
    onUpdate: (self) => {
      const menuAberto = document.querySelector('[data-menu-toggle]')?.getAttribute('aria-expanded') === 'true';
      if (self.scroll() <= 8 || menuAberto || header.contains(document.activeElement)) {
        esconder(false);
      } else if (self.direction > 0 && self.scroll() > window.innerHeight) {
        esconder(true);
      } else if (self.direction < 0) {
        esconder(false);
      }
    },
  });
  limpar.push(ouvir(header, 'focusin', () => esconder(false)));
  limpar.push(ouvir(document, 'festeja:menu', (event) => event.detail.open && esconder(false)));

  // Ligações para âncoras da página: scroll com o offset do cabeçalho e foco no título de destino
  limpar.push(ouvir(document, 'click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const link = event.target.closest?.('a[href^="#"]');
    const id = link ? link.getAttribute('href').slice(1) : '';
    const alvo = id && id !== 'conteudo' ? document.getElementById(id) : null;
    if (!alvo) {
      return;
    }
    event.preventDefault();
    irPara(alvo, id, header);
  }));

  // Indicador da página atual na navegação (aria-current), que segue o rato e o foco
  limpar.push(indicador(header));

  return () => {
    limpar.forEach((fn) => fn());
    esconder(false);
  };
}

function irPara(alvo, id, header) {
  const titulo = alvo.matches('h1, h2, h3') ? alvo : alvo.querySelector('h1, h2, h3');
  // Com scroll-behavior: smooth na página, cada scrollTo do GSAP vira um scroll animado do navegador e o autoKill do
  // ScrollToPlugin lê-o como scroll do utilizador e para a meio (sem foco). Desliga o smooth nativo só durante este scroll.
  const raiz = document.documentElement;
  const restaurar = () => { raiz.style.scrollBehavior = ''; };
  raiz.style.scrollBehavior = 'auto';
  window.gsap.to(window, {
    duration: 0.9,
    ease: 'power3.inOut',
    scrollTo: { y: alvo, offsetY: header.offsetHeight + 16 },
    onAutoKill: restaurar,
    onComplete: () => {
      restaurar();
      if (!titulo) {
        return;
      }
      titulo.setAttribute('tabindex', '-1');
      titulo.focus({ preventScroll: true });
      titulo.addEventListener('blur', () => titulo.removeAttribute('tabindex'), { once: true });
    },
  });
  history.pushState(null, '', `#${id}`);
}

// Indicador da página atual na navegação (aria-current). Desliza para o link sob o rato ou o foco e volta ao
// atual quando sai. Sem página atual (ex.: a home), fica escondido até haver um link sob o rato ou o foco.
function indicador(header) {
  const { gsap } = window;
  const lista = header.querySelector('.site-header__list');
  const links = [...(lista?.querySelectorAll('a') ?? [])];
  if (links.length === 0) {
    return () => {};
  }

  const marca = document.createElement('span');
  marca.className = 'site-header__indicador';
  marca.setAttribute('aria-hidden', 'true');
  lista.append(marca);
  lista.classList.add('is-indicador');

  const atual = () => lista.querySelector('a[aria-current="page"]');
  const ir = (link, duracao = 0.4) => {
    if (!link) {
      gsap.to(marca, { opacity: 0, duration: 0.2, overwrite: true });
      return;
    }
    gsap.to(marca, {
      x: link.parentElement.offsetLeft,
      width: link.offsetWidth,
      opacity: 1,
      duration: duracao,
      ease: 'power3.out',
      overwrite: true,
    });
  };
  const voltar = () => ir(atual());

  const limpar = [];
  links.forEach((link) => {
    limpar.push(ouvir(link, 'pointerenter', () => ir(link)));
    limpar.push(ouvir(link, 'focus', () => ir(link)));
    limpar.push(ouvir(link, 'blur', voltar));
  });
  limpar.push(ouvir(lista, 'pointerleave', voltar));
  ir(atual(), 0);

  return () => {
    limpar.forEach((fn) => fn());
    marca.remove();
    lista.classList.remove('is-indicador');
  };
}

// ---------------------------------------------------------------- revelações (data-mov="revelar" e "escalonar")

function revelacoes(root) {
  root.querySelectorAll('[data-mov="revelar"]').forEach((el) => revelar(el));
  root.querySelectorAll('[data-mov="escalonar"]').forEach((el) => escalonar(el));
}

function revelar(el) {
  const { gsap } = window;
  const estilo = ESTILOS[el.dataset.movEstilo] ?? ESTILOS.revelar;
  if (!abaixoDaDobra(el)) {
    return;
  }
  gsap.set(el, estilo.de);
  gsap.to(el, {
    ...estilo.para,
    duration: 0.8,
    ease: 'power3.out',
    clearProps: 'transform,opacity,overflow',
    scrollTrigger: { trigger: el, start: 'top 92%', once: true },
  });
}

function escalonar(el) {
  const { gsap } = window;
  const estilo = ESTILOS[el.dataset.movEstilo] ?? ESTILOS.revelar;
  const alvos = [...el.querySelectorAll(el.dataset.movAlvo || ':scope > *')].filter(abaixoDaDobra);
  if (alvos.length === 0) {
    return;
  }
  const passo = dados(el, 'movPasso', PASSO);
  gsap.set(alvos, estilo.de);

  const tl = gsap.timeline({ scrollTrigger: { trigger: el, start: 'top 92%', once: true } });
  tl.to(alvos, { ...estilo.para, duration: 0.8, ease: 'power3.out', stagger: passo, clearProps: 'transform,opacity,overflow' }, 0);

  const depois = DEPOIS[el.dataset.movEstilo];
  if (depois) {
    gsap.set(alvos, depois.de);
    tl.to(alvos, { ...depois.para, duration: 0.4, ease: 'power2.out', stagger: passo }, depois.atraso);
  }
}

// ---------------------------------------------------------------- desktop (≥ 1024 px)

// Parallax com scrub: a secção que contém o elemento é o trigger (de topo a topo). data-mov-vel: fração do
// tamanho que o elemento se desloca (positivo: sobe mais depressa que a página; negativo: sobe mais devagar).
// data-mov-giro: graus de rotação extra ao longo da mesma passagem.
function parallax(el) {
  const { gsap } = window;
  const vel = dados(el, 'movVel', 0);
  const giro = dados(el, 'movGiro', 0);
  if (vel === 0 && giro === 0) {
    return;
  }
  const vars = {
    ease: 'none',
    scrollTrigger: { trigger: el.closest('section') ?? el, start: 'top top', end: 'bottom top', scrub: true },
  };
  if (vel !== 0) {
    vars.yPercent = -vel * 100;
  }
  if (giro !== 0) {
    vars.rotation = giro;
  }
  gsap.to(el, vars);
}

// Palavras que se acendem ao scroll (de muted para a cor final). Cada palavra vira um span com aria-hidden;
// o elemento fica com aria-label = texto original, para os leitores de ecrã.
function palavras(el) {
  const { gsap } = window;
  if (!abaixoDaDobra(el)) {
    return;
  }
  const texto = el.textContent.replace(/\s+/g, ' ').trim();
  const nos = [];
  dividirPalavras(el, nos);
  el.setAttribute('aria-label', texto);

  const sonda = document.createElement('span');
  sonda.style.color = 'var(--ink-muted)';
  el.append(sonda);
  const muted = getComputedStyle(sonda).color;
  sonda.remove();

  const finais = nos.map((no) => getComputedStyle(no).color);
  nos.forEach((no) => gsap.set(no, { color: muted }));

  const tl = gsap.timeline({ scrollTrigger: { trigger: el, start: 'top 80%', end: 'bottom 45%', scrub: true } });
  const n = nos.length;
  nos.forEach((no, i) => tl.to(no, { color: finais[i], duration: 0.35, ease: 'none' }, (i / n) * 0.65));
}

function dividirPalavras(no, lista) {
  for (const filho of [...no.childNodes]) {
    if (filho.nodeType === Node.TEXT_NODE) {
      const fragmento = document.createDocumentFragment();
      for (const parte of filho.textContent.split(/(\s+)/)) {
        if (parte === '') {
          continue;
        }
        if (/^\s+$/.test(parte)) {
          fragmento.append(parte);
          continue;
        }
        const span = document.createElement('span');
        span.setAttribute('aria-hidden', 'true');
        span.textContent = parte;
        lista.push(span);
        fragmento.append(span);
      }
      filho.replaceWith(fragmento);
    } else if (filho.nodeType === Node.ELEMENT_NODE) {
      dividirPalavras(filho, lista);
    }
  }
}

// Faixa em movimento: a inclinação segue a velocidade do scroll (≤ 6°) e a velocidade da animação acompanha.
// Com a faixa pausada (botão da faixa, marquee.js) não reage.
function inclinar(faixa) {
  const { gsap, ScrollTrigger } = window;
  const viewport = faixa.querySelector('.fj-marquee__viewport');
  const track = faixa.querySelector('.fj-marquee__track');
  if (!viewport || !track) {
    return;
  }

  const inclinacao = gsap.quickTo(viewport, 'skewX', { duration: 0.5, ease: 'power3.out' });
  const ritmo = { valor: 1 };
  const acelerar = gsap.quickTo(ritmo, 'valor', {
    duration: 0.8,
    ease: 'power3.out',
    onUpdate: () => track.getAnimations().forEach((animacao) => { animacao.playbackRate = ritmo.valor; }),
  });

  let espera = null;
  ScrollTrigger.create({
    start: 0,
    end: 'max',
    onUpdate: (self) => {
      if (faixa.classList.contains('is-paused')) {
        return;
      }
      const velocidade = self.getVelocity(); // px por segundo
      inclinacao(gsap.utils.clamp(-6, 6, velocidade / 400));
      acelerar(1 + Math.min(Math.abs(velocidade) / 1500, 1.5));
      espera?.kill();
      espera = gsap.delayedCall(0.15, () => {
        inclinacao(0);
        acelerar(1);
      });
    },
  });
}
