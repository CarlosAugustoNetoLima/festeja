<?php

declare(strict_types=1);

namespace App\Helpers;

final class Component
{
    /**
     * Renderiza app/Views/Components/{$name}.php com as propriedades em $props.
     */
    public static function render(string $name, array $props = []): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Nome de componente inválido: {$name}");
        }

        $file = ROOT . '/app/Views/Components/' . $name . '.php';
        if (!is_file($file)) {
            $message = "Componente não encontrado: {$name}";
            if (config('env') === 'prod') {
                error_log($message);
                return '';
            }
            throw new \RuntimeException($message);
        }

        extract($props, EXTR_SKIP);
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
