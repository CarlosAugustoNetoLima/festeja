# Direção visual do site

Base: style guide (`design-system/`) e a linha gráfica 2027 da apresentação (`extraido-pdf/`). Ideia central: **o site é o cartaz a ganhar vida**. Tipografia de cartaz, cores chapadas, a mandala e os símbolos da marca. Um só momento orquestrado por página, o resto calmo.

## Motivos gráficos

- **Proibido criar arcos, anéis, círculos ou outras formas derivadas.** Nada de semicírculos, anéis concêntricos nem arco-íris, e nada de recortar, deformar, recolorir ou compor os motivos. Só entram os motivos abaixo, sempre na forma original.
- **Mandala** (símbolo 01, igual ao do logótipo vertical): no hero, grande, entra com uma animação curta e depois roda devagar em loop (ver Movimento).
- **Logótipos**: o vertical e o horizontal, sem recolorir. O vertical só sobre papel (no tema Noite as raias verdes desaparecem).
- **Símbolos**: os 24 em `public/assets/img/simbolos/`, nas cores da marca (laranja, ocre, verde). Marcam secções ao lado do eyebrow, separam nomes no cartaz, formam grelhas decorativas (cabeçalho de página, fecho, 404) e ocupam os cartões sem foto. São decorativos (`alt=""`, `aria-hidden="true"`).
- **Contraste.** Sobre ocre (fecho) usam-se os símbolos com mais verde (7, 20, 21), porque o ocre da marca se perde no fundo. No tema Noite (cartão do local, rodapé) usam-se o 13 e o 10, que se leem sobre verde.
- **Padrão geométrico**: fica de fora até haver o vetorial original.

## Página inicial (desktop)

1. **Header** fixo em `surface`. Logótipo horizontal à esquerda; ao centro, navegação (Cartaz, Bilhetes, Informações, Como chegar); à direita, contador do carrinho (só com bilhetes no carrinho) e botão "Comprar bilhetes". Ganha `border-bottom: 1px var(--line)` depois de 8px de scroll.
2. **Hero** (100svh menos o header, papel):
   - grelha de 12 colunas;
   - à esquerda (7 colunas): eyebrow "F E S T I V A L · R T P  Á F R I C A", a data em `poster` (Rubik Dirt, enorme, laranja, "23 E 24 JULHO"), o local em `heading`, o resumo em `lead`, os dois botões (Comprar bilhetes, Ver cartaz) e uma linha de factos (2 dias · 20+ artistas · desde 15 €);
   - à direita (5 colunas): só a mandala, grande, centrada na área. O diâmetro é o menor entre o clamp por largura, metade da coluna e metade da altura da área (menos 24 px em baixo): a mandala inteira cabe sempre dentro do hero, sem cortes (nem pelo ecrã, nem pelo header, nem pela faixa);
   - no rodapé do hero, a faixa `Marquee` com os nomes do cartaz, com botão de pausa.
3. **Factos rápidos** (`surface-sunken`): datas, portas, local, preço desde e organizador, numa linha de 5 colunas com rótulo pequeno e valor em `title`. É o bloco que motores de pesquisa e assistentes de IA citam.
4. **Cartaz** (papel): eyebrow, h2 "Cartaz 2027" e os dois dias lado a lado em colunas de cartaz centradas.
   - Os nomes estão em `display` com tamanho por destaque: 1 → 56px, 2 → 36px, 3 → 24px.
   - As cores alternam entre `ink` e `brand-orange`; o laranja só a partir de 24px.
   - Os nomes estão separados por um ponto ocre.
   - Momento do desktop: ao passar o rato num nome, a fotografia do artista aparece a seguir ao cursor (ou, sem foto, um cartão liso com um símbolo da marca), com a bandeira e o dia. No mobile não há este efeito: cada nome é só um link.
5. **Bilhetes** (tema Noite): h2 "Garante o teu lugar", lead e grelha de cartões `fj-ticket` a partir da leitura da página do checkout da Tickett (`TickettService`). Cada cartão tem sombra sticker, selo de lote, estado e, quando a Tickett o indicar, "Próximo lote: data, hora". Por baixo, a nota "Acresce a taxa de serviço da Tickett, indicada no checkout. Pagamento seguro pela Tickett."
6. **O festival** (papel): uma frase grande em `display-2`, "Angola, Cabo Verde, Guiné-Bissau, Moçambique, São Tomé e Príncipe e Portugal no mesmo palco.", e 3 colunas curtas: Kizomba na Rua abre os dois dias, Pagamentos com pulseira cashless, Transmissão RTP África.
7. **Como chegar** (`surface-sunken`): local, zona, transportes e o link "Ver no mapa".
8. **Perguntas frequentes** (papel): as 5 perguntas marcadas como `home` e o link "Ver todas as perguntas".
9. **Fecho** (fundo `brand-ochre`, texto `brand-green`): "Vemo-nos em Moscavide", a data em `display-1`, um botão verde e uma faixa de símbolos da marca.
10. **Rodapé** (tema Noite): nome em texto, navegação, redes, páginas legais e "Bilhetes vendidos pela Tickett".

## Mobile (< 768px)

- **Header**: logótipo horizontal (40px de altura), botão "Bilhetes" pequeno e botão de menu que abre um menu de ecrã inteiro.
- **Hero**: logótipo vertical a 120px, data `poster` a 56px, local, um só botão em largura total e a linha de factos. Sem arte: a mandala fica só no desktop (no mobile o hero é só texto e logótipo). Sem marquee.
- **Cartaz**: separadores Dia 1 / Dia 2 (`role="tablist"`) e lista centrada.
- **Bilhetes**: uma coluna.
- **StickyBuyBar** fixa em baixo a partir do fim do hero, com "desde X €" e o botão "Comprar". Esconde-se quando a secção de bilhetes está visível.

