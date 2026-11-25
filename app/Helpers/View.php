<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    /**
     * Renderiza app/Views/Pages/{$page}.php dentro de app/Views/layout.php.
     * As chaves de $data ficam disponíveis como variáveis nos dois ficheiros.
     */
    public static function page(string $page, array $data): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $page) !== 1) {
            throw new \InvalidArgumentException("Nome de página inválido: {$page}");
        }

        $pageFile = ROOT . '/app/Views/Pages/' . $page . '.php';
        if (!is_file($pageFile)) {
            throw new \RuntimeException("Página não encontrada: {$page}");
        }

        $content = self::include($pageFile, $data);

        return self::include(ROOT . '/app/Views/layout.php', ['content' => $content] + $data);
    }

    private static function include(string $file, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }
        return (string) ob_get_clean();
    }
}
