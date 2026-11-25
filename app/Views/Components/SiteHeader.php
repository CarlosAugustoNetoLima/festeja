<?php
// Cabeçalho do site: logótipo, navegação principal, seletor de idioma (PT | EN), carrinho, compra e menu móvel (< 1024px).
// Props: active (chave do item atual: 'cartaz', 'bilhetes', 'informacoes', 'como-chegar'),
// alternativo (['pt' => caminho, 'en' => caminho]: a página no outro idioma, do seletor; ver App\Helpers\Rotas::trocaIdioma).
// Comportamento em public/assets/js/header.js. Contagem do carrinho em checkout.js.

use App\Helpers\Icon;
use App\Helpers\Rotas;
use App\Services\Content;
use App\Services\TickettService;

$active = $active ?? null;
// Sem evento na Tickett, os botões de compra são só links para /bilhetes (sem abrir o checkout).
$dataCompra = TickettService::temEvento(config('tickett')) ? ' data-checkout-open' : '';
$festival = Content::get('festival');
$alternativo = $alternativo ?? Rotas::trocaIdioma('/');
$lang = idioma();

$itens = [
    ['href' => Rotas::url('cartaz'), 'label' => t('nav.cartaz'), 'key' => 'cartaz'],
    ['href' => Rotas::url('bilhetes'), 'label' => t('nav.bilhetes'), 'key' => 'bilhetes'],
    ['href' => Rotas::url('informacoes'), 'label' => t('nav.informacoes'), 'key' => 'informacoes'],
    ['href' => Rotas::url('como-chegar'), 'label' => t('nav.como-chegar'), 'key' => 'como-chegar'],
];

// Logótipo: a altura é 44px (desktop) e o width sai do viewBox do ficheiro.
$logoFile = ROOT . '/public/assets/img/logo/logo-horizontal.svg';
$logoRatio = 2592 / 812;
if (is_file($logoFile)
    && preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', (string) file_get_contents($logoFile), $vb) === 1
    && (float) $vb[2] > 0) {
    $logoRatio = (float) $vb[1] / (float) $vb[2];
}
$logoAltura = 44;
$logoLargura = (int) round($logoAltura * $logoRatio);

$atual = static fn (string $key): string => $active === $key ? ' aria-current="page"' : '';

// Seletor de idioma: o idioma da página vai marcado (aria-current); cada link leva à página no outro idioma.
$seletor = static function (string $classe) use ($alternativo, $lang): string {
    $html = '<nav class="' . $classe . '" aria-label="' . e(t('nav.idioma')) . '"><ul role="list">';
    foreach (['pt' => ['PT', 'pt-PT'], 'en' => ['EN', 'en']] as $codigo => [$rotulo, $hreflang]) {
        $marca = $codigo === $lang ? ' aria-current="true"' : '';
        $html .= '<li><a href="' . e($alternativo[$codigo]) . '" hreflang="' . $hreflang . '" lang="' . $hreflang . '"' . $marca . '>' . $rotulo . '</a></li>';
    }
    return $html . '</ul></nav>';
};
?>
<header class="site-header" data-header>
  <div class="wrap site-header__inner">
    <a class="site-header__logo" href="<?= e(Rotas::url('inicio')) ?>" aria-label="<?= e(t('nav.logo')) ?>">
      <img src="<?= e(asset('img/logo/logo-horizontal.svg')) ?>" alt="" width="<?= $logoLargura ?>" height="<?= $logoAltura ?>">
    </a>

    <nav class="site-header__nav" aria-label="<?= e(t('nav.principal')) ?>">
      <ul class="site-header__list">
        <?php foreach ($itens as $item): ?>
        <li><a href="<?= e($item['href']) ?>"<?= $atual($item['key']) ?>><?= e($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="site-header__actions">
      <?= $seletor('site-header__idioma') ?>
      <a class="site-header__cart" href="#" data-cart-count hidden>
        <?= Icon::svg('shopping-bag', ['class' => 'site-header__cart-icon']) ?>
        <span class="site-header__cart-count">0</span>
        <span class="fj-visually-hidden"><?= e(t('carrinho.bilhetes')) ?></span>
      </a>
      <a class="fj-btn fj-btn--primary site-header__buy" href="<?= e(Rotas::url('bilhetes')) ?>"<?= $dataCompra ?>><?= e(t('botao.comprar_bilhetes')) ?></a>
      <a class="fj-btn fj-btn--primary site-header__buy-compact" href="<?= e(Rotas::url('bilhetes')) ?>"<?= $dataCompra ?>><?= e(t('nav.bilhetes')) ?></a>
      <button type="button" class="site-header__menu-btn" aria-expanded="false" aria-controls="menu-movel" data-menu-toggle>
        <?= Icon::svg('menu', ['class' => 'icon-menu']) ?>
        <?= Icon::svg('x', ['class' => 'icon-x']) ?>
        <span class="fj-visually-hidden" data-menu-label data-rotulo-abrir="<?= e(t('menu.abrir')) ?>" data-rotulo-fechar="<?= e(t('menu.fechar')) ?>"><?= e(t('menu.abrir')) ?></span>
      </button>
    </div>
  </div>

  <div id="menu-movel" class="mobile-menu" data-mobile-menu hidden>
    <nav aria-label="<?= e(t('nav.principal')) ?>">
      <ul class="mobile-menu__list" role="list">
        <?php foreach ($itens as $item): ?>
        <li><a href="<?= e($item['href']) ?>"<?= $atual($item['key']) ?>><?= e($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="mobile-menu__foot">
      <a class="fj-btn fj-btn--primary fj-btn--lg fj-btn--block" href="<?= e(Rotas::url('bilhetes')) ?>"<?= $dataCompra ?>><?= e(t('botao.comprar_bilhetes')) ?></a>
      <?= $seletor('mobile-menu__idioma') ?>
      <ul class="mobile-menu__social" role="list">
        <li><a href="<?= e($festival['redes']['instagram'] ?? '#') ?>" rel="noopener"><?= e(t('social.instagram_festival')) ?></a></li>
      </ul>
    </div>
  </div>
</header>
