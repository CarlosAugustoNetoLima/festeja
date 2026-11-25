<?php

declare(strict_types=1);

// Na Vercel, o runtime define VERCEL. Sem variáveis de ambiente, o site corre em produção contra a Tickett real.
$naVercel = getenv('VERCEL') !== false;

$config = [
  'env' => $naVercel ? 'prod' : 'dev',                    // 'prod' em produção
  'base_url' => 'http://127.0.0.1:8080',
  'site_name' => 'Festival Festeja',
  'tickett' => [
    // Checkout da Tickett tal como está publicado (não é alterado). O site faz de proxy em /checkout.
    'base' => $naVercel ? 'https://checkout.lebillet.eu' : 'http://127.0.0.1:8081',   // produção: https://checkout.lebillet.eu
    'event_id' => 2001,
    'cache_ttl' => 300,
    'timeout' => 3,
  ],
  'analytics' => ['ga4_id' => null],
];

// Valores locais (ignorados pelo git) sobrepõem os de cima.
$local = __DIR__ . '/site.local.php';
if (is_file($local)) {
    $overrides = require $local;
    if (is_array($overrides)) {
        $config = array_replace_recursive($config, $overrides);
    }
}

// Variáveis de ambiente sobrepõem as anteriores (Vercel, servidor). Vazias ou ausentes não mudam nada.
$variavel = static function (string $nome): ?string {
    $valor = getenv($nome);
    return $valor === false || $valor === '' ? null : $valor;
};

if (in_array($variavel('SITE_ENV'), ['dev', 'prod'], true)) {
    $config['env'] = $variavel('SITE_ENV');
}

// Sem BASE_URL, na Vercel deduz-se o domínio: o de produção, ou o Host do pedido.
$baseUrl = $variavel('BASE_URL');
if ($baseUrl === null && $naVercel) {
    $dominio = $variavel('VERCEL_PROJECT_PRODUCTION_URL') ?? ($_SERVER['HTTP_HOST'] ?? null);
    $baseUrl = $dominio !== null && $dominio !== '' ? 'https://' . $dominio : null;
}
if ($baseUrl !== null) {
    $config['base_url'] = rtrim($baseUrl, '/');
}

if ($variavel('TICKETT_BASE') !== null) {
    $config['tickett']['base'] = $variavel('TICKETT_BASE');
}
if (ctype_digit($variavel('TICKETT_EVENT_ID') ?? '')) {
    $config['tickett']['event_id'] = (int) $variavel('TICKETT_EVENT_ID');
}
if ($variavel('GA4_ID') !== null) {
    $config['analytics']['ga4_id'] = $variavel('GA4_ID');
}

return $config;
