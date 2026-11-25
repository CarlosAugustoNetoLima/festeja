<?php
/**
 * Dados simulados para os templates reais. Sem DB: o formato segue o que os templates leem
 * (app/views/products/group.volt, index.volt, partials/header.volt) e não o SQL de ProductsLib.
 */
declare(strict_types=1);

const SIM_EVENT_ID = 2001;

function sim_crypt(): Phalcon\Encryption\Crypt
{
    $crypt = new Phalcon\Encryption\Crypt();
    $crypt->setKey('festeja-simulador-dev');
    return $crypt;
}

/** Objeto do evento: fnac=0, sem banda, sem mapa de assentos (group.volt normal). */
function sim_event(array $catalog): stdClass
{
    $ev = $catalog['event'];
    $place = (object) [
        'name' => 'Recinto Festeja',
        'location' => '',
        'Cities' => (object) ['name' => 'Lisboa'],
        'Regions' => (object) ['acronym' => 'LX'],
    ];
    return (object) [
        'id' => (int) $ev['id'],
        'name' => $ev['name'],
        'description' => 'Festival Festeja 2027',
        'style' => '',
        'tag_metapixel' => '',
        'tag_googletag' => '',
        'fnac' => '0',
        'seats_event' => null,
        'seats_account' => 0,
        'versao_mapa' => '0',
        'date_sales_start' => '2026-01-01 00:00:00',
        'date_sales_end' => '2027-07-22 23:59:00',
        'date_opening' => '2027-07-23 12:00:00',
        'label_date' => '',
        'ticket_sub_name' => '',
        'parental_rating' => '',
        'EventsImages' => [(object) ['name' => 'festeja-2027.jpg']],
        'Places' => $place,
    ];
}

/** Linhas no formato de ProductsLib (campos que products/group.volt lê). */
function sim_products(array $catalog): array
{
    $rows = [];
    $products = $catalog['products'];
    usort($products, fn ($a, $b) => $a['sort'] <=> $b['sort']);
    foreach ($products as $p) {
        $batch = $p['batch'];
        $lotNumber = $batch['number'] ?? 1;
        $status = $p['status'];
        $rows[] = [
            'id_event' => (int) $catalog['event']['id'],
            'id_product' => (int) $p['id'],
            'id_type' => 1,
            'id_lot' => (int) $p['id'] * 10 + $lotNumber,
            'id_genre' => 0,
            'id_maps_sector' => 0,
            'seats_channel' => '',
            'seats_section' => '',
            'name' => $p['name'],
            'description' => $p['description'],
            'genre_name' => '',
            'type_name' => 'Bilhete',
            'lot_name' => $batch['name'] ?? '',
            'lot_number' => $lotNumber,
            'price' => (float) $p['price'],
            'fee' => (float) $p['fee'],
            'fraction' => 1,
            'purchase_limit' => 10,
            'available' => $status === 'sold_out' ? 0 : ($status === 'low' ? 3 : 10),
            'sales_product_qty' => 0,
            'sales_limit_product' => 0,
            'msg' => $status === 'sold_out' ? 'soldout' : '',
            'hidden' => 0,
            'lot_hidden' => 0,
            'lots_soldout' => 0,
        ];
    }
    return $rows;
}

/**
 * Entrega disponível, no formato do fallback de CartController::deliveryPaymentAction (sem linhas na BD):
 * arrays com delivery_id, delivery_name, title, value, value_tax (cart/delivery.volt). Email, sem custo.
 */
function sim_deliveries(): array
{
    return [[
        'delivery_id' => 6,
        'delivery_name' => 'Email',
        'title' => null,
        'resume' => null,
        'description' => null,
        'value' => 0,
        'value_tax' => 0,
        'qty' => 1,
    ]];
}

/**
 * Pagamento disponível: objetos EventsPaymentType/SalesPointsPaymentType com PaymentTypes.name (cart/payment.volt).
 * No canal online (terminal 1) o <select> é d-none e só aparecem os tiles com radio de cart/payment.volt, e esses
 * tiles só existem para os ids 8, 121, 122, 126 e 127. Por isso o simulador devolve:
 * - 122: tiles "MB Way" e "Multibanco" (o template põe os dois com o id 122);
 * - 126: tile de cartão (groupcard.png).
 * Dinheiro (1) não entra: setDeliveryPaymentAction recusa-o no canal online (CartController, "Selecione a forma correta").
 */
