# Plano de implementação — site Festeja 2027

Site oficial do Festeja (RTP África). Foco principal: **vender bilhetes** pelo checkout da Tickett sem tirar a pessoa do site. Foco secundário: apresentar o evento (cartaz, datas, local, informações práticas) de forma rápida e acessível.

- Style guide (tokens, componentes, acessibilidade, desktop × mobile): https://claude.ai/artifact/HWVHE2JoF4j7G6EJWnz1gg
- Referência de estrutura e integração: `/Users/carloslima/festival-crato`
- Código do checkout Tickett/LeBillet: `/Users/carloslima/checkout` (não é alterado desde 24/11/2026; ver secção 5)
- Logótipos e símbolos (vetorizados da apresentação): `extraido-pdf/svg/`

## 1. Metas

| Meta | Alvo |
| --- | --- |
| Caminho até ao checkout | 1 toque a partir de qualquer página (header no desktop, `StickyBuyBar` no mobile) |
| Acessibilidade | WCAG 2.2 AA; axe sem erros; fluxo de compra testado com VoiceOver, NVDA e só teclado |
| Desempenho (4G, mobile) | LCP < 2,5s · INP < 200ms · CLS < 0,1 · JS da página < 50 KB |
| SEO e IA | Todo o conteúdo em HTML renderizado no servidor; JSON-LD de evento, artistas e FAQ válido no Rich Results Test |
| Medição | Funil `view_item_list → select_item → begin_checkout → purchase` no GA4, com consentimento |

## 2. Decisões em aberto

Precisam de resposta antes ou durante a Fase 1:

1. ~~**Modelo de integração com a Tickett** (secção 5): embed oficial no checkout (recomendado) ou proxy como no Crato.~~ **Decidido a 24/11/2026:** proxy no domínio do site, sem alterar o checkout (secção 5, "Decisão de 24/11/2026").
2. **Datas e local de 2027.** **Resolvido pela arte oficial do cartaz:** 30 e 31 de julho de 2027, no Estádio Olivais e Moscavide (Lisboa). Ficou em aberto só o horário de portas (16h00, a confirmar). O post de encerramento de 2026 no Instagram anunciava 23 e 24 de julho; essa informação está desatualizada.
3. **Idiomas.** Só pt-PT, ou pt-PT + EN para o público internacional. Recomendo começar só em pt-PT e deixar as rotas preparadas para `/en/`.
4. **Domínio** (ex.: `festeja.pt`) e alojamento (o mesmo servidor PHP do Crato?).
5. **Ficheiros originais do designer**: logótipos vetoriais, versão monocromática creme, padrão geométrico do fundo e os 24 símbolos. Os SVG atuais foram vetorizados de um JPEG e servem só até lá.
6. **ID do evento e dos produtos na Tickett** para 2027 e quando abre a venda. Em 2026 a venda foi pela BOL; confirmar que 2027 passa toda para a Tickett e se os "locais habituais" (venda física) continuam.
7. **Conteúdo**: cartaz por dia com hierarquia, biografias e fotografias dos artistas, FAQ, parceiros, regras do recinto e informação do cashless.
## 2a. O que o Instagram mostra (edição 2026)

Lido com sessão iniciada em 24/11/2026: perfil, cerca de 75 publicações (abril a agosto de 2026) e os destaques "Dia 17", "Dia 18" e "Notícias".

O Instagram serve só como referência de conceito e de copy. A identidade visual do site é a da apresentação de 2027 e do style guide; a de 2026 (o "JA" com o mapa de África, o cartaz multicolor) não entra.

