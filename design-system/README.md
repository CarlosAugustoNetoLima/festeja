O Festeja é o festival da RTP África que junta a música dos países africanos de língua portuguesa num estádio de Lisboa: "o festival que liga continentes". O site tem um trabalho principal: **vender bilhetes**. O segundo é apresentar o evento (cartaz, datas, local) depressa e sem esforço. Cada decisão abaixo serve um dos dois.

## Princípios

1. **O bilhete está sempre a um toque.** Em todas as páginas há um caminho visível para comprar: o botão principal no header (desktop) e a barra fixa `StickyBuyBar` (mobile).
2. **Alegria na superfície, calma na estrutura.** A cor e os símbolos dão a festa; a grelha, a tipografia e o espaço dão o equilíbrio. Um momento ousado por secção, o resto sossegado.
3. **Uma pergunta, um sítio.** Datas, local, preços, horários e regras aparecem cada um num único lugar previsível. Nada de informação essencial escondida em carrosséis, imagens ou PDFs.
4. **O desktop mostra, o mobile resolve.** O desktop tem composição de cartaz, fotografia grande e movimento; o mobile é uma coluna enxuta com a compra em primeiro plano (secção *Desktop e mobile*).

## Voz e conteúdo

- Todo o texto do site em **português de Portugal**. Trata o visitante por **tu**, como o Instagram do festival ("Ficou saldo na tua pulseira?").
- Frases curtas e diretas. Verbo primeiro nos botões: "Comprar bilhetes", "Ver cartaz", "Como chegar". Maiúscula só na primeira palavra.
- Datas por extenso e sem zeros: "30 e 31 julho 2027", "Sexta, 30 julho". Horas: "16h00". Preços: "35 €" (valor, espaço, símbolo).
- Nomes de artistas como os escrevem os próprios (Anna Joyce, Pérola, Filho do Zua). No cartaz em maiúsculas, nas páginas de artista em caixa normal.
- Sem emoji na interface. As bandeiras dos PALOP aparecem só quando dizem de onde vem um artista, nunca como decoração.
- Exemplos reais: título do hero "30 e 31 julho · Estádio dos Olivais e Moscavide"; secção de bilhetes "Garante o teu lugar"; FAQ "Posso sair e voltar a entrar?".

## Cor

O site vive em dois temas de secção, aplicados com `data-theme` no elemento da secção:

- **Papel** (por defeito): `surface` creme com `ink` verde. É o tom da apresentação oficial.
- **Noite**: `surface` verde com `ink` creme. Para a secção de bilhetes e o rodapé, para dar peso e marcar a mudança de assunto. Nunca mais de duas secções Noite seguidas.

Regras:

- Texto corrido sempre em `ink` ou `ink-muted`. Nunca texto pequeno em `brand-orange`, `brand-ochre` ou `brand-clay`.
- `brand-orange` e `brand-ochre` são cores de **preenchimento**: símbolos, blocos, sublinhados grossos, títulos display a partir de 24px.
- O botão principal usa `action` + `on-action`. O laranja da marca com texto branco fica em 3,6:1 e não passa no AA, por isso `action` é um laranja escurecido no tema Papel e ocre com texto verde no tema Noite.
- Links em texto corrido: `link`, sempre sublinhados.
- Foco: contorno de 3px em `focus`, afastado 2px, em todos os elementos interativos.
- Estados (`danger`, `success`) levam sempre palavra e ícone. "Esgotado" nunca é só um cartão a cinzento.
- O verde da marca é o `ink` do Papel e o `surface` da Noite. Use os tokens semânticos, nunca `brand-green` direto.

Proporção de referência por ecrã: 70% `surface`, 20% `ink`, 10% laranja e ocre juntos.

## Tipografia

Três famílias do Google Fonts, carregadas com `display=swap` e subset latin:

- **Rubik** (`display`, pesos 700–900) para títulos. É geométrica e arredondada como as letras do logótipo, sem copiar o desenho delas.
- **Rubik Dirt** (`poster`) só para o estilo `poster`: a data e o nome no hero, com textura de carimbo como nos cartazes. Uma vez por página.
- **Atkinson Hyperlegible Next** (`body`) para todo o texto. Foi desenhada para leitura com baixa visão e distingue bem I/l/1 e O/0, o que ajuda em preços, horas e códigos.