## Movimento

- **Entrada do hero (desktop, uma vez)**. Só CSS (`home.css`), sem depender de JS nem de fontes:
  - o texto sobe 12px em 300ms, sem fade: está visível desde o primeiro paint (a data é a LCP);
  - a mandala entra em 900ms (escala 0,85 → 1, roda -135° → 0, `opacity` 0 → 1, `cubic-bezier(0.16, 1, 0.3, 1)`).
- **Loop da mandala** (depois da entrada): roda uma volta em 50s, linear, e respira (escala 1 → 1,03, 6s, ease-in-out, alternate). Só `transform` e `scale`; `will-change` só na mandala.
- **Pausa.** O botão "Pausar animação" da faixa pausa também o loop da mandala (`is-loop-paused` no hero, em `marquee.js`; WCAG 2.2.2). Fora do ecrã, o loop pausa (`is-offscreen`, IntersectionObserver em `hero.js`).
- Só com `html.js`, desktop (≥ 1024px) e sem `prefers-reduced-motion`. Com reduced motion o hero é estático e não há loop. Sem JS o hero fica igual, sem pausa fora do ecrã.
- O conteúdo nunca fica invisível por causa de uma animação: as animações são CSS e acabam no estado visível (`fill-mode: both`).
- **Hover**:
  - botões e cartões sticker: `translate(-2px, -2px)` e sombra maior; no press, `translate(3px, 3px)` com a sombra de press;
  - links: o sublinhado desenha-se da esquerda para a direita;
  - nomes do cartaz: passam a `brand-orange` e aparece a imagem.
### Scroll (GSAP)

A regra antiga ("sem reveal ao scroll, sem parallax") foi substituída: o pedido é mais interação e a navegação mais viva, sem prejudicar a leitura. Há movimento ligado ao scroll, com GSAP 3 (ScrollTrigger e ScrollToPlugin, em `public/assets/vendor/gsap/`), e o módulo `public/assets/js/movimento.js`. Como se marca cada efeito: ver `docs/guia-de-manutencao.md`, secção 18.

- **Navegação.** O cabeçalho esconde-se ao descer (depois de 1 ecrã) e volta ao subir (250 ms). Nunca se esconde com foco dentro ou menu aberto. As âncoras da página (ex.: "Ver cartaz") deslocam-se com o offset do cabeçalho e levam o foco ao título da secção. O indicador da página atual (sublinhado que desliza) segue o rato e o foco pelos links e volta ao link da página atual quando sai.
- **Hero (desktop).** Ao sair, a mandala sobe mais devagar do que a página (parallax com scrub, `yPercent`), e ganha uma rotação extra ligada ao scroll (somada ao loop CSS) e a data "23 E 24 JULHO" tem um ligeiro desfasamento.
- **Faixa em movimento (desktop).** A inclinação segue a velocidade do scroll (até 6°) e volta a 0 quando o scroll para. Respeita o botão de pausa.
- **Faixa de factos.** Os itens entram escalonados (16 px e opacidade); as divisórias desenham-se (escala 0 → 1, centradas).
- **Cartaz.** Os nomes sobem por máscara, linha a linha, em grupo por dia; os pontos entre nomes aparecem depois.
- **Bilhetes (tema noite).** Os cartões entram como autocolantes a ser colados (24 px, rodam -2° → 0), escalonados em 80 ms.
- **O festival.** A frase grande acende palavra a palavra (de cinzento para a cor final), ligada ao scroll. Os leitores de ecrã leem o texto original (`aria-label`).
- **Como chegar e perguntas frequentes.** Revelação simples, escalonada.
- **Fecho (fundo ocre).** O título escala de 0,94 para 1 e os símbolos da faixa aparecem um a um (escala 0,8 → 1 e opacidade, sem rodar).
- **Símbolos dos eyebrows.** Entram a rodar de -90° até à posição original (0°): acabam sempre na orientação do desenho.
- **Páginas internas.** As mesmas revelações (cartaz, bilhetes, artista, informações, como chegar), por marcação nos componentes partilhados. Sem parallax pesado.
- **Regras.** Só com `prefers-reduced-motion: no-preference`; o GSAP carrega depois do `load`. Desktop (≥ 1024 px) tem tudo; tablet e telemóvel só revelações curtas. Durações de 0,6 a 0,9 s, ease `power3.out` ou `expo.out`. As revelações acontecem uma vez (não repetem ao voltar a subir); os efeitos ligados ao scroll (scrub) acompanham-no. Sobre propriedades: `transform` e `opacity`, com três exceções: a cor das palavras do festival, a variável CSS das divisórias e dos pontos, e o `overflow` da máscara do cartaz (só durante a entrada). Sem scroll-jacking, sem smooth-scroll de bibliotecas, sem pins longos.
- **Sem JS, falha do vendor ou reduced-motion.** O site fica como antes e o conteúdo fica sempre visível: os estados iniciais escondidos são aplicados pelo GSAP, nunca pelo CSS.
- **Limites.** Não há reveal de blocos que já estão visíveis ao carregar; nada muda o layout (LCP e CLS iguais aos da versão sem GSAP).

## Imagens

- Fotografias de artista em 4:5. Enquanto não houver fotos, o cartão mostra um fundo liso de papel com um símbolo da marca centrado, escolhido pelo slug (`App\Helpers\Simbolo::um`).
- Imagem de partilha `og.jpg` com 1200×630.
