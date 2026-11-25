# Festeja: site do festival

Site oficial do Festival Festeja (RTP África): cartaz, bilhetes, informações e como chegar.

PHP renderiza as páginas. O CSS e o JavaScript são ficheiros estáticos. Não há framework, build, npm nem Composer.

Os bilhetes são vendidos pela Tickett. O site lê os bilhetes da página pública do checkout e mostra o checkout num modal, através de um proxy no próprio domínio (`/checkout`). O checkout da Tickett não é alterado. O site não guarda preços nem stock.

## Requisitos

- PHP 8.2 ou superior, com `curl`, `dom`, `json` e `mbstring`.
- Escrita em `storage/cache/`.

## Correr localmente

Dois terminais, na raiz do projeto:

```
bin/serve                 # site em http://127.0.0.1:8080
dev/tickett-stub/serve    # simulador da Tickett em http://127.0.0.1:8081
```

Páginas úteis:

- http://127.0.0.1:8080/ (início)
- http://127.0.0.1:8080/bilhetes, /cartaz, /informacoes, /como-chegar
- http://127.0.0.1:8080/artistas/anna-joyce
- http://127.0.0.1:8080/kit (kit de componentes, só em desenvolvimento)
- http://127.0.0.1:8080/checkout (o checkout do simulador, pelo proxy do site)
- http://127.0.0.1:8080/llms.txt, /sitemap.xml, /robots.txt
- http://127.0.0.1:8081/2001 (checkout do simulador, direto)

Sem o simulador, a página /bilhetes mostra a mensagem de falha e o resto do site continua a funcionar.

Configuração local: `cp config/site.local.example.php config/site.local.php`. Este ficheiro não vai para o git.

## Estrutura

```
festeja/
├── app/
│   ├── bootstrap.php            autoload, config(), e(), asset(), idioma() e t() (textos de interface)
│   ├── Lang/                    textos de interface: pt.php e en.php (mesmas chaves)
│   ├── Helpers/                 Component (renderiza componentes), View, Format (datas, preços e selo "Novo", em pt e en), Rotas (mapa pt ↔ en das páginas), Seo (JSON-LD, llms.txt, sitemap), Icon
│   ├── Services/                Content (lê content/*.json e, em inglês, content/en/), TickettService (bilhetes lidos da página do checkout, com cache), CheckoutProxy (checkout da Tickett no domínio do site)
│   └── Views/
│       ├── layout.php           HTML comum: cabeçalho da página, rodapé, scripts
│       ├── Pages/               uma página por ficheiro (home, bilhetes, cartaz, artista...)
│       └── Components/          blocos reutilizáveis (ArtistCard, TicketCard, TicketGrid, CheckoutModal, Partners, Faq...)
├── config/
│   ├── site.php                 configuração: env, base_url, tickett, analytics
│   └── site.local.example.php   modelo para config/site.local.php (local ou produção)
├── content/                     textos e factos: festival.json (inclui transportes), home.json (textos da home), artistas.json, faq.json, parceiros.json, paginas/
│   └── en/                      traduções para inglês: mesma estrutura, só os campos traduzíveis (festival, home, faq, artistas, paginas/)
├── design-system/               style guide: tokens.json, tokens.css, componentes e acessibilidade
├── dev/                         ferramentas de desenvolvimento, não entram no site
│   ├── og/                      gera og.jpg e apple-touch-icon.png (node dev/og/build.mjs)
│   ├── qa/                      suíte de QA com Chrome headless (node dev/qa/qa.mjs all)
│   ├── svg/                     reduz o peso dos SVG do potrace (node dev/svg/round.mjs)
│   ├── tickett-parse-check.php  verifica a leitura dos bilhetes contra o simulador
│   └── tickett-stub/            simulador da Tickett (imita o checkout publicado)
├── docs/                        guia de manutenção, contrato com a Tickett, direção visual, plano
├── extraido-pdf/                slides e SVG extraídos da apresentação 2027 (origem dos logótipos)
├── public/                      raiz do servidor web
│   ├── index.php                router (idioma pelo prefixo /en), rotas fixas e rotas do proxy /checkout
│   └── assets/                  css/ (tokens, base, components, site, home, pages, checkout, checkout-tema), js/ (módulos e checkout-ponte.js), img/ (og.jpg, favicon.svg, apple-touch-icon.png, logótipos, símbolos)
├── storage/cache/               catálogo da Tickett em cache (gerado; ignorado pelo git)
└── bin/serve                    arranca o site em 127.0.0.1:8080
```

## Documentação

- [docs/guia-de-manutencao.md](docs/guia-de-manutencao.md): como mudar cada coisa, por tarefa. Começa aqui.
- Publicar na Vercel (runtime `vercel-php`, variáveis de ambiente, limitações): secção 16.1 do [guia de manutenção](docs/guia-de-manutencao.md).
- [content/LEIA-ME.md](content/LEIA-ME.md): campos dos ficheiros JSON (incluindo `home.json` e os transportes), e as traduções em `content/en/`.
- Idiomas: português de Portugal na raiz (`/bilhetes`) e inglês com `/en` (`/en/tickets`). Mapa de rotas em `app/Helpers/Rotas.php`; textos de interface em `app/Lang/`. Ver a secção 19 do guia.
- [dev/og/README.md](dev/og/README.md): imagem de partilha e ícone de ecrã inicial.
- [dev/qa/README.md](dev/qa/README.md): suíte de QA (como correr, variáveis, alvos de toque).
- [docs/contrato-tickett.md](docs/contrato-tickett.md): como o site lê os bilhetes da Tickett, como funciona o proxy do checkout e as mensagens da ponte.
- [docs/checkout-tickett-alteracoes.md](docs/checkout-tickett-alteracoes.md): não alteramos o checkout da Tickett; o que o site faz em vez disso, pedidos à Tickett e checklist de lançamento.
- [docs/direcao-visual.md](docs/direcao-visual.md): como deve parecer cada página.
- [docs/plano-implementacao.md](docs/plano-implementacao.md): plano, fases, decisões (incluindo a de 24/11/2026 sobre o checkout) e riscos.
- [design-system/README.md](design-system/README.md): cores, tipografia, voz e regras de acessibilidade.
