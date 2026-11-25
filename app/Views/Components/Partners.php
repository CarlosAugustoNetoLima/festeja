<?php
// Parceiros: logótipos em grelha, agrupados por tema. Dados em content/parceiros.json:
//   {"grupos": [{"titulo": "...", "parceiros": [{"nome": "...", "logo": "/assets/img/parceiros/x.png", "url": "https://..."}]}]}
// "url" é opcional (o logótipo fica então sem link). Sem nenhum grupo com parceiros, o componente não escreve nada.
// Os logótipos saem tal como vêm, a 48 px de altura (home.css, .partners__lista img).

use App\Services\Content;

$grupos = [];
foreach (Content::get('parceiros')['grupos'] ?? [] as $grupo) {
    $parceiros = array_values(array_filter(
        $grupo['parceiros'] ?? [],
        static fn (array $p): bool => ($p['nome'] ?? '') !== '' && ($p['logo'] ?? '') !== ''
    ));
    if ($parceiros !== []) {
        $grupos[] = ['titulo' => (string) ($grupo['titulo'] ?? ''), 'parceiros' => $parceiros];
    }
}
?>
<?php if ($grupos !== []): ?>
<section class="section partners" aria-labelledby="parceiros-titulo">
  <div class="wrap">
    <h2 id="parceiros-titulo" class="fj-visually-hidden"><?= e(t('parceiros.titulo')) ?></h2>
    <?php foreach ($grupos as $grupo): ?>
    <div class="partners__grupo">
      <?php if ($grupo['titulo'] !== ''): ?>
      <h3 class="partners__titulo"><?= e($grupo['titulo']) ?></h3>
      <?php endif; ?>
      <ul class="partners__lista" role="list">
        <?php foreach ($grupo['parceiros'] as $parceiro):
            $logo = '<img src="' . e($parceiro['logo']) . '" alt="' . e($parceiro['nome']) . '" height="48" loading="lazy" decoding="async">';
        ?>
        <li class="partners__item">
          <?php if (!empty($parceiro['url'])): ?>
          <a href="<?= e($parceiro['url']) ?>" rel="noopener"><?= $logo ?></a>
          <?php else: ?>
          <?= $logo ?>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
