<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Helpers\Format;
use App\Helpers\Rotas;
use App\Helpers\Seo;
use App\Helpers\View;
use App\Services\Content;
use App\Services\TickettService;

/*
 * Rotas que não têm versão em inglês (as páginas com versão estão em App\Helpers\Rotas, mapa pt ↔ en).
 *  - page:      ficheiro em app/Views/Pages/ (sem .php). Se não existir, mostra "Em construção".
 *  - title:     título da página (o nome do site é acrescentado). Só português.
 *  - generated: resposta gerada (texto/XML), sem layout.
 *  - kit:       página de teste, só em dev (responde 404 nos outros ambientes).
 * Trailing slash: /bilhetes/ redireciona com 301 para /bilhetes.
 */
$rotasFixas = [
    '/kit'             => ['page' => 'kit', 'title' => 'Kit de componentes'],
    '/llms.txt'        => ['generated' => 'llms'],
    '/sitemap.xml'     => ['generated' => 'sitemap'],
    '/robots.txt'      => ['generated' => 'robots'],
];

/**
 * Corta um texto a $max caracteres, na última palavra inteira, e acrescenta "…".
 */
function truncar(string $texto, int $max = 155): string
{
    $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
    if (mb_strlen($texto) <= $max) {
        return $texto;
    }
    $corte = mb_substr($texto, 0, $max - 1);
    $espaco = mb_strrpos($corte, ' ');
    if ($espaco !== false) {
        $corte = mb_substr($corte, 0, $espaco);
    }
    return rtrim($corte, " ,;:-") . '…';
}

/**
 * Lista no idioma da página: "a, b e c" (en: "a, b and c").
 */
function lista(array $itens): string
{
    if (count($itens) < 2) {
        return (string) ($itens[0] ?? '');
    }
    $ultimo = array_pop($itens);
    return implode(', ', $itens) . t('lista.e') . $ultimo;
}

/**
 * Meta description de cada página, gerada a partir de festival.json, artistas.json e faq.json.
 * Devolve null se a página fica com o resumo do festival (home).
 */
function metaDescription(string $page, ?string $slug, array $festival, array $artistas, array $grupos, ?array $artista = null): ?string
{
    $nome = $festival['nome'] . ' ' . $festival['edicao'];
    $datas = (string) preg_replace('/ \d{4}$/', '', Format::range($festival['datas']['inicio'], $festival['datas']['fim']));
    $local = $festival['local']['nome'];
    $zona = $festival['local']['zona'];

    $horas = array_values(array_unique(array_column($festival['dias'], 'portas')));
    $portas = count($horas) === 1 ? ' ' . t('meta.portas', ['hora' => Format::time($horas[0])]) : '';

    switch ($page) {
        case 'bilhetes':
            return t('meta.bilhetes', ['nome' => $nome, 'datas' => $datas, 'local' => $local]);

        case 'cartaz':
            $destaques = array_values(array_map(
                static fn (array $a): string => $a['nome'],
                array_filter($artistas, static fn (array $a): bool => (int) $a['destaque'] === 1)
            ));
            $nomes = array_slice($destaques, 0, 4);
            $nomesTexto = count($destaques) > count($nomes)
                ? t('meta.lista_mais', ['nomes' => implode(', ', $nomes)])
                : lista($nomes);
            return t('meta.cartaz', ['nome' => $nome, 'lista' => $nomesTexto, 'datas' => $datas, 'local' => $local]);

        case 'informacoes':
            $temas = array_map(static fn (array $g): string => mb_strtolower($g['titulo']), $grupos);
            return t('meta.informacoes', ['nome' => $nome, 'temas' => lista($temas)]);

        case 'como-chegar':
            $meios = array_map(static fn (array $t): string => mb_strtolower($t['titulo']), $festival['transportes']);
            return t('meta.como_chegar', ['local' => $local, 'zona' => $zona, 'meios' => lista($meios)]) . $portas;

        case 'legal':
            return t('meta.legal.' . $slug, ['nome' => $nome]);

        case '404':
            return t('meta.404', ['nome' => $nome]);

        case 'artista':
            $bio = trim((string) ($artista['bio'] ?? ''));
            if ($bio !== '') {
                return truncar($bio);
            }
            $dia = array_values(array_filter($festival['dias'], static fn (array $d): bool => $d['id'] === $artista['dia']))[0] ?? null;
            if ($dia === null) {
                $quando = $datas;
            } elseif (idioma() === 'en') {
                $quando = Format::date($dia['data'], 'weekday');
            } else {
                $quando = mb_strtolower(Format::date($dia['data'], 'weekday'));
            }
            return t('meta.artista', ['nome' => $artista['nome'], 'evento' => $nome, 'quando' => $quando, 'local' => $local]);

        default:
            return null;
    }
}

/**
 * Catálogo de bilhetes (cache e falha graciosa em TickettService).
 */
function tickettCatalog(): array
{
    return (new TickettService(config('tickett')))->getCatalog();
}