**Factos**
- 2026: 17 e 18 de julho, Estádio do Olivais e Moscavide, Lisboa. Mote: "RTP África · 30 anos de rádio" e "O festival que liga continentes".
- 2027: "regressamos ao Estádio do Olivais e Moscavide nos dias 23 e 24 de julho" (post de encerramento, 18/07/2026).
- Produção Grupo Chiado e Platinaline. Parceiros no cartaz: Horizonte Seguros, Muana Bata, RTP África, Jornal Económico, CE-CPLP, Lisboa, Uriage, Kavi, Mosaic.
- Bilhetes 2026 pela BOL e "nos locais habituais", em lotes: Golden Circle (lote 1 a 45 €), Plateia em pé (lote 1 a 30 €) e Early Entry (15 €). O lote 1 do Golden Circle esgotou primeiro e o lote 2 abriu a uma hora anunciada ("a partir das 14h").
- Pagamento no recinto com pulseira cashless; o reembolso do saldo foi feito num formulário externo, aberto até 31/07.
- Cartaz por dia, com o Kizomba na Rua a abrir os dois dias. Cabeças de cartaz: Anna Joyce, Paulo Flores, Lura, Pérola, Cuca Roseta, Tabanka Djaz, Justino Delgado, Anderson Mário, Yasmine, Dynamo, Filho do Zua, Twenty Fingers, Edmázia Mayembe, entre outros.
- Público: comunidades PALOP em Portugal (angolanos, cabo-verdianos, guineenses, moçambicanos, são-tomenses). Chega pelo "link na bio" e pelo autocolante "Bilhetes aqui" dos stories, ou seja, quase sempre no telemóvel.
- Ritmo de comunicação: confirmações de artistas de abril a julho, uma a uma, com vídeo; depois cartaz completo; durante o festival, entrevistas e reels; depois, agradecimento e cashless.

**O que muda no site**
1. **Lotes e esgotados.** O `TicketCard` mostra o lote atual ("Lote 2"), o estado "Esgotado" por lote e, quando houver, "Próximo lote: 12 junho, 14h00". O `products.json` (modelo B) precisava de expor lote, estado e data de abertura do próximo. Nota de 24/11/2026: o modelo B foi abandonado. O lote e o estado vêm agora do HTML do checkout (secção 5), e a data do próximo lote não existe lá.
2. **Mobile vindo do Instagram é o caso principal.** A home no mobile abre com data, local e "Comprar bilhetes" acima da dobra, sem nada antes. Links próprios para cada contexto, com UTM: `/bilhetes?utm_source=instagram&utm_medium=bio`, `/bilhetes?tipo=golden-circle`, `/cartaz#dia-1`.
3. **O cartaz anuncia-se aos poucos.** `artistas.json` com `confirmado_em`, para mostrar "Novo" nos últimos 14 dias (implementado; a faixa do hero continua a seguir a ordem da lista). Cada confirmação tem página própria (`/artistas/{slug}`) para o reel apontar para ela.
4. **Cashless no FAQ e no pós-evento.** Secção "Cashless" em `/informacoes` (carregar, usar, reembolsar) e um modo pós-evento na home: agradecimento, data da próxima edição, link do reembolso enquanto estiver aberto e um "Avisa-me quando abrir a venda".
5. **Parceiros** em `content/parceiros.json`, com os níveis do cartaz (Produção, Patrocinadores, Media partners, Apoios). Implementado: componente `Partners`, na home antes do fecho, só com parceiros.
6. **Vídeo.** Os reels são o conteúdo mais forte. Na página de artista entra um vídeo curto com legendas, sem autoplay com som, e com uma imagem de capa para não pesar no LCP.
7. **Voz.** Confirma o tom do style guide: tratar por tu, verbos de ação, "Garante já o teu lugar". No site sem emojis e sem exclamações em série.

## 3. Stack

O mesmo modelo do Crato, que já está em produção e é conhecido:

- **PHP 8.2+, sem framework e sem build.** Renderização no servidor (bom para SEO e para agentes de IA), um `public/index.php` como router e componentes PHP em `app/Views/Components`.
- **CSS**: `tokens.css` gerado a partir do `tokens.json` do style guide + `components.css` com as classes `fj-` do style guide. Sem Tailwind.
- **JS**: vanilla, em módulos pequenos e só onde há comportamento (menu, modal do checkout, contador, faixa, separadores do cartaz). A página funciona e mostra todo o conteúdo sem JS. Sem JS, os botões gerais de compra vão para `/bilhetes` e só "Escolher" vai diretamente ao checkout da Tickett (na mesma página).
- **Conteúdo**: ficheiros JSON em `content/` (`festival.json`, `artistas.json`, `faq.json`, `parceiros.json`), uma única fonte de verdade para datas, local e preços "desde". Nada disto fica espalhado no HTML.
- **Imagens**: AVIF/WebP com `srcset`, geradas por um script (`bin/images`), nunca na hora do pedido.

Estrutura:

