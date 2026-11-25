<?php
// Fecho da página inicial: fundo ocre, texto verde, data grande, botão de compra e uma faixa de símbolos da marca por baixo.
// Props: data (intervalo completo com ano, ex.: "23 e 24 julho 2027"), eyebrow (texto de home.json, fecho.eyebrow).
// O botão usa a variante .btn-on-ochre (home.css): fundo var(--brand-green), texto var(--surface) da marca.

use App\Helpers\Component;
use App\Helpers\Rotas;

$data = (string) ($data ?? '');
$eyebrow = (string) ($eyebrow ?? '');
// Símbolos sobre ocre: os que têm mais verde (7, 20, 21), porque o ocre da marca se perde no fundo ocre.
?>
<section class="section home-close" aria-labelledby="fecho-titulo">
  <div class="wrap home-close__inner">
    <p class="fj-eyebrow home-close__eyebrow"><?= e($eyebrow) ?></p>
    <h2 id="fecho-titulo" class="home-close__date" data-mov="revelar" data-mov-estilo="escala"><?= e($data) ?></h2>
    <a class="fj-btn fj-btn--lg btn-on-ochre" href="<?= e(Rotas::url('bilhetes')) ?>"<?= \App\Services\TickettService::temEvento(config('tickett')) ? ' data-checkout-open' : '' ?>><?= e(t('botao.comprar_bilhetes')) ?></a>
    <?= Component::render('SymbolGrid', ['simbolos' => [7, 20, 21, 7, 20, 21], 'size' => 64, 'class' => 'home-close__simbolos', 'mov' => true]) ?>
  </div>
</section>
