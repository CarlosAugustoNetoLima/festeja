# Contrato site ↔ checkout Tickett

Estado: em vigor desde 24/11/2026. Substitui o modelo de embed que estava previsto (ver `docs/checkout-tickett-alteracoes.md`).

O checkout da Tickett é o **único** sítio onde vivem bilhetes, preços, lotes, stock, carrinho e pagamento. Não alteramos o checkout. O site só lê a página pública, mostra o checkout dentro de um modal e o estiliza.

O site fala com a Tickett em três pontos:

1. **Leitura dos bilhetes.** O servidor do site pede a página pública do evento (`{base}/{event_id}`) e lê os blocos de bilhetes do HTML. A Tickett não tem API de produtos nem `products.json`.
2. **Proxy do checkout.** O modal abre um iframe do próprio domínio, `/checkout`. O site pede a página à Tickett, reescreve-a e reencaminha os pedidos AJAX.
3. **Mensagens.** Uma ponte (`checkout-ponte.js`), injetada pelo proxy na página do checkout, avisa o site sobre passo, carrinho, compra e fecho.

Se um ponto falhar, o visitante não fica sem saída: a grelha mostra a última leitura boa ou uma mensagem com o link para a bilheteira, e o modal tem o link "Abre a bilheteira numa página nova".

## 1. Leitura dos bilhetes (`TickettService`)

### Pedido

`GET {base}/{event_id}`, com `Accept: text/html` e `User-Agent: FestejaSite/1.0`. O certificado é verificado. Timeout de `tickett.timeout` (3 s). Quem pede é o servidor do site, não o browser.

Falha (rede, certificado, timeout, HTTP diferente de 200 ou página sem o bloco de bilhetes) → usa a cache ou mostra a mensagem de falha. A causa vai para o log, com o prefixo `TickettService:`.

### O que se lê

Os bilhetes estão no bloco `#tickets-form-products`, que é o `products/group.volt` do checkout. Cada bilhete é uma tabela `table.ticket-box`. Um bloco sem bilhetes à venda é válido: a venda está fechada ou ainda não abriu.

| Campo | Origem no HTML | Notas |
| --- | --- | --- |
| `name` | Texto do `h5`, sem a descrição | |
| `description` | `<small>` dentro do `h5` | Pode estar vazio |
| `price` | "€ 14,25", na 2.ª célula da 2.ª linha | Preço sem taxa |
| `batch` | "Tier N" ou "Lote N", na 1.ª célula ou no nome | Normalizado para "Lote N" |
| `id` e `produto` | Select `product_{id_product}_{id_type}` (`select.quantity-select`) | `id` = `id_product`; `produto` = `{id_product}_{id_type}` |
| `status` | Com select: `available`. Sem select, com "em breve", "brevemente" ou "coming soon": `upcoming`. Outro caso: `sold_out` | |
| `checkout_url` | `{base}/{event_id}?produto={produto}` | Pré-seleção do bilhete |

Ficam `null` por não existirem no HTML: `total`, `fee`, `highlight`, `next_batch` e `sale.opens_at`. `days` fica `[]`.

Dados do evento:

- `name`: texto do `<title>` antes de " | ".
- `sale.status`: `open` se algum bilhete estiver `available`; `upcoming` se algum estiver `upcoming`; senão `closed`.

O estado `low` (últimos bilhetes) não existe no HTML e nunca é devolvido.

### Taxa de serviço

A taxa só aparece no carrinho. O site mostra o preço com "Acresce a taxa de serviço da Tickett, indicada no checkout.". `fee_included` é sempre `false`.

Se um dia a taxa vier incluída no preço, é preciso mudar o parser (`fee_included` em `TickettService::parseProduto`). Só então os textos passam a "taxas incluídas". A decisão está em `TickettService::taxaIncluida()`, usada por `Seo`, `FactsStrip`, `HomeHero`, `StickyBuyBar`, `bilhetes.php` e `kit.php`. `TicketCard` lê o campo `fee_included` diretamente.

### Cache

- Ficheiro: `storage/cache/tickett-{event_id}.json`. Validade: `tickett.cache_ttl` (300 s).
- Se a Tickett falhar, usa a última leitura boa, mesmo antiga. O visitante não vê aviso; o aviso vai para o log.
- Sem cache e sem leitura, a grelha mostra "A bilheteira não respondeu. Compra diretamente na página da bilheteira.".

### Verificação

`php dev/tickett-parse-check.php` imprime o catálogo lido e compara-o com `dev/tickett-stub/data/catalog.json` (ordem, nomes, descrições, preços, lotes e esgotados). Sai com código 1 se algum campo não bater. Aceita `--base=` e `--ttl=`. Por exemplo, `--base=http://127.0.0.1:9 --ttl=0` testa a falha.