```
festeja/
├── app/
│   ├── Helpers/        Component.php, Seo.php (meta + JSON-LD), Icon.php
│   ├── Services/       TickettService.php (eventos e produtos, com cache)
│   └── Views/
│       ├── Components/ SiteHeader, Marquee, Countdown, TicketCard, StickyBuyBar,
│       │               CheckoutModal, LineupDay, ArtistCard, Faq, Footer…
│       └── Pages/      home, bilhetes, cartaz, artista, informacoes, como-chegar, legais, 404
├── content/            festival.json, artistas.json, faq.json, parceiros.json
├── public/
│   ├── index.php       router
│   ├── assets/css      tokens.css, base.css, components.css
│   ├── assets/js       menu.js, checkout.js, countdown.js, marquee.js, tabs.js
│   ├── assets/img      logos/, simbolos/, artistas/, fotos/
│   ├── robots.txt  llms.txt  sitemap.xml (gerado)
│   └── checkout-proxy.php   (modelo A, histórico: o proxy em uso é app/Services/CheckoutProxy.php)
└── docs/               este plano
```

## 4. Páginas e URLs

| URL | Conteúdo | Prioridade |
| --- | --- | --- |
| `/` | Hero (data, local, comprar), faixa do cartaz, bilhetes, cartaz, artistas em destaque, como chegar, FAQ curto, parceiros | Fase 2 |
| `/bilhetes` | Todos os bilhetes com o que inclui cada um, preços com taxas, regras de compra, reembolso, faixa etária | Fase 2 |
| `/cartaz` | Cartaz por dia (separadores no mobile) e horários por palco quando existirem | Fase 2 |
| `/artistas/{slug}` | Uma página por artista: bio curta, país, dia e hora, fotografia, links oficiais, botão do bilhete desse dia | Fase 2 |
| `/informacoes` | FAQ completo agrupado (Bilhetes, Acesso, No recinto, Cashless, Acessibilidade) | Fase 2 |
| `/como-chegar` | Morada, mapa estático com link para mapas, metro/autocarro/comboio, estacionamento, acessos para mobilidade reduzida | Fase 2 |
| `/noticias`, `/noticias/{slug}` | Anúncios do cartaz e avisos | Fase 6 (opcional) |
| `/acessibilidade`, `/privacidade`, `/cookies`, `/termos` | Legais e declaração de acessibilidade | Fase 2 |

Ordem e diferenças entre desktop e mobile: secção *Desktop e mobile* do style guide.

## 5. Integração com o checkout da Tickett

### Decisão de 24/11/2026 (modelo em vigor)

O cliente decidiu que **não há autorização para alterar o checkout da Tickett**. O site só o chama e o estiliza. Por isso:

- Não há modo embed, `products.json`, tema no checkout nem subdomínio `bilhetes.festeja.pt`. Tudo o que tinha sido feito no checkout foi revertido (ver `docs/checkout-tickett-alteracoes.md`).
- O site lê os bilhetes da página pública do checkout (`{base}/{event_id}`) e extrai-os do HTML (`TickettService`). Mostra o checkout num modal, através de um proxy no próprio domínio (`/checkout`, `CheckoutProxy`). É o modelo do projeto festival-crato.
- Um tema CSS só de cor (`checkout-tema.css`) e uma ponte (`checkout-ponte.js`) são injetados pelo proxy na página do checkout. A ponte fala com o site por `postMessage`, com origem verificada.
- Trocar de bilhete recarrega o iframe com `?produto=`. Já não há mensagem `select`.

Consequências: o parser e o proxy dependem do HTML atual da Tickett; os pagamentos com redirecionamento externo saem do proxy; e os cookies `tk_*` são cookies do domínio do site. Ver a secção 10 (riscos) e `docs/contrato-tickett.md`.

As subsecções seguintes registam a análise anterior. Ficam como histórico.


### Como funciona hoje (Crato)

O Crato usa o checkout da LeBillet (`checkout.lebillet.eu/{evento}`), que não tem API JSON:

- `LeBilletService` lê os bilhetes fazendo scraping do HTML (`GET /{evento}` para obter o `sid`, `POST /{evento}/products`, regex nas classes `textprodXXXX`).
- `checkout-proxy.php` descarrega o HTML do checkout, reescreve URLs, injeta CSS com `!important` para pintar com a marca, injeta JS para filtrar o produto e contar o carrinho, e faz relay dos pedidos AJAX. O site mostra isto num iframe dentro de um modal.

Funciona, mas é frágil. Já partiu uma vez quando o HTML da LeBillet mudou (commit `cf6a125` do Crato). Os problemas:

- O scraping e o CSS injetado dependem da estrutura interna do checkout.
- O proxy desliga a verificação SSL (`verify_peer => false`) e reencaminha cookies.
- O `postMessage` usa `"*"` como origem.
- A altura do iframe é calculada com timers e MutationObserver.
- A acessibilidade do checkout fica fora do nosso controlo.

