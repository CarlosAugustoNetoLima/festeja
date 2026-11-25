<?php

/**
 * Verificação do parser de TickettService contra o simulador da Tickett (http://127.0.0.1:8081).
 * Imprime o catálogo normalizado e confere com dev/tickett-stub/data/catalog.json (ordem, nomes, descrições,
 * preços, lotes, esgotados). Não é um teste automático de PHPUnit: é um script para correr à mão.
 *
 * Uso:
 *   php dev/tickett-parse-check.php
 *   php dev/tickett-parse-check.php --base=http://127.0.0.1:9 --ttl=0   (teste de falha)
 *
 * Sai com código 1 se algum campo não bater com o simulador.
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\TickettService;

$opcoes = getopt('', ['base:', 'ttl:']);
$cfg = config('tickett');
if (isset($opcoes['base'])) {
    $cfg['base'] = $opcoes['base'];
}
if (isset($opcoes['ttl'])) {
    $cfg['cache_ttl'] = (int) $opcoes['ttl'];
}

$servico = new TickettService($cfg);
$catalogo = $servico->getCatalog();

echo json_encode($catalogo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n\n";
echo 'checkoutUrl() = ', $servico->checkoutUrl(), "\n";
echo 'checkoutUrl(9001) = ', $servico->checkoutUrl(9001), "\n";
echo 'embedUrl(9001) = ', $servico->embedUrl(9001), "\n";
echo 'minPrice() = ', var_export($servico->minPrice(), true), "\n\n";

$falhas = 0;
$confere = static function (bool $ok, string $descricao) use (&$falhas): void {
    echo ($ok ? 'OK    ' : 'FALHA ') . $descricao . "\n";
    if (!$ok) {
        $falhas++;
    }
};

if (!$catalogo['ok']) {
    $confere(false, 'catálogo disponível (ok = true)');
    exit(1);
}

// Dados do simulador. Ordem = ordem por "sort", como o checkout a mostra.
$simulador = json_decode((string) file_get_contents(ROOT . '/dev/tickett-stub/data/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
$esperados = $simulador['products'];
usort($esperados, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

$produtos = $catalogo['products'];
$confere(count($produtos) === count($esperados), 'número de bilhetes: ' . count($produtos) . ' (esperado ' . count($esperados) . ')');

foreach ($esperados as $i => $esperado) {
    $obtido = $produtos[$i] ?? null;
    $rotulo = "bilhete {$i} ({$esperado['id']} {$esperado['name']})";
    if ($obtido === null) {
        $confere(false, "{$rotulo} em falta");
        continue;
    }

    // O simulador mostra "upcoming" como bilhete selecionável (sim_products() dá available = 10).
    // O parser devolve o que o HTML mostra, por isso o esperado é available.
    $estadoEsperado = match ($esperado['status']) {
        'sold_out' => 'sold_out',
        'upcoming' => 'available',
        default => 'available',
    };
    // Esgotado: o HTML não tem select nem preço (só "soldout"), nem id do bilhete.
    $esgotado = $esperado['status'] === 'sold_out';
    $idEsperado = $esgotado ? null : $esperado['id'];
    $precoEsperado = $esgotado ? null : (float) $esperado['price'];
    $loteEsperado = 'Lote ' . ($esperado['batch']['number'] ?? 1);

    $confere($obtido['name'] === $esperado['name'], "{$rotulo}: nome \"{$obtido['name']}\"");
    $confere($obtido['description'] === $esperado['description'], "{$rotulo}: descrição");
    $confere($obtido['price'] === $precoEsperado, "{$rotulo}: preço " . var_export($obtido['price'], true) . ' (esperado ' . var_export($precoEsperado, true) . ')');
    $confere($obtido['status'] === $estadoEsperado, "{$rotulo}: estado {$obtido['status']} (esperado {$estadoEsperado})");
    $confere($obtido['id'] === $idEsperado, "{$rotulo}: id " . var_export($obtido['id'], true) . ' (esperado ' . var_export($idEsperado, true) . ')');
    $confere(($obtido['batch']['name'] ?? null) === $loteEsperado, "{$rotulo}: lote " . var_export($obtido['batch']['name'] ?? null, true) . " (esperado {$loteEsperado})");
    $confere($obtido['total'] === null && $obtido['fee'] === null, "{$rotulo}: total e taxa null (o HTML não os mostra)");
    $confere($obtido['sort'] === $i, "{$rotulo}: sort {$obtido['sort']}");
}

$confere($catalogo['event']['sale']['status'] === 'open', 'venda aberta (sale.status = open)');
$confere($catalogo['event']['name'] === 'Festival Festeja 2027', 'nome do evento vindo do <title>');

if ($falhas > 0) {
    echo "\n{$falhas} falha(s).\n";
    exit(1);
}
echo "\nTudo confere com o simulador.\n";
