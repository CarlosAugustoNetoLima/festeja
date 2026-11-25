# Imagens de marca (dev/og)

Gera a imagem de partilha e o ícone de ecrã inicial a partir de um HTML. Só para desenvolvimento: não entra no site.

- `og.html`: a composição. 1200 × 630, fundo creme, data em Rubik Dirt laranja, nome do festival e local, o logótipo vertical e três símbolos da marca, na forma original (sem arcos nem anéis). Abre-o no browser para ver.
- `build.mjs`: abre `og.html` no Chrome headless (CDP, com `dev/qa/lib/cdp.mjs`), põe a data, o nome e o local de `content/festival.json` e grava:
  - `public/assets/img/og.jpg`: 1200 × 630, JPEG qualidade 85;
  - `public/assets/img/apple-touch-icon.png`: 180 × 180, fundo creme, mandala ao centro.

## Como correr

```sh
node dev/og/build.mjs
```

Precisa de Google Chrome (`/Applications/Google Chrome.app`) e de rede, para as fontes do Google. Não precisa de `npm install`.

Depois de correr, abre `public/assets/img/og.jpg` e confirma a data e o local. Se o Chrome não arrancar, define `QA_CHROME` com o caminho do binário (o mesmo que o QA usa).

## Quando correr

- Depois de mudares a data, as horas do festival ou o local em `content/festival.json`.
- Depois de mudares o desenho em `og.html`.

O `favicon.svg` não é gerado aqui: vem de `public/assets/img/simbolos/icone-01.svg` com `dev/svg/round.mjs` (ver guia, secção 12).
