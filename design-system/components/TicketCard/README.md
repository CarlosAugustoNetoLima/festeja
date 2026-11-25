# TicketCard

Cartão de um tipo de bilhete (dia, passe, VIP) com nome, datas, preço, o que inclui e o botão que abre o `CheckoutModal` já filtrado nesse produto.

- Os dados vêm da Tickett (nome, preço, `product_id`); nunca escrever preços à mão no HTML.
- Usar dentro de uma lista `<ul class="fj-tickets">`, um `<li>` por bilhete.
- Destaque (`fj-ticket--featured` + selo "Mais vendido") em **um** cartão por lista, o que a organização quer vender mais.
- Esgotado: `fj-ticket--soldout`, selo `fj-badge--soldout` com a palavra "Esgotado", preço riscado e botão com `aria-disabled`. Nunca remover o cartão: o visitante tem de perceber que existiu.
- "Últimos bilhetes" só quando a Tickett indicar stock baixo; nunca como truque permanente.
- O preço mostra "35 €" e por baixo "Taxa de serviço incluída" ou o valor da taxa, conforme a Tickett.
- Mobile: cartões empilhados com o destacado primeiro.

Preços e nomes de bilhete da pré-visualização são exemplos.
