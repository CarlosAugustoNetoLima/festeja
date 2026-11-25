<?php

// Gera os pacotes de estilo do site: public/assets/css/pacote-comum.css e pacote-home.css.
// Juntam, pela ordem de App\Helpers\Estilos, as folhas de estilo do layout num só pedido.
// Uso (na raiz do projeto): php bin/css-pacote.php
// Rodar depois de editar qualquer folha de estilo do site. Sem isto o layout usa as folhas soltas (correto, mais lento).

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Helpers\Estilos;

$pacotes = [
    'pacote-comum' => Estilos::COMUM,
    'pacote-home' => Estilos::HOME,
];

foreach ($pacotes as $nome => $folhas) {
    $saida = "/* Pacote gerado por bin/css-pacote.php. Não editar: editar as folhas de origem e voltar a gerar. */\n";
    foreach ($folhas as $folha) {
        $ficheiro = ROOT . '/public/assets/css/' . $folha . '.css';
        if (!is_file($ficheiro)) {
            fwrite(STDERR, "Folha em falta: {$ficheiro}\n");
            exit(1);
        }
        $saida .= "\n/* ---- {$folha}.css ---- */\n" . rtrim((string) file_get_contents($ficheiro)) . "\n";
    }
    $destino = ROOT . '/public/assets/css/' . $nome . '.css';
    file_put_contents($destino, $saida);
    echo $nome . '.css: ' . strlen($saida) . " bytes (" . count($folhas) . " folhas)\n";
}
