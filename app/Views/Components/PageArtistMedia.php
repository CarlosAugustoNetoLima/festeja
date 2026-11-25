<?php
// Fotografia do artista na página do artista: retrato 4:5 ou, sem foto, fundo liso com um símbolo da marca (como ArtistCard).
// Props: artista (entrada de content/artistas.json).

use App\Helpers\Component;
use App\Helpers\Simbolo;

$artista = $artista ?? [];
$nome = (string) $artista['nome'];
?>
<div class="page-artist-media">
  <?php if (!empty($artista['foto'])): ?>
  <img src="<?= e($artista['foto']) ?>" alt="<?= e(t('artista.foto_alt', ['nome' => $nome])) ?>" decoding="async">
  <?php else: ?>
  <span class="page-artist-media__fallback" aria-hidden="true">
    <?= Component::render('Symbol', ['n' => Simbolo::um((string) $artista['slug']), 'size' => 240]) ?>
  </span>
  <?php endif; ?>
</div>
