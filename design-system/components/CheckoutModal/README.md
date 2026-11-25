# CheckoutModal

Janela onde decorre a compra na Tickett, sem sair do site. O conteúdo é o checkout da Tickett num iframe com o tema do Festeja; o modal é só a moldura.

- Abre a partir de qualquer botão "Comprar" com o evento e, opcionalmente, o `product_id` do bilhete escolhido.
- `role="dialog"`, `aria-modal="true"`, `aria-labelledby` no título. Foco no botão Fechar ao abrir, foco preso dentro, `Esc` fecha, o foco volta ao botão que abriu.
- O iframe tem `title="Checkout de bilhetes Tickett"`. Enquanto carrega mostra "A carregar bilhetes…" num `role="status"`.
- Fechar não apaga o carrinho: o iframe fica carregado para voltar ao mesmo passo.
- Desktop: até 1000px de largura, `radius-lg`, `scrim` por trás. Mobile: ecrã inteiro, sem cantos, com o cabeçalho fixo.
- Os passos (Bilhetes → Dados → Pagamento) vêm do checkout; o modal não os duplica.
