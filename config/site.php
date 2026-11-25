<?php

declare(strict_types=1);

// Na Vercel, o runtime define VERCEL. Sem variáveis de ambiente, o site corre em produção contra a Tickett real.
$naVercel = getenv('VERCEL') !== false;
$producao = $naVercel || getenv('SITE_ENV') === 'prod';
// Modo demonstração (tickett.demo): sem evento Festeja na Tickett, a produção usa este evento real só para simular a
// bilheteira. Os avisos de demonstração aparecem no site (ver docs/guia-de-manutencao.md, 16.1).
$idDemonstracao = 2133;

$config = [
  'env' => $naVercel ? 'prod' : 'dev',                    // 'prod' em produção
  'base_url' => 'http://127.0.0.1:8080',
  'site_name' => 'Festival Festeja',
  'tickett' => [
    // Checkout da Tickett tal como está publicado (não é alterado). O site faz de proxy em /checkout.
    'base' => $naVercel ? 'https://checkout.lebillet.eu' : 'http://127.0.0.1:8081',   // produção: https://checkout.lebillet.eu
    // 2001 é só do simulador (dev). Na Tickett real o 2001 é outro evento: em produção o evento vem de
    // TICKETT_EVENT_ID (dígitos) ou, sem ele, do evento de demonstração (ver acima).
    'event_id' => $producao ? $idDemonstracao : 2001,
    'demo' => false,
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
$eventoDefinido = ctype_digit($variavel('TICKETT_EVENT_ID') ?? '');
if ($eventoDefinido) {
    $config['tickett']['event_id'] = (int) $variavel('TICKETT_EVENT_ID');
}
// TICKETT_DEMO=0 desliga a demonstração (sem TICKETT_EVENT_ID em produção, não há evento: "venda em breve").
// TICKETT_DEMO=1 liga-a também com TICKETT_EVENT_ID definido.
$demoVariavel = $variavel('TICKETT_DEMO');
if (!$eventoDefinido && $producao) {
    $config['tickett']['event_id'] = $demoVariavel === '0' ? null : $idDemonstracao;
}
$demo = $demoVariavel === '1' || (!$eventoDefinido && $producao && $demoVariavel !== '0');
$config['tickett']['demo'] = $demo && $config['tickett']['event_id'] !== null;
if ($variavel('GA4_ID') !== null) {
    $config['analytics']['ga4_id'] = $variavel('GA4_ID');
}

return $config;
