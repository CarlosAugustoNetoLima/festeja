<?php

declare(strict_types=1);

// Entrada na Vercel (runtime vercel-php, ver vercel.json). Recebe os pedidos que não são estáticos e entrega-os ao
// front controller, como faz public/router-dev.php em local. O caminho segue em $_SERVER['REQUEST_URI'].

// Atrás do proxy da Vercel, o protocolo chega no cabeçalho. O proxy do checkout usa $_SERVER['HTTPS'] para os cookies Secure.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

require __DIR__ . '/../public/index.php';
