<?php

// Gera public/assets/img/simbolos/sprite.svg: os 24 símbolos da linha gráfica (icone-01 a icone-24) como <symbol>,
// para o componente Symbol os referenciar com <use href=".../sprite.svg#icone-NN">. Um pedido para o site todo, em cache.
// Uso (na raiz do projeto): php bin/simbolos-sprite.php
// Rodar depois de mudar qualquer icone-NN.svg. O sprite não é usado nos símbolos da mandala (Mandala.php, inline).

declare(strict_types=1);

$pasta = dirname(__DIR__) . '/public/assets/img/simbolos';
$simbolos = '';

for ($n = 1; $n <= 24; $n++) {
    $id = 'icone-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ficheiro = $pasta . '/' . $id . '.svg';
    $svg = is_file($ficheiro) ? (string) file_get_contents($ficheiro) : '';
    if (preg_match('/<svg\b[^>]*\bviewBox="([^"]+)"[^>]*>(.*)<\/svg>/s', $svg, $m) !== 1) {
        fwrite(STDERR, "Símbolo em falta ou inválido: {$ficheiro}\n");
        exit(1);
    }
    // Sem comentários no conteúdo, para o sprite ficar mais leve
    $conteudo = trim((string) preg_replace('/<!--.*?-->/s', '', $m[2]));
    $simbolos .= '<symbol id="' . $id . '" viewBox="' . htmlspecialchars($m[1], ENT_QUOTES) . '">' . $conteudo . "</symbol>\n";
}

$saida = '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true">' . "\n" . $simbolos . "</svg>\n";
file_put_contents($pasta . '/sprite.svg', $saida);
echo 'sprite.svg: ' . strlen($saida) . " bytes (24 símbolos)\n";
