<?php
/**
 * Traduções do simulador: imitam a tabela `languages` do checkout real (colunas pt_pt e en_us, lidas por
 * app/languages/geral.php). Chave = texto exato de t._('...') nos templates (multi-linha inclusive) ou valor
 * dinâmico (nome de país, método de pagamento, tipo de bilhete). Se faltar uma chave, o checkout mostra a própria
 * chave, como o real: é isso que o teste de chaves em bruto do QA apanha.
 *
 * Formato: 'chave' => [pt_pt, en_us].
 * Sem marca: texto que já vem no template (a chave está em português ou em inglês) ou num bundle da Tickett.
 * "inventado: confirmar com a Tickett": texto escrito aqui, sem fonte real. Marca a coluna que foi inventada.
 * Não há SQL nem dump da tabela no repositório do checkout (/Users/carloslima/checkout): só as chaves.
 */
return [
    // ---- Chaves em inglês (a coluna en_us é a própria chave; o pt_pt é escrito aqui)
    'Activate your purchase' => ['Ativa a tua compra', 'Activate your purchase'], // pt inventado: confirmar com a Tickett
    'Agree to terms and conditions' => ['Aceito os termos e condições', 'Agree to terms and conditions'], // pt inventado: confirmar com a Tickett
    'Amount paid' => ['Valor pago', 'Amount paid'], // pt inventado: confirmar com a Tickett
    'Apply' => ['Aplicar', 'Apply'], // pt inventado: confirmar com a Tickett
    'Available for printing after payment' => ['Disponível para impressão após o pagamento', 'Available for printing after payment'], // pt inventado: confirmar com a Tickett
    'Buy Map' => ['Comprar mapa', 'Buy Map'], // pt inventado: confirmar com a Tickett
    'Buy here' => ['Comprar aqui', 'Buy here'], // pt inventado: confirmar com a Tickett
    'Cash' => ['Dinheiro', 'Cash'], // pt inventado: confirmar com a Tickett
    'Change of Money' => ['Troco', 'Change of Money'], // pt inventado: confirmar com a Tickett
    'Choose delivery method' => ['Escolhe o método de entrega', 'Choose delivery method'], // pt inventado: confirmar com a Tickett
    'Choose products' => ['Escolhe os bilhetes', 'Choose products'], // pt inventado: confirmar com a Tickett
    'Choose your products' => ['Escolhe os teus bilhetes', 'Choose your products'], // pt inventado: confirmar com a Tickett
    "Click here to complete the\n\t\t\t\tprocess" => ['Clica aqui para concluir o processo', "Click here to complete the\n\t\t\t\tprocess"], // pt inventado: confirmar com a Tickett
    'Confirm email' => ['Confirmar email', 'Confirm email'], // pt inventado: confirmar com a Tickett
    'Confirm mobile' => ['Confirmar telemóvel', 'Confirm mobile'], // pt inventado: confirmar com a Tickett
    'Continue' => ['Continuar', 'Continue'], // pt: "Continuar" também aparece nos bundles da Tickett (public/126.7674); confirmar
    'Country' => ['País', 'Country'], // pt inventado: confirmar com a Tickett
    'Courtesy' => ['Cortesia', 'Courtesy'], // pt inventado: confirmar com a Tickett
    'Coupon' => ['Cupão', 'Coupon'], // pt inventado: confirmar com a Tickett
    'Credit card' => ['Cartão de crédito', 'Credit card'], // pt inventado: confirmar com a Tickett (nome do método 126 no simulador)
    'Emails do not match' => ['Os emails não coincidem', 'Emails do not match'], // pt inventado: confirmar com a Tickett
    'Enter the Whatsapp number' => ['Introduz o número de WhatsApp', 'Enter the Whatsapp number'], // pt inventado: confirmar com a Tickett
    'Enter the Whatsapp number to which tickets will be sent' => ['Introduz o número de WhatsApp para onde serão enviados os bilhetes', 'Enter the Whatsapp number to which tickets will be sent'], // pt inventado: confirmar com a Tickett
    'Enter the email' => ['Introduz o email', 'Enter the email'], // pt inventado: confirmar com a Tickett
    'Enter the email to which tickets will be sent' => ['Introduz o email para onde serão enviados os bilhetes', 'Enter the email to which tickets will be sent'], // pt inventado: confirmar com a Tickett
    'Enter the mobile number' => ['Introduz o número de telemóvel', 'Enter the mobile number'], // pt inventado: confirmar com a Tickett
    'Enter the mobile number to which tickets will be sent' => ['Introduz o número de telemóvel para onde serão enviados os bilhetes', 'Enter the mobile number to which tickets will be sent'], // pt inventado: confirmar com a Tickett
    'Hello' => ['Olá', 'Hello'], // pt inventado: confirmar com a Tickett
    'Interest' => ['Juros', 'Interest'], // pt inventado: confirmar com a Tickett
    'Login' => ['Iniciar sessão', 'Login'], // pt inventado: confirmar com a Tickett
    'Loading...' => ['A carregar...', 'Loading...'], // pt inventado: confirmar com a Tickett
    'MB Way' => ['MB Way', 'MB Way'], // nome de marca (sem tradução); nome do método 122 no simulador
    'Mobile Number' => ['Telemóvel', 'Mobile Number'], // pt inventado: confirmar com a Tickett
    'Mobile Number Confirm' => ['Confirmar telemóvel', 'Confirm mobile'], // pt inventado: confirmar com a Tickett (en curto: a 390 px o placeholder não cabia)
    'Mobile number' => ['Telemóvel', 'Mobile number'], // pt inventado: confirmar com a Tickett
    'Next step' => ['Próximo passo', 'Next step'], // pt inventado: confirmar com a Tickett
    'Payment' => ['Pagamento', 'Payment'], // pt inventado: confirmar com a Tickett
    'Payment Mode' => ['Modo de pagamento', 'Payment Mode'], // pt inventado: confirmar com a Tickett
    'Perform the process now, thus avoiding purchase cancellation' => ['Conclui o processo agora para evitar o cancelamento da compra', 'Perform the process now, thus avoiding purchase cancellation'], // pt inventado: confirmar com a Tickett
    'Product' => ['Produto', 'Product'], // pt inventado: confirmar com a Tickett
    'Promotional Code' => ['Código promocional', 'Promotional Code'], // pt inventado: confirmar com a Tickett
    'Purchase Completed' => ['Compra concluída', 'Purchase Completed'], // pt inventado: confirmar com a Tickett
    'Return' => ['Voltar', 'Return'], // pt inventado: confirmar com a Tickett
    'Retype Whatsapp number for confirmation' => ['Repete o número de WhatsApp para confirmação', 'Retype Whatsapp number for confirmation'], // pt inventado: confirmar com a Tickett
    'Retype email for confirmation' => ['Repete o email para confirmação', 'Retype email for confirmation'], // pt inventado: confirmar com a Tickett
    'Retype mobile number for confirmation' => ['Repete o número de telemóvel para confirmação', 'Retype mobile number for confirmation'], // pt inventado: confirmar com a Tickett
    'SIM' => ['SIM', 'YES'], // en inventado: confirmar com a Tickett
    'Sales start in' => ['As vendas começam em', 'Sales start in'], // pt inventado: confirmar com a Tickett
    'Seats' => ['Lugares', 'Seats'], // pt inventado: confirmar com a Tickett
    'Terms and Conditions' => ['Termos e condições', 'Terms and Conditions'], // pt inventado: confirmar com a Tickett
    'The numbers do not match' => ['Os números não coincidem', 'The numbers do not match'], // pt inventado: confirmar com a Tickett
    "The presentation of supporting documents is required to activate your purchase and receive your\n\t\t\ttickets" => ['É necessário apresentar os documentos comprovativos para ativar a compra e receber os bilhetes', "The presentation of supporting documents is required to activate your purchase and receive your\n\t\t\ttickets"], // pt inventado: confirmar com a Tickett
    'Tickets' => ['Bilhetes', 'Tickets'], // pt inventado: confirmar com a Tickett
    'Total' => ['Total', 'Total'], // pt e en inventados: confirmar com a Tickett
    'Tier' => ['Lote', 'Tier'], // pt inventado: confirmar com a Tickett (o parser do site lê "Tier N" e "Lote N")
    'Total value of products' => ['Valor total dos bilhetes', 'Total value of products'], // pt inventado: confirmar com a Tickett
    'Type' => ['Tipo', 'Type'], // pt inventado: confirmar com a Tickett
    'Wait' => ['Aguarda', 'Wait'], // pt inventado: confirmar com a Tickett
    'Whatsapp number' => ['Número de WhatsApp', 'Whatsapp number'], // pt inventado: confirmar com a Tickett
    'Your Order' => ['O teu pedido', 'Your Order'], // pt inventado: confirmar com a Tickett
    'You have not yet added any products to your order' => ['Ainda não adicionaste bilhetes ao pedido', 'You have not yet added any products to your order'], // pt inventado: confirmar com a Tickett

    // ---- Chaves em português (a coluna pt_pt é a própria chave; o en_us é escrito aqui)
    'Aceito os termos e condições e a política de privacidade e segurança da Lebillet, LDa' => ['Aceito os termos e condições e a política de privacidade e segurança da Lebillet, LDa', 'I accept the terms and conditions and the privacy and security policy of Lebillet, Lda'], // en inventado: confirmar com a Tickett
    "Autorizo que os meus dados, nome e email sejam utilizados pela Lebillet e entidade(s)\n\t\t\t\tPromotora(s) desta compra para fins promocionais." => ["Autorizo que os meus dados, nome e email sejam utilizados pela Lebillet e entidade(s)\n\t\t\t\tPromotora(s) desta compra para fins promocionais.", "I authorise Lebillet and the promoter(s) of this purchase to use my data, name and email for promotional purposes."], // en inventado: confirmar com a Tickett
    'Apenas o número' => ['Apenas o número', 'Number only'], // en inventado: confirmar com a Tickett
    'Emitir Fatura' => ['Emitir Fatura', 'Issue invoice'], // en inventado: confirmar com a Tickett
    'Enviar fatura para o e-mail' => ['Enviar fatura para o e-mail', 'Send invoice to email'], // en inventado: confirmar com a Tickett
    'ID Fiscal' => ['ID Fiscal', 'Tax ID'], // en inventado: confirmar com a Tickett
    'Itens' => ['Itens', 'Items'], // en inventado: confirmar com a Tickett
    'Loja' => ['Loja', 'Shop'], // en inventado: confirmar com a Tickett
    'Morada' => ['Morada', 'Address'], // en inventado: confirmar com a Tickett
    'NIF' => ['NIF', 'Tax number (NIF)'], // en inventado: confirmar com a Tickett
    'NÃO' => ['NÃO', 'NO'], // en inventado: confirmar com a Tickett
    'Nome' => ['Nome', 'Name'], // en inventado: confirmar com a Tickett
    'Seu carrinho foi zerado, pois o tempo limite para a compra acabou.' => ['Seu carrinho foi zerado, pois o tempo limite para a compra acabou.', 'Your cart was emptied because the time limit for the purchase has expired.'], // en inventado: confirmar com a Tickett
    'Tempo Esgotado' => ['Tempo Esgotado', 'Time expired'], // en inventado: confirmar com a Tickett
    'Tempo para finalizar compra' => ['Tempo para finalizar compra', 'Time to complete purchase'], // en inventado: confirmar com a Tickett

    // ---- Chaves snake_case (identificadores: nenhuma coluna tem fonte real aqui)
    'Event' => ['Evento', 'Event'], // pt e en inventados: confirmar com a Tickett
    'Date' => ['Data', 'Date'], // pt e en inventados: confirmar com a Tickett
    'Delivery' => ['Entrega', 'Delivery'], // pt e en inventados: confirmar com a Tickett
    'Email' => ['Email', 'Email'], // pt e en inventados: confirmar com a Tickett
    'CPF' => ['CPF', 'Tax ID (CPF)'], // pt e en inventados: confirmar com a Tickett
    'Password' => ['Palavra-passe', 'Password'], // pt inventado: confirmar com a Tickett
    'password' => ['Palavra-passe', 'Password'], // pt e en inventados: confirmar com a Tickett
    'address_1' => ['Morada', 'Address line 1'], // pt e en inventados: confirmar com a Tickett
    'address_2' => ['Complemento da morada', 'Address line 2'], // pt e en inventados: confirmar com a Tickett
    'cel' => ['Telemóvel', 'Mobile'], // pt e en inventados: confirmar com a Tickett
    'city' => ['Cidade', 'City'], // pt e en inventados: confirmar com a Tickett
    'country' => ['País', 'Country'], // pt e en inventados: confirmar com a Tickett
    'delivery' => ['Entrega', 'Delivery'], // pt e en inventados: confirmar com a Tickett
    'email' => ['Email', 'Email'], // pt e en inventados: confirmar com a Tickett
    'email_address' => ['Email', 'Email address'], // pt e en inventados: confirmar com a Tickett
    'email_address_confirm' => ['Confirmar email', 'Confirm email address'], // pt e en inventados: confirmar com a Tickett
    'email_confirm' => ['Confirmar email', 'Confirm email'], // pt e en inventados: confirmar com a Tickett
    'fee' => ['Taxa', 'Fee'], // pt e en inventados: confirmar com a Tickett
    'fee_tax' => ['IVA da taxa', 'Fee VAT'], // pt e en inventados: confirmar com a Tickett
    'forgot_password' => ['Esqueceste-te da palavra-passe?', 'Forgot your password?'], // pt e en inventados: confirmar com a Tickett
    'full_name' => ['Nome', 'First name'], // pt e en inventados: confirmar com a Tickett
    'full_name_apelido' => ['Apelido', 'Last name'], // pt e en inventados: confirmar com a Tickett
    'have_a_account' => ['Já tens conta?', 'Already have an account?'], // pt e en inventados: confirmar com a Tickett
    'log_in' => ['Iniciar sessão', 'Log in'], // pt e en inventados: confirmar com a Tickett
    'logout' => ['Terminar sessão', 'Log out'], // pt e en inventados: confirmar com a Tickett
    'new_user' => ['Novo utilizador?', 'New user?'], // pt e en inventados: confirmar com a Tickett
    'parental_rating' => ['Classificação etária', 'Age rating'], // pt e en inventados: confirmar com a Tickett
    'region' => ['Distrito ou região', 'Region'], // pt e en inventados: confirmar com a Tickett
    'remember' => ['Lembrar-me', 'Remember me'], // pt e en inventados: confirmar com a Tickett
    'remember_password' => ['Recuperar a palavra-passe', 'Recover password'], // pt e en inventados: confirmar com a Tickett
    'reservation' => ['Reserva', 'Reservation'], // pt e en inventados: confirmar com a Tickett
    'send_message_ticket' => ['Os bilhetes são enviados para o email e o telemóvel indicados.', 'Tickets will be sent to the email address and mobile number you enter.'], // pt e en inventados: confirmar com a Tickett
    'sign_up' => ['Criar conta', 'Sign up'], // pt e en inventados: confirmar com a Tickett
    'soldout' => ['Esgotado', 'Sold out'], // pt e en inventados: confirmar com a Tickett
    'tax' => ['IVA', 'Tax'], // pt e en inventados: confirmar com a Tickett
    'total' => ['Total', 'Total'], // pt e en inventados: confirmar com a Tickett
    'zip' => ['Código postal', 'Postal code'], // pt e en inventados: confirmar com a Tickett

    // ---- Valores dinâmicos (dados de catálogo e de países, lidos com t._(variável))
    'Bilhete' => ['Bilhete', 'Ticket'], // pt e en inventados: confirmar com a Tickett (type_name do catálogo do simulador)
    'Portugal' => ['Portugal', 'Portugal'], // pt e en inventados: confirmar com a Tickett (nomes de país do simulador)
    'Angola' => ['Angola', 'Angola'], // pt e en inventados: confirmar com a Tickett
    'Cabo Verde' => ['Cabo Verde', 'Cape Verde'], // pt e en inventados: confirmar com a Tickett
    'Guiné-Bissau' => ['Guiné-Bissau', 'Guinea-Bissau'], // pt e en inventados: confirmar com a Tickett
    'Moçambique' => ['Moçambique', 'Mozambique'], // pt e en inventados: confirmar com a Tickett
    'São Tomé e Príncipe' => ['São Tomé e Príncipe', 'São Tomé and Príncipe'], // pt e en inventados: confirmar com a Tickett
    'Brasil' => ['Brasil', 'Brazil'], // pt e en inventados: confirmar com a Tickett
    'Espanha' => ['Espanha', 'Spain'], // pt e en inventados: confirmar com a Tickett
    'França' => ['França', 'France'], // pt e en inventados: confirmar com a Tickett
    'Reino Unido' => ['Reino Unido', 'United Kingdom'], // pt e en inventados: confirmar com a Tickett
];
