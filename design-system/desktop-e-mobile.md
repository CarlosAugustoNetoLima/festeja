# Desktop e mobile

O mesmo conteúdo, duas intenções. O desktop é o cartaz: composição, fotografia e ritmo. O mobile é o bilhete no bolso: uma coluna, pouca decoração, a compra sempre à mão.

## Pontos de quebra

| Nome | Largura | O que muda |
| --- | --- | --- |
| mobile | até 767px | Uma coluna. Menu em painel. `StickyBuyBar` visível. Sem símbolos animados. |
| tablet | 768–1099px | Duas colunas nos cartões. Menu ainda em painel. |
| desktop | 1024px ou mais | Grelha de 12 colunas, menu completo no header com botão de compra, hero com composição de cartaz. |

## Ordem da página inicial

| # | Desktop | Mobile |
| --- | --- | --- |
| 1 | `SiteHeader` com navegação e botão "Comprar bilhetes" | `SiteHeader` só com logótipo e menu |
| 2 | Hero: data em `poster`, local, dois botões, símbolos animados e a faixa com os nomes. Sem `Countdown` | Hero: logótipo vertical (abaixo de 1024 px), data em `poster`, local, dois botões. Sem `Countdown` |
| 3 | `Marquee` com nomes do cartaz | Não aparece |
| 4 | Bilhetes (tema Noite): 3 `TicketCard` lado a lado | Bilhetes: `TicketCard` empilhados, o mais vendido primeiro |
| 5 | Cartaz: dois `LineupDay` lado a lado | Cartaz: dias em separadores (Sexta / Sábado) |
| 6 | Artistas em destaque: grelha de `ArtistCard` 4 colunas | Lista de 4 artistas em 2 colunas e link "Ver todos" |
| 7 | Como chegar: mapa estático + transportes | Morada, botão "Abrir no mapa" e transportes em lista |
| 8 | `Faq` com as 5 perguntas marcadas como `home` em `faq.json`, e link para todas | As mesmas 5 perguntas, e link para todas |
| 9 | Parceiros (só se houver em `parceiros.json`): logótipos a 48 px, em grelha, tal como vêm | Mesma secção, em grelha que quebra linha |
| 10 | Rodapé (tema Noite) | Rodapé compacto |

O mobile omite o que é decorativo ou repetido. Não omite informação: tudo o que sai da página inicial tem a sua página própria e está no menu.

## Navegação

- Desktop: Bilhetes · Cartaz · Informações · Como chegar · Notícias, e o botão principal à direita.
- Mobile: o menu abre um painel com os mesmos itens em lista grande (alvos de 48px) e o botão de compra no fim.
- A página atual é marcada com `aria-current="page"` e sublinhado grosso em `brand-orange`.

## Compra no mobile

- A `StickyBuyBar` aparece depois de o hero sair do ecrã e esconde-se quando a secção de bilhetes está visível, para não haver dois botões iguais.
- O `CheckoutModal` no mobile ocupa o ecrã inteiro, com o botão de fechar fixo no topo.
- Nada de pop-ups de newsletter, banners promocionais ou chat flutuante a disputar o mesmo espaço.

## Desempenho

- LCP abaixo de 2,5s em 4G: a imagem do hero do desktop é a única carregada com prioridade; no mobile o hero é só tipografia.
- Fontes com `font-display: swap` e pré-carregamento só de Rubik 900 e Atkinson 400.
- Fotografias em AVIF/WebP com `srcset`, `loading="lazy"` abaixo da dobra e dimensões explícitas para não haver saltos (CLS < 0,1).