### Recomendação anterior: modo embed oficial no checkout (modelo B, abandonado em 24/11/2026)

*Histórico, não implementado.* Ver a decisão acima.

Como temos o código do checkout (`/Users/carloslima/checkout`, Phalcon), a integração personalizada pode ser feita nos dois lados, com um contrato estável:

1. **Subdomínio próprio**: `bilhetes.festeja.pt` aponta (CNAME) para o checkout da Tickett. Assim o iframe é *same-site* e os cookies de sessão funcionam no Safari e com bloqueio de cookies de terceiros, que é o motivo real para o Crato usar proxy.
2. **Modo embed**: `/{evento}?embed=1&theme=festeja&product={id}`.
   - Esconde o header e o rodapé da Tickett e filtra o produto no servidor.
   - Aplica um tema por empresa (folha CSS com custom properties mapeadas para os tokens do Festeja), em vez de CSS injetado.
3. **Endpoint JSON de produtos**: `GET /{evento}/products.json` com nome, descrição, preço, taxa, estado (disponível, últimos, esgotado) e `product_id`. Substitui o scraping. Cache de 5 min no site.
4. **Contrato `postMessage`**, com origem verificada dos dois lados (`https://festeja.pt` ↔ `https://bilhetes.festeja.pt`):

   | Mensagem | Direção | Dados | Uso |
   | --- | --- | --- | --- |
   | `tickett:ready` | checkout → site | — | Esconder o "A carregar…" |
   | `tickett:resize` | checkout → site | `height` | Altura do iframe sem timers |
   | `tickett:cart` | checkout → site | `count`, `total` | Contador do carrinho no header |
   | `tickett:step` | checkout → site | `step` (`produtos`, `dados`, `pagamento`) | Título do modal e analytics |
   | `tickett:purchase` | checkout → site | `orderId`, `value`, `currency`, `items` | Evento `purchase` no GA4 |
   | `tickett:close` | checkout → site | — | Fechar o modal |
   | `tickett:filter` | site → checkout | `productId` | Trocar de bilhete sem recarregar |

5. **Cabeçalhos**: `Content-Security-Policy: frame-ancestors https://festeja.pt` por empresa, em vez de `X-Frame-Options`.
6. **Acessibilidade do checkout**: auditoria WCAG 2.2 AA do modo embed (foco, rótulos, erros, contraste do tema). Sem isto o site não cumpre AA no fluxo principal.

As alterações no checkout (pontos 2 a 5) são um projeto próprio no repositório `checkout` e beneficiam todos os clientes da Tickett.

### Alternativa: proxy endurecido (modelo A, adotado em 24/11/2026)

*Adotado, com alterações:* o proxy é `app/Services/CheckoutProxy.php`, e o catálogo é lido do HTML da página do checkout, sem `products.json`.

Se o modelo B não estiver pronto quando abrir a venda, reutiliza-se o proxy do Crato com estas correções:

- `verify_peer => true`.
- `postMessage` com origem explícita.
- Tema injetado limitado a custom properties.
- Cache do scraping.
- Teste automático diário que avisa quando o HTML do checkout muda.

O site fala com os dois modelos através da mesma interface (`TickettService` + `CheckoutModal.open(eventId, productId)`), por isso trocar de A para B não mexe nas páginas.

### Fluxo no site

Continua válido, exceto a troca de bilhete. Hoje o iframe é recarregado com `?produto=`, sem mensagem `select`.

1. O visitante escolhe um `TicketCard` (ou "Comprar bilhetes" no header ou na `StickyBuyBar`).
2. Abre o `CheckoutModal` com o iframe já filtrado no produto, com foco no botão Fechar e `Esc` a fechar.
3. Fechar o modal mantém o carrinho; o contador no header mostra os bilhetes escolhidos.
4. Na compra concluída, `tickett:purchase` regista a conversão e o modal mostra "Compra concluída. Os bilhetes vão para o teu email."
5. Sem JS, os botões gerais de compra são links para `/bilhetes`, e "Escolher" é um link direto para o checkout (na mesma página). Com o iframe bloqueado ou a demorar, a mensagem de "A bilheteira está a demorar" tem o link para o checkout numa página nova.

## 6. SEO e pesquisa por agentes de IA

