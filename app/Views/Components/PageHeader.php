<?php
// Cabeçalho de página interna: migalhas, eyebrow, h1 (display-1) e lead opcional. Grelha decorativa de símbolos à direita
// no desktop: três símbolos diferentes, escolhidos pelo título (Simbolo::trio), repetidos em 2 × 3.
// Props: items (migalhas, ver Breadcrumbs), eyebrow, title, lead (opcional), theme (opcional, ex.: 'noite').

use App\Helpers\Component;
use App\Helpers\Simbolo;

$items = $items ?? [];
$eyebrow = $eyebrow ?? '';
$title = $title ?? '';
$lead = $lead ?? '';
$theme = $theme ?? null;
$trio = Simbolo::trio($title);
?>
<header class="page-header"<?= $theme !== null ? ' data-theme="' . e($theme) . '"' : '' ?>>
  <div class="wrap page-header__inner">
    <?php if ($items !== []): ?>
    <?= Component::render('Breadcrumbs', ['items' => $items]) ?>
    <?php endif; ?>
    <div class="page-header__corpo">
      <div class="page-header__text">
        <?php if ($eyebrow !== ''): ?>
        <p class="fj-eyebrow"><?= e($eyebrow) ?></p>
        <?php endif; ?>
        <h1 class="display-1 page-header__title"><?= e($title) ?></h1>
        <?php if ($lead !== ''): ?>
        <p class="page-header__lead"><?= e($lead) ?></p>
        <?php endif; ?>
      </div>
      <?= Component::render('SymbolGrid', ['simbolos' => array_merge($trio, array_reverse($trio)), 'size' => 56, 'class' => 'page-header__simbolos', 'mov' => true]) ?>
    </div>
  </div>
</header>
