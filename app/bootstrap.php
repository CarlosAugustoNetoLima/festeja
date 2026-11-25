<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Autoload PSR-4: App\Foo\Bar -> app/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/**
 * Valor de configuração por chave com pontos: config('tickett.timeout').
 */
function config(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    $cfg ??= require ROOT . '/config/site.php';

    $value = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/**
 * Escapa para HTML (texto e atributos).
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Caminho público de um asset, com ?v= pela data de modificação.
 * Recebe o caminho dentro de public/assets/, ex.: 'css/base.css'.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT . '/public/assets/' . $path;
    $url = '/assets/' . $path;
    return is_file($file) ? $url . '?v=' . filemtime($file) : $url;
}

/**
 * Idioma da página: 'pt' (predefinido) ou 'en'. O router define-o pelo prefixo /en (public/index.php).
 * Sem argumento, devolve o idioma atual.
 */
function idioma(?string $definir = null): string
{
    static $atual = 'pt';
    if ($definir !== null && in_array($definir, ['pt', 'en'], true)) {
        $atual = $definir;
    }
    return $atual;
}

/**
 * Texto de interface de app/Lang/{idioma}.php. Em {chave} dos parâmetros troca-se pelo valor de $params['chave'].
 * Em dev, uma chave em falta no idioma atual lança exceção; em produção cai no português e regista no log.
 */
function t(string $chave, array $params = []): string
{
    static $tabelas = [];
    $lang = idioma();
    $tabelas[$lang] ??= require ROOT . '/app/Lang/' . $lang . '.php';

    $texto = $tabelas[$lang][$chave] ?? null;
    if ($texto === null) {
        $mensagem = "Texto em falta ({$lang}): {$chave}";
        if (config('env') === 'dev') {
            throw new \RuntimeException($mensagem);
        }
        error_log($mensagem);
        $tabelas['pt'] ??= require ROOT . '/app/Lang/pt.php';
        $texto = $tabelas['pt'][$chave] ?? $chave;
    }

    $trocas = [];
    foreach ($params as $nome => $valor) {
        $trocas['{' . $nome . '}'] = (string) $valor;
    }
    return strtr($texto, $trocas);
}
