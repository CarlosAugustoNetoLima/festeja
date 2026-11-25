# Countdown

Contagem até à abertura de portas, no hero do desktop. Dá urgência à compra.

- Mostra dias, horas e minutos (segundos só distraem). Atualiza a cada minuto.
- É uma lista com `aria-label="Faltam 248 dias, 6 horas e 12 minutos"` atualizado em silêncio; sem `aria-live`.
- No mobile não aparece: a data já está no hero.
- Depois do início do festival é substituído por "Hoje no Festeja" com o horário do dia.
- O valor inicial vem do servidor (HTML completo sem JS); o JS só atualiza.
