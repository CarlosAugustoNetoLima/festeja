# LineupDay

O cartaz de um dia, com a mesma hierarquia dos cartazes impressos: cabeças de cartaz grandes, depois os restantes nomes em tamanhos decrescentes, separados por pontos laranja.

- Um `LineupDay` por dia, com título `<h3>` e `<time datetime="2027-07-30">`.
- Os nomes são uma lista; cada nome é link para a página do artista. A ordem e o nível (`is-head`, `is-mid`, `is-base`) vêm da organização, nunca alfabéticos por defeito.
- Desktop: os dois dias lado a lado. Mobile: separadores "Sexta" / "Sábado" (`role="tablist"`), com o dia mais próximo aberto.
- Sem imagem de cartaz no lugar do texto.
