<?php

declare(strict_types=1);

namespace App\Helpers;

// Folhas de estilo do layout. Um pacote (public/assets/css/pacote-comum.css e pacote-home.css, gerados por
// bin/css-pacote.php) junta-as num só pedido. Se o pacote não existir ou alguma folha for mais recente do que ele,
// saem as folhas soltas: a página fica sempre certa, só mais lenta até regenerar o pacote.
final class Estilos
{
    // Mesma ordem em cascata que o layout tinha antes. fontes.css só tem @font-face (não depende da ordem).
    public const COMUM = ['fontes', 'tokens', 'base', 'components', 'checkout', 'site', 'pages'];
    public const HOME = ['fontes', 'tokens', 'base', 'components', 'checkout', 'site', 'home', 'pages'];

    /**
     * Os <link> de estilo da página: o pacote, ou as folhas soltas se o pacote estiver desatualizado.
     */
    public static function links(bool $home): string
    {
        $folhas = $home ? self::HOME : self::COMUM;
        $pacote = $home ? 'pacote-home' : 'pacote-comum';

        if (self::atualizado($folhas, $pacote)) {
            return '<link rel="stylesheet" href="' . e(asset('css/' . $pacote . '.css')) . '">';
        }

        $html = '';
        foreach ($folhas as $folha) {
            $html .= '<link rel="stylesheet" href="' . e(asset('css/' . $folha . '.css')) . '">';
        }
        return $html;
    }

    /**
     * O pacote existe e nenhuma das suas folhas é mais recente do que ele.
     */
    public static function atualizado(array $folhas, string $pacote): bool
    {
        $ficheiro = ROOT . '/public/assets/css/' . $pacote . '.css';
        if (!is_file($ficheiro)) {
            return false;
        }
        $data = filemtime($ficheiro);
        foreach ($folhas as $folha) {
            $fonte = ROOT . '/public/assets/css/' . $folha . '.css';
            if (!is_file($fonte) || filemtime($fonte) > $data) {
                return false;
            }
        }
        return true;
    }
}
