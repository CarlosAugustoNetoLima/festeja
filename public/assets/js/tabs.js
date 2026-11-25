// Separadores com o padrão ARIA (tablist, tab, tabpanel). Setas, Home e End mudam de separador.
// Marcação: [data-tabs] contém [role="tablist"] com botões [data-tab="id"] e painéis [data-panel="id"].
// Sem JS, todos os painéis ficam visíveis e a tablist é escondida pelo CSS (classe no-js).

export default function init(root = document) {
  root.querySelectorAll('[data-tabs]').forEach(configurar);
}

function configurar(contentor) {
  const lista = contentor.querySelector('[role="tablist"]');
  if (!lista) {
    return;
  }

  const separadores = [...lista.querySelectorAll('[data-tab]')];
  const paineis = separadores.map((separador) => contentor.querySelector(`[data-panel="${CSS.escape(separador.dataset.tab)}"]`));
  if (separadores.length === 0 || paineis.some((painel) => !painel)) {
    return;
  }

  lista.setAttribute('role', 'tablist');
  separadores.forEach((separador, i) => {
    const painel = paineis[i];
    separador.id ||= `tab-${separador.dataset.tab}`;
    painel.id ||= `panel-${separador.dataset.tab}`;
    separador.setAttribute('role', 'tab');
    separador.setAttribute('aria-controls', painel.id);
    painel.setAttribute('role', 'tabpanel');
    painel.setAttribute('aria-labelledby', separador.id);
  });

  const ativar = (indice, focar = false) => {
    separadores.forEach((separador, i) => {
      const ativo = i === indice;
      separador.setAttribute('aria-selected', String(ativo));
      separador.tabIndex = ativo ? 0 : -1;
      paineis[i].hidden = !ativo;
    });
    if (focar) {
      separadores[indice].focus();
    }
  };

  const inicial = Math.max(0, separadores.findIndex((s) => s.getAttribute('aria-selected') === 'true'));
  ativar(inicial);

  separadores.forEach((separador, i) => {
    separador.addEventListener('click', () => ativar(i));
    separador.addEventListener('keydown', (event) => {
      const ultimo = separadores.length - 1;
      const destinos = {
        ArrowRight: (i + 1) % separadores.length,
        ArrowLeft: (i - 1 + separadores.length) % separadores.length,
        Home: 0,
        End: ultimo,
      };
      if (!(event.key in destinos)) {
        return;
      }
      event.preventDefault();
      ativar(destinos[event.key], true);
    });
  });
}