- **HTML completo no servidor**: cartaz, preços, datas e FAQ em texto, nunca só em imagens ou carregados por JS.
- **Bloco "Factos rápidos"** logo abaixo do hero e em `/informacoes`: datas, horário de portas, local e morada, preço "desde", idade mínima e organizador. É o texto que os assistentes citam.
- **JSON-LD** gerado de `content/` e da Tickett:
  - `MusicFestival` (com `Event`) e `startDate`/`endDate`, `location` (`Place` com morada e geo), `organizer` (RTP África), `performer` (lista de `MusicGroup`/`Person`), `offers` (um `Offer` por bilhete, com `price`, `priceCurrency`, `availability` e `url`), `eventStatus` e `eventAttendanceMode`;
  - `FAQPage` em `/informacoes`;
  - `MusicGroup` ou `Person` em cada página de artista;
  - `BreadcrumbList` e `Organization` + `WebSite`.
- **Meta**: `title` e `description` únicos por página, `canonical`, Open Graph e Twitter com imagem 1200×630 por página (gerada do style guide), `lang="pt-PT"` e `hreflang` se houver EN.
- **`sitemap.xml`** gerado com `lastmod` real.
- **`robots.txt`** permite os crawlers de pesquisa e de IA (Googlebot, Bingbot, GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot) e bloqueia `/checkout-proxy.php`.
- **`/llms.txt`**: resumo do festival em markdown com links para as páginas principais e um `/llms-full.txt` com os factos, o cartaz e o FAQ em texto.
- **Datas estruturadas consistentes**: uma única fonte (`festival.json`) alimenta o hero, os factos rápidos, o JSON-LD e o `llms.txt`.
- **URLs estáveis** entre edições: `/cartaz` é sempre a edição atual; edições passadas em `/2026/cartaz`.

## 7. Acessibilidade

Segue a secção *Acessibilidade* do style guide. Na prática do projeto:

- Componentes acessíveis de origem (o style guide já define papéis, foco e teclado de cada um).
- `pa11y-ci` ou `axe` no CI a todas as rotas do sitemap; falha o build com qualquer erro.
- Checklist manual por página antes de cada entrega: teclado, zoom 200%, 320px, VoiceOver iOS, NVDA.
- Declaração de acessibilidade publicada em `/acessibilidade`.

## 8. Privacidade e medição

- Banner de cookies com "Aceitar" e "Rejeitar" ao mesmo nível (como no Crato) e Consent Mode v2.
- GA4 com eventos de e-commerce: `view_item_list` (secção de bilhetes visível), `select_item` (clique num bilhete), `begin_checkout` (modal aberto), `purchase` (mensagem da Tickett).
- Pixels de redes sociais só com consentimento e só se a organização os pedir.

## 9. Fases e tarefas

Cada tarefa é pequena e independente o suficiente para ser delegada; o critério de pronto vai entre parênteses.

### Fase 0 — Insumos e decisões
- Responder às decisões da secção 2 (registadas neste ficheiro).
- Receber os originais do designer, as fotografias de 2026 com autorização de uso e os textos.

### Fase 1 — Fundação
1. Copiar a base do Crato (router, `Component`, autoloader) e limpar o que é específico do Crato (router responde às rotas da secção 4 com 404 real nas outras).
2. Gerar `tokens.css` a partir do `tokens.json` do style guide e criar `base.css` (reset, tipografia, temas `papel`/`noite`, foco) (tokens iguais aos do style guide).
3. `SiteHeader` com menu mobile acessível e `Footer` (teclado, `Esc`, `aria-expanded`, foco preso).
4. `Seo.php`: meta, canonical, OG e JSON-LD a partir de `content/` (validação sem erros no Rich Results Test).
5. `content/festival.json` com datas, local, horários e organizador (todas as páginas leem daqui).

### Fase 2 — Páginas
1. Página inicial nas duas composições (desktop e mobile conforme o style guide).
2. `/bilhetes` com `TicketCard` e estados (disponível, últimos, esgotado).
3. `/cartaz` com `LineupDay` e separadores no mobile.
4. `/artistas/{slug}` gerada de `artistas.json`.
5. `/informacoes` com `Faq` e `FAQPage`.
6. `/como-chegar` com mapa estático e transportes.
7. Páginas legais, de acessibilidade e 404.