function sim_payments(): array
{
    return [
        (object) ['id_payment_type' => 122, 'PaymentTypes' => (object) ['id' => 122, 'name' => 'MB Way']],
        (object) ['id_payment_type' => 126, 'PaymentTypes' => (object) ['id' => 126, 'name' => 'Credit card']],
    ];
}

/**
 * Países do select (login/site.volt): objetos com code (ISO3, como o real: $val->code == 'PRT') e name.
 * Mínimo do simulador; o nome é a chave de t._(val.name), por isso traduz-se em data/traducoes.php.
 */
function sim_countries(): array
{
    return [
        (object) ['code' => 'PRT', 'name' => 'Portugal'],
        (object) ['code' => 'AGO', 'name' => 'Angola'],
        (object) ['code' => 'CPV', 'name' => 'Cabo Verde'],
        (object) ['code' => 'GNB', 'name' => 'Guiné-Bissau'],
        (object) ['code' => 'MOZ', 'name' => 'Moçambique'],
        (object) ['code' => 'STP', 'name' => 'São Tomé e Príncipe'],
        (object) ['code' => 'BRA', 'name' => 'Brasil'],
        (object) ['code' => 'ESP', 'name' => 'Espanha'],
        (object) ['code' => 'FRA', 'name' => 'França'],
        (object) ['code' => 'GBR', 'name' => 'Reino Unido'],
    ];
}

/** Cidades do select, por país (o JS filtra por item.code, igual ao real). Mínimo do simulador. */
function sim_cities(): array
{
    $cidades = [
        'PRT' => ['Lisboa', 'Porto', 'Coimbra', 'Braga'],
        'AGO' => ['Luanda', 'Benguela'],
        'CPV' => ['Praia', 'Mindelo'],
        'GNB' => ['Bissau'],
        'MOZ' => ['Maputo', 'Beira'],
        'STP' => ['São Tomé'],
        'BRA' => ['São Paulo', 'Rio de Janeiro'],
        'ESP' => ['Madrid', 'Barcelona'],
        'FRA' => ['Paris', 'Lyon'],
        'GBR' => ['Londres', 'Manchester'],
    ];
    $saida = [];
    foreach ($cidades as $codigo => $nomes) {
        foreach ($nomes as $nome) {
            $saida[] = (object) ['code' => $codigo, 'name' => $nome];
        }
    }
    return $saida;
}

/** Carrinho em sessão, mesmo formato de $cart['event'][id]['products'][id_product][id_type][0]['qty']. */
function sim_cart_get(): array
{
    return sim_cart_totals($_SESSION['sim']['cart'] ?? ['event' => []]);
}

/**
 * Totais calculados ao ler (como CartLib: valor dos bilhetes + taxa por linha). Sem impostos
 * nem descontos (cupões não simulados). As chaves são as que cart/resume.volt e payment.volt leem.
 */
function sim_cart_totals(array $cart): array
{
    $total = 0.0;
    $fees = 0.0;
    $pieces = 0;
    foreach ($cart['event'] as $eid => $ev) {
        $evProduct = 0.0;
        $evFee = 0.0;
        foreach ($ev['products'] ?? [] as $idp => $types) {
            foreach ($types as $idt => $seats) {
                foreach ($seats as $ids => $tmp) {
                    $q = (int) $tmp['qty'];
                    $cart['event'][$eid]['products'][$idp][$idt][$ids]['tax'] = 0;
                    $cart['event'][$eid]['products'][$idp][$idt][$ids]['discount'] = 0;
                    $evProduct += $q * (float) $tmp['price'];
                    $evFee += $q * (float) ($tmp['fee'] ?? 0);
                    $pieces += $q;
                }
            }
        }
        // array_replace e não +=: o carrinho guardado em sessão já traz estas chaves da chamada anterior (total a 0).
        $cart['event'][$eid] = array_replace($cart['event'][$eid], [
            'event_name' => 'Festival Festeja 2027',
            'piece_value' => $pieces,
            'value_product' => round($evProduct, 2),
            'value_product_tax' => 0,
            'value_fee' => round($evFee, 2),
            'value_fee_tax' => 0,
            'value_fee_org' => round($evFee, 2),
            'value_fee_org_tax' => 0,
            'value_interest' => 0,
        ]);
        $total += $evProduct + $evFee;
        $fees += $evFee;
    }
    // Totais calculados sobrescrevem; os restantes campos só entram se ainda não existirem (fatura, payment_set...).
    $cart = array_replace($cart, [
        'value_total' => round($total, 2),
        'value_fee' => round($fees, 2),
        'value_fee_tax' => 0,
        'value_fee_org' => 0,
        'value_fee_org_tax' => 0,
        'value_interest' => 0,
        'value_delivery' => 0,
        'piece_value' => $pieces,
    ]);
    $cart += [
        'fatura' => null,
        'coupon' => null,
        'installments' => 1,
        'payment_set' => null,
        'customer' => null,
        'delivery_set' => ['delivery_value' => 0, 'delivery_name' => ''],
    ];
    return $cart;
}

