<?php
// Cartão de um bilhete (produto da Tickett). Chega normalizado por TickettService::normalizeProduct().
// Props: product (array), checkoutUrl (fallback do checkout geral). Esgotado fica visível, sem botão ativo.
// Renderiza um <li> para ser filho de <ul class="ticket-grid">.

use App\Helpers\Format;
use App\Helpers\Icon;
use App\Helpers\Rotas;
use App\Services\Content;
use App\Services\TickettService;

$product = $product ?? [];
$checkoutUrl = $checkoutUrl ?? Rotas::url('bilhetes');
$festival = Content::get('festival');

$status = (string) ($product['status'] ?? 'available');
$emStock = in_array($status, ['available', 'low'], true);
$destaque = !empty($product['highlight']);
$lote = $product['batch'] ?? null;
$proximo = $product['next_batch'] ?? null;

// Quando é válido: sem dias = todos os dias do festival; um dia = "Sexta, 23 julho"; vários = intervalo.
$dias = $product['days'] ?? [];
if ($dias === []) {
    $quando = Format::range($festival['datas']['inicio'], $festival['datas']['fim']);
} elseif (count($dias) === 1) {
    $quando = Format::date($dias[0], 'weekday');
} else {
    $quando = Format::range($dias[0], $dias[count($dias) - 1]);
}

$textoProximo = null;
if (is_array($proximo) && !empty($proximo['opens_at'])) {
    $textoProximo = t('bilhetes.proximo_lote', ['quando' => Format::dateTime($proximo['opens_at'])]);
    if (isset($proximo['total']) && is_numeric($proximo['total'])) {
        $textoProximo .= ' · ' . Format::price((float) $proximo['total']);
    }
}

$classes = 'fj-ticket'
    . ($destaque ? ' fj-ticket--featured' : '')
    . ($status === 'sold_out' ? ' fj-ticket--soldout' : '');
?>
<li class="<?= e($classes) ?>">
  <div class="ticket-card__badges">
    <?php if ($destaque): ?>
    <span class="fj-badge"><?= e($product['highlight']) ?></span>
    <?php endif; ?>
    <?php if ($status === 'low'): ?>
    <span class="fj-badge"><?= Icon::svg('alert-circle') ?><?= e(t('bilhetes.badge_ultimos')) ?></span>
    <?php endif; ?>
    <?php if ($status === 'sold_out'): ?>
    <span class="fj-badge fj-badge--soldout"><?= Icon::svg('alert-circle') ?><?= e(t('bilhetes.badge_esgotado')) ?></span>
    <?php endif; ?>
    <?php if ($status === 'upcoming'): ?>
    <span class="fj-badge fj-badge--lote"><?= Icon::svg('clock') ?><?= e(t('bilhetes.badge_brevemente')) ?></span>
    <?php endif; ?>
    <?php if (is_array($lote) && ($lote['name'] ?? '') !== ''): ?>
    <span class="fj-badge fj-badge--lote"><?= e(isset($lote['number']) ? t('bilhete.lote', ['n' => $lote['number']]) : $lote['name']) ?></span>
    <?php endif; ?>
  </div>

  <h3 class="fj-ticket__name"><?= e($product['name']) ?></h3>
  <?php if (($product['description'] ?? '') !== ''): ?>
  <p class="fj-ticket__desc"><?= e($product['description']) ?></p>
  <?php endif; ?>
  <p class="fj-ticket__when"><?= e($quando) ?></p>
<?php
  // Valor mostrado: total, ou o preço do HTML. Esgotado sem preço não mostra linha de preço (o badge já diz).
  $valor = $product['total'] ?? $product['price'] ?? null;
  if ($valor !== null): ?>
  <p class="fj-ticket__price"><span><?= e(Format::price((float) $valor)) ?></span></p>
  <p class="fj-ticket__fee"><?= e(($product['fee_included'] ?? false) ? t('taxa.incluidas_minusculas') : t('taxa.acresce_longa')) ?></p>
  <?php elseif ($status !== 'sold_out'): ?>
  <p class="fj-ticket__price"><span><?= e(t('taxa.preco_no_checkout')) ?></span></p>
  <?php endif; ?>

  <?php if ($status === 'sold_out' && $textoProximo !== null): ?>
  <p class="fj-ticket__next"><?= e($textoProximo) ?></p>
  <?php endif; ?>

  <?php if ($emStock): ?>
  <div class="fj-ticket__actions">
    <a class="fj-btn fj-btn--primary fj-btn--block"
       href="<?= e(TickettService::comIdioma($product['checkout_url'] ?? $checkoutUrl)) ?>"
       data-checkout-open
       data-product="<?= e((string) ($product['produto'] ?? '')) ?>"
       aria-label="<?= e(t('botao.escolher_nome', ['nome' => $product['name']])) ?>"><?= e(t('botao.escolher')) ?></a>
  </div>
  <?php endif; ?>
</li>
