<?php
// Cartaz de um dia: cabeças de cartaz grandes, depois os restantes em tamanhos decrescentes.
// Um <ul> por nível de destaque: o nível 1 fica um nome por linha, sem pontos; os níveis 2 e 3 correm em linha,
// centrados, com um ponto só entre nomes do mesmo nível (nunca depois do último).
// Props: dia (item de festival.json, dias[]), artistas (já filtrados para este dia, na ordem da organização),
// hover (bool: ativa a pré-visualização do retrato no desktop, ver lineup-hover.js).
// Artistas confirmados nos últimos 14 dias levam o texto "Novo" depois do nome (fora do link).
// data-simbolo: o símbolo da pré-visualização quando não há foto (lineup-hover.js), escolhido pelo slug como em ArtistCard.

use App\Helpers\Format;
use App\Helpers\Rotas;
use App\Helpers\Simbolo;

$dia = $dia ?? [];
$artistas = $artistas ?? [];
$hover = !empty($hover);

$titulo = Format::date($dia['data'], 'weekday');
$portas = Format::time($dia['portas']);
$idTitulo = (string) $dia['id'];

$niveis = [1 => [], 2 => [], 3 => []];
foreach ($artistas as $artista) {
    $niveis[min(3, max(1, (int) $artista['destaque']))][] = $artista;
}
?>
<section class="lineup-day" aria-labelledby="<?= e($idTitulo) ?>"<?= $hover ? ' data-lineup-hover' : '' ?>>
  <header class="lineup-day__head">
    <h3 id="<?= e($idTitulo) ?>" class="lineup-day__title"><time datetime="<?= e($dia['data']) ?>"><?= e($titulo) ?></time></h3>
    <p class="lineup-day__portas"><?= e(t('lineup.portas', ['hora' => $portas])) ?><?php if (!empty($dia['rever'])): ?> <span class="lineup-day__note"><?= e(t('geral.a_confirmar')) ?></span><?php endif; ?></p>
  </header>

  <div class="lineup-day__niveis" data-mov="escalonar" data-mov-estilo="mascara" data-mov-alvo=".lineup-name">
    <?php foreach ($niveis as $nivel => $lista): ?>
    <?php if ($lista === []) { continue; } ?>
    <ul class="lineup-day__list lineup-day__list--<?= $nivel ?>" role="list">
      <?php foreach ($lista as $artista):
          $pais = $artista['pais'] ?? null;
      ?>
      <li class="lineup-name lineup-name--<?= $nivel ?>">
        <a href="<?= e(Rotas::url('artista', ['slug' => $artista['slug']])) ?>"
           data-photo="<?= e($artista['foto'] ?? '') ?>"
           data-simbolo="<?= e(Simbolo::href(Simbolo::um((string) $artista['slug']))) ?>"
           data-country="<?= e($pais ?? '') ?>"
           data-country-name="<?= e(Format::country($pais)) ?>"><?= e($artista['nome']) ?></a><?php if (Format::recente($artista['confirmado_em'] ?? null)): ?><span class="lineup-name__novo"><?= e(t('geral.novo')) ?></span><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </div>
</section>
