# Button

Botão de ação com três variantes. **Primário** (`fj-btn--primary`) só para comprar bilhetes ou avançar no checkout, no máximo um por ecrã visível. **Secundário** para ações de apoio ("Ver cartaz"). **Ghost** para ações terciárias dentro de texto.

- O consumidor dá o elemento: `<a>` quando leva a outra página ou âncora, `<button>` quando abre o checkout ou submete.
- Verbo primeiro, maiúscula só no início: "Comprar bilhetes", "Ver cartaz", "Como chegar".
- Altura mínima 48px (56px com `fj-btn--lg` no hero). Nunca mais pequeno no mobile.
- No tema Noite o primário fica ocre com texto verde, sem mudar o HTML: os tokens `action`/`on-action` tratam disso.
- Desativado: `aria-disabled="true"` e o motivo escrito ao lado ("Esgotado"), nunca só o botão cinzento.
