# Simulador local da Tickett (`dev/tickett-stub/`)

Só para desenvolvimento. Imita o **checkout publicado** da Tickett (a página `/{evento}` e os fragmentos AJAX) e mais nada. Não tem `products.json`, não tem modo embed nem `postMessage`: essas coisas são do site (`app/Services/CheckoutProxy.php`, `public/assets/js/checkout-ponte.js`).

## O que é

O checkout que se vê aqui é o **checkout real** da Tickett: os templates Volt (`app/views/index.volt`, `products/group.volt`, `cart/*.volt`, `payment/*.volt`, `delivery/sent.volt`, `login/site.volt`), o CSS (`public/css/style.css`, Bootstrap local) e o JS (`public/js/checkout.js`, jQuery). Tudo lido de `/Users/carloslima/checkout` em **só leitura**. Não há cópias nem HTML escrito à mão.

O tema Festeja não está aqui: é o `public/assets/css/checkout-tema.css` do site, aplicado pelo proxy `/checkout` do site.

## Como correr

```sh
dev/tickett-stub/serve            # porta 8081 (primeiro plano)
nohup dev/tickett-stub/serve &    # em background
```

Requer o PHP com a extensão `phalcon` 5.x (`php -m | grep phalcon`). Testado com PHP 8.5.2 e Phalcon 5.17.0.

Páginas:

- `http://127.0.0.1:8081/2001`: checkout normal (como publicado).
- `http://127.0.0.1:8081/2001?produto=9001_1`: pré-seleção nativa do checkout real (`index.volt`, bloco `isset($_GET['produto'])`, select `#product_9001_1`).
- `?fail=1` responde 500 e `?slow=1` espera 5 s (para testar o 502 e o timeout do proxy do site).

O site não fala com o simulador diretamente: pede-lhe as páginas e os relays pelo proxy `/checkout` (`app/Services/CheckoutProxy.php`).

## Como funciona

- `router.php`: rotas. `GET /{evento}` renderiza `index`. `POST /{evento}/{ação}` devolve fragmentos renderizados pelos templates reais. Os ficheiros estáticos (`/css`, `/js`, `/img`, `/logos`, …) são servidos de `checkout/public`, só com extensões de asset e `realpath` dentro de `public/`.
- `lib/bootstrap.php`: DI Phalcon com os serviços que os templates usam (`url`, `t`, `crypt`, `session`, `formatdates`, `urlSite`, `router`, `config`). Config da empresa `lebillet_pt` (a que o `index.php` real usa para `bilhetes.festeja.pt`). Volt com cache em `cache/volt/`. Avisos e notices dos templates vão para `cache/render.log`, não para a página.
- `lib/sim-data.php`: dados simulados no formato que os templates leem (evento, linhas de produto no formato de `ProductsLib`, carrinho em sessão, totais, encomenda para a página de conclusão).
- `data/catalog.json`: catálogo simulado (inalterado).
- `data/traducoes.php`: traduções do simulador, `'chave' => [pt_pt, en_us]`. Imitam a tabela `languages` da BD da Tickett (`app/languages/geral.php` do checkout real). Chaves: todas as de `t._('…')` dos passos produtos, carrinho, dados, entrega/pagamento e fim, mais os nomes de país e o tipo de bilhete. Os textos sem fonte real estão marcados "inventado: confirmar com a Tickett".
- **Idioma.** Como o service `t` de `services.php`: `?l=pt_pt` ou `?l=en_us` grava o idioma na sessão (`sim_idioma()` em `lib/bootstrap.php`); sem `?l=` usa a sessão ou `pt_pt`. Nos fragmentos AJAX segue a sessão. O proxy do site envia `l` na página (ver `docs/contrato-tickett.md`, secção 2).

