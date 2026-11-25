<?php
/**
 * Simulador do checkout Tickett: renderiza os TEMPLATES REAIS de /Users/carloslima/checkout
 * (só leitura) com Phalcon Volt e um DI simulado. Só desenvolvimento.
 *
 * Shims (documentados em dev/tickett-stub/README.md):
 * - Phalcon\Mvc\User\Component não existe no Phalcon 5 (é a base de Formatdates.php); declarada aqui vazia.
 * - CartLib (app/library, depende da BD) só é instanciado por cart/payment.volt sem o usar; declarada aqui vazia.
 * - Sem DB: $event, $products, $cart e $coupon são construídos em lib/sim-data.php.
 */
declare(strict_types=1);

namespace Phalcon\Mvc\User {
    if (!class_exists(Component::class)) {
        class Component
        {
        }
    }
}

namespace {

if (!class_exists('CartLib', false)) {
    final class CartLib
    {
    }
}

const CHECKOUT_ROOT = '/Users/carloslima/checkout';
const CHECKOUT_APP = CHECKOUT_ROOT . '/app';
const CHECKOUT_PUBLIC = CHECKOUT_ROOT . '/public';
const STUB_LIB = __DIR__;
const STUB_CACHE = __DIR__ . '/../cache';
const STUB_ORIGIN = 'http://127.0.0.1:8081';
const STUB_ORIGINS = ['http://127.0.0.1:8080', 'http://localhost:8080'];

// Mesmas constantes que public/index.php define antes de carregar os templates.
if (!defined('BASE_PATH')) {
    define('BASE_PATH', CHECKOUT_ROOT);
}
if (!defined('APP_PATH')) {
    define('APP_PATH', CHECKOUT_APP);
}
// bilhetes.festeja.pt -> lebillet_pt (public/index.php)
if (!defined('COMPANY')) {
    define('COMPANY', 'lebillet_pt');
}

require_once CHECKOUT_APP . '/functions/formats.php';
require_once CHECKOUT_APP . '/library/Formatdates.php';

if (!is_dir(STUB_CACHE . '/volt')) {
    mkdir(STUB_CACHE . '/volt', 0775, true);
}

/** Avisos e notices dos templates vão para cache/render.log (não para a página). */
set_error_handler(function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    $short = str_replace(CHECKOUT_ROOT . '/', '', $file);
    file_put_contents(STUB_CACHE . '/render.log', "[$no] $str ($short:$line)\n", FILE_APPEND);
    return true;
});

function sim_config(): Phalcon\Config\Config
{
    $config2 = [];
    require CHECKOUT_APP . '/config/config_company/' . COMPANY . '.php';
    $global = [
        'application' => [
            'baseUri' => STUB_ORIGIN . '/',
            'viewsDir' => APP_PATH . '/views/',
            'cacheViews' => STUB_CACHE . '/volt/',
        ],
    ];
    return new Phalcon\Config\Config(array_replace_recursive($config2, $global));
}

/** Idiomas que o simulador conhece: pt_pt (predefinido, config_company default_language) e en_us (?l=en_us). */
const SIM_IDIOMAS = ['pt_pt' => 0, 'en_us' => 1];

/**
 * Idioma como o service 't' de services.php: ?l= grava-se na sessão; sem ?l= usa-se o da sessão ou pt_pt.
 * Outros valores de ?l= são ignorados (o real cai em en_us; o simulador só tem os dois idiomas do site).
 */
function sim_idioma(): string
{
    $pedido = $_REQUEST['l'] ?? null;
    if (is_string($pedido) && isset(SIM_IDIOMAS[$pedido])) {
        $_SESSION['sim']['language'] = $pedido;
    }
    return $_SESSION['sim']['language'] ?? 'pt_pt';
}

/**
 * Traduções do idioma: data/traducoes.php ('chave' => [pt_pt, en_us]). Chave em falta → o template mostra a chave,
 * como o checkout real quando a BD não tem a string.
 */
function sim_messages(string $idioma): array
{
    $coluna = SIM_IDIOMAS[$idioma] ?? 0;
    $messages = [];
    foreach (require STUB_LIB . '/../data/traducoes.php' as $chave => $par) {
        $messages[$chave] = $par[$coluna];
    }
    return $messages;
}