### Fase 3 — Tickett
1. `TickettService` (eventos e produtos, cache de 5 min, falha graciosa: sem dados mostra "Bilhetes em breve" e o link direto).
2. `CheckoutModal` + `checkout.js` com o contrato `postMessage` (modal acessível, carrinho preservado, contador no header).
3. Modelo A: `checkout-proxy.php` endurecido. **Ou** modelo B: modo embed, tema, `products.json`, `frame-ancestors` e subdomínio no repositório do checkout. **Decidido a 24/11/2026: modelo A (`CheckoutProxy.php`), sem alterar o checkout.**
4. `StickyBuyBar` com a lógica de aparecer e esconder.
5. Analytics do funil com Consent Mode.

### Fase 4 — SEO e IA
1. `sitemap.xml`, `robots.txt`, `llms.txt` e `llms-full.txt` gerados de `content/`.
2. Imagens Open Graph por página.
3. Bloco "Factos rápidos".

### Fase 5 — Qualidade
1. axe/pa11y no CI e testes manuais de acessibilidade.
2. Lighthouse CI com os orçamentos da secção 1.
3. Testes de compra reais em iOS Safari, Android Chrome, desktop Safari, Chrome e Firefox, incluindo cookies de terceiros bloqueados.
4. Revisão de texto pt-PT.

### Fase 6 — Lançamento
1. DNS (`festeja.pt`; `bilhetes.festeja.pt` deixou de ser necessário a 24/11/2026), HTTPS, redirecionamentos das URLs antigas.
2. Search Console e Bing Webmaster com o sitemap.
3. Monitorização de disponibilidade do site e do checkout.
4. Notícias (opcional).

## 10. Riscos

| Risco | Impacto | Mitigação |
| --- | --- | --- |
| HTML do checkout muda (modelo A) | Bilhetes somem ou o tema parte | Modelo B abandonado a 24/11/2026. No A: falha graciosa com link direto (existe); teste diário (ainda não existe) e `dev/tickett-parse-check.php` |
| Cookies de terceiros bloqueados no iframe | Carrinho perde-se no Safari | Proxy no próprio domínio (A, em uso): o iframe é same-site e os cookies `tk_*` são do domínio do site. Subdomínio (B) abandonado. Fallback para página própria |
| Logótipos vetorizados de JPEG | Bordas e texturas diferentes do original | Trocar pelos originais na Fase 0 |
| Checkout da Tickett não cumpre AA | Fluxo principal inacessível | Sem alterações no checkout (decisão de 24/11/2026). Pedir correções à Tickett e ajustar só o tema de cor no site |
| Cartaz anunciado aos poucos | Páginas vazias | Componentes com estado "Mais nomes em breve"; o FAQ e os bilhetes sustentam a página |
| Pico de acesso na abertura de um lote | Checkout lento ou carrinho a falhar | Hora de abertura anunciada no site, cache curta da página do evento (5 min), teste de carga no checkout antes da venda |
| Data de 2027 errada publicada | Perda de confiança e de SEO | Data só em `festival.json`, revista antes do lançamento (datas confirmadas pela arte: 30 e 31 julho) |
| Parser da leitura da Tickett (`TickettService`) partir se mudarem o markup dos bilhetes | Bilhetes em falta ou cache antiga | `dev/tickett-parse-check.php` antes de cada venda; log com prefixo `TickettService:`; cache de 5 min com a última leitura boa |
| Proxy partir se mudar `var BASE_URL` ou a forma dos atributos da página | Checkout não funciona no iframe, ou o carrinho deixa de funcionar | Teste de compra completo sempre que a Tickett mudar o checkout; `CheckoutProxy::reescrever` é o ponto de ajuste |
| Pagamentos com redirecionamento externo (Viva Wallet, myPOS, PayPal, PicPay, PIX, 3DS) saem do proxy | Sem `step` nem `purchase`; o cookie `tk_*` não segue; a página de retorno pode não voltar ao site | Pagamento real de teste em cada método antes de lançar. Aceitar a limitação ou pedir à Tickett um retorno ao site |
| Termos da Tickett sobre proxy e reescrita das suas páginas | Pedido para retirar o proxy, ou restrições | Confirmar por escrito com a Tickett antes de lançar |
| Cookies `tk_*` no domínio do site | Textos de privacidade e de cookies desatualizados (dizem que não há cookies próprios) | Rever `content/paginas/cookies.html` e `privacidade.html` antes de lançar |
| Suíte de QA desatualizada (`dev/qa`) | Falsa sensação de segurança no fluxo de compra | Reescrever a suíte para o proxy `/checkout` e a ponte antes de a usar como critério |