function notFound(): never
{
    $festival = Content::get('festival');

    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo View::page('404', [
        'title' => t('erro.404.titulo_pagina') . ' · ' . config('site_name'),
        'description' => metaDescription('404', null, $festival, Content::get('artistas'), Content::get('faq')['grupos']) ?? $festival['resumo'],
        'canonical' => config('base_url') . Rotas::url('inicio'),
        'page' => '404',
        'theme_color' => '#f5eed1',
        'alternativo' => Rotas::trocaIdioma(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
    ]);
    exit;
}

// Checkout da Tickett servido pelo proxy do próprio domínio (app/Services/CheckoutProxy.php).
// Fica antes da verificação de método, porque o relay aceita POST, e antes do redirect de barra final.
$proxyUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($proxyUri === '/checkout' || str_starts_with($proxyUri, '/checkout/relay/')) {
    $proxy = new \App\Services\CheckoutProxy(config('tickett'));
    $metodoProxy = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $httpsProxy = ($_SERVER['HTTPS'] ?? 'off') !== 'off';
    $cookiesProxy = $_SERVER['HTTP_COOKIE'] ?? '';
    $resposta = $proxyUri === '/checkout'
        ? $proxy->pagina($metodoProxy, $_GET['produto'] ?? null, $cookiesProxy, $httpsProxy)
        : $proxy->relay($metodoProxy, substr($proxyUri, strlen('/checkout/relay/')), $_SERVER['CONTENT_TYPE'] ?? '', $cookiesProxy, $httpsProxy);
    \App\Services\CheckoutProxy::enviar($resposta);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($uri, '/') ?: '/';
if ($path !== $uri) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $path . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

// O idioma da página vem do prefixo /en (Rotas::idiomaDoCaminho). Tudo o que se segue (textos, datas, conteúdo) usa-o.
idioma(Rotas::idiomaDoCaminho($path));

$match = null;
foreach ($rotasFixas as $pattern => $definition) {
    if (Rotas::corresponde($pattern, $path) !== null) {
        $match = $definition;
        break;
    }
}
$resolvida = $match === null ? Rotas::resolver($path) : null;

if ($match === null && $resolvida === null) {
    notFound();
}

// Respostas geradas (texto/XML), sem layout.
if (isset($match['generated'])) {
    [$type, $body] = match ($match['generated']) {
        'llms' => ['text/plain; charset=utf-8', Seo::llms(Content::get('festival'), Content::get('artistas'), Content::get('faq'), tickettCatalog())],
        'sitemap' => ['application/xml; charset=utf-8', Seo::sitemap(Content::get('artistas'))],
        'robots' => ['text/plain; charset=utf-8', Seo::robots()],
    };
    header('Content-Type: ' . $type);
    echo $body;
    exit;
}

$festival = Content::get('festival');
$siteName = config('site_name');
$page = $resolvida['page'] ?? $match['page'];

// O kit de componentes só existe em dev.
if ($page === 'kit' && config('env') !== 'dev') {
    notFound();
}

$data = [
    'page' => $page,
    'title' => $siteName,
    'description' => $festival['resumo'],
    'canonical' => config('base_url') . $path,
    'theme_color' => '#f5eed1',
    // Seletor de idioma: caminho pt e en da página (ou da inicial, se a página não tem par).
    'alternativo' => Rotas::trocaIdioma($path),
    // hreflang só nas páginas com versão nos dois idiomas.
    'alternates' => $resolvida === null ? [] : [
        'pt-PT' => config('base_url') . $resolvida['pt'],
        'en' => config('base_url') . $resolvida['en'],
        'x-default' => config('base_url') . $resolvida['pt'],
    ],
];

if ($page === 'home') {
    $data['title'] = $festival['nome'] . ' ' . $festival['edicao'] . ' · ' . $festival['mote'];
} elseif ($page === 'artista') {
    $artista = null;
    foreach (Content::get('artistas') as $item) {
        if ($item['slug'] === $resolvida['params']['slug']) {
            $artista = $item;
            break;
        }
    }
    if ($artista === null) {
        notFound();
    }
    $data['title'] = $artista['nome'] . ' · ' . $siteName;
    $data['artista'] = $artista;
} elseif ($page === 'legal') {
    $data['title'] = t('legal.titulo.' . $resolvida['slug']) . ' · ' . $siteName;
} elseif ($resolvida !== null) {
    $data['title'] = t('nav.' . $resolvida['chave']) . ' · ' . $siteName;
} else {
    $data['title'] = $match['title'] . ' · ' . $siteName;
}

if ($resolvida !== null && $resolvida['slug'] !== null) {
    $data['slug'] = $resolvida['slug'];
}

$descricao = metaDescription($page, $resolvida['slug'] ?? null, $festival, Content::get('artistas'), Content::get('faq')['grupos'], $data['artista'] ?? null);
if ($descricao !== null) {
    $data['description'] = $descricao;
}

// JSON-LD por página (layout.php imprime cada bloco de $jsonld).
$perguntas = array_merge([], ...array_column(Content::get('faq')['grupos'], 'perguntas'));
$data['jsonld'] = match ($page) {
    'home' => [
        Seo::event($festival, Content::get('artistas'), tickettCatalog()),
        Seo::faq(array_values(array_filter($perguntas, static fn (array $p): bool => ($p['home'] ?? false) === true))),
    ],
    'bilhetes', 'cartaz' => [Seo::event($festival, Content::get('artistas'), tickettCatalog())],
    'informacoes' => [Seo::faq($perguntas)],
    'artista' => [
        Seo::artist($data['artista'], $festival),
        Seo::breadcrumbs([
            ['name' => t('nav.inicio'), 'url' => Rotas::url('inicio')],
            ['name' => t('nav.cartaz'), 'url' => Rotas::url('cartaz')],
            ['name' => $data['artista']['nome'], 'url' => Rotas::url('artista', ['slug' => $data['artista']['slug']])],
        ]),
    ],
    default => [],
};

$template = is_file(ROOT . '/app/Views/Pages/' . $page . '.php') ? $page : 'em-construcao';

header('Content-Type: text/html; charset=utf-8');
echo View::page($template, $data);
