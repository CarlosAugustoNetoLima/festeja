// Barra de compra do mobile: aparece depois de o hero sair do ecrã e esconde-se quando a secção
// de bilhetes (#bilhetes) está visível ou o menu está aberto. Sem [data-hero] na página, aparece logo.

export default function init(root = document) {
  const barra = root.querySelector('[data-buybar]');
  if (!barra) {
    return;
  }

  const mobile = matchMedia('(max-width: 767px)');
  const hero = document.querySelector('[data-hero]');
  const bilhetes = document.getElementById('bilhetes');

  const estado = { heroFora: !hero, bilhetesVisiveis: false, menuAberto: false };

  const atualizar = () => {
    const mostrar = mobile.matches && estado.heroFora && !estado.bilhetesVisiveis && !estado.menuAberto;
    barra.hidden = !mostrar;
    barra.classList.toggle('is-visible', mostrar);
  };

  if (hero) {
    new IntersectionObserver(([entrada]) => {
      estado.heroFora = !entrada.isIntersecting;
      atualizar();
    }).observe(hero);
  }

  if (bilhetes) {
    new IntersectionObserver(([entrada]) => {
      estado.bilhetesVisiveis = entrada.isIntersecting;
      atualizar();
    }).observe(bilhetes);
  }

  document.addEventListener('festeja:menu', (event) => {
    estado.menuAberto = event.detail.open;
    atualizar();
  });
  mobile.addEventListener('change', atualizar);

  atualizar();
}
