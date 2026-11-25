# QA do site Festeja

Testes de ponta a ponta com Chrome headless (CDP). Só módulos nativos do Node 22+, sem `npm install`.

## Antes de correr

1. Site, a partir da raiz do projeto: `php -S 127.0.0.1:8080 -t public public/router-dev.php`
2. Simulador da Tickett: `dev/tickett-stub/serve` (porta 8081). O CSP do simulador só aceita 8080.
3. Google Chrome em `/Applications/Google Chrome.app` (ou `QA_CHROME=/caminho/do/chrome`).

## Como correr

```sh
node dev/qa/qa.mjs compra 1440     # fluxo de compra (home → modal → simulador → purchase, Esc, foco)
node dev/qa/qa.mjs compra 390      # o mesmo a 390 px
node dev/qa/qa.mjs escolher        # "Escolher" de um TicketCard abre com o produto destacado
node dev/qa/qa.mjs checkout-layout # checkout por passo a 1440, 1280, 1024, 768 e 390: scroll do modal, overflow, barras, direita, margens
node dev/qa/qa.mjs a11y            # auditoria por página a 1440 e 390 (DOM + árvore de acessibilidade)
node dev/qa/qa.mjs teclado         # skip link, menu móvel (foco preso, Esc), setas nos separadores
node dev/qa/qa.mjs movimento       # prefers-reduced-motion e GSAP (revelações, cabeçalho, âncoras)
node dev/qa/qa.mjs semjs           # sem JavaScript
node dev/qa/qa.mjs overflow        # scroll horizontal a 320, 390, 768, 1024 e 1440
node dev/qa/qa.mjs desempenho      # peso, pedidos, JS do site, vendor GSAP, LCP e CLS (390 com 4G simulado)
node dev/qa/qa.mjs seo             # robots, sitemap, llms e HTTP de cada página
node dev/qa/qa.mjs i18n            # pt e en: HTTP, lang, hreflang recíprocos, canonical, seletor, texto pt nas páginas en, modal en (?lang=en)
node dev/qa/qa.mjs fallback        # simulador desligado: com cache e sem cache (ver abaixo)
node dev/qa/qa.mjs sem-evento      # produção sem TICKETT_EVENT_ID (SITE_ENV=prod, TICKETT_DEMO=0): servidor temporário na porta 8090; sem checkout, "em breve" em pt e en
node dev/qa/qa.mjs demo              # modo demonstração (evento 2133) contra um upstream de teste com o HTML guardado (portas 8092-8094): avisos, noindex, robots, sem Offers, /checkout
node dev/qa/qa.mjs all             # tudo, exceto fallback
```

O checkout é o iframe `/checkout` (proxy do site, mesma origem). Os passos avançam com cliques reais nos botões do checkout (sem chamar funções do checkout diretamente).

Cada verificação imprime `PASS` ou `FAIL`. No fim sai com código 1 se houver falhas.

## Variáveis (opcionais)

| Variável | Predefinição | Para quê |
| --- | --- | --- |
| `QA_OUT` | pasta temporária do sistema + `/festeja-qa` | resultados em JSON, capturas e perfis do Chrome |
| `QA_BASE` | `http://127.0.0.1:8080` | URL do site |
| `QA_STUB` | `http://127.0.0.1:8081` | URL do simulador |
| `QA_CHROME` | Google Chrome em `/Applications` | binário do Chrome |
| `QA_PROJECT` | duas pastas acima de `dev/qa/` | raiz do projeto (cache e simulador) |
| `QA_SEM_EVENTO_PORTA` | `8090` | porta do servidor temporário de `sem-evento` |
| `QA_DEMO_UPSTREAM`, `QA_DEMO_PORTA`, `QA_DEMO_EVENTO_PORTA` | `8092`, `8093`, `8094` | portas do upstream de teste e dos dois servidores de `demo` |

## Fallback (simulador desligado)

Este comando **para o simulador e repõe-no** no fim, e faz backup e restauro de `storage/cache/tickett-2001.json`.
Antes de correr, grava o PID do simulador em `$QA_OUT/stub.pid`:

```sh
dev/tickett-stub/serve & echo $! > "$QA_OUT/stub.pid"
```

Sem esse ficheiro, o comando recusa-se a correr e não mata nada.

## Resultados

- Saída no terminal: `PASS`/`FAIL` por verificação e a tabela da auditoria.
- `$QA_OUT/<comando>.json`: todas as verificações, com detalhe.
- `$QA_OUT/a11y-detalhe.json`: contraste, alvos, cabeçalhos e nomes por página.

## Movimento e a11y

- A auditoria `a11y` rola a página toda antes de medir: as revelações do GSAP só acontecem ao rolar, e o estado final é o que conta.
- `desempenho` mede o vendor GSAP à parte (`vendor GSAP KB`). Orçamento: 130 KB (`ORCAMENTO_VENDOR_KB` em `qa.mjs`). O orçamento de JS do site continua em 50 KB, sem o vendor.

## Alvos de toque (a11y)

- **Falha:** alvo com menos de 24 × 24 px (WCAG 2.2 AA, 2.5.8). Sai como "alvos abaixo de 24 px" na tabela.
- **Aviso:** alvo entre 24 e 44 px. Não falha; aparece em `issues.targetsInfo` do JSON e na coluna "alvos 24-44 px (aviso)" da tabela (critério AAA, 2.5.5).
- Ligações dentro de texto corrido ficam fora da conta (exceção de texto em linha).
- Capturas do modal e do fallback em `$QA_OUT`.
