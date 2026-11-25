<?php
// Simulador local do checkout Tickett (só desenvolvimento).
// Imita o checkout publicado e mais nada: a página e os fragmentos AJAX são renderizados pelos
// TEMPLATES REAIS de /Users/carloslima/checkout (só leitura), ver lib/bootstrap.php e README.md.
declare(strict_types=1);

date_default_timezone_set('Europe/Lisbon');

const STUB_ROOT = __DIR__;
const STATIC_EXTENSIONS = ['css', 'js', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf'];

require STUB_ROOT . '/lib/bootstrap.php';
require STUB_ROOT . '/lib/sim-data.php';

function send_text(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function is_head_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
}

function send_file(string $file, string $type): never
{
    if (!is_file($file)) {
        send_text(404, 'Não encontrado');
    }
    header('Content-Type: ' . $type);
    header('Cache-Control: no-cache');
    if (!is_head_request()) {
        readfile($file);
    }
    exit;
}

function send_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if (!is_head_request()) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
    exit;
}

function send_html(string $html): never
{
    header('Content-Type: text/html; charset=utf-8');
    if (!is_head_request()) {
        echo $html;
    }
    exit;
}

function load_catalog(): array
{
    $raw = file_get_contents(STUB_ROOT . '/data/catalog.json');
    $catalog = json_decode(str_replace('{base}', 'http://127.0.0.1:8081', $raw), true, 512, JSON_THROW_ON_ERROR);
    return ['version' => 1, 'updated_at' => date('c')] + $catalog;
}

function is_known_event(array $catalog, string $evento): bool
{
    return (string) $catalog['event']['id'] === $evento;
}

/** Ficheiros estáticos do checkout real (public/), só leitura, só extensões de assets, sem traversal. */
function serve_public_asset(string $path): void
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, STATIC_EXTENSIONS, true)) {
        return;
    }
    $real = realpath(CHECKOUT_PUBLIC . $path);
    if ($real === false || !str_starts_with($real, CHECKOUT_PUBLIC . '/') || !is_file($real)) {
        return;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $type = finfo_file($finfo, $real) ?: 'application/octet-stream';
    if ($ext === 'css') {
        $type = 'text/css';
    } elseif ($ext === 'js') {
        $type = 'application/javascript';
    } elseif ($ext === 'json') {
        $type = 'application/json';
    }
    send_file($real, $type . '; charset=' . ($ext === 'css' || $ext === 'js' || $ext === 'json' ? 'utf-8' : 'binary'));
}

/**
 * Página do checkout (GET /{evento}), como o checkout publicado: sem embed nem tema.
 * ?produto=ID_TIPO é lido pelo próprio index.volt (pré-seleção nativa). ?fail=1 responde 500 e ?slow=1 espera 5 s,
 * para testar o 502 e o timeout do proxy do site.
 */
function checkout_page(string $evento): never
{
    $catalog = load_catalog();
    if (!is_known_event($catalog, $evento)) {
        send_text(404, 'Evento não encontrado');
    }
    if (($_GET['slow'] ?? '') === '1') {
        sleep(5);
    }
    if (($_GET['fail'] ?? '') === '1') {
        send_text(500, 'stub_failure');
    }

    $ctx = [
        'evento' => $evento,
        'event' => sim_event($catalog),
        'products' => sim_products($catalog),
        'cart' => sim_cart_get(),
        'deliveries_available' => sim_deliveries(),
        'payments_available' => sim_payments(),
    ];
    send_html(sim_render('index', $ctx, []));
}

/**
 * Fragmentos AJAX (POST /{evento}/{acao}), com a mesma forma que checkout.js espera.
 * Ações não simuladas respondem 501 e ficam documentadas no README.
 */
function checkout_ajax(string $evento, string $acao): never
{
    $catalog = load_catalog();
    if (!is_known_event($catalog, $evento)) {
        send_text(404, 'Evento não encontrado');
    }
    $products = sim_products($catalog);
    $event = sim_event($catalog);
    $ctx = [
        'evento' => $evento,
        'event' => $event,
        'products' => $products,
        'cart' => sim_cart_get(),
        'deliveries_available' => sim_deliveries(),
        'payments_available' => sim_payments(),
    ];

    switch ($acao) {
        case 'products':
            send_html(sim_render('products/group', $ctx, []));
        case 'products/add':
            sim_cart_apply_form($_POST, $products);
            $ctx['cart'] = sim_cart_get();
            send_html(sim_render('cart/resume', $ctx, []));
        case 'products/rem':
            $token = $_POST['token'] ?? '';
            $cart = sim_cart_get();
            $row = is_string($token) ? sim_row_from_token($token, $products) : null;
            if ($row !== null) {
                unset($cart['event'][SIM_EVENT_ID]['products'][$row['id_product']][$row['id_type']]);
                sim_cart_set($cart);
            }
            $ctx['cart'] = sim_cart_get();
            send_html(sim_render('cart/resume', $ctx, []));
        case 'cart/resume':
            send_html(sim_render('cart/resume', $ctx, []));
        case 'cart/set-delivery':
            // Como setDeliveryAction: guarda a entrega no carrinho e devolve cart/resume. É isto que reativa
            // #button-products (o script de cart/resume.volt chama buttonWait), por isso o simulador tem de o fazer.
            $cart = sim_cart_get();
            $cart['delivery_set'] = ['delivery_id' => 6, 'delivery_name' => 'Email', 'delivery_value' => 0];
            sim_cart_set($cart);
            $ctx['cart'] = sim_cart_get();
            send_html(sim_render('cart/resume', $ctx, []));
        case 'login':
            send_html(sim_render('login/site', $ctx, []));
        case 'login/register':
            // Simulado: aceita qualquer registo (sem validar campos nem criar cliente).
            send_json(['success' => true]);
        case 'cart/delivery-payment':
            send_html(sim_render('cart/delivery-payment', $ctx, []));
        case 'cart/set-delivery-payment':
            // SIMULADO: qualquer método escolhido (122 ou 126, ver sim_payments) entrega o formulário de payment/cash.volt
            // (#tickets-form-payment), sem gateway. Não se segue o ramo real de cada id (CartController::setDeliveryPaymentAction).
            send_html(sim_render('payment/cash', $ctx, []));
        case 'payment/proccess':
        case 'cart/finish':
            $ctx['order'] = sim_order($catalog, sim_cart_get());
            send_html(sim_render('delivery/sent', $ctx, []));
        case 'payment/clearCart':
            sim_cart_set(['event' => []]);
            send_html('');
        default:
            send_text(501, 'Ação não simulada no simulador: ' . $acao);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD' && $method !== 'POST') {
    header('Allow: GET, HEAD, POST');
    send_text(405, 'Método não permitido');
}

if (session_status() === PHP_SESSION_NONE) {
    // Como services.php: o parâmetro sid escolhe a sessão (o iframe pode não ter cookie).
    if (isset($_REQUEST['sid']) && is_string($_REQUEST['sid']) && preg_match('/^[a-zA-Z0-9,-]{8,128}$/', $_REQUEST['sid'])) {
        session_id($_REQUEST['sid']);
    }
    session_start();
}

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/([0-9]+)/([a-zA-Z/-]+)$#', $path, $m) === 1 && $method === 'POST') {
    checkout_ajax($m[1], $m[2]);
}
if (preg_match('#^/([0-9]+)$#', $path, $m) === 1 && $method !== 'POST') {
    checkout_page($m[1]);
}

serve_public_asset($path);

send_text(404, 'Não encontrado');
