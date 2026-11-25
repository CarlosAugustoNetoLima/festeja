<?php
// Secção "O festival" da página inicial: frase grande com os países do cartaz e três notas curtas.
// Textos em content/home.json (festival). Países com "destaque": true saem a laranja, os outros a ink.
// Nota com "rever": comentário HTML para a equipa (o texto não muda). Os símbolos são da linha gráfica, por ordem.

use App\Helpers\Component;
use App\Services\Content;

$festival = Content::get('home')['festival'];
$paises = $festival['paises'];
$ultimo = count($paises) - 1;
$simbolos = [9, 14, 21];
?>
<section class="section home-festival" aria-labelledby="festival-titulo">
  <div class="wrap">
    <h2 id="festival-titulo" class="home-festival__title" data-mov="palavras">
      <?php foreach ($paises as $i => $pais): ?>
      <span class="<?= $pais['destaque'] ? 'home-festival__pais--laranja' : 'home-festival__pais--ink' ?>"><?= e($pais['nome']) ?></span><?= $i < $ultimo ? ', ' : e(t('lista.e')) ?>
      <?php endforeach; ?>
      <span class="home-festival__pais--ink"><?= e($festival['fecho']['nome']) ?></span> <?= e($festival['fecho']['texto']) ?>
    </h2>

    <ul class="home-festival__notas" role="list" data-mov="escalonar">
      <?php foreach ($festival['notas'] as $i => $nota): ?>
      <?php if (!empty($nota['rever'])): ?>
      <!-- rever com a organização -->
      <?php endif; ?>
      <li class="home-festival__nota">
        <?= Component::render('Symbol', ['n' => $simbolos[$i] ?? 9, 'size' => 40]) ?>
        <h3 class="home-festival__nota-titulo"><?= e($nota['titulo']) ?></h3>
        <p class="home-festival__nota-texto"><?= e($nota['texto']) ?></p>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
