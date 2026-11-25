<?php
// Grelha de bilhetes a partir do catálogo da Tickett (resultado de TickettService::getCatalog()).
// Props: catalog (array com ok, stale, event, products), checkoutUrl (checkout geral do evento).
// Mensagens, por esta ordem: sem TICKETT_EVENT_ID (venda em breve, com link para o Instagram); falha da Tickett sem cache
// (ok = false); venda por abrir (upcoming); venda terminada (closed); sem produtos à venda. Só com a venda aberta e produtos
// é que mostra a grelha. Cache antiga (stale) mostra-se sem aviso.

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Rotas;
use App\Services\Content;
use App\Services\TickettService;

$catalog = $catalog ?? ['ok' => false, 'stale' => false, 'event' => null, 'products' => []];
$checkoutUrl = $checkoutUrl ?? Rotas::url('bilhetes');
$products = $catalog['products'] ?? [];
$event = $catalog['event'] ?? null;

if (!empty($catalog['stale'])) {
    error_log('TicketGrid: a mostrar o catálogo em cache (Tickett sem resposta)');
}

$venda = is_array($event) ? ($event['sale'] ?? []) : [];
$estadoVenda = (string) ($venda['status'] ?? '');
$semEvento = !TickettService::temEvento(config('tickett'));
$instagram = Content::get('festival')['redes']['instagram'] ?? null;
if ($semEvento) {
    $mensagem = t('bilhetes.em_breve');
} elseif (empty($catalog['ok'])) {
    $mensagem = t('bilhetes.bilheteira_sem_resposta');
} elseif ($estadoVenda === 'upcoming') {
    $mensagem = !empty($venda['opens_at'])
        ? t('bilhetes.venda_abre', ['quando' => Format::dateTime($venda['opens_at'])])
        : t('bilhetes.venda_abre_breve');
} elseif ($estadoVenda === 'closed') {
    $mensagem = t('bilhetes.venda_terminou');
} elseif ($products !== []) {
    $mensagem = null;
} else {
    $mensagem = t('bilhetes.indisponiveis');
}
?>
<?php if ($mensagem === null): ?>
<?php if (TickettService::emDemo(config('tickett'))): ?>
<p class="ticket-demo" role="note"><?= e(t('demo.nota')) ?></p>
<?php endif; ?>
<ul class="ticket-grid" role="list" data-mov="escalonar" data-mov-estilo="sticker">
  <?php foreach ($products as $product): ?>
  <?= Component::render('TicketCard', ['product' => $product, 'checkoutUrl' => $checkoutUrl]) ?>
  <?php endforeach; ?>
</ul>
<?php elseif ($semEvento): ?>
<div class="ticket-empty">
  <p class="ticket-empty__text"><?= e($mensagem) ?></p>
  <?php if ($instagram !== null): ?>
  <a class="fj-btn fj-btn--secondary" href="<?= e($instagram) ?>" rel="noopener"><?= e(t('footer.instagram')) ?></a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="ticket-empty">
  <p class="ticket-empty__text"><?= e($mensagem) ?></p>
  <a class="fj-btn fj-btn--primary" href="<?= e($checkoutUrl) ?>" data-checkout-open><?= e(t('botao.ir_bilheteira')) ?></a>
</div>
<?php endif; ?>
