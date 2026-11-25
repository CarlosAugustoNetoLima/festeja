<!doctype html>
<html lang="<?= idioma() === 'en' ? 'en' : 'pt-PT' ?>" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if (\App\Services\TickettService::emDemo(config('tickett'))): ?>
  <meta name="robots" content="noindex,nofollow">
  <?php endif; ?>
  <script>document.documentElement.classList.replace('no-js', 'js');</script>
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <?php foreach ($alternates ?? [] as $hreflang => $href): ?>
  <link rel="alternate" hreflang="<?= e($hreflang) ?>" href="<?= e($href) ?>">
  <?php endforeach; ?>
  <meta name="theme-color" content="<?= e($theme_color) ?>">

  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:locale" content="<?= idioma() === 'en' ? 'en_GB' : 'pt_PT' ?>">
  <?php if (!empty($alternates)): ?>
  <meta property="og:locale:alternate" content="<?= idioma() === 'en' ? 'pt_PT' : 'en_GB' ?>">
  <?php endif; ?>
  <meta property="og:image" content="<?= e(config('base_url') . asset('img/og.jpg')) ?>">
  <meta name="twitter:card" content="summary_large_image">

  <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('img/apple-touch-icon.png')) ?>">

  <?= \App\Helpers\Estilos::links(($page ?? null) === 'home') ?>
</head>
<body>
  <a class="skip-link" href="#conteudo"><?= e(t('geral.saltar')) ?></a>

  <?= \App\Helpers\Component::render('SiteHeader', ['active' => $nav_active ?? $page ?? null, 'alternativo' => $alternativo ?? null]) ?>

  <?= $content ?>

  <?= \App\Helpers\Component::render('SiteFooter') ?>

  <?php if (($buybar ?? true) !== false): ?>
  <?= \App\Helpers\Component::render('StickyBuyBar') ?>
  <?php endif; ?>

  <?php if (is_file(ROOT . '/app/Views/Components/CheckoutModal.php') && \App\Services\TickettService::temEvento(config('tickett'))): ?>
  <?= \App\Helpers\Component::render('CheckoutModal', ['idioma' => idioma()]) ?>
  <?php endif; ?>


  <?php if (!empty($jsonld)): ?>
    <?php foreach ($jsonld as $block): ?>
  <script type="application/ld+json"><?= json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php endforeach; ?>
  <?php endif; ?>

  <?= $scripts ?? '' ?>
  <script type="module" src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
