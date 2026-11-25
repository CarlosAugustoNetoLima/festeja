<?php

declare(strict_types=1);

// Router para `php -S` (ver bin/serve). Ficheiros estáticos existentes são servidos diretamente.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $real = realpath(__DIR__ . $path);
    if ($real !== false && str_starts_with($real, __DIR__ . DIRECTORY_SEPARATOR) && is_file($real)) {
        return false;
    }
}

require __DIR__ . '/index.php';
