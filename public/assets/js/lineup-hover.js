// Pré-visualização do artista no cartaz (só desktop com rato): um único cartão segue o cursor com
// o retrato (ou, sem foto, o símbolo da marca sobre fundo claro) e o nome do país. Com reduced motion fica fixo ao lado do nome.
// Não aparece quando o foco chega por teclado.

const DESLOCAMENTO = 24;
const SUAVIDADE = 0.2;
const SVG = 'http://www.w3.org/2000/svg';

export default function init(root = document) {
  const listas = [...root.querySelectorAll('[data-lineup-hover]')];
  if (listas.length === 0) {
    return;
  }

  const semMovimento = matchMedia('(prefers-reduced-motion: reduce)');
  const cartao = criarCartao();
  document.body.append(cartao);

  let atual = null;
  let alvoX = 0;
  let alvoY = 0;
  let x = 0;
  let y = 0;
  let frame = 0;

  const desenhar = () => {
    x += (alvoX - x) * SUAVIDADE;
    y += (alvoY - y) * SUAVIDADE;
    cartao.style.transform = `translate3d(${x}px, ${y}px, 0)`;
    frame = Math.abs(alvoX - x) > 0.5 || Math.abs(alvoY - y) > 0.5
      ? requestAnimationFrame(desenhar)
      : 0;
  };

  const posicionarAoLado = (link) => {
    const r = link.getBoundingClientRect();
    x = r.right + DESLOCAMENTO;
    y = r.top;
    cartao.style.transform = `translate3d(${x}px, ${y}px, 0)`;
  };

  const preencher = (link) => {
    const foto = link.dataset.photo;
    const imagem = cartao.querySelector('img');
    const fallback = cartao.querySelector('.lineup-float__fallback');
    if (foto) {
      imagem.src = foto;
      imagem.hidden = false;
      fallback.hidden = true;
    } else {
      imagem.removeAttribute('src');
      imagem.hidden = true;
      fallback.hidden = false;
      fallback.replaceChildren(simbolo(link.dataset.simbolo));
    }
    const pais = link.dataset.countryName ?? '';
    cartao.lastElementChild.textContent = pais;
    cartao.lastElementChild.hidden = pais === '';
  };

  const mostrar = (link, evento) => {
    if (atual !== link) {
      preencher(link);
      atual = link;
    }
    if (semMovimento.matches) {
      posicionarAoLado(link);
    } else {
      alvoX = evento.clientX + DESLOCAMENTO;
      alvoY = evento.clientY + DESLOCAMENTO;
      if (!cartao.classList.contains('is-visible')) {
        x = alvoX;
        y = alvoY;
      }
      if (!frame) {
        frame = requestAnimationFrame(desenhar);
      }
    }
    cartao.classList.add('is-visible');
  };

  const esconder = () => {
    atual = null;
    cartao.classList.remove('is-visible');
  };

  for (const lista of listas) {
    lista.addEventListener('pointerover', (evento) => {
      const link = evento.target.closest('a[data-photo]');
      if (link && lista.contains(link)) {
        mostrar(link, evento);
      }
    });
    lista.addEventListener('pointermove', (evento) => {
      if (atual && !semMovimento.matches) {
        alvoX = evento.clientX + DESLOCAMENTO;
        alvoY = evento.clientY + DESLOCAMENTO;
      }
    });
    lista.addEventListener('pointerleave', esconder);
    lista.addEventListener('pointerout', (evento) => {
      if (!evento.relatedTarget || !evento.relatedTarget.closest?.('a[data-photo]')) {
        esconder();
      }
    });
  }

  // Foco por teclado não mostra a pré-visualização.
  document.addEventListener('focusin', (evento) => {
    if (evento.target.matches?.(':focus-visible')) {
      esconder();
    }
  });
}

// Símbolo da marca (sprite.svg, como o componente Symbol). O href vem de data-simbolo, já com o caminho do sprite.
function simbolo(href) {
  const svg = document.createElementNS(SVG, 'svg');
  svg.setAttribute('class', 'symbol');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const uso = document.createElementNS(SVG, 'use');
  uso.setAttribute('href', href ?? '');
  svg.append(uso);
  return svg;
}

function criarCartao() {
  const cartao = document.createElement('div');
  cartao.className = 'lineup-float';
  cartao.setAttribute('aria-hidden', 'true');

  const imagem = document.createElement('img');
  imagem.alt = '';
  imagem.decoding = 'async';

  const fallback = document.createElement('span');
  fallback.className = 'lineup-float__fallback';

  const pais = document.createElement('span');
  pais.className = 'lineup-float__chip';

  cartao.append(imagem, fallback, pais);
  return cartao;
}
