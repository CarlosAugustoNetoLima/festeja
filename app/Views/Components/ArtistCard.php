<?php
// Cartão de artista: retrato 4:5 (ou, sem foto, fundo liso com um símbolo da marca), nome e país · dia.
// Props: artista (entrada de content/artistas.json). O link do nome cobre o cartão inteiro (::after em components.css).
// Selo "Novo" quando confirmado_em é dos últimos 14 dias (Format::recente, fuso de Lisboa).

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Rotas;
use App\Helpers\Simbolo;
use App\Services\Content;

$artista = $artista ?? [];
$festival = Content::get('festival');
$nome = (string) $artista['nome'];
$novo = Format::recente($artista['confirmado_em'] ?? null);

// Dias de atuação: o dia principal e os extra (ex.: Kizomba na Rua), pelo nome do dia da semana.
$datasPorId = [];
foreach ($festival['dias'] as $dia) {
    $datasPorId[$dia['id']] = $dia['data'];
}
$idsDias = array_values(array_unique(array_merge([$artista['dia']], $artista['dias'] ?? [])));
$nomesDias = [];
foreach ($idsDias as $idDia) {
    if (isset($datasPorId[$idDia])) {
        $nomesDias[] = explode(',', Format::date($datasPorId[$idDia], 'weekday'))[0];
    }
}

$pais = Format::country($artista['pais'] ?? null);
$meta = implode(' · ', array_filter([$pais, implode(' e ', $nomesDias)], static fn (string $s): bool => $s !== ''));
?>
<li class="fj-artist artist-card">
  <div class="fj-artist__media">
    <?php if ($novo): ?>
    <span class="fj-badge artist-card__novo"><?= e(t('geral.novo')) ?></span>
    <?php endif; ?>
    <?php if (!empty($artista['foto'])): ?>
    <img src="<?= e($artista['foto']) ?>" alt="<?= e($nome) ?> em palco" loading="lazy" decoding="async">
    <?php else: ?>
    <span class="artist-card__fallback" aria-hidden="true">
      <?= Component::render('Symbol', ['n' => Simbolo::um((string) $artista['slug']), 'size' => 160]) ?>
    </span>
    <?php endif; ?>
  </div>
  <h3 class="fj-artist__name"><a href="<?= e(Rotas::url('artista', ['slug' => $artista['slug']])) ?>"><?= e($nome) ?></a></h3>
  <?php if ($meta !== ''): ?>
  <p class="fj-artist__meta"><?= e($meta) ?></p>
  <?php endif; ?>
</li>
