<?php
// Cabeçalho de secção: eyebrow (com símbolo opcional à esquerda), título e lead opcional.
// Props: eyebrow, title, lead (opcional), level (2), symbol (n do símbolo, opcional), align ('start' | 'center'), id (no título).

use App\Helpers\Component;

$level = min(6, max(1, (int) ($level ?? 2)));
$align = ($align ?? 'start') === 'center' ? 'center' : 'start';
$id = $id ?? null;
$eyebrow = $eyebrow ?? '';
$lead = $lead ?? '';
$symbol = $symbol ?? null;
$title = $title ?? '';
?>
<div class="section-head section-head--<?= e($align) ?>" data-mov="revelar">
  <?php if ($eyebrow !== ''): ?>
  <p class="fj-eyebrow section-head__eyebrow">
    <?php if ($symbol !== null): ?><?= Component::render('Symbol', ['n' => (int) $symbol, 'size' => 28, 'mov' => true]) ?><?php endif; ?>
    <span><?= e($eyebrow) ?></span>
  </p>
  <?php endif; ?>
  <h<?= $level ?> class="section-head__title"<?= $id !== null ? ' id="' . e($id) . '"' : '' ?>><?= e($title) ?></h<?= $level ?>>
  <?php if ($lead !== ''): ?>
  <p class="section-head__lead"><?= e($lead) ?></p>
  <?php endif; ?>
</div>
