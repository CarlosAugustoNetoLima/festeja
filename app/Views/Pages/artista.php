<?php
// Página de um artista: fotografia, dia e hora, bio, links oficiais, bilhete desse dia e outros artistas do mesmo dia.
// Variável: $artista (entrada de content/artistas.json, ver public/index.php).

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Icon;
use App\Helpers\Rotas;
use App\Services\Content;
use App\Services\TickettService;

$festival = Content::get('festival');
$artistas = Content::get('artistas');

$diasPorId = [];
foreach ($festival['dias'] as $dia) {
    $diasPorId[$dia['id']] = $dia;
}
$diaPrincipal = $diasPorId[$artista['dia']];

// Dias de atuação: o dia principal e os extra (ex.: Kizomba na Rua), na ordem do festival.
$idsDias = array_values(array_unique(array_merge([$artista['dia']], $artista['dias'] ?? [])));
$diasArtista = array_values(array_filter(
    $festival['dias'],
    static fn (array $dia): bool => in_array($dia['id'], $idsDias, true)
));
$nomesDias = implode(t('lista.e'), array_map(
    static fn (array $dia): string => Format::date($dia['data'], 'weekday'),
    $diasArtista
));

$pais = Format::country($artista['pais'] ?? null);
$bio = trim((string) ($artista['bio'] ?? ''));
$links = $artista['links'] ?? [];
$rotulosLinks = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook',
    'youtube' => 'YouTube',
    'spotify' => 'Spotify',
    'site' => t('artista.site_oficial'),
    'website' => t('artista.site_oficial'),
];

// Bilhete desse dia: o primeiro produto à venda cujo "days" inclui a data do dia principal.
$catalogo = (new TickettService(config('tickett')))->getCatalog();
$produto = null;
foreach ($catalogo['products'] as $item) {
    if (in_array($item['status'], ['available', 'low'], true) && in_array($diaPrincipal['data'], $item['days'], true)) {
        $produto = $item;
        break;
    }
}
$compraHref = isset($produto['checkout_url'])
    ? TickettService::comIdioma($produto['checkout_url'])
    : Rotas::url('bilhetes');
$compraProduto = ($produto['produto'] ?? '') !== '' ? ' data-product="' . e((string) $produto['produto']) . '"' : '';
// "23 julho": a data curta sai do nome do dia ("Sexta, 23 julho" / "Friday, 23 July").
$diaCurto = trim(explode(',', Format::date($diaPrincipal['data'], 'weekday'))[1]);

$mesmoDia = array_values(array_filter(
    $artistas,
    static fn (array $a): bool => $a['dia'] === $artista['dia'] && $a['slug'] !== $artista['slug']
));
$mesmoDia = array_slice($mesmoDia, 0, 6);
?>
<main id="conteudo">
  <div class="wrap artista">
    <div class="artista__grid">
      <?= Component::render('PageArtistMedia', ['artista' => $artista]) ?>

      <div class="artista__info">
        <?= Component::render('Breadcrumbs', ['items' => [
            ['label' => t('nav.inicio'), 'href' => Rotas::url('inicio')],
            ['label' => t('nav.cartaz'), 'href' => Rotas::url('cartaz')],
            ['label' => $artista['nome']],
        ]]) ?>

        <?php if ($pais !== ''): ?>
        <p class="fj-eyebrow"><?= e($pais) ?></p>
        <?php endif; ?>

        <h1 class="display-1 artista__name"><?= e($artista['nome']) ?></h1>

        <dl class="artista__facts">
          <div class="artista__fact">
            <dt><?= e(t('artista.dia')) ?></dt>
            <dd><?= e($nomesDias) ?></dd>
          </div>
          <div class="artista__fact">
            <dt><?= e(t('factos.portas')) ?></dt>
            <dd>
              <?php foreach ($diasArtista as $dia): ?>
              <span class="artista__line"><?= e(Format::time($dia['portas'])) ?><?php if (!empty($dia['rever'])): ?> <span class="artista__note"><?= e(t('geral.a_confirmar')) ?></span><?php endif; ?></span>
              <?php endforeach; ?>
            </dd>
          </div>
          <div class="artista__fact">
            <dt><?= e(t('factos.local')) ?></dt>
            <dd><?= e($festival['local']['nome']) ?>, <?= e($festival['local']['zona']) ?></dd>
          </div>
        </dl>

        <?php if ($bio !== ''): ?>
        <p class="artista__bio"><?= e($bio) ?></p>
        <?php else: ?>
        <p class="artista__bio artista__bio--vazio"><?= e(t('artista.bio_vazia')) ?></p>
        <?php endif; ?>

        <?php if ($links !== []): ?>
        <nav class="artista__links" aria-label="<?= e(t('artista.links_oficiais')) ?>">
          <ul role="list">
            <?php foreach ($links as $chave => $url): ?>
            <li>
              <a href="<?= e($url) ?>" rel="noopener">
                <?= in_array($chave, ['instagram', 'facebook', 'youtube'], true) ? Icon::svg($chave) : Icon::svg('external-link') ?>
                <?= e($rotulosLinks[$chave] ?? ucfirst((string) $chave)) ?>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </nav>
        <?php endif; ?>

        <div class="btn-row">
          <a class="fj-btn fj-btn--primary fj-btn--lg" href="<?= e($compraHref) ?>" data-checkout-open<?= $compraProduto ?>><?= e(t('botao.comprar_para', ['dia' => $diaCurto])) ?></a>
        </div>
      </div>
    </div>

    <?php if ($mesmoDia !== []): ?>
    <section class="artista-related" aria-labelledby="artista-mesmo-dia">
      <h2 id="artista-mesmo-dia" class="artista-related__title"><?= e(t('artista.tambem_no_dia', ['data' => Format::date($diaPrincipal['data'])])) ?></h2>
      <ul class="fj-artists" role="list">
        <?php foreach ($mesmoDia as $outro): ?>
        <?= Component::render('ArtistCard', ['artista' => $outro]) ?>
        <?php endforeach; ?>
      </ul>
      <p class="artista-related__more"><a href="<?= e(Rotas::url('cartaz')) ?>"><?= e(t('artista.ver_cartaz_completo')) ?></a></p>
    </section>
    <?php endif; ?>
  </div>
</main>
