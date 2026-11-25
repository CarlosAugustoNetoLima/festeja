<?php
// Factos rápidos do festival, lidos de content/festival.json. É o bloco que motores de pesquisa e assistentes citam.
// Props: minPrice (float ou null): preço "desde". Sem preço mostra "Em breve".

use App\Helpers\Format;
use App\Services\Content;

$festival = Content::get('festival');
$minPrice = $minPrice ?? null;

$datas = Format::range($festival['datas']['inicio'], $festival['datas']['fim']);

// Mesma hora em todos os dias: uma linha só ("16h00 nos dois dias") e a nota "(a confirmar)" uma vez.
$dias = $festival['dias'];
$horas = array_values(array_unique(array_column($dias, 'portas')));
$aConfirmar = array_filter(array_column($dias, 'rever')) !== [];

$portas = '';
if (count($horas) === 1) {
    $sufixo = match (count($dias)) {
        1 => '',
        2 => t('factos.dois_dias'),
        default => t('factos.todos_dias'),
    };
    $portas = '<span class="facts__line">' . e(Format::time($horas[0]) . $sufixo);
    if ($aConfirmar) {
        $portas .= ' <span class="facts__note">' . e(t('geral.a_confirmar')) . '</span>';
    }
    $portas .= '</span>';
} else {
    foreach ($dias as $dia) {
        $portas .= '<span class="facts__line">' . e(Format::time($dia['portas']));
        if (!empty($dia['rever'])) {
            $portas .= ' <span class="facts__note">' . e(t('geral.a_confirmar')) . '</span>';
        }
        $portas .= '</span>';
    }
}

$local = $festival['local'];
$taxa = $minPrice !== null && !(new \App\Services\TickettService(config('tickett')))->taxaIncluida();
$bilhetes = $minPrice !== null ? t('factos.desde', ['preco' => Format::price($minPrice)]) : t('factos.em_breve');
?>
<section class="facts" aria-labelledby="factos-titulo">
  <div class="wrap">
    <h2 id="factos-titulo" class="fj-visually-hidden"><?= e(t('factos.titulo')) ?></h2>
    <dl class="facts__list" data-mov="escalonar" data-mov-estilo="facto" data-mov-alvo=".facts__item">
      <div class="facts__item">
        <dt><?= e(t('factos.datas')) ?></dt>
        <dd><?= e($datas) ?></dd>
      </div>
      <div class="facts__item">
        <dt><?= e(t('factos.portas')) ?></dt>
        <dd><?= $portas ?></dd>
      </div>
      <div class="facts__item">
        <dt><?= e(t('factos.local')) ?></dt>
        <dd><?= e($local['nome']) ?><span class="facts__note"><?= e($local['zona']) ?></span></dd>
      </div>
      <div class="facts__item">
        <dt><?= e(t('factos.bilhetes')) ?></dt>
        <dd><?= e($bilhetes) ?><?php if ($taxa): ?><span class="facts__note"><?= e(t('taxa.mais')) ?></span><?php endif; ?></dd>
      </div>
      <div class="facts__item">
        <dt><?= e(t('factos.organizacao')) ?></dt>
        <dd><?= e($festival['organizador']['nome']) ?></dd>
      </div>
    </dl>
  </div>
</section>
