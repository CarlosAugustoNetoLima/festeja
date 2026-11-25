# Guia de manutenção do site Festeja

Para quem vai mudar o site sem ter estado no projeto: programadores júnior, designers e a equipa de conteúdo da RTP África.

Cada secção responde a uma tarefa ("Quero…"). Os caminhos são relativos à raiz do projeto (`festeja/`), exceto quando dizem o contrário.

## Antes de começar

Três regras explicam quase tudo:

1. **Factos vivem em `content/`.** Datas, horas, local, morada, transportes, textos de SEO, artistas, perguntas frequentes, parceiros e os textos das secções da home (`content/home.json`) são ficheiros JSON e HTML. Não copies estes dados para o PHP.
2. **Bilhetes e preços vivem na Tickett.** O site lê os bilhetes da página do checkout e mostra o checkout num modal, pelo proxy do próprio site. O checkout da Tickett não se altera. Nunca escrevas um preço no site.
3. **Cores, tipos e espaços vêm de `tokens.css`.** Não uses cores soltas nos componentes.

Ferramentas úteis:

- `php -l ficheiro.php` verifica a sintaxe de um ficheiro PHP.
- Os JSON não aceitam comentários. Uma vírgula a mais parte a página. Valida assim, a partir da raiz do projeto:

  ```
  php -r 'json_decode(file_get_contents("content/festival.json"), true, 512, JSON_THROW_ON_ERROR); echo "OK\n";'
  ```

  Troca `content/festival.json` pelo ficheiro que mudaste.
- Para encontrar onde está um texto: `grep -rn "Mais nomes em breve" app content`.

Índice de tarefas:

1. [Datas, horas, local e morada](#1-datas-horas-local-e-morada)
2. [Texto do hero, textos da home e resumo (SEO)](#2-texto-do-hero-textos-da-home-e-resumo-seo)
3. [Cartaz: artistas](#3-cartaz-artistas)
4. [Perguntas frequentes](#4-perguntas-frequentes)
5. [Parceiros](#5-parceiros)
6. [Páginas legais](#6-páginas-legais)
7. [Bilhetes e preços (não se mudam no site)](#7-bilhetes-e-preços-não-se-mudam-no-site)
8. [Quero mudar as cores do checkout](#8-quero-mudar-as-cores-do-checkout)
9. [Cores, tipografia e espaços](#9-cores-tipografia-e-espaços)
10. [Criar uma página nova](#10-criar-uma-página-nova)
11. [Criar ou alterar um componente](#11-criar-ou-alterar-um-componente)
12. [Imagem de partilha, favicon e ficheiros de marca](#12-imagem-de-partilha-favicon-e-ficheiros-de-marca)
13. [Analytics (GA4)](#13-analytics-ga4)
14. [SEO e IA: o que é gerado](#14-seo-e-ia-o-que-é-gerado)
15. [Preparar a próxima edição](#15-preparar-a-próxima-edição)
16. [Publicar em produção](#16-publicar-em-produção)
17. [Verificar antes de dar como pronto (QA)](#17-verificar-antes-de-dar-como-pronto-qa)
18. [Quero animar um bloco ao fazer scroll](#18-quero-animar-um-bloco-ao-fazer-scroll)
19. [Quero mudar um texto / traduzir](#19-quero-mudar-um-texto--traduzir)

Secções transversais: [Como o site fala com a Tickett](#como-o-site-fala-com-a-tickett), [Problemas comuns](#problemas-comuns), [Regras que não se quebram](#regras-que-não-se-quebram), [Antes de lançar](#antes-de-lançar).

---

## 1. Datas, horas, local e morada

**Onde:** `content/festival.json`.

**O que mudar.** Exemplo real do ficheiro:

```json
"datas": { "inicio": "2027-07-23", "fim": "2027-07-24", "rever": "A apresentação diz 30 e 31 julho; o Instagram anunciou 23 e 24 julho." },
"dias": [
  { "id": "dia-1", "data": "2027-07-23", "portas": "16:00", "rever": true },
  { "id": "dia-2", "data": "2027-07-24", "portas": "16:00", "rever": true }
],
"local": { "nome": "Estádio do Olivais e Moscavide", "zona": "Moscavide, Lisboa", "morada": null, "geo": null, "rever": "Morada completa e coordenadas" },
"idade_minima": null,
```

Regras:

- Datas em ISO: `AAAA-MM-DD`. Horas em `HH:MM`. O site mostra `16:00` como "16h00".
- `datas.inicio` e `datas.fim` são o primeiro e o último dia. Devem bater com `dias[0].data` e com o último item de `dias`.
- `dias[].id` tem de ser `dia-1` e `dia-2`. Os artistas usam estes ids.
- `morada` é texto completo, por exemplo `"morada": "<rua, número, código postal e localidade>"`. Enquanto for `null`, a página Como chegar diz "Morada completa em breve.".
- `geo` é `"geo": { "lat": <número>, "lng": <número> }`. Enquanto for `null`, o JSON-LD não inclui coordenadas.
- `idade_minima` é um número ou `null`.
- `"rever": true` num dia mostra "(a confirmar)" nas páginas. Remove a marca quando a organização confirmar.
- `datas.rever` e `local.rever` são notas internas. Não aparecem no site.

**O que atualiza sozinho:**

| Campo | Aparece automaticamente em |
| --- | --- |
| `datas.inicio` / `datas.fim` | Hero (data sem ano), factos rápidos, rodapé, fecho da home (com ano), cabeçalho de /cartaz, datas dos bilhetes sem `days`, JSON-LD (`startDate`, `endDate`), /llms.txt |
| `dias[].data` | Nomes dos dias no cartaz (separadores e títulos), página do artista ("Dia" e "Também no dia…"), horários em /como-chegar, JSON-LD, /llms.txt |
| `dias[].portas` | Factos rápidos, cartaz ("Portas às…"), /como-chegar, hora de início e fim no JSON-LD, /llms.txt |
| `dias[].rever` | "(a confirmar)" nos factos, no cartaz, em /como-chegar e na página do artista, /llms.txt |
| `local.nome` / `local.zona` | Hero, factos, rodapé, cartão "Como chegar" da home, página do artista, /cartaz, JSON-LD, /llms.txt |
| `local.morada` | /como-chegar, JSON-LD, /llms.txt |
| `local.geo` | Só o JSON-LD (coordenadas) |
| `idade_minima` | Só o /llms.txt. Nas páginas, a resposta da FAQ é texto fixo |
| `festival.edicao` | Títulos das páginas, título de /bilhetes, rodapé ("© 2027"), JSON-LD, título do cartaz ("Cartaz 2027") na home, no kit e em /cartaz |
| `transportes` | Secção "Como chegar" da home e a lista de transportes de /como-chegar (são a mesma lista) |
| `dias[].rever`, `local.rever` | Notas internas, sem efeito no site (ver `rever` acima) |

**O que NÃO se atualiza sozinho.** Estes textos estão escritos à mão. Se mudares a data ou a hora, tens de mudar também aqui:

- Resposta da FAQ "A que horas abrem as portas?", em `content/faq.json`. Diz "16h00 nos dois dias".
- "Mais nomes em breve" (na home e em `app/Views/Pages/cartaz.php`) e "Horários por palco publicados perto do festival", em `app/Views/Pages/cartaz.php`.
- Textos das secções da home (eyebrows, títulos, lead, países, notas, "Vemo-nos em Moscavide"): agora estão em `content/home.json`, não no PHP. Ver a secção 2.

**Como verificar.**

1. Valida o JSON (comando na secção "Antes de começar").
2. Abre `http://127.0.0.1:8080/`, `http://127.0.0.1:8080/cartaz` e `http://127.0.0.1:8080/como-chegar`.
3. Confirma o texto de SEO: `curl -s http://127.0.0.1:8080/llms.txt | head -20`. As datas e as portas devem aparecer.
4. Confirma o JSON-LD: `curl -s http://127.0.0.1:8080/ | grep -o '"startDate":"[^"]*"'`.

**Cuidados.**

- O site assume dois dias. Um terceiro dia não é só JSON: o código percorre `dias[]`, mas os textos ("nos dois dias"), a grelha da home e os separadores foram pensados para dois. Não testado com três dias.
- Se mudares `datas` mas não `dias[].data`, o cartaz mostra nomes de dias antigos. Muda os dois.
- Os bilhetes não trazem datas próprias: o HTML da Tickett não tem `days`. O cartão mostra o intervalo de `festival.json`, por isso mudar `festival.json` muda também o que os bilhetes dizem sobre os dias.

---

## 2. Texto do hero, textos da home e resumo (SEO)

**Onde:** `content/festival.json` (chamada, resumo, mote) e `content/home.json` (textos das secções da home).

**Textos da home (`content/home.json`).** Cada secção tem o seu bloco:

| Bloco | O que é |
| --- | --- |
| `hero.eyebrow` | Linha pequena por cima da data no hero ("Festival · RTP África") |
| `cartaz.eyebrow`, `cartaz.titulo` | Cabeçalho do cartaz. `titulo` é `"Cartaz {edicao}"`: o `{edicao}` sai de `festival.json` (hoje "Cartaz 2027"). Também usado no kit e em /cartaz |
| `bilhetes.eyebrow`, `bilhetes.titulo`, `bilhetes.lead` | Cabeçalho da secção de bilhetes |
| `festival.paises[]`, `festival.fecho`, `festival.notas[]` | Secção "O festival": a frase dos países (`destaque: true` sai a laranja, os outros a ink), o país final e as três notas |
| `visita.eyebrow`, `visita.titulo` | Cabeçalho de "Como chegar" (os transportes estão em `festival.json`) |
| `faq.eyebrow`, `faq.titulo` | Cabeçalho das perguntas frequentes |
| `fecho.eyebrow` | Linha por cima da data no fecho ("Vemo-nos em Moscavide") |

JSON não tem comentários. Para marcar uma nota como "rever", usa `"rever": true` na nota: fica um comentário HTML para a equipa, sem efeito no texto.

Os textos de SEO continuam em `festival.json`, como descrito abaixo.

```json
"mote": "O festival que liga continentes",
"resumo": "Dois dias de música dos países africanos de língua portuguesa no Estádio do Olivais e Moscavide, em Lisboa.",
"chamada": "Dois dias de música dos países africanos de língua portuguesa, em Lisboa.",
```

- `chamada` é a frase do hero da home (`hero__lead`). Frase curta. Não repitas o local, que já aparece logo abaixo.
- `resumo` é a meta description da home e a og:description de quase todas as páginas, com exceção das que têm texto próprio (ver 14). Também é a descrição do /llms.txt. Uma frase. Recomendação (não vem do código): até cerca de 155 caracteres, para não ser cortada na pesquisa.
- `mote` aparece no título da home ("Festival Festeja 2027 · O festival que liga continentes") e no rodapé.

**Como verificar.** Abre a home e vê o título do separador. Depois: `curl -s http://127.0.0.1:8080/ | grep -o '<meta name="description"[^>]*>'`.

**Cuidados.**

- A meta description de uma página de artista é a bio cortada a 155 caracteres, na última palavra inteira, com "…". Sem bio, sai uma frase gerada ("{nome} atua no Festival Festeja 2027, {dia}, no {local}."). A bio na página continua inteira.
- O hero usa `chamada`, não `resumo`. Mudar um não muda o outro.

---

## 3. Cartaz: artistas

**Onde:** `content/artistas.json`. É uma lista. Cada entrada gera uma página `/artistas/{slug}`.

Exemplo real (uma entrada):

```json
{ "slug": "anna-joyce", "nome": "Anna Joyce", "pais": "AO", "dia": "dia-1", "destaque": 1, "confirmado_em": "2027-03-02", "bio": null, "foto": null, "links": {} },
```

Campos:

| Campo | Regra |
| --- | --- |
| `slug` | Minúsculas, sem acentos, hífenes. Único. A rota aceita só `a-z`, `0-9` e `-`. Um slug com maiúsculas ou acentos dá 404. |
| `nome` | Nome de apresentação, como o artista o escreve. |
| `pais` | Código ISO de 2 letras: `AO`, `CV`, `GW`, `MZ`, `ST`, `PT`, `BR`, `TL`, `GQ`, ou `null`. Outro código aparece tal como está escrito. |
| `dia` | `dia-1` ou `dia-2`. Tem de existir em `festival.json`. |
| `dias` | Só se tocar em mais de um dia. Ver 3.7. |
| `destaque` | `1` cabeça de cartaz, `2` médio, `3` pequeno. Ver 3.4. |
| `confirmado_em` | Data de confirmação. Até 14 dias depois, o artista tem o selo "Novo" (ver 3.8). |
| `bio` | Texto, ou `null`. |
| `foto` | Caminho da fotografia, ou `null`. Ver 3.5. |
| `links` | Objeto com as redes do artista. `{}` se não houver. |

### 3.1 Adicionar um artista

1. Copia uma entrada do mesmo dia.
2. Escolhe um `slug` novo. Exemplo (nome fictício): `"slug": "nome-novo"`.
3. Põe a entrada na posição certa. A ordem da lista é a ordem no cartaz, dentro de cada nível de destaque.
4. Valida o JSON.
5. Verifica (ver abaixo).

Exemplo (nome fictício):

```json
{ "slug": "nome-novo", "nome": "Nome Novo", "pais": "PT", "dia": "dia-2", "destaque": 3, "confirmado_em": "2027-07-01", "bio": null, "foto": null, "links": {} },
```

### 3.2 Remover um artista

Apaga a entrada. A página `/artistas/{slug}` passa a 404. O artista sai do cartaz, do sitemap, do /llms.txt e do JSON-LD sem mais passos.

Não há redirecionamento. Se houver links externos para a página, deixam de funcionar.

### 3.3 Reordenar

Muda a entrada de posição. No cartaz, a ordem é a da lista, dentro de cada nível (cabeças, médios, pequenos). O /llms.txt ordena por `destaque` e mantém a ordem da lista dentro de cada nível.

### 3.4 Marcar cabeça de cartaz (destaque)

- `"destaque": 1` é cabeça de cartaz. Aparece em tamanho maior no cartaz.
- `"destaque": 2` é médio. `"destaque": 3` é pequeno.
- A faixa em movimento do hero mostra só os artistas com `destaque` 1 e 2 (em maiúsculas).

### 3.5 Pôr uma fotografia

- **Formato:** retrato 4:5. O CSS usa `aspect-ratio: 4 / 5` nos cartões e na página do artista. Sem foto, aparece um fundo liso de papel com um símbolo da marca centrado (escolhido pelo slug, `App\Helpers\Simbolo::um`).
- **Tamanho:** recomendação (não imposta pelo código): 800 × 1000 px, WebP, qualidade perto de 80, abaixo de cerca de 200 KB.
- **Onde guardar:** `public/assets/img/artistas/`. A pasta ainda não existe. Cria-a.
- **Caminho no JSON:** começa por `/`. Exemplo: `"foto": "/assets/img/artistas/anna-joyce.webp"`. Sem a barra inicial, o browser resolve o caminho a partir de `/artistas/{slug}` e a imagem não aparece.
- **Texto alternativo:** é gerado. Sai "Nome em palco". Não há campo para o alterar.
- **Otimização:** o site não gera versões AVIF ou WebP, nem `srcset`. Exporta já otimizado, com a ferramenta do teu editor de imagem ou um conversor no browser. O plano prevê um script `bin/images`, mas ele não existe.

Verifica: abre `/artistas/{slug}`, depois `/cartaz` e passa o rato sobre o nome na home (no desktop mostra a foto a seguir ao cursor).

### 3.6 Bio e links

Exemplo de formato (substitui pelos links oficiais):

```json
"bio": "Cantora e compositora de Luanda. Cresceu entre Lisboa e Angola.",
"links": { "instagram": "https://www.instagram.com/NOME", "spotify": "https://open.spotify.com/artist/ID" },
```

- `bio: null` mostra "Biografia em breve.".
- Aspas dentro da bio escapam-se com `\"`.
- Chaves de `links` com rótulo próprio: `instagram`, `facebook`, `youtube`, `spotify`, `site` ou `website` (rótulo "Site oficial"). Outra chave aparece com a primeira letra em maiúscula e um ícone de ligação externa.

### 3.7 Artista em dois dias

Exemplo real (Kizomba na Rua):

```json
{ "slug": "kizomba-na-rua", "nome": "Kizomba na Rua", "pais": null, "dia": "dia-1", "dias": ["dia-1", "dia-2"], "destaque": 3, "confirmado_em": "2027-05-11", "bio": null, "foto": null, "links": {} },
```

- `dia` é o dia principal. É aqui que o artista aparece no cartaz, e o botão "Comprar bilhete para…" usa este dia.
- `dias` lista todos os dias, incluindo o principal. A página do artista mostra os dois.
- O cartaz mostra o artista só no dia principal. O /llms.txt lista-o nos dois dias.

### 3.8 Selo "Novo"

- Um artista com `confirmado_em` nos últimos 14 dias (contados em Lisboa, hoje incluído) mostra o selo "Novo": no cartão do artista (canto da fotografia) e, no cartaz, um marcador "Novo" depois do nome.
- O selo é só texto. Não depende de cor, e não é um link.
- Não se pode antecipar: data futura ou vazia não tem selo. Depois de 14 dias o selo desaparece sozinho.
- A regra está em `Format::recente()` (`app/Helpers/Format.php`). Usada por `ArtistCard.php` e `LineupDay.php`.

### Como verificar (qualquer alteração de artistas)

1. Valida o JSON.
2. Abre `/cartaz` e `/artistas/{slug}`.
3. Confirma no sitemap: `curl -s http://127.0.0.1:8080/sitemap.xml | grep {slug}`.
4. Confirma no /llms.txt: `curl -s http://127.0.0.1:8080/llms.txt | grep "Nome"`.

**Cuidados.** Se `dia` não existir em `festival.json`, a página do artista dá erro. Confirma sempre os ids `dia-1` e `dia-2`.

---

## 4. Perguntas frequentes

**Onde:** `content/faq.json`.

Exemplo real (início do grupo Bilhetes):

```json
{
  "id": "bilhetes",
  "titulo": "Bilhetes",
  "perguntas": [
    { "p": "Onde compro bilhetes?", "r": "Os bilhetes são vendidos pela Tickett. Escolhe o bilhete na página de bilhetes e paga no checkout.", "home": true },
    { "p": "Posso mudar ou reembolsar o bilhete?", "r": "As condições de alteração e reembolso estão no checkout da Tickett. Lê-as antes de pagar. Publicamos mais detalhes até junho.", "rever": true }
  ]
}
```

### Adicionar uma pergunta ou um grupo

- **Pergunta:** põe-na no grupo certo, com `p` (pergunta) e `r` (resposta). Trata o leitor por tu.
- **Grupo:** `id` único, `titulo`. O `id` é a âncora em `/informacoes#id`.
- **Não mudes o `id` do grupo `bilhetes`.** A página /bilhetes filtra por esse id. Se o mudares, a secção de perguntas sobre bilhetes fica vazia.
- **Não mudes o texto da pergunta sem necessidade.** O âncora de cada pergunta sai do texto. Se mudares o texto, muda o link para ela.

### Escolher as perguntas da home

- `"home": true` mostra a pergunta na home. A home usa todas as marcadas, pela ordem do ficheiro.
- O LEIA-ME pede cinco no total. O código não verifica. Hoje são cinco: duas em Bilhetes, uma em Acesso, uma em No recinto, uma em Cashless.
- As mesmas perguntas alimentam o JSON-LD `FAQPage` da home.

### A marca `rever`

- `"rever": true` marca a resposta como dependente de confirmação.
- **A marca não aparece no site.** Só o /llms.txt acrescenta "(Informação a confirmar)". Quem visita o site não a vê.
- Hoje há 11 de 15 perguntas com `rever`. Antes de lançar, confirma cada uma e remove a marca.

### Cuidados

- Horas e datas nas respostas são texto fixo. Quando mudares as portas, muda também a resposta "A que horas abrem as portas?".
- As respostas são texto simples. Não cabem HTML nem links: um `<a>` aparece como texto.

---

## 5. Parceiros

**Onde:** `content/parceiros.json`. A secção aparece na home, depois das perguntas frequentes e antes do fecho. Componente: `app/Views/Components/Partners.php`.

**Estado atual.** O ficheiro está vazio (`{"grupos":[]}`). Sem parceiros, a secção não aparece: o componente não escreve nada.

**Formato.**

```json
{"grupos":[{"titulo":"Media partners","parceiros":[{"nome":"RTP África","logo":"/assets/img/parceiros/rtp-africa.svg","url":"https://www.rtp.pt/rtpafrica"}]}]}
```

- `titulo` do grupo: título visível. Os níveis previstos são Produção, Patrocinadores, Media partners e Apoios.
- `nome`: é o `alt` do logótipo.
- `logo`: caminho com barra inicial, dentro de `public/`. Pasta: `public/assets/img/parceiros/` (ainda não existe; cria-a). SVG ou PNG tal como vêm.
- `url`: opcional. Sem `url`, o logótipo fica sem link.
- Parceiros sem `nome` ou sem `logo` são ignorados. Grupos sem parceiros válidos não aparecem.

**Aspeto.** Logótipos a 48 px de altura, em grelha centrada. Os logótipos não são pintados a monocromático: saem como o parceiro os entrega. Estilo em `home.css` (`.partners*`).

**Como verificar.** Põe um parceiro de teste, abre `/`, confirma a secção antes do fecho, e depois remove-o. Confirma que sem parceiros a página não tem a secção.

---

## 6. Páginas legais

**Onde:**

- Texto: `content/paginas/acessibilidade.html`, `privacidade.html`, `cookies.html` e `termos.html`.
- Título: quatro sítios. `app/Views/Pages/legal.php` (array `$titulos`), `public/index.php` (campo `title` da rota), `app/Views/Components/SiteFooter.php` (links do rodapé) e `app/Helpers/Seo.php` (constante `LEGAIS`, para o /llms.txt).

**Como editar.**

- Usa `<h2>`, `<p>`, `<ul>` e `<strong>`. Começa em `<h2>`: a página já tem o `<h1>` do cabeçalho.
- O HTML é inserido tal como está, sem escape. Só edita quem é de confiança.
- Não inventes conteúdo jurídico. Os textos precisam de revisão.

**O que dizem `privacidade.html` e `cookies.html` (estado atual).** Descrevem a realidade do site:

- Não há analytics. Não há banner de cookies, porque não há nada a consentir.
- **O proxy do checkout põe cookies no browser.** São os da Tickett, com o prefixo `tk_` e `Path=/checkout`, mas são cookies do domínio do site. O `cookies.html` diz que o site não usa cookies próprios, e isso deixou de ser verdade. Rever os dois textos antes de lançar (ver a checklist de `docs/checkout-tickett-alteracoes.md`).
- O site guarda só dois dados no `sessionStorage` do browser (não são cookies): o número de bilhetes no carrinho (`festeja-cart-count`) e as compras já registadas (`festeja-purchases`). Apagam-se ao fechar o separador.
- A compra é feita no checkout da Tickett, que tem a sua própria política de cookies e de privacidade.
- Se o GA4 for ativado, estes dois textos e um banner de consentimento têm de ser atualizados antes (secção 13).

**Estado RASCUNHO.** Os quatro ficheiros começam com `<!-- RASCUNHO: rever com o jurídico da RTP -->`. Este comentário não aparece no site. Em desenvolvimento (`env` = `dev`), a página mostra por cima do texto a faixa "Rascunho — rever com o jurídico antes de publicar". Em produção (`env` = `prod`), a faixa não aparece. Antes do lançamento, revê o texto com o jurídico e só então apaga o comentário.

Outros pontos a rever:

- Comentários `<!-- rever -->` (também invisíveis).
- "contacto a definir" em `acessibilidade.html`, `privacidade.html` e `termos.html`.
- "Última revisão: novembro de 2026" em `acessibilidade.html`. É texto fixo: atualiza-o à mão.

**Meta description.** Cada página legal tem a sua (ver 14). O texto está em `app/Lang/pt.php` (chaves `meta.legal.*`) e em `app/Lang/en.php`.

**Como verificar.** Abre `/privacidade`, `/cookies`, `/termos` e `/acessibilidade` com `env` = `dev` (vês a faixa) e confirma o título e a navegação por teclado. Com `env` = `prod`, a faixa tem de desaparecer.

---


## 7. Bilhetes e preços (não se mudam no site)

### O modelo

- Os bilhetes, os preços, os lotes, o stock e a abertura de venda vivem na **Tickett**.
- O site lê a página pública do evento (`{base}/{event_id}`) e extrai os bilhetes do HTML. A Tickett não tem API de produtos, e não há `products.json`.
- O site não tem nenhum preço escrito. O "desde" e os cartões vêm dessa leitura.
- Quando o visitante escolhe um bilhete, o checkout abre no modal, pelo proxy do site (`/checkout`), com esse bilhete pré-selecionado.

Os detalhes do parser e do proxy estão em `docs/contrato-tickett.md`.

### Mudar um preço, um lote, esgotar um bilhete ou abrir a venda

1. Faz a alteração no backoffice da Tickett. A interface não está documentada neste repositório. Pede acesso a quem gere a conta da Tickett.
2. Espera até 5 minutos, ou apaga a cache (abaixo).
3. Confere em `/bilhetes` e na home (faixa "desde…" e factos rápidos).

### O que o site lê da página do checkout

| O que a página mostra | Uso no site |
| --- | --- |
| Nome e descrição do bilhete (`h5` e `small`) | Título e descrição do cartão |
| Preço ("€ 14,25") | Preço do cartão e o "desde". Sem taxa |
| "Tier N" ou "Lote N" | Selo do lote, normalizado para "Lote N" |
| Select de quantidade `product_{id_product}_{id_type}` | Bilhete à venda. "Escolher" abre o checkout com `?produto={id_product}_{id_type}` |
| Sem select, com "em breve" | Selo "Brevemente". Sem botão |
| Sem select, sem "em breve" | "Esgotado". Sem botão |

Estado da venda, calculado a partir dos bilhetes: `open` se algum estiver à venda; `upcoming` se algum estiver "em breve" (a grelha mostra "A venda abre em breve."); senão `closed` (a grelha mostra "A venda online terminou.").

Não existem no HTML, e ficam vazios no site: a taxa (só aparece no carrinho), o selo de destaque ("Mais vendido"), o próximo lote com data, a data de abertura da venda e o estado "Últimos bilhetes".

### Preço e taxa

- O preço mostrado é o do HTML, sem taxa. A taxa só aparece no carrinho.
- O cartão diz "Acresce a taxa de serviço da Tickett, indicada no checkout."
- Se a taxa passar a vir incluída no preço, é preciso mudar o parser (`fee_included` em `TickettService::parseProduto`). Só então os textos passam a "taxas incluídas".

### O que a secção de bilhetes mostra

A grelha de bilhetes (home e /bilhetes) decide a mensagem por esta ordem (`app/Views/Components/TicketGrid.php`):

| Situação | Texto | Botão |
| --- | --- | --- |
| A Tickett não respondeu e não há cache | "A bilheteira não respondeu. Compra diretamente na página da bilheteira." | "Ir para a bilheteira" (abre o checkout) |
| `sale.status` = `upcoming` | "A venda abre a 12 junho, 14h00." (ou "A venda abre em breve." sem `opens_at`) | "Ir para a bilheteira" |
| `sale.status` = `closed` | "A venda online terminou." | "Ir para a bilheteira" |
| Venda aberta, sem produtos | "Os bilhetes estão indisponíveis de momento. Tenta na bilheteira." | "Ir para a bilheteira" |
| Venda aberta, com produtos | A grelha de cartões | Cada cartão tem "Escolher" |

A página da Tickett não traz a data de abertura (`opens_at` fica `null`), por isso a mensagem de venda por abrir é sempre "A venda abre em breve.".

Com cache antiga (Tickett em baixo) a grelha mostra-se sem aviso, como antes. Esgotado e "Brevemente" são estados de cada cartão, não da secção.

### Cache de 5 minutos

- Ficheiro: `storage/cache/tickett-{event_id}.json`. Exemplo: `storage/cache/tickett-2001.json`.
- Duração: `tickett.cache_ttl`, em segundos (`300`).
- Para forçar a leitura: apaga o ficheiro. Na próxima visita, o site vai à Tickett. Não é preciso reiniciar o servidor.
- Se a Tickett não responder, o site usa a última cache, mesmo que seja antiga. O aviso só vai para o log.
- A pasta `storage/cache/` precisa de permissão de escrita.

### Mudar o ID do evento

Cria `config/site.local.php` a partir de `config/site.local.example.php` (este ficheiro não vai para o git) e põe:

```php
'tickett' => [
    'event_id' => 2001,
],
```

O `2001` é um valor provisório. Depois de mudares o ID:

- Na Tickett, o evento com esse ID tem de existir com bilhetes na página pública.
- O site cria um novo ficheiro de cache com o novo ID.
- No checkout não há nada a mudar.

### Mudar o domínio da bilheteira

Em `config/site.local.php` (a produção está em `config/site.php`, com `https://checkout.lebillet.eu`):

```php
'tickett' => [
    'base' => 'https://checkout.lebillet.eu',
],
```

- Sem barra final.
- Tem de ser o domínio do checkout publicado, com o mesmo esquema e porta. O proxy e a leitura usam este valor.
- Não há DNS a configurar nem origens a autorizar no checkout.

### Como verificar

1. `php dev/tickett-parse-check.php` imprime o catálogo que o parser lê e compara-o com o simulador. Sai com código 1 se algum campo não bater.
2. Em desenvolvimento, abre `http://127.0.0.1:8081/2001` (checkout do simulador) e `http://127.0.0.1:8080/bilhetes` (cartões).
3. Para testar uma falha: `php dev/tickett-parse-check.php --base=http://127.0.0.1:9 --ttl=0`. Ou `curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8081/2001?fail=1"`, que devolve 500. O parâmetro `slow=1` demora 5 segundos.

### Cuidados

- Um bilhete sem nome, sem linha de preço, ou com um select fora do formato `product_N_N`, é ignorado. O erro vai para o log, com o prefixo `TickettService:`.
- Um bilhete sem select e sem "em breve" é tratado como esgotado.
- Eventos com mapa de lugares não são lidos pelo parser.
- Os bilhetes não trazem datas próprias: o cartão mostra o intervalo do festival (ver secção 1).

---


## 8. Quero mudar as cores do checkout

**Onde:** `public/assets/css/checkout-tema.css`. O proxy injeta este ficheiro na página do checkout (`/checkout`). Não entra em `layout.php`, nem em nenhuma outra página do site.

Todas as regras começam por `.fj-checkout-tema`, a classe que o proxy põe no `<html>` do checkout. Por isso o tema não afeta o resto do site.

**Regras.**

- **Só aspeto de cor.** O ficheiro já mexe também em tipos de letra, raios e sombras, e pode continuar a fazê-lo. Não mexe em layout: não mudes `display`, posições, larguras, alturas, margens nem paddings. Não mudes textos nem a ordem dos elementos.
- **Usa as variáveis `--tk-*`** do topo do ficheiro. Os valores são a paleta "papel" de `public/assets/css/tokens.css`.
- **Fontes.** O ficheiro tem um `@import` das fontes do Google. Se mudares a fonte, muda o `@import` e `--tk-font`.
- **`!important`.** O ficheiro usa-o nos fundos, porque a Tickett pinta com regras fortes. Só o uses onde for preciso ganhar à Tickett.

**Contraste.** Texto normal: 4,5:1. Texto grande, controlos e anel de foco: 3:1. Testa os pares no WebAIM Contrast Checker, como na secção 9. O botão principal do checkout usa `--tk-action` com `--tk-on-action`, e é o par mais importante.

**Exemplo.** Trocar a cor do botão principal (valor de teste):

```css
.fj-checkout-tema {
	--tk-action: #8f2e06;
}
```

**Como verificar.**

1. Com o simulador a correr (`dev/tickett-stub/serve`), abre `http://127.0.0.1:8080/bilhetes` e clica em "Escolher". O modal deve mostrar o tema novo.
2. Testa o contraste dos pares que mudaste.
3. Testa em 390 px. O layout não pode mudar.

**Cuidados.**

- O tema não se vê em `http://127.0.0.1:8081/2001`, que é o simulador direto. Só se vê pelo proxy (`/checkout` no site).
- Se algum elemento sair do sítio (cartões, carrinho, botões), a alteração foi além da cor. Reverte.
- **Formulário de dados (telefone e campos `novo-campo`).** A Tickett põe nos campos `novo-campo` fundo translúcido, borda 0 e texto branco, e no telefone um select2 sem caixa. O tema uniformiza isto e dá ao grupo do telefone a largura da linha inteira a partir de 768 px, para o placeholder ("Mobile Number Confirm") caber. Se a Tickett mudar esse markup, rever as regras `novo-campo` e `.number_fone` em `checkout-tema.css`. `dev/qa/qa.mjs checkout-layout` confirma o placeholder e o alinhamento do telefone.
- **Google Translate.** O proxy remove o widget do Google Translate do checkout original (`CheckoutProxy::removerGoogleTranslate`). Porquê: recarregava o iframe ~1 s depois de abrir, os pedidos à Google eram bloqueados por captcha, e o idioma já vem do `?l=` nativo. Os nomes de países e cidades do formulário de dados continuam a ser traduzidos pelo próprio checkout, com pedidos diretos a `translate.googleapis.com` (`traduzirTexto` em `login/site.volt`). Se falharem, ficam em português.

---

## 9. Cores, tipografia e espaços

### Onde estão os tokens

- **Referência:** `design-system/tokens.json`.
- **Usado pelo site:** `public/assets/css/tokens.css`.
- **Cópia do style guide:** `design-system/tokens.css`. É idêntica a `public/assets/css/tokens.css`.

O cabeçalho de `tokens.css` diz "generated from tokens.json", mas **não há gerador no repositório**: não há script, nem `package.json`. A cópia é manual. Hoje os valores de cor coincidem entre `tokens.json` e `tokens.css`.

### Como mudar uma cor

1. Muda o valor no `tokens.json` (campo `value`).
2. Muda a variável em `public/assets/css/tokens.css`. Exemplo: no tema Papel, `--action: #b53a08;`.
3. Copia o mesmo ficheiro para `design-system/tokens.css`.

Nos componentes usa sempre o token semântico (`var(--action)`, `var(--ink)`). Não uses `--brand-green` nem valores hex.

### Regras de contraste

- **Botão principal:** `--action` com `--on-action`. O laranja da marca (`--brand-orange`) com texto branco fica em 3,6:1 e não passa no AA. Por isso o botão usa o laranja escurecido (`--action`) no tema Papel, e ocre com texto verde no tema Noite.
- **`--brand-orange`, `--brand-ochre`, `--brand-clay`:** só para preenchimentos, símbolos e títulos grandes (24 px ou mais, ou 19 px em negrito). Nunca em texto corrido ou pequeno. `--brand-clay` só serve para os eyebrows (14 px ou mais, negrito, espaçado).
- **Texto corrido:** sempre `--ink` ou `--ink-muted`.
- **Foco:** `--focus`, contorno de 3 px.
- **Estados** (`--danger`, `--success`): sempre com palavra e ícone.

Para testar um par de cores, usa um verificador de contraste no browser (por exemplo, o WebAIM Contrast Checker). Requisito: 4,5:1 para texto, 3:1 para texto grande, controlos e anel de foco.

### Tipografia e espaços

- **Fontes:** servidas pelo próprio site, sem Google Fonts. Os ficheiros são `public/assets/fonts/*.woff2` (subsets latin e latin-ext do Google Fonts, licença OFL; Atkinson Hyperlegible Next e Rubik são variáveis). As `@font-face` estão em `public/assets/css/fontes.css`, com `font-display: swap`. Rubik Dirt é um subset de maiúsculas, dígitos, espaço e acentos (ÁÂÃÀÇÉÊÍÓÔÕÚ). Para mudar uma fonte, muda o ficheiro e a `@font-face`, as variáveis `--font-*` em `tokens.css` e a fonte do tema do checkout (`--tk-font`, em `public/assets/css/checkout-tema.css`). Os fallbacks `Atkinson Fallback`, `Rubik Fallback` e `Rubik Dirt Fallback` têm `size-adjust` e overrides calculados para não deslocar o texto quando a fonte web chega (CLS).
- **Rubik Dirt só serve para maiúsculas.** Hoje só é usada em `.hero__date` (`home.css`), que tem `text-transform: uppercase`. Se a usares noutro sítio com minúsculas, essas letras caem para Rubik. Se precisares de outros caracteres, gera outro subset com `pyftsubset` (o mesmo conjunto de caracteres, mais os novos) e atualiza o `unicode-range` em `fontes.css`. Nota que o peso sobe (o ficheiro atual pesa ~110 KB, por causa dos contornos da fonte).
- **Escala:** classes `.display-1`, `.display-2`, `.heading`, `.title`, `.lead`, `.body`, `.small`, `.eyebrow` e `.button`, em `tokens.css`. A classe `.poster` existe em `tokens.css`, mas nenhum template a usa: a data do hero tem o estilo próprio em `home.css`.
- **Espaços:** `--space-1` a `--space-32`.
- **Raios e sombras:** `--radius-*` e `--shadow-*` em `tokens.css`.

**Como verificar.**

- Testa a 390, 1024 e 1440 px de largura (ferramentas de programador do browser). Os pontos de quebra são 768 e 1024 px (`site.css`, `header.js`).
- Testa contraste de cada par que mudaste.
- Verifica a home, /bilhetes (tema Noite) e /cartaz.

---

## 10. Criar uma página nova

Exemplo fictício: `/voluntarios`. Os passos são sempre estes.

**1. Rota (nas duas línguas).** Em `app/Helpers/Rotas.php`, dentro de `PAGINAS`, junto às outras. Português na raiz, inglês com `/en`:

```php
'voluntarios' => ['pt' => '/voluntarios', 'en' => '/en/volunteers', 'page' => 'voluntarios'],
```

O título vem da chave `nav.voluntarios` em `app/Lang/pt.php` (`'Voluntários'`) e em `app/Lang/en.php` (`'Volunteers'`). Fica `Voluntários · Festival Festeja` no `<title>` em português e `Volunteers · Festival Festeja` em inglês. O router, o seletor de idioma, o hreflang e o sitemap leem este mapa sozinhos. Não escrevas caminhos como `/voluntarios` nos templates: usa `Rotas::url('voluntarios')`.

**2. Página.** Cria `app/Views/Pages/voluntarios.php`:

```php
<?php
// Voluntários: como ajudar e contacto.

use App\Helpers\Component;
use App\Helpers\Rotas;
?>
<main id="conteudo">
  <?= Component::render('PageHeader', [
      'items' => [['label' => t('nav.inicio'), 'href' => Rotas::url('inicio')], ['label' => t('nav.voluntarios')]],
      'eyebrow' => t('voluntarios.eyebrow'),
      'title' => t('nav.voluntarios'),
      'lead' => t('voluntarios.lead'),
  ]) ?>

  <div class="wrap">
    <section aria-labelledby="voluntarios-como">
      <h2 id="voluntarios-como">Como ajudar</h2>
      <p>Texto da página.</p>
    </section>
  </div>
</main>
```

O `<main id="conteudo">` é obrigatório: é o destino do link "Saltar para o conteúdo". O `PageHeader` já traz o único `<h1>`.

**3. CSS.** Um bloco novo em `public/assets/css/pages.css`, com o prefixo da página (`voluntarios-`).

**4. Menu.** Se a página deve aparecer no menu:

- `app/Views/Components/SiteHeader.php`: lista `$itens`, com `Rotas::url('voluntarios')` e `t('nav.voluntarios')`. A chave `key` tem de ser o nome da página, para o item ficar com `aria-current`. O menu móvel usa a mesma lista.
- `app/Views/Components/SiteFooter.php`: lista `$links`, se quiseres a página no rodapé.

**5. Sitemap.** Não precisa de nada: `Seo::sitemap()` percorre `Rotas::pares()`, com as duas versões e os alternates.

**6. /llms.txt.** Só português. Em `app/Helpers/Seo.php`, constante `PAGINAS`: `'/voluntarios' => 'Voluntários',`.

**7. JSON-LD** (só se a página precisar). Em `public/index.php`, no `match ($page)` dos dados JSON-LD. O caso `default` devolve vazio.

**Cuidado: página em falta.** Se criares a rota mas não o ficheiro em `Pages/`, a página responde 200 com "Esta página está em construção". Não dá 404. Confirma sempre o ficheiro.

**Como verificar.**

1. Abre a página e confirma o título do separador.
2. Confirma o item no menu e o destaque (`aria-current`).
3. Confirma no sitemap: `curl -s http://127.0.0.1:8080/sitemap.xml | grep voluntarios`, e `grep volunteers` para a versão en.
4. Confirma a versão inglesa: `/en/volunteers`, o seletor PT | EN e o `hreflang`.
5. Testa por teclado e em 390 px.

---

## 11. Criar ou alterar um componente

### Convenções

- Ficheiro em `app/Views/Components/Nome.php`, com nome em PascalCase.
- Chamada: `Component::render('Nome', ['prop' => valor])`. O nome só pode ter letras, números, `_` e `-`.
- Props: variáveis com o nome da chave, com valor por defeito: `$x = $x ?? 'defeito';`.
- Cabeçalho do ficheiro: um comentário com o que faz e uma linha `// Props:`. Todos os componentes seguem esta regra.
- Todo o dado sai com `e()`, que escapa o HTML. Só o HTML de `Icon::svg()` e de `Component::render()` sai sem escape.
- Sem JavaScript dentro do componente. O comportamento vai por atributo `data-*` e por um módulo em `public/assets/js/`.
- Se o componente não existir: em desenvolvimento dá erro; em produção (`env` = `prod`) escreve no log e não imprime nada.

### Exemplo real: `Breadcrumbs.php` (completo)

```php
<?php
// Migalhas de pão para páginas internas.
// Props: items (lista de ['label' => texto, 'href' => url ou null]). O último item é a página atual (aria-current).

$items = $items ?? [];
$ultimo = count($items) - 1;
?>
<nav class="breadcrumbs" aria-label="Caminho">
  <ol class="breadcrumbs__list">
    <?php foreach ($items as $i => $item): ?>
    <li class="breadcrumbs__item">
      <?php if ($i === $ultimo || empty($item['href'])): ?>
      <span aria-current="page"><?= e($item['label']) ?></span>
      <?php else: ?>
      <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
```

### Onde vai o CSS

Ordem de carregamento em `layout.php`: `fontes.css`, `tokens.css`, `base.css`, `components.css`, `checkout.css`, `site.css`, `home.css` (só na home) e `pages.css`. Com a mesma especificidade, ganha a regra que carrega por último.

**Pacotes.** Em vez de uma folha por ficheiro, o layout carrega um pacote: `public/assets/css/pacote-comum.css` (todas as páginas) ou `pacote-home.css` (home, com `home.css`). Os pacotes são gerados por `php bin/css-pacote.php` (ordem em `App\Helpers\Estilos`). **Depois de editar qualquer folha, volta a gerar.** Se uma folha for mais recente do que o pacote, o layout volta sozinho às folhas soltas (a página fica certa, só mais lenta). Não edites os pacotes à mão.

- `components.css`: classes `fj-` de base (botões, selos, cartão de bilhete, FAQ, faixa, modal).
- `site.css`: cabeçalho, rodapé, faixa em movimento, cartaz (`lineup-day`), cartões de artista, factos, bilhetes, FAQ e barra de compra.
- `home.css`: tudo o que é da página inicial (`hero`, `home-*`).
- `pages.css`: páginas internas (`page-header`, `cartaz-*`, `artista*`, `bilhetes-*`, `informacoes*`, `como-chegar*`, `legal`, `not-found`).
- `checkout.css`: só a moldura do modal do checkout.
- `checkout-tema.css`: não entra em `layout.php`. O proxy injeta-o só na página do checkout (`/checkout`). Ver secção 8.

Antes de criar uma classe, procura se já existe: `grep -rn "nome-da-classe" public/assets/css`.

### Onde vai o JavaScript

`public/assets/js/main.js` tem uma lista `modules`. Cada módulo carrega-se só se o seletor existir na página (e se o `media`, quando houver, corresponder).

| Módulo | Carrega quando existe | Faz |
| --- | --- | --- |
| `header.js` | Sempre | Menu móvel, sombra ao fazer scroll, foco preso no menu |
| `marquee.js` | `[data-marquee]` | Botão de pausa da faixa |
| `tabs.js` | `[data-tabs]` | Separadores por dia (setas, Home e End) |
| `lineup-hover.js` | `[data-lineup-hover]` e ecrã ≥ 1024 px com rato | Pré-visualização do artista ao lado do cursor |
| `buybar.js` | `[data-buybar]` | Mostra a barra de compra no mobile depois do hero |
| `checkout.js` | `[data-checkout]` | Modal do checkout, mensagens da Tickett, contador do carrinho |
| `hero.js` | `[data-hero]` e ecrã ≥ 1024 px sem movimento reduzido | Entrada do hero (uma vez, cerca de 2 s) |

Para criar um módulo: um ficheiro novo com `export default function init(root = document) { ... }` e uma entrada em `modules`, com o `selector`.

`checkout-ponte.js` não é um módulo. O proxy injeta-o dentro da página do checkout (ver `docs/contrato-tickett.md`, secção 3). Não o cargas em `main.js`.

### Componentes existentes

| Componente | Props | Usado em |
| --- | --- | --- |
| `ArtistCard` | `artista` (entrada de `artistas.json`). Mostra o selo "Novo" se `confirmado_em` for dos últimos 14 dias | Cartaz, relacionados na página do artista |
| `Breadcrumbs` | `items` (lista de `label` e `href`; o último é a página atual) | Páginas internas |
| `CheckoutModal` | Nenhuma (lê a configuração) | Layout |
| `Countdown` | `target` (ISO com fuso) | Só a página de kit (`/kit`). Não está no hero nem na home |
| `FactsStrip` | `minPrice` | Home, /informacoes, kit |
| `Faq` | `perguntas` (`p` e `r`), `id` | Home, /informacoes, /bilhetes |
| `HomeClose` | `data` (intervalo com ano), `eyebrow` (de `home.json`). Faixa de símbolos por baixo do botão | Home |
| `HomeFestival` | Nenhuma (lê `home.json`, bloco `festival`) | Home |
| `HomeHero` | `festival`, `artistas`, `minPrice`, `data`, `eyebrow` | Home |
| `HomeVisit` | `festival` (usa `transportes`; os títulos vêm de `home.json`, bloco `visita`) | Home |
| `LineupDay` | `dia`, `artistas`, `hover`. Marca "Novo" depois do nome nos artistas recentes | Home, /cartaz |
| `Partners` | Nenhuma (lê `parceiros.json`). Sem parceiros, não escreve nada | Home, antes do fecho |
| `Mandala` | `class` | Hero, kit |
| `SymbolGrid` | `simbolos` (números de 1 a 24, por ordem), `size` (px de cada símbolo), `class`, `mov` (bool: entra um a um, `escalonar` com `simbolo`). Decorativa | Cabeçalho de página (2 × 3, à direita, só desktop), fecho, 404 |
| `Marquee` | `items`, `label` | Hero, kit |
| `PageArtistMedia` | `artista` | Página do artista |
| `PageHeader` | `items`, `eyebrow`, `title`, `lead`, `theme` | Páginas internas |
| `SectionHead` | `eyebrow`, `title`, `lead`, `level` (2), `symbol`, `align` (`start` ou `center`), `id` | Home, /bilhetes, kit |
| `SiteFooter` | Nenhuma | Layout |
| `SiteHeader` | `active` (nome da página atual) | Layout |
| `StickyBuyBar` | `minPrice` | Layout (mobile) |
| `Symbol` | `n` (1 a 24), `size` (32 por defeito) | Títulos de secção, faixa, home, cartão do local, rodapé, cartões de artista sem foto |
| `TicketCard` | `product`, `checkoutUrl` | `TicketGrid` |
| `TicketGrid` | `catalog`, `checkoutUrl`. Mensagens de falha e de venda (ver secção 7) | Bilhetes, home, kit |

Ícones: `Icon::svg('nome')` aceita `menu`, `x`, `map-pin`, `calendar`, `clock`, `ticket`, `arrow-right`, `chevron-down`, `pause`, `play`, `shopping-bag`, `train`, `bus`, `car`, `accessibility`, `external-link`, `check`, `alert-circle`, `instagram`, `facebook` e `youtube`.

**Como verificar.** Abre as páginas onde o componente aparece. Testa com teclado e com o movimento reduzido ativado no sistema.

---

## 12. Imagem de partilha, favicon e ficheiros de marca

### Imagem de partilha (og.jpg)

- **Ficheiro:** `public/assets/img/og.jpg`, 1200 × 630, JPEG. Gerado por `dev/og/build.mjs`, não à mão.
- **Como regenerar:** `node dev/og/build.mjs` (precisa de Google Chrome e de rede, para as fontes). O script lê a data, o nome do festival e o local de `content/festival.json` e põe-nos na composição de `dev/og/og.html`. Depois de mudares esses campos, corre o script de novo.
- **Mudar o desenho:** edita `dev/og/og.html`, que é um HTML normal. Abre-o no browser para ver. Os valores de exemplo do HTML são os de `festival.json`; o script substitui-os.
- **Uma imagem para todas as páginas.** O código não tem imagem por página.
- **Onde é usada:** `app/Views/layout.php` (`og:image`) e `app/Helpers/Seo.php` (campo `image` do JSON-LD).
- **Redes sociais guardam a imagem em cache.** Depois de mudares, usa o depurador da plataforma (Facebook, LinkedIn) para forçar a atualização.

**Como verificar.** `curl -s http://127.0.0.1:8080/ | grep og:image` e abre a URL da imagem no browser.

### Favicon e ícone de ecrã inicial

- **Favicon:** `public/assets/img/favicon.svg`, o símbolo da mandala (`icone-01.svg`) já otimizado. O `layout.php` usa-o em `<link rel="icon">`. Não usa o logótipo.
- **Apple touch icon:** `public/assets/img/apple-touch-icon.png`, 180 × 180, fundo creme. Gerado por `dev/og/build.mjs` (mesmo script da imagem de partilha).
- Não há `favicon.ico`. Um browser que o peça recebe 404.
- Para mudar o símbolo: substitui o `favicon.svg` e corre `dev/og/build.mjs` para atualizar o ícone de ecrã inicial. Para regenerar o favicon a partir de outro SVG: `node dev/svg/round.mjs entrada.svg public/assets/img/favicon.svg`.

### Símbolos (sprite)

Os símbolos 1 a 24 são um sprite externo, `public/assets/img/simbolos/sprite.svg`, gerado por `php bin/simbolos-sprite.php`. O componente `Symbol` referencia `#icone-NN` com `<use>`. **Depois de mudar um `icone-NN.svg`, volta a gerar.** A mandala do hero continua inline (`Mandala.php`).

### Logótipos e peso (dev/svg/round.mjs)

- `public/assets/img/logo/logo-vertical.svg` (hero, mobile) e `logo-horizontal.svg` (cabeçalho) foram reduzidos com `dev/svg/round.mjs`. O script aplica o transform do `<g>` às coordenadas, converte os paths em comandos relativos e arredonda as coordenadas. Sem dependências.
- Uso: `node dev/svg/round.mjs entrada.svg saída.svg [casas]`. Por omissão, 0 casas decimais.
- **Porque 0 casas e não 1.** O potrace já escreve as coordenadas com 0,1 unidades do viewBox. Com 1 casa decimal o ficheiro cresce (+9 % no logótipo vertical). Com 0 casas (unidades inteiras do viewBox) cai cerca de 32 %: 153 KB → 104 KB no vertical, 70 KB → 48 KB no horizontal. Visualmente, a diferença fica nas arestas (antialiasing): comparado a 2× com o Chrome, sem alteração de forma.
- Sobre o logótipo: o `<picture>` do hero mobile dá ao desktop (≥ 1024 px, onde o CSS esconde o logótipo) uma fonte vazia. O SVG só é descarregado em ecrãs menores.
- **Não é um processo automático.** Se trocares o logótipo original, volta a correr o script no ficheiro novo.

---

## 13. Analytics (GA4)

**Estado atual:**

- `config/site.php` tem `'analytics' => ['ga4_id' => null]`.
- Nenhum código lê esta chave. Não há tag do GA4 no layout nem em JavaScript. **Pôr um ID hoje não faz nada.**
- `checkout.js` envia eventos para `window.dataLayer` (`begin_checkout`, `select_item`, `checkout_step`, `purchase`), mas só se o `dataLayer` existir. Hoje nada o cria.
- Não há analytics, por isso não há banner de cookies. O proxy do checkout põe cookies `tk_*` no domínio do site (secção 6): rever os textos legais com isso em conta. Só guarda no `sessionStorage` o número de bilhetes no carrinho (`festeja-cart-count`) e as compras já registadas (`festeja-purchases`).

**Se o GA4 for ativado, muda-se isto antes (ordem):**

1. Banner com "Aceitar" e "Rejeitar" ao mesmo nível (plano, secção 8). Sem consentimento, nenhuma tag de medição carrega.
2. Consent Mode v2.
3. Tag do GA4 com o ID de `analytics.ga4_id`, carregada só depois de aceitar.
4. Atualizar `content/paginas/privacidade.html` e `content/paginas/cookies.html`: passam a descrever o GA4, a base legal, o consentimento e como se retira.
5. Confirmar que os eventos chegam (vista de depuração do GA4).

Só depois disto ligas o ID em `config/site.local.php`. Sem os passos 1 e 4, ligar o ID viola o que as páginas legais dizem.

---

## 14. SEO e IA: o que é gerado

| Saída | Onde é gerada | De onde vêm os dados |
| --- | --- | --- |
| `<title>`, meta description, canonical, Open Graph | `layout.php`, `public/index.php` | Título: nome da página e nome do site. Home: `nome`, `edicao` e `mote`. Artista: nome do artista. Description: ver a tabela de descriptions abaixo |
| JSON-LD `MusicFestival` | Home, /bilhetes, /cartaz | `festival.json` (nome, edição, resumo, datas, portas, local, organizador), `artistas.json` (`performer`), Tickett (`offers`) |
| JSON-LD `FAQPage` | Home (perguntas com `home`), /informacoes (todas) | `faq.json` |
| JSON-LD `MusicGroup` e `BreadcrumbList` | /artistas/{slug} | `artistas.json` e `festival.json` |
| `/llms.txt` | Gerado a cada pedido | Festival, cartaz, FAQ, preço mínimo e checkout (Tickett) |
| `/sitemap.xml` | Gerado a cada pedido | Cada página nas duas línguas (`Rotas::pares()`), com `xhtml:link` de alternate (pt-PT, en, x-default). Os slugs vêm de `artistas.json`. `lastmod` é a data do ficheiro `content/*.json` mais recente |
| `/robots.txt` | Gerado a cada pedido | Fixo em `Seo.php`: tudo permitido exceto `/kit`, com blocos para os crawlers de IA |

**Meta descriptions por página.** Gera-se em `public/index.php` (função `metaDescription()`), a partir dos dados e dos textos `meta.*` de `app/Lang/pt.php` (português) e `app/Lang/en.php` (inglês). A tabela mostra a versão portuguesa:

| Página | Texto |
| --- | --- |
| `/` | `resumo` de `festival.json` |
| `/bilhetes` | "Bilhetes do Festival Festeja 2027: 23 e 24 julho no Estádio do Olivais e Moscavide. Preços com taxas incluídas, pagamento seguro pela Tickett." (**a rever**: o texto diz 'taxas incluídas', que já não é verdade; ver secção 7 e "Antes de lançar") |
| `/cartaz` | "Cartaz do Festival Festeja 2027: …" com os 4 primeiros artistas de destaque 1 (e "e mais" se houver mais), e as datas e o local |
| `/informacoes` | "Perguntas frequentes sobre o Festival Festeja 2027: …" com os temas dos grupos de `faq.json` |
| `/como-chegar` | "Como chegar ao {local}, em {zona}: …" com os meios de `transportes`, e a hora das portas se for a mesma nos dois dias |
| `/artistas/{slug}` | A bio cortada a 155 caracteres (na última palavra inteira, com "…"). Sem bio: "{nome} atua no Festival Festeja 2027, {dia}, no {local}." |
| Legais (`/acessibilidade`, `/privacidade`, `/cookies`, `/termos`) | Uma frase fixa por página, com o nome do festival |
| 404 | "Esta página não existe no site do Festival Festeja 2027. Vai ao início ou vê o cartaz." |

Se mudares o nome do festival, o ano ou o local, as descriptions mudam sozinhas. Se mudares o texto de uma página legal, edita a chave `meta.legal.*` em `app/Lang/pt.php` e em `app/Lang/en.php`.

Notas:

- O plano prevê JSON-LD de `Organization` e `WebSite`, e um `llms-full.txt`. Não existem ainda.
- O JSON-LD de `offers` só aparece se a Tickett responder. O `availability` segue o estado de cada bilhete.
- Editar qualquer `content/*.json` muda o `lastmod` de todo o sitemap. Editar `content/paginas/*.html` não muda.
- A página `/kit` está fora do sitemap e bloqueada no robots.

**Como validar.** Copia o HTML da página para o validador de schema.org (validator.schema.org) ou usa o Rich Results Test da Google.

---

## 15. Preparar a próxima edição

Checklist:

- [ ] `content/festival.json`: `edicao`, `datas`, `dias` (id, data, portas), `local` (nome, zona, morada, geo), `idade_minima`, `mote`, `resumo`, `chamada`, `marca`, `organizador`, `producao`, `redes`. Remove as marcas `rever` quando confirmadas.
- [ ] `content/artistas.json`: substitui o cartaz de exemplo. Confirma `dia`, `destaque` e os slugs.
- [ ] `content/faq.json`: rever todas as respostas com horas ou datas (por exemplo, "16h00" e "até junho"). Confirma as cinco da home.
- [ ] Textos da home em `content/home.json` (o título do cartaz troca `{edicao}` sozinho). Os textos fixos que restam estão na lista da secção 1.
- [ ] Transportes em `festival.json` (`transportes`): confirma os itens e tira as marcas `rever`.
- [ ] Tickett: evento novo com `event_id`, bilhetes, lotes e venda aberta, visíveis na página pública do checkout.
- [ ] `config/site.local.php`: `event_id` novo. No checkout não há nada a fazer (secção 16).
- [ ] Fotografias dos artistas em 4:5. Depois de mudar a data ou o local, `node dev/og/build.mjs` para atualizar o `og.jpg`.
- [ ] Parceiros em `parceiros.json`, com os logótipos em `public/assets/img/parceiros/`.
- [ ] Páginas legais: revisão jurídica, "contacto a definir" e "Última revisão".
- [ ] Apaga `storage/cache/tickett-*.json` e confirma que o catálogo novo aparece.
- [ ] Verificações: `php -l` nos PHP alterados, JSON válido, endpoints (`/llms.txt`, `/sitemap.xml`, `/robots.txt`), teste a 390, 1024 e 1440 px, teste só com teclado, e a suíte de QA (secção 17).

---


## 16. Publicar em produção

Checklist:

1. **Configuração local de produção.** Cria `config/site.local.php` (não vai para o git):

   ```php
   <?php

   return [
     'env' => 'prod',
     'base_url' => 'https://festeja.pt',
     'tickett' => [
       'base' => 'https://checkout.lebillet.eu',
       'event_id' => 2001,   // confirmar o ID real
     ],
     'analytics' => ['ga4_id' => null],
   ];
   ```

   O `event_id` `2001` é provisório. Confirma-o com a Tickett.

2. **Efeitos de `env` = `prod`.** A página `/kit` responde 404. A faixa "Rascunho" das páginas legais não aparece. Um componente em falta não dá erro visível: escreve no log e fica vazio.

3. **Servidor.** A raiz do site é `public/`. O `public/.htaccess` precisa de Apache com `mod_rewrite` ativo e `AllowOverride` para o `.htaccess`. Noutro servidor, o pedido de um caminho que não é ficheiro tem de chegar a `public/index.php`. Isto inclui `/checkout` e `/checkout/relay/…`.

4. **Extensões PHP.** `curl` (proxy do checkout) e `dom` (leitura dos bilhetes). Sem `curl`, o `/checkout` falha. Sem `dom`, os bilhetes não são lidos.

5. **HTTPS.** O repositório não força HTTPS. Configura-o no servidor e usa `https://` no `base_url`. Os cookies `tk_*` só saem com `Secure` se `$_SERVER['HTTPS']` não for `off`. Atrás de um proxy de TLS, confirma isto.

6. **Escrita em `storage/cache/`.** O PHP grava `tickett-{event_id}.json` aqui. Sem escrita, a cache não funciona e cada pedido vai à Tickett, com erro no log.

7. **Checkout da Tickett.** Não há nada a mudar no checkout. O site só precisa de `tickett.base` e `tickett.event_id`.

8. **Testes de compra.** Segue a checklist de `docs/checkout-tickett-alteracoes.md`: fluxo completo com pagamento real de teste, pagamentos com redirecionamento externo, cookies, robots e erro.

9. **Conteúdo.** Faz a checklist da secção 15.

Ficheiros ignorados pelo git e que não se publicam pelo repositório: `config/site.local.php` e `storage/cache/*`. Cria-os no servidor.

---


## 17. Verificar antes de dar como pronto (QA)

A suíte está em `dev/qa/` e corre com Chrome headless. Ver `dev/qa/README.md` para variáveis e pré-requisitos (site em 8080, simulador em 8081).

```sh
node dev/qa/qa.mjs all              # tudo, exceto fallback
node dev/qa/qa.mjs compra 1440      # fluxo de compra real (home → modal → simulador → purchase, Esc, foco), a 1440
node dev/qa/qa.mjs compra 390       # o mesmo a 390
node dev/qa/qa.mjs escolher         # "Escolher" de um TicketCard abre com o produto destacado
node dev/qa/qa.mjs checkout-layout  # checkout por passo a 1440, 1280, 1024, 768 e 390
node dev/qa/qa.mjs a11y             # por página a 1440 e 390 px
node dev/qa/qa.mjs teclado          # skip link, menu móvel, setas nos separadores
node dev/qa/qa.mjs movimento        # prefers-reduced-motion e GSAP
node dev/qa/qa.mjs semjs            # sem JavaScript
node dev/qa/qa.mjs overflow         # scroll horizontal a 320, 390, 768, 1024 e 1440
node dev/qa/qa.mjs desempenho       # peso, pedidos, LCP e CLS
node dev/qa/qa.mjs seo              # robots, sitemap, llms e HTTP de cada página
node dev/qa/qa.mjs i18n             # pt e en: HTTP, lang, hreflang, canonical, seletor, texto pt nas páginas en, modal en
node dev/qa/qa.mjs fallback         # simulador desligado (com e sem cache)
```

- **Alvos de toque (`a11y`).** Critério AA: 24 × 24 px (WCAG 2.2, 2.5.8). Um alvo abaixo disto falha. Entre 24 e 44 px é só um aviso informativo (AAA). O cartaz cumpre 24 px sem mudar o aspeto (`site.css`, `.lineup-name a`: padding e margem negativa).
- **Desempenho (`desempenho`).** Compara o peso total e o externo (Google Fonts) de cada página. Números de referência: ver o relatório da última ronda.
- **Imagens geradas.** `og.jpg` e `apple-touch-icon.png` não são verificados pela suíte. Abre-os depois de correr `dev/og/build.mjs`.

---

## 18. Quero animar um bloco ao fazer scroll

Os efeitos ao fazer scroll usam GSAP (ScrollTrigger e ScrollToPlugin). A biblioteca está em `public/assets/vendor/gsap/` (versão e licença no README da pasta) e o comportamento está em `public/assets/js/movimento.js`. Não escreves JavaScript para cada efeito: marcas o HTML com atributos `data-mov` e o módulo lê-os.

| Atributo | O que faz | Parâmetros (`data-mov-*`) |
| --- | --- | --- |
| `data-mov="revelar"` | O bloco sobe 24 px e aparece, uma vez, ao entrar no ecrã. | `data-mov-estilo`: `escala` (0,94 → 1), `girar` (símbolo entra a rodar de -90° até 0°, a orientação original) |
| `data-mov="escalonar"` | Os filhos entram um a um. | `data-mov-alvo` (seletor dos filhos; por defeito, os filhos diretos). `data-mov-estilo`: `mascara` (nomes do cartaz), `sticker` (cartões de bilhete), `facto` (itens da faixa de factos), `simbolo` (símbolos de uma grelha: escala 0,8 → 1 e opacidade, sem rodar). `data-mov-passo` (segundos entre itens; 0,08 por defeito) |
| `data-mov="parallax"` | O bloco desloca-se ligado ao scroll (até sair do ecrã). Só desktop. | `data-mov-vel`: fração do tamanho (negativo sobe mais devagar). `data-mov-giro`: graus de rotação extra |
| `data-mov="palavras"` | Cada palavra acende de cinzento para a cor final, ligada ao scroll. Só desktop. | — |
| `data-mov="inclinar"` | A faixa em movimento inclina até 6° conforme a velocidade do scroll. Só desktop. | — |

Exemplos:

```html
<!-- Bloco que sobe ao entrar -->
<div class="meu-bloco" data-mov="revelar">…</div>

<!-- Lista em que cada item entra com um intervalo de 0,12 s -->
<ul data-mov="escalonar" data-mov-passo="0.12">…</ul>

<!-- Imagem que sobe mais devagar do que a página, no desktop -->
<div data-mov="parallax" data-mov-vel="-0.1">…</div>
```

Regras que o módulo cumpre (não as contornes):

- **Só com movimento permitido.** Em `prefers-reduced-motion: reduce` o GSAP não é carregado e nada muda. O carregamento é depois do `load` (`main.js`), para não piorar o LCP.
- **Só o que está abaixo da dobra.** Ao carregar, só ficam escondidos os blocos ainda fora do ecrã. Um bloco visível desde o início nunca fica escondido.
- **Sem JS ou sem GSAP, o conteúdo fica visível.** Os estados iniciais são aplicados pelo GSAP, nunca pelo CSS.
- **Desktop tem tudo; tablet e telemóvel só revelações.** Abaixo de 1024 px só `revelar` e `escalonar`, sem parallax.
- **Uma vez ou ligado ao scroll.** `revelar` e `escalonar` acontecem uma vez (não repetem ao voltar a subir). `parallax`, `palavras` e `inclinar` acompanham o scroll.
- **Propriedades.** Usa `transform` e `opacity`. Exceções já feitas: a cor das palavras do festival (`palavras`), a variável CSS das divisórias e pontos (`--facts-sep`, `--ponto`) e o `overflow` da máscara do cartaz, só durante a entrada. Não uses `height` nem `box-shadow` nestes efeitos.
- **Menu e checkout.** Com o menu móvel ou o modal do checkout abertos, os efeitos ligados ao scroll param.

Cabeçalho e âncoras (sem marcação): o cabeçalho esconde-se ao descer e volta ao subir. Ligações `href="#id"` para a página deslocam-se com o offset do cabeçalho e levam o foco ao título da secção. O indicador (sublinhado que desliza) mostra a página atual (`aria-current`) e segue o rato e o foco pelos links da navegação.

Para mudar um efeito, edita `public/assets/js/movimento.js` (a tabela `ESTILOS` e as funções `revelar`, `escalonar`, `parallax`, `palavras`, `inclinar`). Para atualizar o GSAP, segue o README da pasta do vendor.

Como verificar: `node dev/qa/qa.mjs movimento` (reduced-motion sem GSAP, nada invisível depois de rolar a página toda, cabeçalho e âncoras). Os alvos `a11y` rolam a página antes de medir, para medir o estado final.

---

## 19. Quero mudar um texto / traduzir

O site está em português de Portugal (predefinido, na raiz: `/`, `/bilhetes`) e em inglês britânico (com `/en`: `/en`, `/en/tickets`). Cada texto existe nas duas línguas. Antes de mudar um texto, descobre onde ele está:

| O que é | Português | Inglês |
| --- | --- | --- |
| Textos de interface: botões, menus, avisos, rótulos, `aria-label`, mensagens de erro, datas por extenso no texto | `app/Lang/pt.php` | `app/Lang/en.php` |
| Textos editoriais: festival (mote, resumo, chamada, transportes), home, perguntas frequentes | `content/*.json` | `content/en/*.json` |
| Artistas: nome, país, dia, datas de confirmação | `content/artistas.json` | só a bio, em `content/en/artistas.json` (`{"slug": {"bio": "…"}}`) |
| Páginas legais | `content/paginas/*.html` | `content/en/paginas/*.html` |
| Endereços das páginas | `app/Helpers/Rotas.php` (mapa `PAGINAS`) | o mesmo ficheiro, coluna `en` |
| Datas, horas, preços e nomes de países | `app/Helpers/Format.php` | o mesmo ficheiro (já em inglês) |
| Títulos dos passos e mensagem de compra do checkout | `app/Lang/*.php` (chaves `checkout.*`) | o mesmo |

**Não traduzas aqui:** os nomes e as descrições dos bilhetes (vêm da Tickett), e os nomes dos artistas. O checkout da Tickett já mostra o próprio idioma (o site passa `?lang=` e `?l=`).

### Mudar um texto de interface

1. Encontra a chave: `grep -rn "Comprar bilhetes" app/Views` mostra `t('botao.comprar_bilhetes')`.
2. Muda o texto em `app/Lang/pt.php` **e** em `app/Lang/en.php` (mesma chave, mesma ordem).
3. Confirma: `php -l app/Lang/pt.php` e `php -l app/Lang/en.php`.

Os ficheiros são arrays `'chave' => 'texto'`. Um apóstrofo dentro do texto escreve-se com `\'`. Em `{nome}` e outros `{…}` estão os parâmetros: não os traduzas, nem os tires do texto inglês.

### Acrescentar uma chave

1. Escolhe um nome com grupo, por exemplo `voluntarios.lead` (o grupo é o sítio onde o texto aparece).
2. Acrescenta-a em `app/Lang/pt.php` **e** em `app/Lang/en.php`, na mesma posição.
3. No template, escreve `<?= e(t('voluntarios.lead')) ?>`. Com parâmetros: `t('botao.escolher_nome', ['nome' => $nome])`.

Em dev, uma chave que falta num dos idiomas lança um erro `Texto em falta (en): …`. Em produção, a página cai no português e regista no log. Por isso, em dev, a página en tem de abrir sem erros.

### Mudar um conteúdo editorial (em inglês)

`content/en/*.json` tem só os campos traduzíveis, com a **mesma estrutura e a mesma ordem** que o português:

- `content/en/festival.json`, `content/en/home.json`: objetos, fundidos chave a chave por cima do português.
- `content/en/faq.json`: os grupos e as perguntas são fundidos **por posição**. Se acrescentares uma pergunta em `content/faq.json`, acrescenta-a no mesmo sítio em `content/en/faq.json`. Não mudes a ordem de um lado só.
- `content/en/artistas.json`: `{ "slug": { "bio": "…" } }`. Um artista sem entrada aqui fica com o texto português (que é o caso das bios que faltam: aparece "Biography coming soon." por causa de `artista.bio_vazia`).

### Acrescentar uma página nas duas línguas

Exemplo: `/voluntarios` e `/en/volunteers`. Faz estes passos pela ordem:

1. **Rota:** `app/Helpers/Rotas.php`, mapa `PAGINAS`: `'voluntarios' => ['pt' => '/voluntarios', 'en' => '/en/volunteers', 'page' => 'voluntarios']`. Ver a secção 10.
2. **Textos:** chaves `nav.voluntarios`, `voluntarios.*` em `app/Lang/pt.php` e `app/Lang/en.php`.
3. **Página:** um único ficheiro, `app/Views/Pages/voluntarios.php`, serve as duas línguas. Usa `t()` e `Rotas::url()`, nunca texto nem caminho fixo.
4. **Conteúdo, se houver:** o mesmo caminho em `content/` e `content/en/`.
5. **Verificar:** `node dev/qa/qa.mjs i18n`, e `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/en/volunteers` (tem de dar 200).

O QA `i18n` falha se uma página en tiver texto em português visível, se os hreflang não forem recíprocos, se o seletor não apontar para o par, ou se o modal não abrir `/checkout?lang=en`. Não há tradução automática: a versão inglesa escreve-se à mão, em inglês britânico, sem decalques do português.

---

## Como o site fala com a Tickett

O site fala com a Tickett em três pontos. O detalhe está em `docs/contrato-tickett.md`.

```
 Visitante              Site Festeja (PHP)                     Tickett (checkout publicado)
 ---------              ------------------                     ----------------------------
 1. Abre /bilhetes ---> lê a página do evento ----------> {base}/{event_id}
                        (cache de 5 min)   <-----------  HTML com os bilhetes
 <--- cartões com preço (sem taxa)

 2. Clica "Escolher" -> abre o modal (checkout.js)
                        iframe com src = /checkout?produto=9001_1
                        /checkout (CheckoutProxy) ----> {base}/{event_id}?produto=9001_1
 <--- página reescrita (tema e ponte) <-----------------  HTML do checkout

 3. Checkout -> site: postMessage (checkout-ponte.js)
    (ready, resize, step, cart, purchase, close)  <---  só para a origem do site
```

1. **Leitura dos bilhetes.** O servidor do site pede a página do evento e lê o HTML. Não é o browser. A leitura fica em cache 5 minutos.
2. **Checkout.** O modal abre um iframe com `/checkout?lang=pt`, o proxy do próprio site. O proxy pede a página à Tickett e devolve-a reescrita, com o tema e a ponte. Os cookies `tk_*` ficam no browser, no domínio do site.
3. **Mensagens.** A ponte avisa o site (passo, carrinho, compra, fechar). `checkout.js` aceita só mensagens da origem do site e do próprio iframe.

**Idioma do checkout (pt ou en).** O idioma do site segue até ao checkout como `?lang=` no iframe. O proxy converte-o para o parâmetro `l` da Tickett (`pt` → `pt_pt`, `en` → `en_us`), que a Tickett grava na sessão e usa nos textos. Quem abre o modal define o idioma pela prop `idioma` de `CheckoutModal` (predefinido `pt`). Para testar o inglês no simulador, abre `/checkout?lang=en` no site, ou muda o `data-src` do iframe antes de abrir o modal. As traduções do simulador estão em `dev/tickett-stub/data/traducoes.php`; as que não vêm da Tickett estão marcadas "inventado: confirmar com a Tickett".

**Quando algo falha:**

| Falha | O que o visitante vê |
| --- | --- |
| A página do evento não responde (limite de 3 segundos), dá erro ou não tem os bilhetes | A última leitura em cache, sem aviso. Sem cache: "A bilheteira não respondeu. Compra diretamente na página da bilheteira." e o botão "Ir para a bilheteira" |
| A venda ainda não abriu | "A venda abre em breve." |
| A venda terminou | "A venda online terminou." |
| O proxy não consegue falar com a Tickett | Página `/checkout` com "A bilheteira não respondeu." e o link "Abre a bilheteira numa página nova" (502) |
| Pagamento com redirecionamento externo (Viva Wallet, myPOS, PayPal, PicPay, PIX, 3DS) | O pagamento segue para o domínio da Tickett, fora do proxy. Sem passos nem compra no site |
| O `ready` não chega em 8 segundos | "A bilheteira está a demorar. Abre-a numa página nova." |
| O JavaScript não corre | "Escolher" vai diretamente à página da Tickett, fora do proxy. Os outros botões de compra vão a /bilhetes |

**Se mudar X na Tickett, preciso de mexer no site?**

| Mudança na Tickett | Mexer no site? |
| --- | --- |
| Preço ou nome de um bilhete | Não. Aparece em até 5 minutos |
| Venda aberta, fechada ou por abrir | Não |
| Estado de um bilhete (à venda, em breve, esgotado) | Não, desde que continue a usar o select de quantidade e o texto "em breve" (ver `docs/contrato-tickett.md`, secção 1) |
| Novo bilhete | Não. Aparece sozinho, pela ordem da página |
| Lote | Não, se vier como "Tier N" ou "Lote N". Outro texto aparece tal como está |
| Taxa de serviço incluída no preço | Sim. `fee_included` em `TickettService::parseProduto` |
| Tema e cores do checkout | Pode ser preciso ajustar `checkout-tema.css` (secção 8) |
| Ids e classes dos passos, do carrinho e da compra | Sim, se mudarem os usados pela ponte (`checkout-ponte.js`) |
| Markup dos bilhetes (`#tickets-form-products`, `table.ticket-box`) | Sim. `TickettService` (parser) |
| `var BASE_URL` ou a forma dos `src`, `href` e `action` | Sim. `CheckoutProxy::reescrever` |
| Novo método de pagamento com redirecionamento externo | Não é código. Sai do proxy. Testar com pagamento real |
| Domínio do checkout | Sim. `tickett.base` em `config/site.local.php` |
| ID do evento | Sim. `config/site.local.php` (secção 7) |
| Cabeçalhos ou política de iframe do checkout | Não. O proxy não passa os cabeçalhos da Tickett |
| Textos do checkout em falta (chave em bruto, ex.: `full_name`) ou em inglês num checkout em pt | Não é código. Pedido à Tickett para a tabela `languages` (texto pt_pt e en_us). Ver "Idioma do checkout" acima |

---


## Problemas comuns

### Os bilhetes não aparecem

1. Olha para o terminal onde corre `bin/serve`. Os erros do `TickettService` aparecem aí, com prefixo `TickettService:`. Exemplos: "sem resposta de", "respondeu HTTP", "sem o bloco de bilhetes".
2. Corre `php dev/tickett-parse-check.php`. Mostra o que o parser lê. Se vier vazio, o layout da página mudou ou o simulador não corre (`dev/tickett-stub/serve`).
3. Testa a página do evento. Em desenvolvimento: `curl -s http://127.0.0.1:8081/2001 | grep -c 'ticket-box'`. Deve dar um número acima de zero.
4. Confirma `tickett.base` e `event_id` em `config/site.php` e em `config/site.local.php`.
5. Olha para a cache: `storage/cache/tickett-2001.json`. Se a Tickett falhar, o site usa esta cache, mesmo que seja antiga. Apaga o ficheiro para forçar a leitura.
6. Para testar uma falha: `curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8081/2001?fail=1"` devolve 500.

### O modal não abre, ou fica a carregar

**Não abre.** O clique leva a outra página. Causa provável: o JavaScript não carregou. Abre a consola do browser (F12) e procura erros de `checkout.js`.

**Fica "A carregar…" e depois aparece "A bilheteira está a demorar".** Causas prováveis, por ordem:

1. **Proxy sem resposta.** Abre `http://127.0.0.1:8080/checkout` diretamente. Se aparecer a página de erro (502), o problema está em `tickett.base` ou na Tickett (ver a tabela "Quando algo falha").
2. **Ponte que não chega a correr.** Sem `ready`, o modal espera 8 s. Confirma que a resposta de `/checkout` traz `checkout-ponte.js` antes de `</body>`, e vê a consola da página `/checkout`.
3. **Demora.** O checkout demora mais de 8 s. Testa com `?slow=1` no simulador.
4. **Origem do iframe.** Se a consola mostrar "Refused to frame…", o site está noutro domínio que não o do proxy. O `/checkout` só deixa enquadrar a partir do próprio domínio.
5. **Teste direto.** Com o simulador a correr, abre `http://127.0.0.1:8080/checkout` no browser. Deves ver a página do checkout, com o tema Festeja.

### O contador do carrinho não atualiza

- O contador só muda quando o checkout envia uma mensagem `cart`. No simulador, adiciona um bilhete e confirma.
- O valor fica em `sessionStorage`, com a chave `festeja-cart-count`. Ao fechar o separador, o contador zera.
- Para repor à mão, na consola do browser: `sessionStorage.removeItem('festeja-cart-count')`.
- Com JavaScript desligado, não há contador.

### Página branca

1. Verifica a sintaxe: `php -l app/Views/Pages/home.php` (usa o ficheiro que alteraste).
2. Olha para o terminal do `bin/serve`.
3. Para forçar a mostrar os erros:

   ```
   php -d display_errors=1 -d error_reporting=-1 -S 127.0.0.1:8080 -t public public/router-dev.php
   ```

4. Causas comuns:
   - JSON inválido: `Content::get` lança "JSON inválido em content/…". Valida o JSON.
   - Artista com `dia` que não existe em `festival.json`.
   - Página legal com um `slug` que não está em `legal.php`.
   - `home.json` ou `parceiros.json` com JSON inválido, ou com uma chave em falta (ex.: `hero.eyebrow`).
   - Componente com nome errado. Em desenvolvimento, a exceção diz "Componente não encontrado".
   - Extensão PHP em falta: `dom` ou `curl` (ver secção 16).
5. Em produção, um componente em falta fica vazio, sem erro visível. Procura no log.
6. Não confundas com "Esta página está em construção": isso acontece quando a rota existe e o ficheiro de página não existe (secção 10).

### A mudança de conteúdo não aparece

- **CSS ou JavaScript.** `asset()` acrescenta `?v=` com a data de modificação do ficheiro, por isso um reload normal costuma bastar. Se não, força o reload (⌘⇧R no Mac) ou desativa a cache nas ferramentas do browser. O mesmo vale para `checkout-tema.css`.
- **JSON.** `content/` é lido a cada pedido, sem cache de conteúdo. Se não aparece, confirma o ficheiro certo. Os textos da home estão em `content/home.json`; os de SEO, em `festival.json` ou em `public/index.php` (secção 14).
- **Bilhetes.** Até 5 minutos de cache. Apaga `storage/cache/tickett-{event_id}.json`.
- **Produção com OPcache.** Alterações em PHP podem não aparecer até o PHP ser reiniciado. Depende do servidor.
- **Sitemap e /llms.txt.** São gerados a cada pedido. Os crawlers é que guardam versões antigas.

---


## Regras que não se quebram

- **Acessibilidade (WCAG 2.2 AA).**
  - Contraste conforme os tokens (secção 9).
  - Foco visível (`--focus`, 3 px). Nunca `outline: none` sem substituto.
  - Fotografias com `alt` descritivo. O alt é gerado: "Nome em palco".
  - Um só `<h1>` por página. O `PageHeader` e o hero já trazem o seu.
  - Movimento reduzido: `prefers-reduced-motion` desliga o hero, a faixa e a pré-visualização do cartaz. Não acrescentes animações sem esta regra.
  - Alvos de toque de pelo menos 24 × 24 px (WCAG 2.2 AA, 2.5.8). Recomendado 44 × 44 px (AAA). A suíte de QA falha abaixo de 24 px e avisa abaixo de 44 px.
  - Funciona a 200% de zoom e a 320 px, sem scroll horizontal.
- **Nada de preços, datas ou horas escritos à mão no HTML.** Os factos vêm de `content/` e os preços da Tickett. As exceções que já existem estão na secção 1. Não acrescentes outras.
- **Cores via tokens.** Nada de hex nos componentes.
- **Textos editoriais da home em `content/home.json`.** Não os copies para o PHP.
- **Textos de interface em `app/Lang/`, nunca escritos no PHP ou no JS.** Usa `t('chave')` e acrescenta a chave nos dois ficheiros (secção 19).
- **Caminhos do site com `Rotas::url()`.** Nada de `/bilhetes` escrito à mão: em inglês o caminho é `/en/tickets`.
- **O checkout da Tickett não se altera.** Decisão de 24/11/2026. O que se muda está no site: proxy, ponte e tema (`checkout-tema.css`, só cor).
- **Testar em 390, 1024 e 1440 px** antes de dar uma alteração como pronta.
- **Sem emojis na interface.** O style guide proíbe.
- **Português de Portugal, a tratar por tu.** Frases curtas. Verbo primeiro nos botões.

---


## Antes de lançar

Estes pontos são provisórios. Não os deixes assim em produção.

| Item | Onde | Estado |
| --- | --- | --- |
| Cartaz de exemplo (cartaz de 2026) | `content/artistas.json` | Substituir pelo cartaz de 2027. As datas de confirmação são inventadas |
| Datas: 23–24 julho ou 30–31 julho | `content/festival.json` | Conflito entre a apresentação e o Instagram. Confirmar com a organização |
| Horas de portas (16h00) | `content/festival.json` (`dias[].portas`) | `rever: true`. Também escrito à mão na FAQ |
| Morada e coordenadas | `content/festival.json` (`local`) | `null`. Página Como chegar com "Morada completa em breve." |
| Idade mínima | `content/festival.json` (`idade_minima`) | `null` |
| Transportes | `content/festival.json` (`transportes`) | Texto por confirmar, marcado com `rever` em cada item. Usado pela home e por /como-chegar |
| FAQ | `content/faq.json` | 11 de 15 perguntas com `rever` (invisível no site). Textos com "até junho" |
| Páginas legais | `content/paginas/*.html` | RASCUNHO (comentário invisível), "contacto a definir", comentários `rever` |
| Cookies do proxy | `content/paginas/cookies.html` e `privacidade.html` | Dizem que o site não usa cookies próprios. O proxy põe cookies `tk_*` no domínio do site. Rever (secção 6) |
| Logótipos e símbolos | `public/assets/img/` | SVG vetorizados de um JPEG. Já reduzidos (0 casas, secção 12). Substituir pelos originais do designer e volta a reduzir |
| Padrão geométrico do fundo | Fora do site | Falta o vetor original |
| Fotografias de artistas | `artistas.json` (`foto`) | Todas `null`. Os cartões mostram um fundo liso com um símbolo da marca |
| Imagem de partilha | `public/assets/img/og.jpg` | Gerado por `dev/og/build.mjs` a partir de `festival.json`. Rever a composição com a data final |
| ID do evento | `config/site.php` (`event_id: 2001`) | Provisório |
| Domínio do checkout | `config/site.local.php` (`tickett.base`) | Produção: `https://checkout.lebillet.eu`. Confirmar com a Tickett |
| Parser e proxy | `TickettService.php`, `CheckoutProxy.php` | Dependem do HTML atual da Tickett. Testar o evento real (parse-check e compra de teste) |
| Pagamentos com redirecionamento externo | Checkout (Tickett) | Viva Wallet, myPOS, PayPal, PicPay, PIX e 3DS: testar um a um com pagamento real. Saem do proxy |
| Termos da Tickett sobre proxy e reescrita das suas páginas | Contrato com a Tickett | Confirmar por escrito antes de lançar |
| Parceiros | `content/parceiros.json` | Vazio. A secção (`Partners`) só aparece com parceiros |
| Analytics | `config/site.php` | Sem ID, sem banner, sem consentimento |
| "Novo" nos artistas | `artistas.json` (`confirmado_em`) | Implementado: 14 dias. Testar com datas reais antes do lançamento |
| Taxa de serviço | `TickettService` (`fee_included`) | O site diz "Acresce a taxa de serviço da Tickett, indicada no checkout.". Confirmar o valor com a Tickett |
| Meta description de /bilhetes | `public/index.php` (linha 102) | Ainda diz "Preços com taxas incluídas", que não é verdade. Código a corrigir. Texto a decidir (secção 14) |
| Textos do checkout em inglês | Checkout (Tickett) | Pedido à Tickett (`docs/checkout-tickett-alteracoes.md`) |
| Suíte de QA | `dev/qa/qa.mjs` | Testa o modelo antigo (embed, `products.json`). Reescrever antes de usar (secção 17) |
| Páginas legais, ao GA4 | `content/paginas/` | Descrevem o estado atual (sem analytics). Atualizar antes de ativar o GA4 (secção 13) |
| Textos do local | Vários | Morada e nome do local vêm de `festival.json`. Os transportes, de `festival.json` (`transportes`) |