Ações AJAX simuladas: `products`, `products/add`, `products/rem`, `cart/resume`, `cart/set-delivery` (guarda a entrega e devolve `cart/resume`, como `setDeliveryAction`), `login` (renderiza o formulário de dados), `login/register` (aceita qualquer registo), `cart/delivery-payment`, `cart/set-delivery-payment`, `payment/proccess` e `cart/finish` (finish real, `.cart-finish`), `payment/clearCart`. Outras ações respondem `501`.

## Limitações (o que não é igual à Tickett)

- **Sem BD.** O SQL de `ProductsLib`, `CartLib` e os models Phalcon não correm. Os dados são construídos em `lib/sim-data.php`. Os preços, lotes e disponibilidades são os de `data/catalog.json`.
- **Um método de entrega e um de pagamento.** Em produção vêm da BD. Entrega: Email, sem custo, no formato do fallback de `CartController::deliveryPaymentAction` (`sim_deliveries()`). Pagamento: `Cash` (`sim_payments()`, id 1), que leva a `payment/cash.volt` (`#tickets-form-payment`), sem gateway. Pagar leva ao finish real.
- **Países e cidades mínimos.** `sim_countries()` e `sim_cities()` em `lib/sim-data.php`: dez países (Portugal, Angola, Cabo Verde, Guiné-Bissau, Moçambique, São Tomé e Príncipe, Brasil, Espanha, França, Reino Unido) e algumas cidades por país. A lista real vem da BD. O registo aceita qualquer dado.
- **Sem cupões, impostos, descontos, assentos (seats.io) nem FNAC.** O evento está com `fnac=0`, `seats_event` vazio e `versao_mapa=0`. Os ramos desses templates não são testados.
- **Sem gestão de utilizador.** Login, logout, recuperar senha e `waiting-list` não são simulados.
- **Encriptação de tokens** com a mesma chave de `services.php` (`Phalcon\Encryption\Crypt`). O token tem o formato real (`evento#lote#produto#tipo#numero`).
- **Phalcon 5.** Duas API removidas: `Phalcon\Mvc\User\Component` (shim vazio em `lib/bootstrap.php`, base de `Formatdates.php`) e `Phalcon\Crypt` (passou a `Phalcon\Encryption\Crypt`). `Formatdates.php` é o real.

- **Ficheiros e cache do Volt:** `cache/volt/` é recompilado a cada pedido (`compileAlways`). Pode apagar-se `cache/volt/` à vontade.
- **Continuar no passo de entrega/pagamento:** depois de `goDeliveryPayment()`, `#button-products` fica em "Wait" até chegar a resposta de `cart/set-delivery` (o `infoDelivery` de `cart/delivery.volt` corre no carregamento e o `cart/resume` que volta repõe o botão). Sem entrega selecionada não há pedido e o botão fica bloqueado. Por isso o simulador tem o método de entrega e a ação `cart/set-delivery`. Os testes clicam no botão normalmente.
- **Total do carrinho:** `sim_cart_totals` recalcula sempre os totais (`array_replace`, porque o carrinho guardado em sessão já traz as chaves anteriores). Sem isto o total ficava a 0 e o checkout passava a mostrar só cortesia.
- **Número da encomenda:** a vista de conclusão real (`delivery/sent.volt`) não mostra o número da encomenda, por isso o `purchase` do simulador tem `order_id` `null`.

## Dados que o simulador fixa (para os templates não avisarem)

`id_terminal = 1` (sem cabeçalho de evento), `id_sale_point = null` (canal online), `currency = EUR`, `deliveries_available` e `payments_available` vazios, `countries`/`cities` de `sim-data.php`, `EventsImages` com uma imagem de nome fictício.

## Verificação

- Fluxo completo pelo proxy do site (scratchpad do executor de checkout): `flow.mjs` abre o site, adiciona um bilhete, avança dados, entrega/pagamento e chega ao fim, com captura em cada passo.

## Ficheiros

- `router.php`, `serve`, `data/catalog.json`, `data/traducoes.php`, `lib/bootstrap.php`, `lib/sim-data.php`.
- `cache/` (ignorada pelo git, ver `.gitignore`): cache do Volt e `render.log`.
