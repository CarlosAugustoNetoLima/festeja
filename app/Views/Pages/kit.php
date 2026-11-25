<?php
// Página de teste: mostra todos os componentes com dados reais (catálogo, artistas, FAQ).
// Só em dev: public/index.php responde 404 nos outros ambientes.

use App\Helpers\Component;
use App\Helpers\Format;
use App\Services\Content;
use App\Services\TickettService;

$festival = Content::get('festival');
$artistas = Content::get('artistas');
$grupos = Content::get('faq')['grupos'];

$tickett = new TickettService(config('tickett'));
$catalogo = $tickett->getCatalog();
$checkoutUrl = $catalogo['event']['checkout_url'] ?? $tickett->checkoutUrl();

$minPrice = $tickett->minPrice();

$primeiroDia = $festival['dias'][0];
$alvo = $primeiroDia['data'] . 'T' . $primeiroDia['portas'] . ':00+01:00';

// Cada artista aparece só no dia principal (os extra estão em "dias").
$artistasDoDia = static fn (string $id): array => array_values(
    array_filter($artistas, static fn (array $a): bool => $a['dia'] === $id)
);
?>
<main id="conteudo" class="wrap kit">
  <h1 class="kit__title">Kit de componentes</h1>
  <p class="kit__intro">Página de teste, só em dev. Cada bloco mostra um componente com dados reais.</p>

  <?= Component::render('Breadcrumbs', ['items' => [['label' => 'Início', 'href' => '/'], ['label' => 'Kit']]]) ?>

  <section class="section" aria-labelledby="kit-simbolos">
    <h2 id="kit-simbolos" class="kit__h2">Símbolos (Symbol)</h2>
    <ul class="kit__symbols" role="list">
      <?php for ($n = 1; $n <= 24; $n++): ?>
      <li><?= Component::render('Symbol', ['n' => $n, 'size' => 48]) ?></li>
      <?php endfor; ?>
    </ul>
  </section>

  <section class="section" aria-labelledby="kit-mandala">
    <h2 id="kit-mandala" class="kit__h2">Mandala</h2>
    <div class="kit__mandala">
      <?= Component::render('Mandala') ?>
    </div>
  </section>

  <section class="section" aria-labelledby="kit-cabecalho">
    <h2 id="kit-cabecalho" class="kit__h2">Cabeçalho de secção (SectionHead)</h2>
    <?= Component::render('SectionHead', ['eyebrow' => 'Cartaz', 'title' => str_replace('{edicao}', $festival['edicao'], Content::get('home')['cartaz']['titulo']), 'lead' => 'Os nomes estão por ordem da organização.', 'symbol' => 7]) ?>
    <?= Component::render('SectionHead', ['eyebrow' => 'Bilhetes', 'title' => 'Garante o teu lugar', 'symbol' => 3, 'align' => 'center']) ?>
  </section>

  <section class="section" aria-labelledby="kit-marquee">
    <h2 id="kit-marquee" class="kit__h2">Faixa em movimento (Marquee)</h2>
    <?= Component::render('Marquee', ['items' => array_map(static fn (array $a): string => mb_strtoupper($a['nome']), array_slice($artistas, 0, 8))]) ?>
  </section>

  <section class="section" aria-labelledby="kit-contagem">
    <h2 id="kit-contagem" class="kit__h2">Contagem (Countdown)</h2>
    <?= Component::render('Countdown', ['target' => $alvo]) ?>
  </section>

  <?= Component::render('FactsStrip', ['minPrice' => $minPrice]) ?>

  <section class="section" data-theme="noite" aria-labelledby="kit-bilhetes">
    <div class="wrap">
      <?= Component::render('SectionHead', ['eyebrow' => 'Bilhetes', 'title' => 'Garante o teu lugar', 'lead' => ($tickett->taxaIncluida() ? 'Preços com taxas incluídas.' : 'Acresce a taxa de serviço da Tickett, indicada no checkout.') . ' Pagamento seguro pela Tickett.', 'symbol' => 12]) ?>
      <h2 id="kit-bilhetes" class="fj-visually-hidden">Bilhetes</h2>
      <?= Component::render('TicketGrid', ['catalog' => $catalogo, 'checkoutUrl' => $checkoutUrl]) ?>
    </div>
  </section>

  <section class="section" aria-labelledby="kit-bilhetes-vazio">
    <h2 id="kit-bilhetes-vazio" class="kit__h2">Bilhetes sem catálogo (TicketGrid, fallback)</h2>
    <?= Component::render('TicketGrid', [
        'catalog' => ['ok' => false, 'stale' => false, 'event' => null, 'products' => []],
        'checkoutUrl' => $checkoutUrl,
    ]) ?>
  </section>

  <section class="section" aria-labelledby="kit-cartaz">
    <h2 id="kit-cartaz" class="kit__h2">Cartaz por dia (LineupDay)</h2>
    <div class="grid-12">
      <?php foreach ($festival['dias'] as $dia): ?>
      <?= Component::render('LineupDay', ['dia' => $dia, 'artistas' => $artistasDoDia($dia['id']), 'hover' => true]) ?>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" aria-labelledby="kit-artistas">
    <h2 id="kit-artistas" class="kit__h2">Artistas (ArtistCard)</h2>
    <ul class="fj-artists" role="list">
      <?php foreach ($artistas as $artista): ?>
      <?= Component::render('ArtistCard', ['artista' => $artista]) ?>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="section" aria-labelledby="kit-perguntas">
    <h2 id="kit-perguntas" class="kit__h2">Perguntas frequentes (Faq)</h2>
    <?php foreach ($grupos as $grupo): ?>
    <h3 class="kit__h3"><?= e($grupo['titulo']) ?></h3>
    <?= Component::render('Faq', ['perguntas' => $grupo['perguntas'], 'id' => 'faq-' . $grupo['id']]) ?>
    <?php endforeach; ?>
  </section>
</main>
