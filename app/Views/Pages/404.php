<?php
// Página 404. O status HTTP 404 é dado em public/index.php (notFound()).

use App\Helpers\Component;
use App\Helpers\Rotas;
?>
<main id="conteudo" class="not-found">
  <div class="wrap not-found__inner">
    <div class="not-found__text">
      <h1 class="not-found__title"><?= e(t('erro.404.titulo')) ?></h1>
      <p class="not-found__lead"><?= e(t('erro.404.texto')) ?></p>
      <div class="btn-row">
        <a class="fj-btn fj-btn--primary fj-btn--lg" href="<?= e(Rotas::url('inicio')) ?>"><?= e(t('erro.404.ir_inicio')) ?></a>
        <a class="fj-btn fj-btn--secondary fj-btn--lg" href="<?= e(Rotas::url('cartaz')) ?>"><?= e(t('botao.ver_cartaz')) ?></a>
      </div>
    </div>
    <?= Component::render('SymbolGrid', ['simbolos' => [1, 9, 24, 1, 9, 24], 'size' => 96, 'class' => 'not-found__simbolos', 'mov' => true]) ?>
  </div>
</main>
