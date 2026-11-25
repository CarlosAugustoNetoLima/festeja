<?php
// Informações: factos rápidos e todas as perguntas frequentes, agrupadas por tema, com índice.

use App\Helpers\Component;
use App\Helpers\Rotas;
use App\Services\Content;
use App\Services\TickettService;

$grupos = Content::get('faq')['grupos'];
$minPrice = (new TickettService(config('tickett')))->minPrice();
?>
<main id="conteudo">
  <?= Component::render('PageHeader', [
      'items' => [['label' => t('nav.inicio'), 'href' => Rotas::url('inicio')], ['label' => t('nav.informacoes')]],
      'eyebrow' => t('nav.informacoes'),
      'title' => t('informacoes.titulo'),
  ]) ?>

  <?= Component::render('FactsStrip', ['minPrice' => $minPrice]) ?>

  <div class="wrap informacoes">
    <div class="informacoes__grid">
      <nav class="informacoes__index" aria-label="<?= e(t('informacoes.temas')) ?>">
        <ul role="list">
          <?php foreach ($grupos as $grupo): ?>
          <li><a href="#<?= e($grupo['id']) ?>"><?= e($grupo['titulo']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <div class="informacoes__content">
        <?php foreach ($grupos as $grupo): ?>
        <section class="informacoes__group" id="<?= e($grupo['id']) ?>" aria-labelledby="titulo-<?= e($grupo['id']) ?>">
          <h2 id="titulo-<?= e($grupo['id']) ?>" class="informacoes__title"><?= e($grupo['titulo']) ?></h2>
          <?= Component::render('Faq', ['perguntas' => $grupo['perguntas'], 'id' => 'faq-' . $grupo['id']]) ?>
        </section>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
