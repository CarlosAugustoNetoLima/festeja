<?php
// Mandala da marca: o símbolo 01 da linha gráfica (sol radial com pétalas em M), o mesmo motivo do logótipo vertical.
// Escolhido visualmente entre os 24 símbolos: é o único radial com o M das pétalas, como na mandala do logótipo.
// Imprime o SVG inline para o CSS poder animá-lo (roda à entrada do hero). As cores da marca passam a var() via style.
// Props: class (opcional).

$class = $class ?? '';
$file = ROOT . '/public/assets/img/simbolos/icone-01.svg';
$svg = is_file($file) ? (string) file_get_contents($file) : '';
if ($svg === '') {
    throw new \RuntimeException('Mandala em falta: public/assets/img/simbolos/icone-01.svg');
}

// Sem declaração XML, com classe e atributos de decoração no <svg>.
$svg = preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg);
$svg = preg_replace(
    '/<svg\b/',
    '<svg class="' . e(trim('mandala ' . $class)) . '" aria-hidden="true" focusable="false"',
    $svg,
    1
);

// Cores da marca: fill="#hex" passa a style="fill:var(--token)" (var() não funciona em atributos de apresentação).
$marca = [
    'e95110' => 'var(--brand-orange)',
    'e3900c' => 'var(--brand-ochre)',
    '133a27' => 'var(--brand-green)',
];
$svg = preg_replace_callback(
    '/fill="#([0-9a-fA-F]{6})"/',
    static fn (array $m): string => isset($marca[strtolower($m[1])])
        ? 'style="fill:' . $marca[strtolower($m[1])] . '"'
        : $m[0],
    $svg
);
?>
<?= $svg ?>
