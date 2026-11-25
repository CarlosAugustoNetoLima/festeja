# StickyBuyBar

Barra fixa no fundo do ecrã, **só no mobile**, com a data, o preço "desde" e o botão de compra. Garante que o bilhete está sempre a um toque sem repetir o botão em cada secção.

- Aparece quando o hero sai do ecrã e esconde-se enquanto a secção de bilhetes está visível (IntersectionObserver).
- O botão abre o `CheckoutModal` sem filtro de produto.
- Respeita a área segura do iPhone: `padding-bottom: env(safe-area-inset-bottom)`.
- Não empilhar com banner de cookies: o banner aparece primeiro e a barra só depois de o banner ser fechado.
- O conteúdo da página tem padding inferior igual à altura da barra, para não tapar o rodapé.