Com a Tickett real, a comparação com o simulador não se aplica: confere à vista o catálogo impresso com a página da bilheteira.

### Limite

O parser cobre só o layout de `group.volt` (lista de bilhetes). Eventos com mapa de lugares não são lidos.

## 2. Proxy do checkout (`CheckoutProxy`)

| Rota | Métodos | Faz |
| --- | --- | --- |
| `/checkout` | GET | Página do checkout, reescrita. Com `?produto=ID_TIPO` (ex.: `9001_1`), a Tickett pré-seleciona esse bilhete e acrescenta 1 unidade. Com `?lang=pt` ou `?lang=en`, o checkout abre nesse idioma (ver "Idioma") |
| `/checkout/relay/{caminho}` | GET, POST | Reencaminha os pedidos AJAX do checkout para `{base}/{caminho}` |

Regras:

- `?produto=` aceita só `número_número`. Outro valor é ignorado.
- `?lang=` aceita só `pt` e `en`. Outro valor, ou a ausência, é `pt`.
- O relay só aceita caminhos do evento (`{event_id}/…`) e `waiting-list`. Caracteres permitidos: letras, números, `/`, `_` e `-`. Sem `.`, para não haver travessia de caminhos. O corpo do POST tem no máximo 64 KB.
- Redirecionamentos são seguidos à mão, no máximo 2, e só para o mesmo host. Redirecionamento para outro host é erro.
- O certificado da Tickett é verificado (peer e host).

### Cookies

- Cada `Set-Cookie` da Tickett chega ao browser com o nome `tk_{nome}`, `Path=/checkout`, `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS. Mantém `Expires` e `Max-Age`; `Domain` e os outros atributos são descartados.
- Nos pedidos ao checkout seguem só os cookies `tk_*`, sem o prefixo. Os cookies do site nunca vão para a Tickett.
- Se o POST trouxer um `sid` válido (o `checkout.js` do checkout envia-o em cada pedido), esse valor vai como `PHPSESSID`.
- Estes cookies são do domínio do site. Ver a secção 6 do `docs/guia-de-manutencao.md` (privacidade).

### Cabeçalhos da resposta

`X-Frame-Options: SAMEORIGIN`, `Content-Security-Policy: frame-ancestors 'self'`, `Referrer-Policy: same-origin`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store` e `X-Robots-Tag: noindex`. Os cabeçalhos da Tickett não chegam ao browser. O `robots.txt` bloqueia `/checkout`.

### Idioma

O checkout da Tickett escolhe o idioma pelo parâmetro `l` (o do rodapé dele, `?l=pt_pt`, `?l=pt_br`, `?l=en_us`, `?l=es_do`). `l` grava o idioma na sessão da Tickett (`services.php`, serviço `t`): sem `l`, usa a sessão ou o predefinido `pt_pt` (`config_company`, `default_language`). Os textos vêm da tabela `languages` da BD da Tickett, por coluna (`pt_pt`, `en_us`…), e a chave em falta aparece em bruto.

O site passa o idioma assim:

1. `CheckoutModal` põe `data-src="/checkout?lang=pt"` no iframe (`lang` vem da prop `idioma` do componente; predefinido `pt`, o mesmo do `<html lang="pt-PT">` de `layout.php`).
2. `checkout.js` lê o `data-src` em cada abertura e acrescenta `&produto=` quando já há query.
3. `CheckoutProxy::pagina()` converte `lang` para o código da Tickett (`pt` → `pt_pt`, `en` → `en_us`) e pede `{base}/{event_id}?l=…` (com `&produto=` se houver). Por isso o idioma fica na sessão da Tickett, gravada no `tk_PHPSESSID`.
4. O relay não leva `l`: os fragmentos AJAX seguem o idioma da sessão, que é a mesma sessão (o `sid` do corpo do POST). Se a sessão expirar, o carrinho também se perde, por isso não se duplica o parâmetro em cada pedido.
5. A ponte, ao trocar de bilhete (`select` do site), recarrega o iframe com `location.replace` mantendo os outros parâmetros (`lang`) e trocando só `produto`.

Para o simulador, `dev/tickett-stub/` imita o mesmo: `?l=` grava o idioma na sessão e as traduções vêm de `dev/tickett-stub/data/traducoes.php`.

Os nomes de países e cidades do formulário de dados (`login/site.volt`, `traduzirTexto`) são traduzidos pelo próprio checkout com um pedido `fetch` direto a `translate.googleapis.com/translate_a/single`, com `tl` = idioma da sessão. Não passa pelo widget, que o proxy remove. Se o pedido falhar, ficam os nomes em português.

