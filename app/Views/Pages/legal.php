<?php
// Páginas legais (acessibilidade, privacidade, cookies, termos). O texto está em content/paginas/{slug}.html
// (em inglês, content/en/paginas/{slug}.html; ver Content::pagina()).
// Variável: $slug (fixo por rota em App\Helpers\Rotas).

use App\Helpers\Component;
use App\Helpers\Rotas;
use App\Services\Content;

$titulo = t('legal.titulo.' . $slug);
$texto = Content::pagina($slug);
?>
<main id="conteudo">
  <?= Component::render('PageHeader', [
      'items' => [['label' => t('nav.inicio'), 'href' => Rotas::url('inicio')], ['label' => $titulo]],
      'eyebrow' => t('legal.eyebrow'),
      'title' => $titulo,
  ]) ?>

  <div class="wrap legal">
    <?php if (config('env') === 'dev'): ?>
    <p class="legal__rascunho" role="note"><?= e(t('legal.rascunho')) ?></p>
    <?php endif; ?>
    <div class="prose">
      <?= $texto ?>
    </div>
  </div>
</main>
