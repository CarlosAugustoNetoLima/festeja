# SiteHeader

Header com o logótipo horizontal, a navegação principal e o botão de compra. A partir de 1024px mostra tudo; abaixo disso fica só o logótipo e o botão "Menu", que abre um painel com os mesmos links em alvos de 48px.

- Fixo no topo com `position: sticky`; o conteúdo das âncoras compensa com `scroll-margin-top`.
- A página atual tem `aria-current="page"` (sublinhado em `brand-orange`).
- O botão do menu é um `<button aria-expanded aria-controls>`. O painel prende o foco e fecha com `Esc`.
- Itens: Bilhetes, Cartaz, Informações, Como chegar, Notícias. Não acrescentar mais de seis.
- O logótipo é um link para a página inicial com `alt="Festeja — página inicial"`.
