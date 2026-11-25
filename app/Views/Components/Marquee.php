<?php
// Faixa laranja com os nomes do cartaz em movimento (só desktop). A lista está duplicada para o ciclo não ter salto;
// a cópia de trás é aria-hidden. O botão de pausa é sempre visível (WCAG 2.2.2).
// Props: items (lista de textos), label (nome da região). Comportamento em public/assets/js/marquee.js.

use App\Helpers\Component;
use App\Helpers\Icon;

$items = array_values($items ?? []);
$label = $label ?? t('marquee.nome');
$simbolos = [3, 7, 12, 18];

$grupo = static function (array $nomes) use ($simbolos): string {
    $html = '';
    foreach ($nomes as $i => $nome) {
        $html .= '<li class="fj-marquee__item">' . e($nome) . '</li>';
        $html .= '<li class="fj-marquee__sep" aria-hidden="true">'
            . Component::render('Symbol', ['n' => $simbolos[$i % count($simbolos)], 'size' => 28])
            . '</li>';
    }
    return $html;
};
?>
<div class="fj-marquee" data-marquee data-mov="inclinar" role="region" aria-label="<?= e($label) ?>">
  <div class="fj-marquee__viewport">
    <div class="fj-marquee__track">
      <ul class="fj-marquee__group" role="list"><?= $grupo($items) ?></ul>
      <ul class="fj-marquee__group" role="list" aria-hidden="true"><?= $grupo($items) ?></ul>
    </div>
  </div>
  <button type="button" class="fj-marquee__pause" data-marquee-toggle aria-pressed="false">
    <?= Icon::svg('pause', ['class' => 'fj-marquee__icon fj-marquee__icon--pause']) ?>
    <?= Icon::svg('play', ['class' => 'fj-marquee__icon fj-marquee__icon--play']) ?>
    <span class="fj-visually-hidden"><?= e(t('botao.pausar')) ?></span>
  </button>
</div>
