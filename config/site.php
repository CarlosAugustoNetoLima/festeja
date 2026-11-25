<?php

declare(strict_types=1);

$config = [
  'env' => 'dev',                    // 'prod' em produção
  'base_url' => 'http://127.0.0.1:8080',
  'site_name' => 'Festival Festeja',
  'tickett' => [
    // Checkout da Tickett tal como está publicado (não é alterado). O site faz de proxy em /checkout.
    'base' => 'http://127.0.0.1:8081',   // produção: https://checkout.lebillet.eu
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

return $config;
