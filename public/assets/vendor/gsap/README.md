# GSAP (vendor)

Biblioteca de animação usada pelo módulo `public/assets/js/movimento.js`, carregada depois do `load` e só sem `prefers-reduced-motion`.

| Ficheiro | Versão | Origem |
| --- | --- | --- |
| `gsap.min.js` | GSAP 3.12.5 | https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js |
| `ScrollTrigger.min.js` | ScrollTrigger 3.12.5 | https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js |
| `ScrollToPlugin.min.js` | ScrollToPlugin 3.12.5 | https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollToPlugin.min.js |

Ficheiros UMD minificados, copiados sem alterações. Expõem `window.gsap`, `window.ScrollTrigger` e `window.ScrollToPlugin`.

Licença: o cabeçalho de cada ficheiro indica "@license Copyright 2024, GreenSock. All rights reserved. Subject to the terms at https://gsap.com/standard-license or for Club GSAP members, the agreement issued with that membership." Ligação: https://gsap.com/standard-license

Para atualizar: descarregar os três ficheiros da mesma versão do pacote `gsap` em `dist/`, substituí-los, atualizar esta tabela e correr `node dev/qa/qa.mjs movimento`.
