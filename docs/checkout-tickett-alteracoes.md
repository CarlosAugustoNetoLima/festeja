# Não alteramos o checkout da Tickett

Decisão do cliente, 24/11/2026: não há autorização para alterar o checkout da Tickett. O site só o chama e o estiliza.

Este documento lista o que foi revertido, o que o site faz em vez disso, os pedidos opcionais à Tickett e o que verificar antes de lançar. O contrato técnico está em `docs/contrato-tickett.md`.

## O que foi revertido

Antes desta decisão, o plano era um modo embed no código do checkout (`/Users/carloslima/checkout`). Tudo isso foi revertido:

- Modo embed (`?embed=1`), ponte `embed-bridge.js` e a camada de CSS do embed.
- Tema do evento no checkout (`themes/festeja.css`) e a configuração por evento (`app/config/embed.php`).
- `products.json` (rota e método no `ProductsController`) e a biblioteca `EmbedLib`.
- Atributos `data-*` nas views (`products/group.volt` e os `.cart-finish` das vistas de compra).
- Override do host `bilhetes.festeja.pt` em `public/index.php`.
- Correções F1, F3 e F4 em `checkout.js` e nas vistas de compra.

Estado verificado no checkout real: não existem `app/config/embed.php`, `app/library/EmbedLib.php`, `public/js/embed-bridge.js`, `public/css/embed-base.css` nem `public/css/themes/festeja.css`. Nas views não há `is-embed`, `TICKETT_EMBED`, `data-product-id` nem `data-order-id`.

## O que o site faz em vez disso

| Necessidade | Solução | Ficheiro |
| --- | --- | --- |
| Ler os bilhetes | Lê a página pública do evento e extrai os blocos de bilhetes do HTML. Cache de 5 min, e a última leitura boa como fallback | `app/Services/TickettService.php` |
| Mostrar o checkout no modal | Iframe do próprio domínio, apontado a `/checkout` (o proxy) | `app/Views/Components/CheckoutModal.php` |
| Passar pela Tickett sem expor cookies do site | Proxy: página e relay AJAX, cookies `tk_*`, certificado verificado, cabeçalhos de segurança | `app/Services/CheckoutProxy.php`, `public/index.php` |
| Receber passo, carrinho, compra e fecho | Ponte injetada na página do checkout, que envia mensagens para a origem do site | `public/assets/js/checkout-ponte.js` |
| Aspeto do checkout | Tema injetado só de cor (com tipos, raios e sombras), escopo `.fj-checkout-tema`. Layout e textos não mudam | `public/assets/css/checkout-tema.css` |
| Escolher um bilhete | Abre o checkout com `?produto={id_product}_{id_type}`, que a Tickett pré-seleciona (acrescenta 1 unidade) | `public/assets/js/checkout.js`, `CheckoutProxy` |

## Pedidos opcionais à Tickett

Não bloqueiam o lançamento:

1. **Número da encomenda na vista de fim de compra.** Um atributo com o `order_id` em `delivery/sent`. Hoje o `purchase` sai com `order_id` `null` na maior parte dos casos.
2. **Tradução pt-PT das chaves em inglês** que aparecem no checkout (por exemplo, "YOUR ORDER" e "Tier").
3. **Valor da taxa de serviço por bilhete.** Hoje o site diz "Acresce a taxa de serviço da Tickett, indicada no checkout." sem valor.
4. **Encravamento do `buttonWait`.** O `buttonWait` em `#button-products` não é restaurado, e há timers de 3 s e 4 s. É comportamento do checkout, não do site.
5. **Expressão da taxa da organização.** Confirmar se `fov.tax * (fov.tax / 100)` em `ProductsLib` (sem `tax_type`) continua no checkout. O site já não calcula taxas; o pedido é só para a Tickett confirmar.

## Checklist antes de lançar

- [ ] `tickett.event_id` com o ID real em `config/site.local.php`. O evento tem bilhetes à venda na página pública (`{base}/{event_id}`).
- [ ] `php dev/tickett-parse-check.php` com `tickett.base` do evento real. A comparação final é com o simulador: com o evento real, confere à vista o catálogo impresso com a página da bilheteira (nomes, preços, lotes e estados).
- [ ] Fluxo completo com um pagamento real de teste: escolher bilhete, dados, pagamento e página de conclusão. Confirmar, na consola ou no `dataLayer`, os passos (`products`, `details`, `payment`, `done`), o `cart` e um só `purchase`.
- [ ] Cada pagamento com redirecionamento externo (Viva Wallet, myPOS, PayPal, PicPay, PIX, 3DS): anotar se sai do proxy e o que a página final mostra. Nesses casos, passos e compra não chegam ao site.
- [ ] Cookies `tk_*`: ver o que o browser guarda em `/checkout` e atualizar `content/paginas/cookies.html` e `privacidade.html`. Hoje dizem que o site não usa cookies próprios, o que deixou de ser verdade.
- [ ] Robots: `robots.txt` tem `Disallow: /checkout`; a resposta de `/checkout` traz `X-Robots-Tag: noindex`; `/checkout` não aparece no `sitemap.xml`.
- [ ] HTTPS: em produção, os cookies `tk_*` saem com `Secure`. Confirmar se o servidor estiver atrás de um proxy de TLS, porque o proxy só lê `$_SERVER['HTTPS']`.
- [ ] Modal a partir do próprio site: abre, carrega o checkout e fecha com Esc e com o botão de fechar.
- [ ] Erro: com a Tickett em baixo (ou `tickett.base` errado), `/checkout` mostra o 502 com o link direto.
- [ ] Suíte de QA (`dev/qa/`): ainda testa o modelo antigo (`products.json`, `embed=1`, mensagem `select`). Não serve como critério até ser reescrita.

## Ativar um evento

Só `tickett.event_id` em `config/site.local.php`, e `tickett.base` em produção. Não há nada a configurar no checkout.

## Histórico

A versão anterior deste documento descrevia o modo embed. Foi substituída a 24/11/2026. Ver `docs/plano-implementacao.md`, secção 5.
