<?php
// Janela da bilheteira Tickett (iframe). Contrato: docs/contrato-tickett.md.
// O iframe carrega /checkout, o proxy do próprio domínio (app/Services/CheckoutProxy.php); checkout.js acrescenta ?produto=.
// O link de fallback e "abrir numa página nova" vai direto para a bilheteira, com o idioma (?l=) da página.
// Idioma do site (pt ou en) segue no iframe como ?lang=; o proxy traduz para o ?l= da Tickett. Predefinido pt.
// data-textos: os textos que checkout.js mostra (títulos dos passos, mensagem de compra), gerados aqui.
use App\Helpers\Rotas;
use App\Services\TickettService;

$idioma = in_array($idioma ?? null, ['pt', 'en'], true) ? $idioma : 'pt';
$checkoutUrl = TickettService::comIdioma(rtrim((string) config('tickett.base'), '/') . '/' . (int) config('tickett.event_id'), $idioma);

$partes = parse_url((string) config('base_url')) ?: [];
$origem = ($partes['scheme'] ?? '') . '://' . ($partes['host'] ?? '')
    . (isset($partes['port']) ? ':' . $partes['port'] : '');

$textos = [
    'passos' => [
        'products' => t('checkout.passo.bilhetes'),
        'details' => t('checkout.passo.dados'),
        'payment' => t('checkout.passo.pagamento'),
        'done' => t('checkout.passo.concluida'),
    ],
    'compra' => t('checkout.compra_concluida'),
];
?>
<div class="fj-modal-backdrop" data-checkout hidden data-origin="<?= e($origem) ?>" data-textos="<?= e(json_encode($textos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">
  <div class="fj-modal fj-checkout" role="dialog" aria-modal="true" aria-labelledby="checkout-titulo">
    <div class="fj-modal__head">
      <h2 id="checkout-titulo"><?= e(t('checkout.titulo')) ?></h2>
      <button type="button" class="fj-modal__close" data-checkout-close aria-label="<?= e(t('geral.fechar')) ?>">
        <?= \App\Helpers\Icon::svg('x') ?>
      </button>
    </div>
    <div class="fj-modal__body fj-checkout__body">
      <p class="fj-checkout__status" role="status" data-checkout-loading><?= e(t('checkout.a_carregar')) ?></p>
      <p class="fj-checkout__alert" role="alert" data-checkout-alert hidden><?= e(t('checkout.demora')) ?> <a href="<?= e($checkoutUrl) ?>" target="_blank" rel="noopener"><?= e(t('checkout.nova_pagina')) ?></a></p>
      <iframe class="fj-checkout__frame" id="Tickets_Embedded_Checkout_Site" title="<?= e(t('checkout.iframe_titulo')) ?>" data-src="/checkout?lang=<?= e($idioma) ?>" loading="lazy" allow="payment"></iframe>
      <p class="fj-visually-hidden" aria-live="polite" data-checkout-live></p>
    </div>
    <div class="fj-checkout__foot">
      <p><?= e(t('geral.pagamento_seguro')) ?></p>
      <a href="<?= e($checkoutUrl) ?>" target="_blank" rel="noopener"><?= e(t('checkout.abrir_nova_pagina')) ?></a>
    </div>
  </div>
</div>
