<?php
// Página do cartaz: nomes por dia (separadores no mobile, lado a lado no desktop) e grelha de artistas por dia.
// O título ("Cartaz {edicao}") vem de content/home.json, o mesmo texto da home.

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Rotas;
use App\Services\Content;

$festival = Content::get('festival');
$artistas = Content::get('artistas');
$dias = $festival['dias'];
$tituloCartaz = str_replace('{edicao}', $festival['edicao'], Content::get('home')['cartaz']['titulo']);

// Cada artista aparece só no dia principal (os extra estão em "dias").
$doDia = static fn (string $id): array => array_values(
    array_filter($artistas, static fn (array $a): bool => $a['dia'] === $id)
);

$lead = Format::range($festival['datas']['inicio'], $festival['datas']['fim'])
    . ' · ' . $festival['local']['nome'] . ', ' . $festival['local']['zona'] . '.';
?>
<main id="conteudo">
  <?= Component::render('PageHeader', [
      'items' => [['label' => t('nav.inicio'), 'href' => Rotas::url('inicio')], ['label' => t('nav.cartaz')]],
      'eyebrow' => t('nav.cartaz'),
      'title' => $tituloCartaz,
      'lead' => $lead,
  ]) ?>

  <section class="section cartaz-days" data-tabs aria-labelledby="cartaz-nomes">
    <div class="wrap">
      <h2 id="cartaz-nomes" class="fj-visually-hidden"><?= e(t('cartaz.nomes_por_dia')) ?></h2>
      <div role="tablist" aria-label="<?= e(t('geral.dias_festival')) ?>" class="cartaz-days__tabs">
        <?php foreach ($dias as $i => $dia): ?>
        <button type="button" class="cartaz-days__tab" data-tab="<?= e($dia['id']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e(Format::date($dia['data'], 'weekday')) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="cartaz-days__panels">
        <?php foreach ($dias as $dia): ?>
        <div data-panel="<?= e($dia['id']) ?>">
          <?= Component::render('LineupDay', ['dia' => $dia, 'artistas' => $doDia($dia['id']), 'hover' => true]) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="section cartaz-groups">
    <div class="wrap">
      <?php foreach ($dias as $dia): ?>
      <section class="cartaz-group" aria-labelledby="grupo-<?= e($dia['id']) ?>">
        <h2 id="grupo-<?= e($dia['id']) ?>" class="cartaz-group__title"><?= e(Format::date($dia['data'], 'weekday')) ?></h2>
        <ul class="fj-artists cartaz-artists" role="list">
          <?php foreach ($doDia($dia['id']) as $artista): ?>
          <?= Component::render('ArtistCard', ['artista' => $artista]) ?>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endforeach; ?>

      <div class="cartaz-notes">
        <p><?= e(t('cartaz.mais_nomes_ponto')) ?></p>
        <p><?= e(t('cartaz.horarios')) ?></p>
      </div>
    </div>
  </div>

  <section class="section cartaz-cta" data-theme="noite" aria-labelledby="cartaz-cta-titulo">
    <div class="wrap cartaz-cta__inner">
      <h2 id="cartaz-cta-titulo" class="cartaz-cta__title"><?= e(t('cartaz.cta_titulo')) ?></h2>
      <a class="fj-btn fj-btn--primary fj-btn--lg" href="<?= e(Rotas::url('bilhetes')) ?>" data-checkout-open><?= e(t('botao.comprar_bilhetes')) ?></a>
    </div>
  </section>
</main>
