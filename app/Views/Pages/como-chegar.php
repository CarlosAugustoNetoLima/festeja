<?php
// Como chegar: morada, mapa, transportes, acessibilidade e portas por dia. Dados de content/festival.json
// (local, transportes e dias). Os transportes são os mesmos da página inicial.

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Icon;
use App\Helpers\Rotas;
use App\Services\Content;

$festival = Content::get('festival');
$local = $festival['local'];
$mapaUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($local['nome'] . ', ' . t('geral.cidade'));

// Transportes: não confirmados pela organização (ver "rever" em cada item de festival.json).
$transportes = $festival['transportes'];
?>
<main id="conteudo">
  <?= Component::render('PageHeader', [
      'items' => [['label' => t('nav.inicio'), 'href' => Rotas::url('inicio')], ['label' => t('nav.como-chegar')]],
      'eyebrow' => t('como.eyebrow'),
      'title' => t('como.titulo', ['local' => $local['nome']]),
  ]) ?>

  <div class="wrap como-chegar">
    <div class="como-chegar__grid">
      <section class="como-chegar__block" aria-labelledby="chegar-morada">
        <h2 id="chegar-morada" class="como-chegar__title"><?= e(t('como.morada')) ?></h2>
        <p><?= e($local['nome']) ?><br><?= e($local['zona']) ?></p>
        <?php if (!empty($local['morada'])): ?>
        <p><?= e($local['morada']) ?></p>
        <?php else: ?>
        <!-- rever -->
        <p class="como-chegar__note"><?= e(t('como.morada_breve')) ?></p>
        <?php endif; ?>
        <div class="btn-row">
          <a class="fj-btn fj-btn--secondary" href="<?= e($mapaUrl) ?>" target="_blank" rel="noopener"><?= Icon::svg('map-pin') ?><?= e(t('botao.ver_no_mapa')) ?><span class="fj-visually-hidden"><?= e(t('como.nova_pagina')) ?></span></a>
        </div>
      </section>

      <section class="como-chegar__block" aria-labelledby="chegar-transportes">
        <h2 id="chegar-transportes" class="como-chegar__title"><?= e(t('como.transportes')) ?></h2>
        <ul class="como-chegar__list" role="list">
          <?php foreach ($transportes as $transporte): ?>
          <li class="como-chegar__item"<?= !empty($transporte['rever']) ? ' data-assumed' : '' ?>>
            <?= Icon::svg($transporte['icone'], ['class' => 'como-chegar__icon']) ?>
            <span><strong><?= e($transporte['titulo']) ?>:</strong> <?= e($transporte['texto']) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="como-chegar__block" aria-labelledby="chegar-acessibilidade">
        <h2 id="chegar-acessibilidade" class="como-chegar__title"><?= Icon::svg('accessibility') ?><?= e(t('como.acessibilidade')) ?></h2>
        <p><?= e(t('como.acessibilidade_texto')) ?></p>
        <p><a href="<?= e(Rotas::url('acessibilidade')) ?>"><?= e(t('como.declaracao')) ?></a></p>
      </section>

      <section class="como-chegar__block" aria-labelledby="chegar-horarios">
        <h2 id="chegar-horarios" class="como-chegar__title"><?= e(t('como.horarios')) ?></h2>
        <dl class="como-chegar__hours">
          <?php foreach ($festival['dias'] as $dia): ?>
          <div class="como-chegar__hour">
            <dt><?= e(Format::date($dia['data'], 'weekday')) ?></dt>
            <dd><?= e(t('lineup.portas', ['hora' => Format::time($dia['portas'])])) ?><?php if (!empty($dia['rever'])): ?> <span class="como-chegar__note"><?= e(t('geral.a_confirmar')) ?></span><?php endif; ?></dd>
          </div>
          <?php endforeach; ?>
        </dl>
      </section>
    </div>
  </div>
</main>
