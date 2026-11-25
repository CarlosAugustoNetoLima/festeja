<?php
// Perguntas frequentes em acordeão nativo (details/summary), sem JavaScript.
// Props: perguntas (lista de ['p' => pergunta, 'r' => resposta]), id (no contentor).
// Cada pergunta tem id derivado do texto (ex.: "Posso sair e voltar a entrar?" => #posso-sair-e-voltar-a-entrar).

use App\Helpers\Icon;

$perguntas = $perguntas ?? [];
$id = $id ?? null;
$slug = static function (string $texto): string {
    $texto = strtr(mb_strtolower($texto), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
    ]);
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $texto), '-');
};
?>
<div class="fj-faq" data-mov="escalonar" data-mov-alvo="details"<?= $id !== null ? ' id="' . e($id) . '"' : '' ?>>
  <?php foreach ($perguntas as $item): ?>
  <details id="<?= e($slug($item['p'])) ?>">
    <summary>
      <span class="fj-faq__question"><?= e($item['p']) ?></span>
      <?= Icon::svg('chevron-down', ['class' => 'fj-faq__icon']) ?>
    </summary>
    <div class="fj-faq__answer">
      <p><?= e($item['r']) ?></p>
    </div>
  </details>
  <?php endforeach; ?>
</div>