Escala: `poster` 112, `display-1` 72, `display-2` 48, `heading` 32, `title` 22, `lead` 21, `body` 18, `button` 17, `small` 15 (px, desktop). No mobile os títulos reduzem conforme a nota de cada estilo e o texto nunca fica abaixo de 16px. Usa `clamp()` entre os dois valores.

O `eyebrow` (maiúsculas com 0,28em de espaçamento) repete o "F E S T I V A L" do logótipo e é o rótulo de todas as secções.

## Logótipo

- **Vertical** (mandala + FESTIVAL FESTEJA + RTP África): hero do desktop, rodapé, partilhas sociais. Largura mínima 160px.
- **Horizontal**: header e mobile. Altura mínima 40px.
- Só sobre `surface` Papel. As raias verdes da mandala desaparecem no tema Noite. Até haver uma versão monocromática creme (a apresentação mostra uma branca sobre laranja, ainda não vetorizada), no tema Noite usa-se o nome em texto.
- Área de proteção: a altura do "F" de FESTEJA à volta de todo o logótipo.
- Não recolorir, não esticar, não pôr sombra, não colocar sobre fotografia sem `scrim`.
- Os ficheiros desta biblioteca foram vetorizados a partir da apresentação (JPEG). Antes da produção, substitui-os pelos vetoriais originais do designer.

## Símbolos

Os 24 símbolos da linha gráfica (grupo *Simbolos*) são o ornamento do site. Usam só `brand-orange`, `brand-ochre` e `brand-green`.

- Marcam secções (um símbolo ao lado do `eyebrow`), separam dias do cartaz e animam o hero.
- Tamanho mínimo 32px. Não misturar mais de três símbolos diferentes no mesmo ecrã.
- São decorativos: `alt=""` e `aria-hidden="true"`.
- **Só estes motivos.** Mandala, logótipos e os 24 símbolos, sempre na forma original. Proibido criar arcos, anéis, círculos ou outras formas derivadas, e recortar, deformar ou recolorir estes motivos.
- Não substituem ícones de interface. Para ações (menu, fechar, mapa, calendário) usa **Lucide** com traço de 2px e cantos redondos, cor `currentColor`, 24px.

## Padrão geométrico

O fundo da apresentação tem um padrão de formas geométricas africanas em tom sobre tom. No site entra apenas no hero e no rodapé, a 6% de opacidade, em `ink` sobre `surface`, e nunca atrás de texto corrido. Falta o ficheiro vetorial original: pede-o ao designer antes de o reproduzir.

## Fotografia

- Fotografia de palco real das edições anteriores: luz quente, artistas a meio do concerto, público. Nada de banco de imagens.
- Retratos de artista em 4:5, recortados com `radius-lg` ou dentro de uma forma de símbolo (só no desktop).
- Texto sobre fotografia apenas com `scrim` por baixo e só no hero.
- Todas as fotografias levam `alt` com quem e o quê ("Anderson Mário em palco no Festeja 2026").

## Formas, raio e sombra

- Botões e selos em `radius-pill`. Cartões em `radius-md`. Fotografias e modal em `radius-lg`.
- Elementos tocáveis com destaque (cartão de bilhete, botão principal) levam `shadow-sticker`: sombra dura deslocada, sem desfoque, como um autocolante. No pressed passam a `shadow-sticker-press` e descem 3px.
- Nada mais tem sombra. Separação entre blocos faz-se com espaço e com a mudança de tema.

## Movimento

- Um único momento orquestrado: a entrada do hero no desktop (os símbolos aparecem em sequência e a mandala roda uma vez, 1,2s).
- A faixa `Marquee` desloca-se devagar e tem sempre botão de pausa.
- Microinterações de 150–200ms (hover do botão, abrir FAQ).
- Com `prefers-reduced-motion: reduce` tudo isto desliga e os elementos aparecem no estado final.
- O conteúdo nunca depende de scroll para aparecer: nada começa com `opacity: 0`.

## Espaço e grelha

- Desktop: grelha de 12 colunas, largura máxima 1280px, gap `space-8`, secções com `space-24` na vertical.
- Mobile: uma coluna, margens `space-4`, gap `space-6`, secções com `space-16`.
- Texto corrido com no máximo 65 caracteres por linha.

## Componentes

`SiteHeader`, `Marquee`, `Countdown`, `Button`, `TicketCard`, `StickyBuyBar`, `CheckoutModal`, `LineupDay`, `ArtistCard` e `Faq`. Cada um tem guia e pré-visualização. As classes CSS estão em `components/bundle.css` com o prefixo `fj-`.
