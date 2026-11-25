<?php
// Página de bilhetes: catálogo da Tickett (TicketGrid), o que acontece depois da compra e as perguntas sobre bilhetes.

use App\Helpers\Component;
use App\Helpers\Icon;
use App\Helpers\Rotas;
use App\Services\Content;
use App\Services\TickettService;

$festival = Content::get('festival');
$tickett = new TickettService(config('tickett'));
$catalogo = $tickett->getCatalog();
$checkoutUrl = $tickett->checkoutUrl();

$grupoBilhetes = array_values(array_filter(
    Content::get('faq')['grupos'],
    static fn (array $grupo): bool => $grupo['id'] === 'bilhetes'
))[0] ?? ['perguntas' => []];
?>
<main id="conteudo">
  <?= Component::render('PageHeader', [
      'items' => [['label' => t('nav.inicio'), 'href' => Rotas::url('inicio')], ['label' => t('nav.bilhetes')]],
      'eyebrow' => t('nav.bilhetes'),
      'title' => t('bilhetes.titulo', ['edicao' => $festival['edicao']]),
      // A linha de taxas só fala de preços: sem preços à venda (sem evento, ou venda por abrir) não aparece.
      'lead' => t('bilhetes.lead') . ($tickett->minPrice() === null ? '' : ' ' . ($tickett->taxaIncluida() ? t('bilhetes.taxas_incluidas') : t('taxa.acresce_longa'))),
  ]) ?>

  <section class="section" data-theme="noite" id="bilhetes" aria-labelledby="bilhetes-titulo">
    <div class="wrap">
      <?= Component::render('SectionHead', ['title' => t('bilhetes.a_venda'), 'id' => 'bilhetes-titulo']) ?>
      <?= Component::render('TicketGrid', ['catalog' => $catalogo, 'checkoutUrl' => $checkoutUrl]) ?>
    </div>
  </section>

  <section class="section" aria-labelledby="antes-comprar">
    <div class="wrap">
      <?= Component::render('SectionHead', ['title' => t('bilhetes.antes_comprar'), 'id' => 'antes-comprar']) ?>
      <div class="bilhetes-info">
        <article class="bilhetes-info__item">
          <h3 class="bilhetes-info__title"><?= e(t('bilhetes.recebes_titulo')) ?></h3>
          <p><?= e(t('bilhetes.recebes_texto')) ?></p>
        </article>
        <article class="bilhetes-info__item">
          <h3 class="bilhetes-info__title"><?= e(t('bilhetes.pagamento_titulo')) ?></h3>
          <!-- rever -->
          <p><?= e(t('bilhetes.pagamento_texto')) ?></p>
        </article>
        <article class="bilhetes-info__item">
          <h3 class="bilhetes-info__title"><?= e(t('bilhetes.trocas_titulo')) ?></h3>
          <!-- rever -->
          <p><?= e(t('bilhetes.trocas_texto')) ?></p>
        </article>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="perguntas-bilhetes">
    <div class="wrap">
      <?= Component::render('SectionHead', ['title' => t('bilhetes.perguntas'), 'id' => 'perguntas-bilhetes']) ?>
      <div class="bilhetes-faq">
        <?= Component::render('Faq', ['perguntas' => $grupoBilhetes['perguntas'], 'id' => 'faq-bilhetes']) ?>
      </div>
      <div class="btn-row bilhetes-more">
        <a class="fj-btn fj-btn--secondary" href="<?= e(Rotas::url('informacoes')) ?>"><?= e(t('botao.todas_perguntas')) ?> <?= Icon::svg('arrow-right') ?></a>
      </div>
    </div>
  </section>
</main>
