<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Escolha determinística dos símbolos da linha gráfica (icone-01 a icone-24), a partir de uma semente (slug ou título).
 * A mesma semente dá sempre o mesmo símbolo: a página não muda de um pedido para o outro.
 */
final class Simbolo
{
    // Número do símbolo (1 a 24) para uma semente.
    public static function um(string $semente): int
    {
        return (int) sprintf('%u', crc32($semente)) % 24 + 1;
    }

    // Três símbolos diferentes, espaçados na lista (para grelhas; o design system pede no máximo três por ecrã).
    public static function trio(string $semente): array
    {
        $inicio = (int) sprintf('%u', crc32($semente)) % 24;
        return [$inicio + 1, ($inicio + 8) % 24 + 1, ($inicio + 16) % 24 + 1];
    }

    // Caminho do símbolo no sprite (o mesmo que o componente Symbol usa).
    public static function href(int $n): string
    {
        return asset('img/simbolos/sprite.svg') . '#icone-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
