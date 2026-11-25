<?php
// Rodapé (tema Noite): símbolo pequeno, nome, mote, datas e local; navegação; redes; produção e direitos.
// O símbolo 19 e não a mandala: no Noite a mandala perde as raias verdes (design-system, Logótipo).
// Espaço extra no fundo no mobile para a StickyBuyBar não tapar o conteúdo.

use App\Helpers\Component;
use App\Helpers\Format;
use App\Helpers\Icon;
use App\Helpers\Rotas;
use App\Services\Content;

$festival = Content::get('festival');
$producao = implode(', ', $festival['producao'] ?? []);
$instagram = $festival['redes']['instagram'] ?? null;
$datas = Format::range($festival['datas']['inicio'], $festival['datas']['fim']);

$links = [
    ['href' => Rotas::url('bilhetes'), 'label' => t('nav.bilhetes')],
    ['href' => Rotas::url('cartaz'), 'label' => t('nav.cartaz')],
    ['href' => Rotas::url('informacoes'), 'label' => t('nav.informacoes')],
    ['href' => Rotas::url('como-chegar'), 'label' => t('nav.como-chegar')],
    ['href' => Rotas::url('acessibilidade'), 'label' => t('nav.acessibilidade')],
    ['href' => Rotas::url('privacidade'), 'label' => t('nav.privacidade')],
    ['href' => Rotas::url('cookies'), 'label' => t('nav.cookies')],
    ['href' => Rotas::url('termos'), 'label' => t('nav.termos')],
];
?>
<footer data-theme="noite" class="site-footer">
  <div class="wrap site-footer__grid">
    <div class="site-footer__brand">
      <div class="site-footer__simbolo"><?= Component::render('Symbol', ['n' => 10, 'size' => 56]) ?></div>
      <p class="site-footer__name"><?= e($festival['nome']) ?></p>
      <p class="site-footer__mote"><?= e($festival['mote']) ?></p>
      <p class="site-footer__meta"><?= e($datas) ?> · <?= e($festival['local']['nome']) ?>, <?= e($festival['local']['zona']) ?></p>
    </div>

    <nav class="site-footer__nav" aria-label="<?= e(t('nav.rodape')) ?>">
      <ul class="site-footer__list" role="list">
        <?php foreach ($links as $link): ?>
        <li><a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="site-footer__social">
      <p class="site-footer__label"><?= e(t('footer.segue')) ?></p>
      <ul class="site-footer__list" role="list">
        <?php if ($instagram !== null): ?>
        <li><a href="<?= e($instagram) ?>" rel="noopener"><?= Icon::svg('instagram') ?><?= e(t('footer.instagram')) ?></a></li>
        <?php endif; ?>
      </ul>
      <p class="site-footer__tickets"><?= e(t('footer.bilhetes_tickett')) ?></p>
    </div>
  </div>

  <div class="wrap site-footer__bottom">
    <p>© <?= e($festival['edicao']) ?> <?= e($festival['marca']) ?></p>
    <?php if ($producao !== ''): ?>
    <p><?= e(t('footer.producao', ['nomes' => $producao])) ?></p>
    <?php endif; ?>
  </div>
</footer>