Fora do iframe, os links diretos para a Tickett (o fallback sem JavaScript, "Abrir a bilheteira numa página nova" e o `checkout_url` de cada bilhete) levam também `?l=pt_pt` ou `?l=en_us`, conforme a página (`TickettService::comIdioma()`). O catálogo em inglês é lido da página da Tickett com `?l=en_us`, com cache própria (`storage/cache/tickett-{id}-en.json`). Os nomes e as descrições dos bilhetes são os que a Tickett devolve no idioma pedido: se a Tickett não tiver a tradução, ficam em português.

### Reescrita da página

- Remove `integrity` e `crossorigin`.
- Torna absolutos os `src`, `href` e `action`. As ligações à página do evento ficam no proxy (`/checkout` ou `/checkout/relay/…`).
- Troca `var BASE_URL = "…";` por `var BASE_URL = "/checkout/relay/";`.
- Remove o widget do Google Translate que o checkout original carrega: o `<script>` externo, o `<script>` inline com `googleTranslateElementInit` e o `div#google_translate_element`. Não é preciso, porque o idioma já vem do `?l=` nativo. O widget recarregava o iframe ~1 s depois de abrir, e os pedidos à Google eram bloqueados por captcha. A remoção é `CheckoutProxy::removerGoogleTranslate`, a primeira passagem da reescrita.
- Acrescenta a classe `fj-checkout-tema` ao `<html>`.
- Injeta `public/assets/css/checkout-tema.css` antes de `</head>` e `public/assets/js/checkout-ponte.js` (com `data-product`) antes de `</body>`.

A reescrita é feita com expressões regulares. Se a Tickett mudar a forma destes atributos, a reescrita falha sem erro visível (ver a secção 4).

### Erros

- Sem resposta, HTTP diferente de 200 ou redirecionamento externo: `502`, com "A bilheteira não respondeu." e o link "Abre a bilheteira numa página nova", que vai diretamente à Tickett.
- Falha no relay: `502` com `{"error":"relay failed"}`.

### Limites

- Pagamentos com redirecionamento externo (Viva Wallet, myPOS, PayPal, PicPay, PIX, 3DS) saem do proxy para o domínio da Tickett. Nesse caso não há passos nem compra na ponte, e o cookie `tk_*` não segue.
- O fluxo FNAC usa `window.top`. Dentro do iframe, isso aponta para a página do site.
- Os recursos da Tickett (imagens, fontes) carregam do domínio dela.

## 3. Ponte e mensagens

A ponte (`public/assets/js/checkout-ponte.js`) corre dentro da página do checkout e só envia mensagens se estiver num iframe. Envia sempre para `location.origin` (nunca `"*"`), com este envelope:

```json
{ "source": "tickett", "version": 1, "type": "cart", "data": { } }
```

`public/assets/js/checkout.js` valida `event.origin === location.origin`, `event.source === iframe.contentWindow`, `source === "tickett"` e `version === 1`. Ignora `type` desconhecidos.

| `type` | `data` | O que o site faz |
| --- | --- | --- |
| `ready` | `{}` | Esconde "A carregar a bilheteira…". Se não chegar em 8 s, mostra "A bilheteira está a demorar" com o link para a página nova |
| `resize` | `{ "height": 812 }` | Ajusta a altura do iframe (entre 480 e 4000 px) |
| `step` | `{ "step": "products" \| "details" \| "payment" \| "done" }` | Atualiza o título do modal e envia `checkout_step` ao `dataLayer` |
| `cart` | `{ "count": 2, "total": 63.00, "currency": "EUR" }` | Atualiza o contador do carrinho no header e guarda-o em `sessionStorage` |
| `purchase` | `{ "order_id": null, "total": 63.00, "currency": "EUR", "items": [ { "id": 9001, "name": "…", "quantity": 2, "price": 31.50 } ] }` | Envia `purchase` ao `dataLayer` (uma vez) e mostra a mensagem de sucesso |
| `close` | `{}` | Fecha o modal. Enviado pela tecla Esc dentro do checkout |

Notas:

- **Passo.** A ponte só envia `step` depois de 150 ms sem mudanças no DOM, porque o checkout troca o carrinho em vários passos transitórios. Um regresso a `products` só conta se a lista de bilhetes estiver visível (por exemplo, com "Voltar").
- **`purchase.order_id`** é `null` no fim normal: a vista de conclusão da Tickett não mostra o número. Só aparece "#N" na vista `finishedMbWay` e, no myPOS, no script de conversão. Sem `order_id`, o site ignora repetições dentro de 5 s.
- **`items`** vêm do carrinho guardado antes da compra, porque o carrinho é limpo ao concluir. `id` fica `null` se o nome da linha não bater com um bilhete visto na página.
- **Passos e carrinho** são lidos do DOM: `#tickets-form-products`, `#tickets-form-register`, `#tickets-form-payment`, `.cart-finish`, `table.table-cart-resume`, `tr.total-all` e `table.ticket-box`. Se a Tickett mudar estes ids e classes, as mensagens param sem erro visível.
- **Bilhete destacado.** Com `?produto=`, a ponte acrescenta a classe `fj-produto-selecionado` ao bilhete pré-selecionado e leva-o à vista. Não filtra nem esconde nada.
- **Cabeçalho do iframe.** O iframe tem `id="Tickets_Embedded_Checkout_Site"`. O `checkout.js` do próprio checkout usa esse id para esconder o `.header` quando está num iframe, por isso o layout é o da Tickett.

