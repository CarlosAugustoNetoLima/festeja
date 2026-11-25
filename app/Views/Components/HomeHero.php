<?php
// Hero da página inicial: texto à esquerda; à direita (desktop) a mandala do logótipo, grande, no centro da área.
// A área fica acima da Marquee. Mobile: só texto e logótipo.
// Props: festival (festival.json), artistas (artistas.json), minPrice (float ou null), data (intervalo sem ano, ex.: "23 e 24 julho"),
// eyebrow (texto de home.json, hero.eyebrow).
// A entrada e o loop da mandala são CSS de home.css (sob html.js e sem reduced motion); hero.js só pausa fora do ecrã.

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Rotas;

$festival = $festival ?? [];
$artistas = $artistas ?? [];
$minPrice = $minPrice ?? null;
$data = (string) ($data ?? '');
$eyebrow = (string) ($eyebrow ?? '');
$local = $festival['local'];

$nomes = [];
foreach ($artistas as $artista) {
    if ((int) $artista['destaque'] <= 2) {
        $nomes[] = mb_strtoupper($artista['nome']);
    }
}

$factos = [t('hero.dias', ['n' => count($festival['dias'])]), t('hero.artistas', ['n' => count($artistas)])];
if ($minPrice !== null) {
    $taxa = (new \App\Services\TickettService(config('tickett')))->taxaIncluida() ? '' : ' ' . t('taxa.mais');
    $factos[] = t('hero.desde', ['preco' => Format::price((float) $minPrice)]) . $taxa;
}
?>
<section class="hero" data-hero aria-labelledby="hero-titulo">
  <div class="hero__body wrap">
    <picture>
      <!-- Desktop (>= 1024 px, onde o CSS esconde o logótipo): a fonte vazia evita descarregar o SVG -->
      <source media="(min-width: 1024px)" srcset="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">
      <img class="hero__logo" src="<?= e(asset('img/logo/logo-vertical.svg')) ?>" alt="" aria-hidden="true" width="120" height="104">
    </picture>

    <div class="hero__grid grid-12">
      <div class="hero__copy">
        <p class="fj-eyebrow hero__eyebrow"><?= Component::render('Symbol', ['n' => 5, 'size' => 28]) ?><span><?= e($eyebrow) ?></span></p>

        <h1 id="hero-titulo" class="hero__title">
          <span class="visually-hidden"><?= e($festival['nome'] . ' ' . $festival['edicao']) ?>, </span><span class="hero__date" data-mov="parallax" data-mov-vel="0.04"><?= e($data) ?></span>
        </h1>

        <p class="hero__place">
          <span class="hero__place-name"><?= e($local['nome']) ?></span>
          <span class="hero__place-zone"><?= e($local['zona']) ?></span>
        </p>

        <p class="hero__lead"><?= e($festival['chamada']) ?></p>

        <div class="btn-row hero__actions">
          <a class="fj-btn fj-btn--primary fj-btn--lg" href="<?= e(Rotas::url('bilhetes')) ?>"<?= \App\Services\TickettService::temEvento(config('tickett')) ? ' data-checkout-open' : '' ?>><?= e(t('botao.comprar_bilhetes')) ?></a>
          <a class="fj-btn fj-btn--secondary fj-btn--lg hero__cartaz" href="#cartaz"><?= e(t('botao.ver_cartaz')) ?></a>
        </div>

        <ul class="hero__facts" role="list">
          <?php foreach ($factos as $facto): ?>
          <li><?= e($facto) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="hero__art" aria-hidden="true">
        <div class="hero__sun" data-mov="parallax" data-mov-vel="0.13">
          <div class="hero__mandala-pos">
            <div class="hero__mandala" data-mov="parallax" data-mov-giro="90"><?= Component::render('Mandala') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="hero__marquee">
    <?= Component::render('Marquee', ['items' => $nomes]) ?>
  </div>
</section>