/** Sessão simulada em $_SESSION['sim'] (session_start é feito pelo router). */
final class SimSession
{
    public function get(string $key, $default = null)
    {
        return $_SESSION['sim'][$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION['sim'][$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION['sim'][$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION['sim'][$key]);
    }
}

final class SimRouter
{
    public function __construct(private string $evento)
    {
    }

    public function getRewriteUri(): string
    {
        return '/' . $this->evento;
    }
}

/**
 * DI com os serviços que os templates usam. $ctx: evento (string), event, products, cart (e order, na conclusão).
 */
function sim_di(array $ctx): Phalcon\Di\FactoryDefault
{
    $di = new Phalcon\Di\FactoryDefault();

    $config = sim_config();
    $di->setShared('config', fn () => $config);

    // Absoluto como em produção (config_company: baseUri é um URL). Assim os src/href dos fragmentos
    // (ex.: {{ url.get() }}img/check.png) apontam para o simulador e não para o site.
    $di->setShared('url', function () {
        $url = new Phalcon\Mvc\Url();
        $url->setBaseUri(STUB_ORIGIN . '/');
        return $url;
    });

    // Como services.php: urlSite = config.application.baseUriSite (site do evento).
    $di->setShared('urlSite', fn () => $config->application->baseUriSite);

    $di->setShared('router', fn () => new SimRouter((string) $ctx['evento']));

    $di->setShared('crypt', function () {
        // Mesma chave do services.php real; o token de produto tem o mesmo formato.
        $crypt = new Phalcon\Encryption\Crypt();
        $crypt->setKey('festeja-simulador-dev');
        return $crypt;
    });

    $di->setShared('session', fn () => new SimSession());

    $idioma = sim_idioma();
    $di->setShared('t', function () use ($idioma) {
        return new Phalcon\Translate\Adapter\NativeArray(new Phalcon\Translate\InterpolatorFactory(), ['content' => sim_messages($idioma)]);
    });

    $di->set('formatdates', function () use ($di, $idioma) {
        return new Formatdates($di->getShared('t'), $idioma);
    });

    return $di;
}

/** Renderiza um template real (ex.: 'index', 'products/group', 'cart/resume') com as variáveis dadas. */
function sim_render(string $template, array $ctx, array $vars): string
{
    $di = sim_di($ctx);

    $view = new Phalcon\Mvc\View();
    $view->setDI($di);
    $view->setViewsDir(APP_PATH . '/views/');
    $view->registerEngines([
        '.volt' => function ($view) use ($di) {
            $volt = new Phalcon\Mvc\View\Engine\Volt($view, $di);
            // compileAlways: o cache antigo (cache/volt/) tinha versões compiladas de templates já alterados no checkout.
            $volt->setOptions([
                'path' => STUB_CACHE . '/volt/',
                'separator' => '_',
                'compileAlways' => true,
            ]);
            return $volt;
        },
    ]);

    $vars += [
        'event' => $ctx['event'],
        'products' => $ctx['products'],
        'cart' => $ctx['cart'],
        'coupon' => null,
        'id_terminal' => 1,
        'urlSite' => STUB_ORIGINS[0] . '/',
        'currency' => 'EUR',
        'countries' => sim_countries(), // sim-data.php: objetos com code e name (login/site.volt, select de país)
        'cities' => sim_cities(), // sim-data.php: objetos com code (país) e name (select de cidade)
        'isProduct' => false,
        'deliveries_available' => $ctx['deliveries_available'] ?? [], // sim_deliveries() (sim-data.php)
        'payments_available' => $ctx['payments_available'] ?? [], // sim_payments() (sim-data.php)
        'order' => $ctx['order'] ?? null,
        'id_sale_point' => null, // canal online: sem ponto de venda físico (products/group.volt:993)
        't' => $di->getShared('t'),
    ];
    $view->setVars($vars);

    // Phalcon 5: render() exige controller/action; partial() renderiza um template pelo caminho relativo a views/.
    ob_start();
    $view->partial($template);
    return (string) ob_get_clean();
}

}
