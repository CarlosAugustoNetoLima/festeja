<?php
// Secção "Como chegar" da página inicial: transportes à esquerda, cartão decorativo do local à direita.
// O símbolo do cartão (13) é o que se lê sobre verde: o verde da marca desaparece no tema Noite.
// Props: festival (festival.json: local e transportes). Textos da secção em content/home.json (visita).
// Não usa mapa nem imagem externa: o botão abre o Google Maps numa nova janela.
// Os transportes são indicações por confirmar: "rever" em festival.json marca cada item com data-assumed.

use App\Helpers\Component;
use App\Helpers\Icon;
use App\Helpers\Rotas;
use App\Services\Content;

$festival = $festival ?? [];
$visita = Content::get('home')['visita'];
$local = $festival['local'];
$mapa = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($local['nome'] . ' ' . t('geral.cidade'));
?>
<section class="section home-sunken home-visit" data-theme="papel" aria-labelledby="visita-titulo">
  <div class="wrap home-visit__grid">
    <div class="home-visit__info">
      <?= Component::render('SectionHead', ['eyebrow' => $visita['eyebrow'], 'title' => $visita['titulo'], 'id' => 'visita-titulo']) ?>

      <h3 class="home-visit__place"><?= e($local['nome']) ?></h3>
      <p class="home-visit__zone"><?= e($local['zona']) ?></p>

      <ul class="home-visit__list" role="list" data-mov="escalonar">
        <?php foreach ($festival['transportes'] as $transporte): ?>
        <li class="home-visit__item"<?= !empty($transporte['rever']) ? ' data-assumed' : '' ?>>
          <?= Icon::svg($transporte['icone'], ['class' => 'home-visit__icon']) ?>
          <span><strong><?= e($transporte['titulo']) ?>:</strong> <?= e($transporte['texto']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>

      <p class="home-visit__more"><a href="<?= e(Rotas::url('como-chegar')) ?>"><?= e(t('visita.mais')) ?></a></p>
    </div>

    <div class="home-visit__card" data-theme="noite" data-mov="revelar">
      <div class="home-visit__simbolo"><?= Component::render('Symbol', ['n' => 13, 'size' => 96]) ?></div>
      <p class="home-visit__card-name"><?= e($local['nome']) ?></p>
      <p class="home-visit__card-zone"><?= e($local['zona']) ?></p>
      <a class="fj-btn fj-btn--secondary home-visit__map" href="<?= e($mapa) ?>" target="_blank" rel="noopener"><?= e(t('botao.ver_no_mapa')) ?><span class="visually-hidden"><?= e(t('geral.nova_janela')) ?></span></a>
    </div>
  </div>
</section>
