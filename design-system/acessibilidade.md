# Acessibilidade

O alvo é **WCAG 2.2 nível AA** em todas as páginas, incluindo o checkout embebido. Estas regras são o mínimo para dar uma página como pronta.

## Cor e contraste

- Texto ≥ 4,5:1, texto grande (24px+ ou 19px+ negrito) ≥ 3:1, contornos de controlos, ícones e anel de foco ≥ 3:1. Os pares estão medidos nas notas dos tokens, nos dois temas.
- Nunca usar só a cor para dar significado: "Esgotado", "Últimos bilhetes" e erros levam palavra e ícone.

## Teclado e foco

- Tudo o que funciona com rato funciona com teclado, na ordem visual.
- Primeiro elemento da página: link "Saltar para o conteúdo".
- Foco visível em `focus` (3px, afastado 2px) e nunca escondido pelo header fixo (`scroll-margin-top` nas âncoras).
- Modal do checkout e painel do menu: foco preso dentro, `Esc` fecha, o foco volta ao botão que abriu.

## Estrutura e semântica

- `lang="pt-PT"` no `html`. Um `h1` por página, títulos sem saltar níveis.
- Regiões: `header`, `nav` (com `aria-label`), `main`, `footer`.
- Cartaz e bilhetes como listas (`ul`/`li`). Horários como `time datetime`.
- O iframe do checkout tem `title="Bilheteira Tickett do Festival Festeja"`.

## Movimento e tempo

- `Marquee` e `Countdown` respeitam `prefers-reduced-motion`. A faixa tem botão de pausa (critério 2.2.2) e o contador não é anunciado a cada segundo (sem `aria-live` nos dígitos).
- Nenhum conteúdo aparece só com scroll ou hover.

## Toque e zoom

- Alvos de toque com pelo menos 44 × 44px (o critério 2.5.8 pede 24, usamos 44).
- A página funciona a 200% de zoom e a 320px de largura sem scroll horizontal (reflow).
- Nada de `user-scalable=no`.

## Imagens e media

- Fotografias com `alt` descritivo; símbolos e padrão com `alt=""` e `aria-hidden="true"`.
- Vídeos com legendas em português e sem som automático.
- O texto do cartaz existe sempre em HTML. Imagens de cartaz não substituem a lista.

## Formulários

- Cada campo com `label` visível, erros por baixo do campo em `danger` com ícone, ligados por `aria-describedby`.
- Autocomplete correto (`email`, `name`, `tel`).

## Verificação

- axe sem erros em cada página (automático no CI).
- Teste manual com VoiceOver (iOS) e NVDA (Windows) no fluxo: página inicial → bilhetes → checkout.
- Teste só com teclado no mesmo fluxo.
- Publicar uma declaração de acessibilidade em `/acessibilidade`.
