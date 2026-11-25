// Ponto de entrada do JavaScript do site. Cada comportamento vai para o seu próprio módulo.
// Um módulo só é carregado quando o seu seletor existe na página (e o media query, se houver).

const modules = [
  { load: () => import('./header.js') },
  { selector: '[data-marquee]', load: () => import('./marquee.js') },
  { selector: '[data-tabs]', load: () => import('./tabs.js') },
  {
    selector: '[data-lineup-hover]',
    media: '(hover: hover) and (pointer: fine) and (min-width: 1024px)',
    load: () => import('./lineup-hover.js'),
  },
  { selector: '[data-buybar]', load: () => import('./buybar.js') },
  { selector: '[data-checkout]', load: () => import('./checkout.js') },
  { selector: '[data-hero]', load: () => import('./hero.js') },
];

const jobs = modules
  .filter(({ selector, media }) => (!selector || document.querySelector(selector))
    && (!media || matchMedia(media).matches))
  .map(async ({ load }) => {
    const { default: init } = await load();
    init(document);
  });

// Um módulo que falha não derruba os outros.
for (const result of await Promise.allSettled(jobs)) {
  if (result.status === 'rejected') {
    console.error('Festeja: módulo não carregado', result.reason);
  }
}

// Movimento (GSAP): só sem prefers-reduced-motion e depois do load, para não competir com o LCP.
// Sem GSAP (falha de rede, por exemplo) a página fica igual: o conteúdo nunca depende deste módulo.
if (matchMedia('(prefers-reduced-motion: no-preference)').matches && document.querySelector('[data-header], [data-mov]')) {
  const depoisDoLoad = () => new Promise((resolve) => {
    const aguardar = () => ('requestIdleCallback' in window ? window.requestIdleCallback(resolve, { timeout: 2000 }) : setTimeout(resolve, 200));
    if (document.readyState === 'complete') {
      aguardar();
    } else {
      window.addEventListener('load', aguardar, { once: true });
    }
  });

  depoisDoLoad()
    .then(() => import('./movimento.js'))
    .then(({ default: init }) => init(document))
    .catch((error) => console.error('Festeja: movimento não carregado', error));
}