function sim_cart_set(array $cart): void
{
    $_SESSION['sim']['cart'] = $cart;
}

/** Token de produto (mesmo formato do group.volt): evento#lote#produto#tipo#numero_do_lote. */
function sim_token(array $row): string
{
    return sim_crypt()->encryptBase64(
        $row['id_event'] . '#' . $row['id_lot'] . '#' . $row['id_product'] . '#' . $row['id_type'] . '#' . $row['lot_number']
    );
}

/** Decodifica o token e devolve a linha de produto correspondente, ou null. */
function sim_row_from_token(string $token, array $products): ?array
{
    $plain = sim_crypt()->decryptBase64($token);
    if (!is_string($plain)) {
        return null;
    }
    $parts = explode('#', $plain);
    if (count($parts) !== 5) {
        return null;
    }
    foreach ($products as $row) {
        if ((string) $row['id_event'] === $parts[0] && (string) $row['id_lot'] === $parts[1]
            && (string) $row['id_product'] === $parts[2] && (string) $row['id_type'] === $parts[3]) {
            return $row;
        }
    }
    return null;
}

/** Quantidade de cada linha: product[token]=qty do formulário #tickets-form-products. */
function sim_cart_apply_form(array $form, array $products): array
{
    $cart = sim_cart_get();
    $eventId = SIM_EVENT_ID;
    foreach ($form['product'] ?? [] as $token => $qty) {
        $row = sim_row_from_token((string) $token, $products);
        if ($row === null) {
            continue;
        }
        $qty = max(0, (int) $qty);
        if ($qty === 0) {
            unset($cart['event'][$eventId]['products'][$row['id_product']][$row['id_type']]);
            continue;
        }
        $cart['event'][$eventId]['products'][$row['id_product']][$row['id_type']] = [[
            'qty' => $qty,
            'id_lot' => $row['id_lot'],
            'id_product' => $row['id_product'],
            'id_type' => $row['id_type'],
            'id_genre' => $row['id_genre'],
            'genre_name' => $row['genre_name'],
            'type_name' => $row['type_name'],
            'lot_number' => $row['lot_number'],
            'price' => $row['price'],
            'fee' => $row['fee'],
            'name' => $row['name'],
        ]];
    }
    sim_cart_set($cart);
    return $cart;
}

/**
 * Encomenda simulada para delivery/sent.volt (página de conclusão, .cart-finish).
 * Pagamento simulado: sem gateway, id_orders_status 4 (não é 10 = em análise).
 */
function sim_order(array $catalog, array $cart): stdClass
{
    $event = sim_event($catalog);
    $products = sim_products($catalog);
    $items = [];
    $total = 0.0;
    foreach ($cart['event'][SIM_EVENT_ID]['products'] ?? [] as $idProduct => $types) {
        foreach ($types as $idType => $lines) {
            $line = $lines[0];
            $row = null;
            foreach ($products as $p) {
                if ((int) $p['id_product'] === (int) $idProduct && (int) $p['id_type'] === (int) $idType) {
                    $row = $p;
                }
            }
            $items[] = (object) [
                'quantity' => (int) $line['qty'],
                'price' => (float) $line['price'],
                'Products' => (object) ['name' => $row['name'] ?? '', 'physical' => 0],
                'Events' => $event,
            ];
            $total += (int) $line['qty'] * (float) $line['price'];
        }
    }
    return (object) [
        'id' => 1001,
        'id_orders_status' => 4,
        'value_total' => round($total, 2),
        'Customers' => (object) ['name' => 'Cliente Simulado', 'email' => 'cliente@example.com'],
        'Sales' => [(object) ['SalesItems' => $items]],
    ];
}
