<?php
// Símbolo decorativo da linha gráfica (icone-01 a icone-24 em public/assets/img/simbolos/).
// Props: n (1 a 24), size (px, 32 por defeito). É sempre decorativo: aria-hidden. O desenho vem de sprite.svg
// (gerado por bin/simbolos-sprite.php), um pedido em cache para o site todo.

$n = (int) ($n ?? 1);
if ($n < 1 || $n > 24) {
    throw new \InvalidArgumentException("Símbolo fora do intervalo 1 a 24: {$n}");
}
$size = (int) ($size ?? 32);
// mov: true põe o símbolo a rodar 90° ao entrar (movimento.js, data-mov do eyebrow)
$mov = !empty($mov);
$href = App\Helpers\Simbolo::href($n);
?>
<svg class="symbol" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true" focusable="false"<?= $mov ? ' data-mov="revelar" data-mov-estilo="girar"' : '' ?>><use href="<?= e($href) ?>" width="100%" height="100%"/></svg>
