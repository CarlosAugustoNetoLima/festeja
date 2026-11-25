<?php

declare(strict_types=1);

// Upstream de teste para `node dev/qa/qa.mjs demo` (ver dev/qa/README.md). Faz de checkout da Tickett e devolve o
// HTML guardado do evento 2133 em /2133. Qualquer outro caminho responde 404. Não é a Tickett real.
// Uso: php -S 127.0.0.1:8092 dev/tickett-parse-fixtures/servidor-upstream.php

$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/2133/?$#', $caminho) === 1) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/tickett-2133.html');
    return;
}

http_response_code(404);
echo 'Não existe neste upstream de teste.';
