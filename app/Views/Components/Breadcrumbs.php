<?php
// Migalhas de pão para páginas internas.
// Props: items (lista de ['label' => texto, 'href' => url ou null]). O último item é a página atual (aria-current).

$items = $items ?? [];
$ultimo = count($items) - 1;
?>
<nav class="breadcrumbs" aria-label="<?= e(t('nav.caminho')) ?>">
  <ol class="breadcrumbs__list">
    <?php foreach ($items as $i => $item): ?>
    <li class="breadcrumbs__item">
      <?php if ($i === $ultimo || empty($item['href'])): ?>
      <span aria-current="page"><?= e($item['label']) ?></span>
      <?php else: ?>
      <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