### Troca de bilhete

O site não envia `select`. Trocar de bilhete recarrega o iframe com `?produto=` (função `open` de `checkout.js`). A ponte ainda tem um ouvinte para `select` com `source: "site"`, que recarrega a página com `?produto=`, mas nada o envia. Pode ser removido.

## 4. Se a Tickett mudar algo

| Mudança na Tickett | Consequência | Onde ajustar | Como detetar |
| --- | --- | --- | --- |
| Markup dos bilhetes (`#tickets-form-products`, `table.ticket-box`, `h5`, `select.quantity-select`, id `product_…`) | Bilhetes em falta, ou "sem o bloco de bilhetes" no log. Sem cache, aparece a mensagem de falha | `TickettService::parseCatalogo` e `parseProduto` | `php dev/tickett-parse-check.php`; log com prefixo `TickettService:` |
| Página do checkout com outro layout (por exemplo, mapa de lugares) | Sem bilhetes | `TickettService` (novo parser) | `dev/tickett-parse-check.php`; `/bilhetes` |
| `var BASE_URL` ou a forma dos `src`, `href` e `action` | A reescrita falha sem erro visível. Os pedidos AJAX passam a ir ao domínio da Tickett, fora do proxy, e o carrinho pode deixar de funcionar | `CheckoutProxy::reescrever` | Abrir `/checkout` e ver o `BASE_URL` no código da página (deve ser `/checkout/relay/`); fazer um teste de compra |
| Domínio do checkout (`tickett.base`) | Leitura e proxy falham | `config/site.local.php` | `curl -sI {base}/{event_id}` |
| ID do evento | Página de outro evento | `tickett.event_id` | `dev/tickett-parse-check.php` |
| Cores, fontes ou aspeto do checkout | O tema pode deixar de encaixar ou ter contraste baixo | `public/assets/css/checkout-tema.css` | Abrir `/bilhetes`, clicar em "Escolher" e ver o modal |
| Ids e classes dos passos (formulários de dados e pagamento, `.cart-finish`, carrinho) | `step`, `cart` ou `purchase` deixam de chegar ao site | `public/assets/js/checkout-ponte.js` | Fazer uma compra de teste e ver as mensagens na consola |
| Novo método de pagamento com redirecionamento externo | Sai do proxy: sem `step` nem `purchase` | Não há código a mudar. Aceitar a limitação ou pedir à Tickett | Pagamento real de teste |
| Textos em falta na tradução `en_us` ou `pt_pt` da Tickett (chave aparece em bruto, ex.: `full_name`) | Aparece a chave, ou texto em inglês num checkout em pt | Pedido à Tickett para a tabela `languages` (ver `docs/checkout-tickett-alteracoes.md`) | Visual e `dev/qa/qa.mjs checkout-layout` (procura chaves em bruto em pt e en) |
| Cabeçalhos de segurança do checkout (`frame-ancestors`, `X-Frame-Options`) | Sem efeito: o proxy não passa os cabeçalhos da Tickett | Nada | Nada |

## Onde está cada lado

- **Site:** `app/Services/TickettService.php` (leitura, cache e parser), `app/Services/CheckoutProxy.php` (proxy), `public/index.php` (rotas `/checkout`), `app/Views/Components/CheckoutModal.php` (iframe), `public/assets/js/checkout.js` (modal e mensagens), `public/assets/js/checkout-ponte.js` (ponte) e `public/assets/css/checkout-tema.css` (tema). Configuração em `config/site.php`, secção `tickett`.
- **Simulador:** `dev/tickett-stub/` imita a página publicada e os fragmentos AJAX. Usa os templates reais do checkout, lidos em só leitura de `/Users/carloslima/checkout`, e os dados de `dev/tickett-stub/data/catalog.json`. Não tem `products.json` nem modo embed. Páginas: `http://127.0.0.1:8081/2001`, `?produto=9001_1`, `?fail=1` (500) e `?slow=1` (5 s).
- **Checkout real:** não é alterado. Ver `docs/checkout-tickett-alteracoes.md`.

Histórico: a versão anterior deste contrato previa um modo embed com `products.json`. Foi substituída a 24/11/2026. A decisão está em `docs/plano-implementacao.md`, secção 5.
