# Conteúdo do site

Os textos e factos do festival vivem nestes ficheiros JSON. As páginas leem-nos com `App\Services\Content::get('nome')`. Não copies datas, locais ou preços para o HTML: altera aqui.

**Inglês.** Cada ficheiro tem a sua tradução em `content/en/` (`festival.json`, `home.json`, `faq.json`, `artistas.json`, `paginas/*.html`). Em inglês, o `Content` funde a tradução por cima do português: só os campos traduzíveis, com a mesma estrutura. Nomes, datas, países e preços não se traduzem aqui. `faq.json` funde-se por posição (mesma ordem de grupos e perguntas). `artistas.json` funde-se por `slug`. Ver o guia, secção 19.

**Atenção:** o cartaz em `artistas.json` segue a arte oficial de 2027 (18 nomes, destaques e ordem da arte), mas a divisão por dia e as datas de `confirmado_em` ainda são provisórias.

## `festival.json`

Dados gerais do evento.

| Campo | Descrição |
| --- | --- |
| `nome`, `edicao`, `marca`, `mote` | Nome do festival, ano da edição, organizador e frase de apresentação. |
| `resumo` | Parágrafo de apresentação, usado também na meta description. |
| `chamada` | Frase curta do hero da página inicial. Não repete o local, que já aparece logo abaixo. |
| `datas.inicio`, `datas.fim` | Primeiro e último dia (ISO `AAAA-MM-DD`). |
| `dias[]` | Um objeto por dia: `id` (`dia-1`, `dia-2`), `data` (ISO), `portas` (`HH:MM`). `rever: true` marca o que falta confirmar. |
| `local` | `nome`, `zona`, `morada` e `geo` (coordenadas). `morada` e `geo` estão a `null` até a organização confirmar. |
| `idade_minima` | Idade mínima, ou `null` se ainda não estiver confirmada. |
| `organizador` | Nome e URL do organizador. |
| `producao` | Lista de empresas de produção. |
| `redes` | Links das redes sociais. |
| `contacto.email` | Email de contacto público, ou `null`. |

## `artistas.json`

Lista de artistas do cartaz. Cada entrada é uma página `/artistas/{slug}`.

| Campo | Descrição |
| --- | --- |
| `slug` | Identificador do URL, em minúsculas e sem acentos (`edmazia-mayembe`). Único. |
| `nome` | Nome de apresentação. |
| `pais` | Código ISO de 2 letras (`AO`, `CV`, `GW`, `MZ`, `PT`) ou `null`. |
| `dia` | `dia-1` ou `dia-2`. |
| `dias` | Só existe quando o artista toca em mais de um dia (ex.: `["dia-1","dia-2"]` no Kizomba na Rua). Nesse caso, a entrada aparece só em `dia` e o campo `dias` indica os restantes. |
| `destaque` | `1` = cabeça de cartaz, `2` = médio, `3` = pequeno. |
| `confirmado_em` | Data de confirmação (ISO). Até 14 dias depois, o artista leva o selo "Novo" (cartão e cartaz). Não ordena nada: a ordem é a da lista. |
| `bio` | Biografia, ou `null`. |
| `foto` | Caminho da fotografia, ou `null`. |
| `links` | Objeto com as redes do artista (`{}` se não houver). |

JSON não aceita comentários, por isso as notas ficam aqui.

## `faq.json`

Perguntas frequentes, agrupadas.

```json
{
  "grupos": [
    {
      "id": "bilhetes",
      "titulo": "Bilhetes",
      "perguntas": [
        { "p": "Pergunta", "r": "Resposta curta.", "home": true, "rever": true }
      ]
    }
  ]
}
```

- `id`: identificador do grupo, usado como âncora.
- `p` e `r`: pergunta e resposta, em tom de tratamento por tu.
- `home`: `true` mostra a pergunta na página inicial. Deve haver cinco no total.
- `rever`: `true` marca uma resposta que depende de confirmação da organização. Quando a informação estiver confirmada, retira a marca. A marca não aparece no site: só o `/llms.txt` a usa, e acrescenta "(Informação a confirmar)".

Onde ainda não há facto, a resposta remete para a Tickett ou diz que a informação é publicada até junho.

## `parceiros.json`

Parceiros do festival, agrupados por tema (Produção, Patrocinadores, Media partners, Apoios). A secção aparece na página inicial, antes do fecho, e só existe se houver pelo menos um grupo com parceiros.

Formato de cada grupo:

```json
{"titulo":"Media partners","parceiros":[{"nome":"RTP África","logo":"/assets/img/parceiros/rtp-africa.svg","url":"https://www.rtp.pt/rtpafrica"}]}
```

- `titulo`: nome do grupo (aparece como título).
- `parceiros[].nome`: nome do parceiro. É o `alt` do logótipo.
- `parceiros[].logo`: caminho do logótipo. Usa barra inicial e a pasta `/assets/img/parceiros/` (ficheiro em `public/assets/img/parceiros/`), para que o caminho funcione em qualquer página. Logótipos SVG ou PNG tal como vêm, a 48 px de altura.
- `parceiros[].url`: site do parceiro. Opcional: sem `url`, o logótipo fica sem link.

Parceiros sem `nome` ou sem `logo` são ignorados. Grupos sem parceiros válidos não aparecem. Está vazio (`{"grupos":[]}`) até haver parceiros confirmados.

## `home.json`

Textos editoriais da página inicial: os títulos e as frases das secções, que antes estavam no PHP.

| Campo | Onde aparece |
| --- | --- |
| `hero.eyebrow` | Linha pequena por cima da data no hero. |
| `cartaz.eyebrow`, `cartaz.titulo` | Cabeçalho do cartaz na home, do kit e da página /cartaz. `{edicao}` é trocado pelo ano de `festival.json` (ex.: "Cartaz {edicao}" → "Cartaz 2027"). |
| `bilhetes.eyebrow`, `bilhetes.titulo`, `bilhetes.lead` | Cabeçalho da secção de bilhetes na home. |
| `festival.paises[]` | Países da frase "O festival". `destaque: true` sai a laranja; os outros a ink. A ordem é a da frase. |
| `festival.fecho` | País final da frase (`nome`, em ink) e o resto da frase (`texto`, "no mesmo palco."). |
| `festival.notas[]` | As três notas (`titulo`, `texto`). `rever: true` põe um comentário HTML para a equipa, sem efeito no texto. |
| `visita.eyebrow`, `visita.titulo` | Cabeçalho de "Como chegar" na home. Os transportes estão em `festival.json`. |
| `faq.eyebrow`, `faq.titulo` | Cabeçalho das perguntas frequentes na home. |
| `fecho.eyebrow` | Linha por cima da data no fecho da home ("Vemo-nos em Moscavide"). |

## Transportes (em `festival.json`)

O campo `transportes` é usado pela home (secção "Como chegar") e pela página /como-chegar. Cada item:

```json
{ "icone": "train", "titulo": "Metro", "texto": "Linha Vermelha, estações Moscavide e Olivais", "rever": true }
```

- `icone`: `train`, `bus` ou `car` (ícones de `app/Helpers/Icon.php`).
- `titulo` e `texto`: aparecem como "Metro: Linha Vermelha, estações Moscavide e Olivais".
- `rever: true` marca o item como indicação por confirmar (`data-assumed` no HTML).
