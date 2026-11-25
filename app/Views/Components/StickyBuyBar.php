<?php
// Barra de compra fixa, só no mobile. Começa escondida; buybar.js mostra-a quando o hero sai do ecrã.
// Props: minPrice (float ou null). Se não vier, consulta o TickettService (cache de 300 s).

use App\Helpers\Format;
use App\Helpers\Rotas;
use App\Services\TickettService;

$servico = new TickettService(config('tickett'));
$minPrice = $minPrice ?? $servico->minPrice();
$taxaIncluida = $servico->taxaIncluida();
?>
<div class="fj-buybar" data-buybar hidden>
  <p class="fj-buybar__info">
    <strong><?= $minPrice !== null ? '<span class="fj-buybar__de">' . e(t('buybar.desde')) . '</span> ' . e(Format::price($minPrice)) : e(t('buybar.bilhetes')) ?></strong>
    <?php if ($minPrice !== null): ?>
    <span><?= e($taxaIncluida ? t('taxa.incluidas') : t('taxa.acresce_curta')) ?></span>
    <?php endif; ?>
  </p>
  <a class="fj-btn fj-btn--primary" href="<?= e(Rotas::url('bilhetes')) ?>"<?= TickettService::temEvento(config('tickett')) ? ' data-checkout-open' : '' ?>><?= e(t('botao.comprar_bilhetes')) ?></a>
</div>
