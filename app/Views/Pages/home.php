<?php
// Página inicial: hero, factos, cartaz, bilhetes, o festival, como chegar, perguntas, parceiros e fecho.
// Dados: content/*.json (Content) e catálogo da Tickett (TickettService). Preços e datas saem do catálogo e do Format.
// Textos editoriais das secções: content/home.json. O título do cartaz tem {edicao}, trocado pelo ano de festival.json.

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Rotas;
use App\Services\Content;
use App\Services\TickettService;

$festival = Content::get('festival');
$artistas = Content::get('artistas');
$grupos = Content::get('faq')['grupos'];
$home = Content::get('home');

$tickett = new TickettService(config('tickett'));
$catalogo = $tickett->getCatalog();
$checkoutUrl = $tickett->checkoutUrl();

$minPrice = $tickett->minPrice();

// Datas: o intervalo completo com ano (fecho) e sem ano (hero, onde o ano está no texto oculto do h1).
$intervalo = Format::range($festival['datas']['inicio'], $festival['datas']['fim']);
$dataCurta = preg_replace('/ \d{4}$/', '', $intervalo);
$tituloCartaz = str_replace('{edicao}', $festival['edicao'], $home['cartaz']['titulo']);

$dias = $festival['dias'];

// Cada artista aparece só no dia principal (os extra estão em "dias").
$artistasDoDia = static fn (string $id): array => array_values(
    array_filter($artistas, static fn (array $a): bool => $a['dia'] === $id)
);

// Perguntas marcadas como "home" em todos os grupos.
$perguntas = [];
foreach ($grupos as $grupo) {
    foreach ($grupo['perguntas'] as $pergunta) {
        if (($pergunta['home'] ?? false) === true) {
            $perguntas[] = $pergunta;
        }
    }
}
?>
<main id="conteudo">
  <?= Component::render('HomeHero', [
      'festival' => $festival,
      'artistas' => $artistas,
      'minPrice' => $minPrice,
      'data' => $dataCurta,
      'eyebrow' => $home['hero']['eyebrow'],
  ]) ?>

  <?= Component::render('FactsStrip', ['minPrice' => $minPrice]) ?>

  <section id="cartaz" class="section" aria-labelledby="cartaz-titulo">
    <div class="wrap">
      <?= Component::render('SectionHead', ['eyebrow' => $home['cartaz']['eyebrow'], 'title' => $tituloCartaz, 'symbol' => 3, 'align' => 'center', 'id' => 'cartaz-titulo']) ?>

      <div class="home-cartaz" data-tabs>
        <div class="home-cartaz__tabs" role="tablist" aria-label="<?= e(t('geral.dias_festival')) ?>">
          <?php foreach ($dias as $i => $dia):
              $rotulo = preg_replace('/ \S+$/u', '', Format::date($dia['data'], 'weekday'));
          ?>
          <button type="button" class="home-cartaz__tab" data-tab="<?= e($dia['id']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($rotulo) ?></button>
          <?php endforeach; ?>
        </div>

        <div class="home-cartaz__dias">
          <?php foreach ($dias as $dia): ?>
          <div class="home-cartaz__painel" data-panel="<?= e($dia['id']) ?>">
            <?= Component::render('LineupDay', ['dia' => $dia, 'artistas' => $artistasDoDia($dia['id']), 'hover' => true]) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <p class="home-cartaz__mais">
        <a class="fj-btn fj-btn--ghost" href="<?= e(Rotas::url('cartaz')) ?>"><?= e(t('botao.ver_cartaz_completo')) ?></a>
        <span><?= e(t('cartaz.mais_nomes')) ?></span>
      </p>
    </div>
  </section>

  <section id="bilhetes" class="section" data-theme="noite" aria-labelledby="bilhetes-titulo">
    <div class="wrap">
      <?= Component::render('SectionHead', [
          'eyebrow' => $home['bilhetes']['eyebrow'],
          'title' => $home['bilhetes']['titulo'],
          'lead' => $home['bilhetes']['lead'],
          'symbol' => 12,
          'id' => 'bilhetes-titulo',
      ]) ?>
      <?= Component::render('TicketGrid', ['catalog' => $catalogo, 'checkoutUrl' => $checkoutUrl]) ?>
      <p class="small home-bilhetes__nota"><?= e(t('bilhetes.venda_oficial')) ?></p>
    </div>
  </section>

  <?= Component::render('HomeFestival') ?>

  <?= Component::render('HomeVisit', ['festival' => $festival]) ?>

  <section class="section" aria-labelledby="faq-titulo">
    <div class="wrap home-faq">
      <?= Component::render('SectionHead', ['eyebrow' => $home['faq']['eyebrow'], 'title' => $home['faq']['titulo'], 'id' => 'faq-titulo']) ?>
      <?= Component::render('Faq', ['perguntas' => $perguntas, 'id' => 'faq-home']) ?>
      <p class="home-faq__mais">
        <a class="fj-btn fj-btn--secondary" href="<?= e(Rotas::url('informacoes')) ?>"><?= e(t('botao.ver_todas_perguntas')) ?></a>
      </p>
    </div>
  </section>

  <?= Component::render('Partners') ?>

  <?= Component::render('HomeClose', ['data' => $intervalo, 'eyebrow' => $home['fecho']['eyebrow']]) ?>
</main>
