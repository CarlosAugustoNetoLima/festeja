# Marquee

Faixa laranja que corre com os nomes do cartaz, logo abaixo do hero no desktop. Dá energia sem ocupar espaço de conteúdo. Não aparece no mobile.

- Os nomes dentro da faixa estão também no `LineupDay`; a faixa é `aria-hidden="true"` para o leitor de ecrã não os ouvir duas vezes, exceto o botão de pausa.
- Tem sempre o botão "Pausar" (WCAG 2.2.2), que alterna `data-paused` e o texto para "Retomar".
- Com `prefers-reduced-motion` fica parada.
- O conteúdo é duplicado no HTML para o ciclo não ter salto.
- Texto verde sobre `brand-orange`: 3,4:1, só aceitável porque é display de 22px a 900.
