// Hero da página inicial: pausa o loop da mandala quando o hero sai do ecrã (is-offscreen, IntersectionObserver).
// A entrada e o loop são CSS (home.css), por isso não dependem deste módulo. O botão de pausa da faixa
// (marquee.js) põe is-loop-paused no hero, que também pausa a mandala (WCAG 2.2.2).

export default function init(root = document) {
  const hero = root.querySelector('[data-hero]');
  if (!hero || !('IntersectionObserver' in window)) {
    return;
  }

  const observer = new IntersectionObserver(([entrada]) => {
    hero.classList.toggle('is-offscreen', !entrada.isIntersecting);
  });
  observer.observe(hero);
}
