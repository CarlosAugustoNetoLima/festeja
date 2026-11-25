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

// Marcação de tabela (group.volt, ex.: evento 2133). Lê o HTML guardado em dev/tickett-parse-fixtures/, sem rede.
$leitor = new ReflectionMethod(TickettService::class, 'parseCatalogo');
$fixture = ROOT . '/dev/tickett-parse-fixtures/tickett-2133.html';
$lido = $leitor->invoke($servico, (string) file_get_contents($fixture));
$confere($lido !== null, 'fixture 2133: bloco de bilhetes encontrado');
$produtosFixture = $lido['products'] ?? [];
$confere($lido['event']['name'] === 'Guitarras ao Alto no Crato', 'fixture 2133: nome do evento vindo do <title>');
$confere($lido['event']['sale']['status'] === 'open', 'fixture 2133: venda aberta');
$confere(count($produtosFixture) === 3, 'fixture 2133: 3 bilhetes (' . count($produtosFixture) . ')');
$esperadosFixture = [
    ['16038_1', '11/12 - Miramar + Gwenifer Raymond / Mosteiro Flor da Rosa.', 'Ínicio do evento às 21h00', 6.0],
    ['16039_1', '12/12 Calcutá / Igreja de Santo António', 'Ínicio do evento as 21h30', 6.0],
    ['16040_1', 'Miramar/Gwenifer Raymond + Calcutá', '1 Bilhete = 2 Concertos', 10.0],
];
foreach ($esperadosFixture as $i => [$produto, $nome, $descricao, $preco]) {
    $obtido = $produtosFixture[$i] ?? [];
    $rotulo = "fixture 2133 bilhete {$i} ({$produto})";
    $confere(($obtido['produto'] ?? null) === $produto, "{$rotulo}: produto (?produto=) " . var_export($obtido['produto'] ?? null, true));
    $confere(($obtido['name'] ?? null) === $nome, "{$rotulo}: nome");
    $confere(($obtido['description'] ?? null) === $descricao, "{$rotulo}: descrição");
    $confere(($obtido['price'] ?? null) === $preco, "{$rotulo}: preço " . var_export($obtido['price'] ?? null, true));
    $confere(($obtido['status'] ?? null) === 'available', "{$rotulo}: disponível");
    $confere(($obtido['batch']['name'] ?? null) === 'Lote 1', "{$rotulo}: lote " . var_export($obtido['batch']['name'] ?? null, true));
    $confere(str_ends_with($obtido['checkout_url'] ?? '', '?produto=' . $produto), "{$rotulo}: link com ?produto=");
}

// Esgotado e em breve no mesmo formato. Sintético (não há exemplo ao vivo): a célula sem select traz só a mensagem
// (group.volt, bloco "soldout" / product['msg']).
$sintetico = '<html><head><title>Evento de teste | x</title></head><body><div id="tickets-form-products"><table class="table table-product"><tbody>'
    . '<tr class="table-tr-no-hover"><td><h6>Bilhete esgotado <small><br>Desc A</small></h6></td></tr>'
    . '<tr class="tr-products"><td>Lote 1</td><td>€ 5,00</td><td><small>Esgotado</small></td></tr>'
    . '<tr class="table-tr-no-hover"><td><h6>Bilhete em breve <small><br>Desc B</small></h6></td></tr>'
    . '<tr class="tr-products"><td>Lote 2</td><td>€ 9,00</td><td><small>Em breve</small></td></tr>'
    . '</tbody></table></div></body></html>';
$sint = $leitor->invoke($servico, $sintetico);
$a = $sint['products'][0] ?? [];
$b = $sint['products'][1] ?? [];
$confere(($a['status'] ?? null) === 'sold_out' && array_key_exists('price', $a) && $a['price'] === null && array_key_exists('id', $a) && $a['id'] === null, 'tabela sintética: bilhete esgotado sem preço nem id');
$confere(($b['status'] ?? null) === 'upcoming' && ($b['batch']['number'] ?? null) === 2, 'tabela sintética: bilhete "em breve" com lote 2');

if ($falhas > 0) {
    echo "\n{$falhas} falha(s).\n";
    exit(1);
}
echo "\nTudo confere (simulador, fixture 2133 e tabela sintética).\n";
