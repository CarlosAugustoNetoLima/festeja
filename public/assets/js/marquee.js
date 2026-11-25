// Faixa em movimento: botão de pausa (alterna is-paused e aria-pressed). Pausa também em hover e foco, no CSS.
// Com prefers-reduced-motion começa parada.

export default function init(root = document) {
  const semMovimento = matchMedia('(prefers-reduced-motion: reduce)');

  root.querySelectorAll('[data-marquee]').forEach((faixa) => {
    const botao = faixa.querySelector('[data-marquee-toggle]');
    if (!botao) {
      return;
    }

    const definir = (pausada) => {
      faixa.classList.toggle('is-paused', pausada);
      // A mandala do hero (na mesma página) pausa com a faixa
      const hero = faixa.closest('[data-hero]');
      if (hero) {
        hero.classList.toggle('is-loop-paused', pausada);
      }
      botao.setAttribute('aria-pressed', String(pausada));
    };

    definir(semMovimento.matches);
    botao.addEventListener('click', () => definir(!faixa.classList.contains('is-paused')));
  });
}
