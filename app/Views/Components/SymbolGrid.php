<?php
// Grelha de símbolos da linha gráfica (Symbol), na forma original, em colunas iguais. Decorativa: aria-hidden.
// Props: simbolos (números de 1 a 24, por ordem), size (px de cada símbolo, 56 por defeito), class (opcional),
// mov (bool: os símbolos aparecem um a um, data-mov="escalonar" com o estilo "simbolo" de movimento.js).
// As colunas e o espaçamento são CSS da página que usa a grelha (.symbol-grid em site.css, com o .symbol).

use App\Helpers\Component;

$simbolos = $simbolos ?? [];
$size = (int) ($size ?? 56);
$class = $class ?? '';
$mov = !empty($mov);
?>
<div class="<?= e(trim('symbol-grid ' . $class)) ?>" aria-hidden="true"<?= $mov ? ' data-mov="escalonar" data-mov-estilo="simbolo"' : '' ?>>
  <?php foreach ($simbolos as $n): ?>
  <?= Component::render('Symbol', ['n' => $n, 'size' => $size]) ?>
  <?php endforeach; ?>
</div>
