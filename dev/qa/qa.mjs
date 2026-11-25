#!/usr/bin/env node
// QA do site Festeja com Chrome headless (CDP). Só módulos nativos do Node 22+.
// Uso: node qa.mjs <comando> [...]   (ver README.md ao lado deste ficheiro)
//   compra [1440|390]   fluxo de compra real (home → modal → simulador → purchase, Esc, foco)
//   escolher            botão "Escolher" de um TicketCard abre com o produto destacado
//   checkout-layout     checkout por passo a 1440, 1280, 1024, 768 e 390: scroll do modal, overflow, barras, direita, margens
//   a11y                auditoria por página a 1440 e 390 (DOM + árvore de acessibilidade; alvos AA de 24 px)
//   teclado             skip link, menu móvel (foco preso, Esc), setas nos separadores do cartaz
//   movimento           prefers-reduced-motion (hero, marquee, sem GSAP) e GSAP: nada invisível após rolar, cabeçalho e âncoras
//   semjs               sem JavaScript: conteúdo visível e botões de compra são links
//   overflow            scroll horizontal a 320, 390, 768, 1024 e 1440
//   desempenho          peso, pedidos, JS do site, vendor GSAP (orçamento à parte), LCP e CLS (390 com 4G simulado)
//   seo                 robots, sitemap, llms e HTTP de cada página
//   i18n                pt e en: HTTP, lang, hreflang recíprocos, canonical, seletor, sem texto pt nas páginas en, modal en (?lang=en)
//   fallback            simulador desligado: com cache e sem cache (para o simulador, o que é reversível)
//   sem-evento          produção sem TICKETT_EVENT_ID (servidor temporário, porta 8090): sem checkout, "em breve" em pt e en
//   all                 tudo exceto fallback
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { openBrowser, sleep } from './lib/cdp.mjs';
import { auditPage, axReport } from './lib/audit.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.QA_BASE || 'http://127.0.0.1:8080';
const STUB = process.env.QA_STUB || 'http://127.0.0.1:8081';
const OUT = process.env.QA_OUT || path.join(os.tmpdir(), 'festeja-qa');
const PROFILES = path.join(OUT, 'profiles');
const FRAME = '/checkout'; // iframe same-origin: o proxy do site (app/Services/CheckoutProxy.php)
const PAGES = ['/', '/bilhetes', '/cartaz', '/artistas/paulo-flores', '/artistas/kizomba-na-rua', '/informacoes',
  '/como-chegar', '/acessibilidade', '/404-inexistente'];
const WIDTHS = { desktop: 1440, mobile: 390 };
// Raiz do projeto: QA_PROJECT, ou duas pastas acima deste ficheiro (quando está em dev/qa/).
const PROJECT = process.env.QA_PROJECT || path.resolve(HERE, '../..');
const STUB_DIR = path.join(PROJECT, 'dev/tickett-stub');

fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(PROFILES, { recursive: true });

class Results {
  constructor(name) {
    this.name = name;
    this.items = [];
  }

  check(label, ok, detail = '') {
    const d = detail === undefined || detail === null ? '' : String(detail);
    this.items.push({ label, ok: !!ok, detail: d });
    console.log(`${ok ? 'PASS' : 'FAIL'} [${this.name}] ${label}${d ? ` — ${d.slice(0, 300)}` : ''}`);
    return !!ok;
  }

  save(extra = {}) {
    const file = path.join(OUT, `${this.name.replace(/[^\w-]+/g, '_')}.json`);
    const fails = this.items.filter((i) => !i.ok).length;
    fs.writeFileSync(file, JSON.stringify({ items: this.items, fails, ...extra }, null, 2));
    console.log(`RESUMO [${this.name}]: ${this.items.length - fails}/${this.items.length} ok → ${file}`);
    return fails;
  }
}

const focusDesc = `(() => { const a = document.activeElement; if (!a) return 'nenhum';
  return a.tagName.toLowerCase() + (a.id ? '#' + a.id : '') + (a.className && typeof a.className === 'string' ? '.' + a.className.trim().split(/\\s+/)[0] : '') + ' "' + (a.getAttribute('aria-label') || a.textContent || '').trim().slice(0, 40) + '"'; })()`;

// Vigia de erros: exceções JS, erros de consola e pedidos falhados (ex.: 404 de recursos locais).
// Chamar watch(page) antes de navegar; errors() devolve a lista acumulada desde a última limpeza.
async function watch(page) {
  let list = [];
  await page.send('Log.enable');
  await page.send('Network.enable');
  page.on('Runtime.exceptionThrown', (p) => list.push(`exceção: ${(p.exceptionDetails.exception?.description || p.exceptionDetails.text).split('\n')[0].slice(0, 160)}`));
  page.on('Runtime.consoleAPICalled', (p) => { if (p.type === 'error') list.push(`consola: ${p.args.map((a) => a.value ?? a.description ?? '').join(' ').slice(0, 160)}`); });
  page.on('Log.entryAdded', (p) => { if (p.entry.level === 'error' && !/favicon/.test(p.entry.url || '')) list.push(`log: ${p.entry.text.slice(0, 120)} ${p.entry.url || ''}`); });
  page.on('Network.responseReceived', (p) => { if (p.type !== 'Document' && p.response.status >= 400 && p.response.url.startsWith(BASE)) list.push(`HTTP ${p.response.status}: ${p.response.url.replace(BASE, '')}`); });
  const urls = new Map();
  page.on('Network.requestWillBeSent', (p) => urls.set(p.requestId, p.request.url));
  page.on('Network.loadingFailed', (p) => { if (!/fonts\.g/.test(p.errorText || '')) list.push(`falhou: ${p.errorText} ${(urls.get(p.requestId) || '').slice(0, 90)}`); });
  return {
    errors: () => [...list],
    clear: () => { list = []; },
  };
}

// ---------------------------------------------------------------- compra

// Erros que vêm do checkout ORIGINAL da Tickett (não do site). São listados no relatório, mas não contam como falha.
// Cada um tem o padrão e a causa; se o checkout mudar e o erro desaparecer, a lista pode ser reduzida.
const ERROS_CONHECIDOS_TICKETT = [
  {
    padrao: /\$\(\.\.\.\)\.mask is not a function/,
    causa: 'login/site.volt (checkout original) inclui jquery.mask.min.js e usa .mask no fragmento; o jquery 3.7.1 de code.jquery.com (mesmo fragmento) substitui o jQuery global e o plugin fica ligado ao anterior. Reproduz-se no HTML original.',
  },
];

function separarErrosConhecidos(erros) {
  const inesperados = [];
  const conhecidos = [];
  for (const erro of erros) {
    const conhecido = ERROS_CONHECIDOS_TICKETT.find((e) => e.padrao.test(erro));
    if (conhecido) conhecidos.push(erro); else inesperados.push(erro);
  }
  return { inesperados, conhecidos };
}

// Seletores do checkout REAL (app/views/index.volt, products/group.volt, login/site.volt,
// cart/payment.volt, delivery/sent.volt, public/js/checkout.js). Cliques no iframe por DOM
// (o template real não tem atributos de teste).
const FRAME_CLICK = (sel) => `(() => { const el = document.querySelector(${JSON.stringify(sel)}); if (!el) return false; el.click(); return true; })()`;
const FRAME_VISIBLE = (sel) => `(() => { const el = document.querySelector(${JSON.stringify(sel)}); return !!el && el.getClientRects().length > 0; })()`;
const PLUS_9002 = `(() => { const s = document.getElementById('product_9002_1'); if (!s) return false; const a = s.parentElement.querySelectorAll('a.button-select-product'); a[a.length - 1].click(); return true; })()`;

async function compra(width) {
  const r = new Results(`compra-${width}`);
  const isMobile = width < 768;
  const page = await openBrowser({ profilesDir: PROFILES, name: `compra-${width}`, width, height: isMobile ? 844 : 900 });
  const w = await watch(page);
  try {
    await page.send('Page.addScriptToEvaluateOnNewDocument', { source: 'window.dataLayer = [];' });
    await page.navigate(`${BASE}/`);
    await sleep(2500); // o hero anima ~2 s no desktop; espera para o clique não cair no meio da animação

    const trigger = '.hero__actions [data-checkout-open]';
    await page.click(trigger);
    r.check('modal abre ao clicar em "Comprar bilhetes" do hero',
      await page.ev(`!document.querySelector('[data-checkout]').hidden`));
    r.check('foco vai para o botão fechar', await page.ev(`document.activeElement.matches('[data-checkout-close]')`),
      await page.ev(focusDesc));

    const src = await page.ev(`document.querySelector('[data-checkout] iframe').getAttribute('src')`);
    r.check('iframe em /checkout?lang=pt (proxy do site, mesma origem, sem parâmetros de embed)',
      src === '/checkout?lang=pt' &&(await page.ev(`new URL(document.querySelector('[data-checkout] iframe').src).origin === location.origin`)), src);

    const ready = await page.waitFor(`document.querySelector('[data-checkout-loading]').hidden === true`, 15000);
    r.check('simulador envia ready (loading escondido)', ready);
    r.check('sem alerta de timeout depois do ready', await page.ev(`document.querySelector('[data-checkout-alert]').hidden === true`));

    const frame = await page.frameContext(FRAME);
    const inFrame = (expr) => page.ev(expr, frame);
    const frameWait = (expr, ms = 8000) => page.waitFor(expr, ms, frame);

    const modalBox = await page.ev(`(() => { const r = document.querySelector('[data-checkout] .fj-checkout').getBoundingClientRect();
      const f = document.querySelector('[data-checkout] iframe').getBoundingClientRect(); return { modal: [r.width, r.height], frame: [f.width, f.height] }; })()`);
    r.check('iframe ocupa a altura do corpo do modal (o documento do iframe rola)',
      (await page.ev(`Math.abs(document.querySelector('[data-checkout] iframe').getBoundingClientRect().height - document.querySelector('[data-checkout] .fj-checkout__body').getBoundingClientRect().height) <= 1`)),
      JSON.stringify(modalBox));

    // Duas unidades do 9002: botão "+" da linha do bilhete (select product_9002_1 ao lado)
    r.check('linha do 9002 existe no products/group', await inFrame(`!!document.getElementById('product_9002_1')`));
    await inFrame(PLUS_9002);
    await sleep(600);
    await inFrame(PLUS_9002);
    const counted = await page.waitFor(`document.querySelector('.site-header__cart-count')?.textContent === '2'
      && !document.querySelector('[data-cart-count]').hidden`, 8000);
    r.check('contador do header mostra 2 (mensagem cart do bridge)', counted, await page.ev(`document.querySelector('.site-header__cart-count')?.textContent`));
    r.check('carrinho guardado em sessionStorage (festeja-cart-count = 2)',
      (await page.ev(`sessionStorage.getItem('festeja-cart-count')`)) === '2');

    // Continuar (checkout.js: goNewFunction(1) → goLogin → formulário de dados)
    await inFrame(FRAME_CLICK('#button-products'));
    r.check('Continuar → formulário de dados visível (step details)',
      await frameWait(FRAME_VISIBLE('#tickets-form-register')));
    r.check('site recebe step details', await page.waitFor(`window.dataLayer.some(e => e.event === 'checkout_step' && e.step === 'details')`, 5000));

    // Dados: preenche o formulário de registo e clica em Continuar (goNewFunction(2) → setRegister → login/register).
    // O simulador aceita qualquer registo.
    await inFrame(`(() => { const f = document.getElementById('tickets-form-register'); f.querySelectorAll('input').forEach((i) => { if (i.type === 'hidden' || i.type === 'checkbox' || i.type === 'radio') return; if (i.type === 'email' || (i.name && /mail/i.test(i.name))) i.value = 'teste@festeja.pt'; else if (!i.value) i.value = 'Teste'; }); return true; })()`);
    await inFrame(FRAME_CLICK('#button-products'));
    // Depois do registo o checkout põe
    // #button-products em "Wait" (goDeliveryPayment); só volta a ficar ativo quando cart/set-delivery responde
    // (infoDelivery no carregamento de cart/delivery.volt → cart/resume). Espera-se esse estado, sem forçar o botão.
    // goDeliveryPayment() põe o onclick em goNewFunction(3) só ao fim de 3 s: antes disso um clique repete o passo anterior.
    r.check('botão Continuar fica ativo depois do registo (cliques normais, sem forçar o botão)',
      await frameWait(`(() => { const b = document.getElementById('button-products'); return !!b && !b.disabled && b.getAttribute('onclick') === 'goNewFunction(3)'; })()`, 15000));
    await inFrame(FRAME_CLICK('#button-products'));
    const paymentOk = await frameWait(FRAME_VISIBLE('#tickets-form-payment'), 12000);
    r.check('entrega → formulário de pagamento visível (step payment)', paymentOk,
      await inFrame(`(document.querySelector('.cart')?.innerHTML || 'sem .cart').trim().slice(0, 120).replace(/\s+/g, ' ')`));
    // Pagar: botão real do formulário de pagamento (#button-payment → payment() → payment/proccess)
    await inFrame(FRAME_CLICK('#button-payment'));

    const done = await frameWait(`!!document.querySelector('.cart-finish')`, 10000);
    r.check('pagamento leva ao finish real (.cart-finish)', done);
    await sleep(800);

    const live = await page.ev(`document.querySelector('[data-checkout-live]').textContent`);
    r.check('live region anuncia a compra', /Compra concluída/.test(live), live);

    const events = JSON.parse(await page.ev(`JSON.stringify(window.dataLayer)`));
    const purchases = events.filter((e) => e.event === 'purchase');
    r.check('purchase aparece UMA vez no dataLayer', purchases.length === 1, `n=${purchases.length}`);
    const p = purchases[0] || { ecommerce: {} };
    // O checkout real não expõe o número da encomenda (delivery/sent.volt): o site gera o id (order_id_source: 'site')
    r.check('purchase: order_id_source = site e transaction_id fj-… (id gerado pelo site)',
      p.order_id_source === 'site' && /^fj-\d+-[a-z0-9]+$/.test(String(p.ecommerce.transaction_id)),
      `source=${p.order_id_source} transaction_id=${p.ecommerce.transaction_id}`);
    r.check('purchase: item 9002 com quantity 2',
      (p.ecommerce.items || []).some((i) => i.item_id === '9002' && i.quantity === 2), JSON.stringify(p.ecommerce.items));
    const steps = events.filter((e) => e.event === 'checkout_step').map((e) => e.step);
    r.check('checkout_step cobre products, details, payment, done', ['products', 'details', 'payment', 'done'].every((s) => steps.includes(s)), steps.join(','));
    r.check('begin_checkout registado na 1.ª abertura', events.filter((e) => e.event === 'begin_checkout').length === 1);

    // Esc com o foco dentro do iframe (o simulador envia close)
    await page.key('Escape');
    r.check('Esc com foco no iframe fecha o modal (via close)', await page.waitFor(`document.querySelector('[data-checkout]').hidden === true`, 3000));
    r.check('foco volta ao botão que abriu (hero)', await page.ev(`!!document.activeElement.closest('.hero__actions')`), await page.ev(focusDesc));
    r.check('body repõe overflow e main sai de inert',
      await page.ev(`document.body.style.overflow === '' && !document.querySelector('main').inert`));
    r.check('purchase continua a 1 depois de fechar', (await page.ev(`window.dataLayer.filter(e => e.event === 'purchase').length`)) === 1);

    // Reabrir: o simulador já está no ecrã de conclusão; fecha com o botão do modal
    await page.click(trigger);
    r.check('reabrir o modal sem repetir o purchase', (await page.ev(`window.dataLayer.filter(e => e.event === 'purchase').length`)) === 1);
    await page.click('[data-checkout-close]');
    r.check('botão fechar fecha o modal', await page.ev(`document.querySelector('[data-checkout]').hidden === true`));

    // Esc com o foco na página
    await page.click(trigger);
    await page.ev(`document.querySelector('[data-checkout-close]').focus()`);
    await page.key('Escape');
    r.check('Esc com foco na página fecha o modal', await page.ev(`document.querySelector('[data-checkout]').hidden === true`));

    if (isMobile) {
      await page.click(trigger);
      // a entrada anima 200 ms (translateY 16px → 0); espera-se o fim da animação (até 3 s) em vez de um tempo fixo
      await page.waitFor(`Math.round(document.querySelector('[data-checkout] .fj-checkout').getBoundingClientRect().top) === 0`, 3000);
      const box = await page.ev(`(() => { const r = document.querySelector('[data-checkout] .fj-checkout').getBoundingClientRect(); return [r.left, r.top, r.width, r.height]; })()`);
      r.check('mobile: modal ocupa o ecrã', box[0] === 0 && Math.abs(box[1]) < 1 && box[2] === width,
        JSON.stringify(box));
      const shot = await page.screenshot(path.join(OUT, `compra-${width}-modal.png`));
      r.check('captura do modal guardada', !!shot, shot);
      await page.key('Escape');
    }
  } catch (error) {
    r.check('execução sem exceções', false, error.stack || error.message);
  } finally {
    const { inesperados, conhecidos } = separarErrosConhecidos(w.errors());
    r.check('sem erros de JS, consola ou pedidos falhados durante a compra (erros conhecidos da Tickett excluídos)',
      inesperados.length === 0, [...inesperados, ...conhecidos.map((e) => `[conhecido] ${e}`)].join(' | '));
    await page.close();
  }
  return r.save();
}

// Escolher: cada TicketCard abre o checkout com esse bilhete pré-selecionado (página /bilhetes, perfil novo).
// Modelo: data-product="ID_TIPO" (ex.: 9002_1) → iframe /checkout?lang=pt&produto=9002_1; o checkout realça o bilhete
// (fj-produto-selecionado, ponte). Outro bilhete com o iframe já aberto: a ponte recarrega com o novo ?produto=.
async function escolher() {
  const r = new Results('escolher');
  for (const width of [WIDTHS.desktop, WIDTHS.mobile]) {
    const page = await openBrowser({ profilesDir: PROFILES, name: `escolher-${width}`, width, height: width < 768 ? 844 : 900 });
    try {
      await page.send('Page.addScriptToEvaluateOnNewDocument', { source: 'window.dataLayer = [];' });
      await page.navigate(`${BASE}/bilhetes`);
      await sleep(400);
      const before = await page.ev('location.href');
      await page.click('[data-checkout-open][data-product="9002_1"]');
      r.check(`${width}: modal abre pelo "Escolher" do bilhete 9002_1`, await page.ev(`!document.querySelector('[data-checkout]').hidden`));
      r.check(`${width}: não navega (mesma URL)`, (await page.ev('location.href')) === before);
      const src = await page.ev(`document.querySelector('[data-checkout] iframe').getAttribute('src')`);
      r.check(`${width}: iframe recebe ?produto=9002_1`, src === '/checkout?lang=pt&produto=9002_1', src);
      r.check(`${width}: select_item com item 9002_1`, await page.ev(`JSON.stringify(window.dataLayer).includes('"item_id":"9002_1"')`));
      // Ponte (checkout-ponte.js): o bilhete pré-selecionado ganha fj-produto-selecionado dentro do iframe
      r.check(`${width}: checkout realça o bilhete 9002_1 (fj-produto-selecionado)`,
        await page.waitFor(`document.querySelector('[data-checkout-loading]').hidden === true`, 15000)
        && await page.waitFor(`!!document.querySelector('table.ticket-box.fj-produto-selecionado select[id="product_9002_1"]')`, 8000, await page.frameContext(FRAME)));
      await page.key('Escape');

      // Outro bilhete: o site envia "select" e a ponte recarrega o checkout com o novo ?produto= (a Tickett só
      // pré-seleciona no carregamento da página).
      await page.click('[data-checkout-open][data-product="9001_1"]');
      r.check(`${width}: modal reabre`, await page.ev(`!document.querySelector('[data-checkout]').hidden`));
      r.check(`${width}: iframe recarrega com ?produto=9001_1`,
        await page.waitFor(`document.querySelector('[data-checkout] iframe').getAttribute('src') === '/checkout?lang=pt&produto=9001_1'`, 5000));
      r.check(`${width}: checkout realça o bilhete 9001_1`,
        await page.waitFor(`!!document.querySelector('table.ticket-box.fj-produto-selecionado select[id="product_9001_1"]')`, 15000, await page.frameContext(FRAME)));
      await page.key('Escape');
    } catch (error) {
      r.check(`${width}: sem exceções`, false, error.stack || error.message);
    } finally {
      await page.close();
    }
  }
  return r.save();
}

// ---------------------------------------------------------------- a11y

// Rola a página toda (dispara as revelações de movimento.js) e volta ao topo. Sem GSAP não faz nada de diferente.
async function rolarTudo(page) {
  const total = await page.ev('document.documentElement.scrollHeight');
  for (let y = 0; y <= total; y += 400) {
    await page.ev(`window.scrollTo(0, ${y})`);
    await sleep(80);
  }
  await sleep(1200);
  await page.ev('window.scrollTo(0, 0)');
  await sleep(400);
}

async function a11y() {
  const r = new Results('a11y');
  const rows = [];
  const all = {};
  for (const [label, width] of Object.entries(WIDTHS)) {
    const page = await openBrowser({ profilesDir: PROFILES, name: `a11y-${width}`, width, height: width < 768 ? 844 : 900 });
    const w = await watch(page);
    try {
      for (const route of PAGES) {
        w.clear();
        await page.navigate(BASE + route);
        await sleep(2600); // a entrada do hero dura 2 s (opacity 0 até acabar): medir depois
        await rolarTudo(page); // as revelações do GSAP (movimento.js) acontecem ao rolar: medir o estado final
        // O 404 da própria página de erro é esperado: não conta como erro de consola.
        const consoleErrors = w.errors().filter((e) => !(route.startsWith('/404') && e.includes(`${BASE}${route}`)));
        const audit = await page.ev(`(${auditPage.toString()})()`);
        const ax = await axReport(page);
        const key = `${route} @${width}`;
        all[key] = { audit, ax };
        const i = audit.issues;
        const fails = [
          audit.h1Count !== 1 ? `h1=${audit.h1Count}` : '',
          i.headings.length ? `saltos: ${i.headings.join('; ')}` : '',
          i.images.filter((x) => x.startsWith('sem alt')).length ? 'img sem alt' : '',
          i.ids.length ? `ids: ${i.ids.join(', ')}` : '',
          ax.missingNames.length ? `sem nome: ${ax.missingNames.length}` : '',
          i.forms.length ? `campos: ${i.forms.join('; ')}` : '',
          i.contrast.length ? `contraste ${i.contrast.length}` : '',
          i.targets.length ? `alvos abaixo de 24 px: ${i.targets.length}` : '',
          audit.scroll.scrollWidth > audit.scroll.innerWidth ? `overflow ${audit.scroll.scrollWidth}>${audit.scroll.innerWidth}` : '',
          audit.lang !== 'pt-PT' ? `lang=${audit.lang}` : '',
          !audit.skipLink ? 'sem skip link' : '',
          audit.mainId !== 'conteudo' ? `main#${audit.mainId}` : '',
          ax.landmarks.filter((x) => x.startsWith('banner')).length !== 1 || ax.landmarks.filter((x) => x.startsWith('contentinfo')).length !== 1 || ax.landmarks.filter((x) => x.startsWith('main')).length !== 1 ? `landmarks AX: ${ax.landmarks.filter((x) => /^(banner|contentinfo|main)/.test(x)).join(',')}` : '',
          audit.landmarks.navNoLabel ? `nav sem nome: ${audit.landmarks.navNoLabel}` : '',
          audit.jsonld.some((j) => !j.ok) ? 'JSON-LD inválido' : '',
          !audit.canonical ? 'sem canonical' : '',
          !audit.description ? 'sem description' : '',
        ].filter(Boolean);
        if (consoleErrors.length) fails.push(`erros: ${consoleErrors.join('; ')}`);
        rows.push({ route, width, title: audit.title, description: audit.description, fails, audit, ax, consoleErrors });
        r.check(`${route} @${width} sem problemas de a11y`, fails.length === 0, fails.join(' | '));
      }
    } catch (error) {
      r.check(`@${width} sem exceções`, false, error.stack || error.message);
    } finally {
      await page.close();
    }
  }
  // Títulos e descrições únicos por página (cruzando as páginas)
  const uniq = (field) => {
    const seen = new Map();
    for (const row of rows.filter((x) => x.width === WIDTHS.desktop)) {
      const v = row[field];
      seen.set(v, [...(seen.get(v) || []), row.route]);
    }
    return [...seen].filter(([, routes]) => routes.length > 1);
  };
  const dupTitles = uniq('title');
  const dupDescs = uniq('description');
  r.check('títulos únicos por página', dupTitles.length === 0, JSON.stringify(dupTitles));
  r.check('descrições únicas por página', dupDescs.length === 0, JSON.stringify(dupDescs));

  fs.writeFileSync(path.join(OUT, 'a11y-detalhe.json'), JSON.stringify(all, null, 2));
  console.log('\nTabela (route @largura | h1 | saltos | ids | sem nome | contraste | alvos < 24 px | alvos 24-44 px (aviso) | overflow)');
  for (const row of rows) {
    const { audit, ax } = row;
    const i = audit.issues;
    console.log(`${row.route} @${row.width} | ${audit.h1Count} | ${i.headings.length} | ${i.ids.length} | ${ax.missingNames.length} | ${i.contrast.length} | ${i.targets.length} | ${i.targetsInfo.length} | ${audit.scroll.scrollWidth}/${audit.scroll.innerWidth}`);
  }
  return r.save({ rows: rows.map(({ audit, ax, ...rest }) => rest) });
}

// ---------------------------------------------------------------- teclado

async function teclado() {
  const r = new Results('teclado');

  // Skip link é o 1.º foco e leva ao main
  const d = await openBrowser({ profilesDir: PROFILES, name: 'teclado-d', width: 1440, height: 900 });
  try {
    await d.navigate(`${BASE}/`);
    await sleep(2500);
    await d.key('Tab');
    const first = await d.ev(focusDesc);
    r.check('1.º Tab foca o skip link', await d.ev(`document.activeElement.classList.contains('skip-link')`), first);
    const skipVisible = await d.ev(`(() => { const r = document.activeElement.getBoundingClientRect(); return r.top >= 0 && r.width > 0; })()`);
    r.check('skip link visível ao receber foco', skipVisible);
    await d.key('Enter');
    await sleep(150);
    await d.key('Tab');
    const inMain = await d.ev(`!!document.activeElement.closest('main#conteudo')`);
    r.check('Enter no skip link leva ao main (próximo Tab cai dentro de main#conteudo)', inMain, await d.ev(focusDesc));
    const hash = await d.ev('location.hash');
    r.check('skip link aponta para #conteudo', hash === '#conteudo', hash);

    // Foco visível nos primeiros alvos
    await d.navigate(`${BASE}/`);
    await sleep(500);
    const focusRings = [];
    for (let i = 0; i < 8; i++) {
      await d.key('Tab');
      focusRings.push(await d.ev(`(() => { const s = getComputedStyle(document.activeElement);
        return { el: ${focusDesc}, outline: s.outlineStyle + ' ' + s.outlineWidth, shadow: s.boxShadow }; })()`));
    }
    const noRing = focusRings.filter((x) => x.outline.startsWith('none') && (x.shadow === 'none' || x.shadow === ''));
    r.check('foco visível nos 8 primeiros alvos (outline ou box-shadow)', noRing.length === 0,
      noRing.map((x) => x.el).join(' | '));
  } catch (error) {
    r.check('teclado desktop sem exceções', false, error.stack || error.message);
  } finally {
    await d.close();
  }

  // Menu móvel a 390
  const m = await openBrowser({ profilesDir: PROFILES, name: 'teclado-m', width: 390, height: 844 });
  try {
    await m.navigate(`${BASE}/`);
    await sleep(800);
    let reached = false;
    for (let i = 0; i < 25 && !reached; i++) {
      reached = await m.ev(`document.activeElement.matches('[data-menu-toggle]')`);
      if (!reached) await m.key('Tab');
    }
    r.check('botão do menu alcançável por Tab', reached, await m.ev(focusDesc));
    await m.key('Enter');
    r.check('Enter abre o menu (aria-expanded=true, painel visível)',
      await m.ev(`document.querySelector('[data-menu-toggle]').getAttribute('aria-expanded') === 'true' && !document.querySelector('[data-mobile-menu]').hidden`));
    r.check('foco entra no menu ao abrir', await m.ev(`!!document.activeElement.closest('[data-mobile-menu]')`), await m.ev(focusDesc));
    r.check('página inerte e sem scroll com o menu aberto',
      await m.ev(`document.querySelector('main').inert === true && document.body.style.overflow === 'hidden'`));
    let escaped = 0;
    const trail = [];
    for (let i = 0; i < 25; i++) {
      await m.key('Tab');
      const inside = await m.ev(`!!document.activeElement.closest('header')`);
      if (!inside) escaped++;
      trail.push(await m.ev(focusDesc));
    }
    r.check('foco preso no menu (25 Tab seguidos)', escaped === 0, `${escaped} escapes; último: ${trail.at(-1)}`);
    await m.key('Escape');
    r.check('Esc fecha o menu', await m.ev(`document.querySelector('[data-menu-toggle]').getAttribute('aria-expanded') === 'false' && document.querySelector('[data-mobile-menu]').hidden`));
    r.check('Esc devolve o foco ao botão do menu', await m.ev(`document.activeElement.matches('[data-menu-toggle]')`), await m.ev(focusDesc));
    r.check('main sai de inert e o scroll volta', await m.ev(`!document.querySelector('main').inert && document.body.style.overflow === ''`));
  } catch (error) {
    r.check('menu móvel sem exceções', false, error.stack || error.message);
  } finally {
    await m.close();
  }

  // Separadores do cartaz com setas
  // Os separadores só existem abaixo de 1024 px (acima, os dois dias ficam lado a lado): testa a 390.
  const t = await openBrowser({ profilesDir: PROFILES, name: 'teclado-t', width: 390, height: 844 });
  try {
    await t.navigate(`${BASE}/cartaz`);
    await sleep(500);
    const tabs = await t.ev(`[...document.querySelectorAll('[role="tablist"] [role="tab"]')].map(x => x.dataset.tab)`);
    r.check('cartaz tem separadores (role=tab)', tabs.length >= 2, tabs.join(','));
    const sel = () => t.ev(`[...document.querySelectorAll('[role="tab"]')].findIndex(x => x.getAttribute('aria-selected') === 'true')`);
    await t.ev(`document.querySelector('[role="tab"][aria-selected="true"]').focus()`);
    const start = await sel();
    await t.key('ArrowRight');
    r.check('ArrowRight muda para o separador seguinte e foca-o',
      (await sel()) === (start + 1) % tabs.length && await t.ev(`document.activeElement.getAttribute('aria-selected') === 'true'`));
    const panelShown = await t.ev(`(() => { const tab = document.activeElement; const p = document.getElementById(tab.getAttribute('aria-controls')); return p && !p.hidden; })()`);
    r.check('painel associado visível', panelShown);
    await t.key('End');
    r.check('End vai para o último', (await sel()) === tabs.length - 1);
    await t.key('Home');
    r.check('Home vai para o primeiro', (await sel()) === 0);
    await t.key('ArrowLeft');
    r.check('ArrowLeft no primeiro dá a volta ao último', (await sel()) === tabs.length - 1);
  } catch (error) {
    r.check('separadores sem exceções', false, error.stack || error.message);
  } finally {
    await t.close();
  }
  return r.save();
}

// ---------------------------------------------------------------- movimento reduzido

async function movimento() {
  const r = new Results('movimento');
  const page = await openBrowser({ profilesDir: PROFILES, name: 'movimento', width: 1440, height: 900 });
  // Animações do hero, lidas com getAnimations(). A entrada e o loop são CSS (home.css, a partir de 1024 px, com .js
  // e sem prefers-reduced-motion). A pausa do marquee põe is-loop-paused no [data-hero] e o loop fica paused.
  const ANIM = `(() => {
    const lista = (el) => (el ? el.getAnimations() : []).map((a) => ({ nome: a.animationName, estado: a.playState }));
    const mandala = document.querySelector('.hero__mandala > .mandala');
    return JSON.stringify({
      entrada: lista(document.querySelector('.hero__mandala')).filter((a) => a.nome === 'fj-hero-mandala-entrada'),
      loop: lista(mandala).filter((a) => a.nome === 'fj-hero-girar' || a.nome === 'fj-hero-respirar'),
      copia: lista(document.querySelector('.hero__copy')).filter((a) => a.nome === 'fj-hero-subir'),
      heroClasses: document.querySelector('[data-hero]').className,
    });
  })()`;
  const estadoLoop = (a) => a.loop.length === 2 && a.loop.every((x) => x.estado === 'running');
  try {
    // 1) Sem reduced-motion: entrada da mandala a correr e loop (girar + respirar) a correr
    await page.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
    await page.navigate(`${BASE}/`);
    await sleep(300);
    const a1 = JSON.parse(await page.ev(ANIM));
    r.check('sem preferência: entrada da mandala em curso (fj-hero-mandala-entrada running)',
      a1.entrada.some((x) => x.estado === 'running'), JSON.stringify(a1.entrada));
    r.check('sem preferência: loop da mandala a correr (fj-hero-girar e fj-hero-respirar running)',
      estadoLoop(a1), JSON.stringify(a1.loop));
    r.check('sem preferência: a faixa começa a correr (is-paused ausente, aria-pressed=false)',
      await page.ev(`!document.querySelector('[data-marquee]').classList.contains('is-paused') && document.querySelector('[data-marquee-toggle]').getAttribute('aria-pressed') === 'false'`));

    // 2) Botão de pausa da faixa: o loop da mandala fica paused e volta a correr ao retomar
    await page.click('[data-marquee-toggle]');
    await sleep(200);
    const a2 = JSON.parse(await page.ev(ANIM));
    r.check('botão de pausa da faixa põe o loop da mandala em paused (is-loop-paused no hero)',
      a2.loop.length === 2 && a2.loop.every((x) => x.estado === 'paused') && a2.heroClasses.includes('is-loop-paused'),
      JSON.stringify(a2.loop) + ' | ' + a2.heroClasses);
    r.check('faixa pausada: is-paused e aria-pressed=true',
      await page.ev(`document.querySelector('[data-marquee]').classList.contains('is-paused') && document.querySelector('[data-marquee-toggle]').getAttribute('aria-pressed') === 'true'`));
    await page.click('[data-marquee-toggle]');
    await sleep(200);
    const a3 = JSON.parse(await page.ev(ANIM));
    r.check('retomar a faixa volta a pôr o loop da mandala a correr', estadoLoop(a3), JSON.stringify(a3.loop));

    // 3) Com reduced-motion: sem animações de hero/mandala e faixa começa parada
    await page.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await page.navigate(`${BASE}/`);
    await sleep(400);
    const a4 = JSON.parse(await page.ev(ANIM));
    r.check('reduced-motion: sem animação de entrada nem de loop no hero',
      a4.entrada.length === 0 && a4.loop.length === 0 && a4.copia.length === 0,
      JSON.stringify({ entrada: a4.entrada, loop: a4.loop, copia: a4.copia }));
    r.check('reduced-motion: faixa começa parada (is-paused, aria-pressed=true)',
      await page.ev(`document.querySelector('[data-marquee]').classList.contains('is-paused') && document.querySelector('[data-marquee-toggle]').getAttribute('aria-pressed') === 'true'`));
    // 4) Reduced-motion com GSAP: nenhum pedido a assets/vendor/gsap, nada escondido nem movido pelos data-mov
    const pedidosGsap = await page.ev(`performance.getEntriesByType('resource').filter((e) => e.name.includes('/assets/vendor/gsap/')).length`);
    r.check('reduced-motion: GSAP não é carregado (nenhum pedido a assets/vendor/gsap)', pedidosGsap === 0, String(pedidosGsap));
    r.check('reduced-motion: data-mov sem estado inicial (sem style inline)',
      await page.ev(`[...document.querySelectorAll('[data-mov]')].every((el) => el.style.transform === '' && el.style.opacity === '')`));

    // 5) Sem reduced-motion: GSAP depois do load; a página toda rolada não deixa nada invisível; âncoras e cabeçalho
    await page.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
    await page.navigate(`${BASE}/`);
    await sleep(2000);
    r.check('sem preferência: GSAP e ScrollTrigger carregados depois do load',
      await page.ev(`!!(window.gsap && window.ScrollTrigger && window.ScrollToPlugin)`));
    const total = await page.ev('document.documentElement.scrollHeight');
    for (let y = 0; y <= total; y += 300) {
      await page.ev(`window.scrollTo(0, ${y})`);
      await sleep(100);
    }
    await sleep(1500);
    const marcados = JSON.parse(await page.ev(`JSON.stringify([...document.querySelectorAll('[data-mov]')].map((el) => {
      const cs = getComputedStyle(el);
      return { tipo: el.dataset.mov, opacidade: cs.opacity, transform: cs.transform, largura: el.getBoundingClientRect().width };
    }))`));
    const escondidos = marcados.filter((x) => x.largura > 0 && x.opacidade !== '1');
    r.check('sem preferência: após rolar a página toda, nenhum data-mov fica invisível', escondidos.length === 0, JSON.stringify(escondidos.slice(0, 5)));
    const presos = marcados.filter((x) => (x.tipo === 'revelar' || x.tipo === 'escalonar') && x.largura > 0 && x.transform !== 'none');
    r.check('sem preferência: revelações acabam sem transform (identidade)', presos.length === 0, JSON.stringify(presos.slice(0, 5)));
    r.check('sem preferência: símbolos do fecho visíveis no fim do scroll (escalonar)',
      await page.ev(`(() => { const s = [...document.querySelectorAll('.home-close__simbolos .symbol')]; return s.length > 0 && s.every((el) => getComputedStyle(el).opacity === '1'); })()`));
    r.check('sem preferência: símbolos dos eyebrows acabam na orientação original (sem transform)',
      await page.ev(`[...document.querySelectorAll('[data-mov-estilo="girar"]')].every((el) => getComputedStyle(el).transform === 'none')`));
    r.check('hero sem anéis: só a mandala na coluna direita (desktop)',
      await page.ev(`!document.querySelector('.hero__aneis') && !!document.querySelector('.hero__sun .hero__mandala > .mandala')`));
    r.check('sem preferência: palavras do festival com aria-label e spans escondidos',
      await page.ev(`(() => { const h = document.getElementById('festival-titulo'); return h.getAttribute('aria-label').includes('Angola') && h.querySelectorAll('[aria-hidden="true"]').length > 10; })()`));

    // Cabeçalho: esconde ao descer depois de 1 ecrã, volta ao subir
    await page.ev('window.scrollTo(0, 0)');
    await sleep(500);
    await page.ev('window.scrollTo(0, 1800)');
    await sleep(700);
    r.check('cabeçalho escondido depois de descer 1 ecrã', await page.ev(`document.querySelector('[data-header]').style.transform.includes('-100%')`));
    await page.ev('window.scrollTo(0, 1500)');
    await sleep(700);
    r.check('cabeçalho volta ao subir (topo visível no ecrã)',
      await page.ev(`document.querySelector('[data-header]').getBoundingClientRect().top >= -1`),
      await page.ev(`document.querySelector('[data-header]').getBoundingClientRect().top`));

    // Âncora "Ver cartaz": scroll com offset do cabeçalho e foco no título de destino
    await page.ev('window.scrollTo(0, 0)');
    await sleep(500);
    await page.click('a[href="#cartaz"]');
    await sleep(1500);
    r.check('âncora "Ver cartaz": foco no título da secção (tabindex -1)',
      await page.ev(`document.activeElement && document.activeElement.id === 'cartaz-titulo'`), await page.ev('document.activeElement && document.activeElement.id'));
    r.check('âncora "Ver cartaz": a página desce até à secção', await page.ev('window.scrollY > 400'), await page.ev('window.scrollY'));
  } catch (error) {
    r.check('movimento sem exceções', false, error.stack || error.message);
  } finally {
    await page.close();
  }
  return r.save();
}

// ---------------------------------------------------------------- sem JavaScript

async function semjs() {
  const r = new Results('semjs');
  for (const width of [WIDTHS.desktop, WIDTHS.mobile]) {
    const page = await openBrowser({ profilesDir: PROFILES, name: `semjs-${width}`, width, height: width < 768 ? 844 : 900 });
    try {
      await page.send('Emulation.setScriptExecutionDisabled', { value: true });
      await page.navigate(`${BASE}/cartaz`);
      await sleep(300);
      const panels = await page.ev(`[...document.querySelectorAll('[data-panel]')].map(p => ({ id: p.dataset.panel, shown: getComputedStyle(p).display !== 'none' && !p.hidden, h: Math.round(p.getBoundingClientRect().height) }))`);
      r.check(`${width}: cartaz mostra os dois dias`, panels.length >= 2 && panels.every((p) => p.shown && p.h > 0), JSON.stringify(panels));
      const tablist = await page.ev(`getComputedStyle(document.querySelector('[role="tablist"]') || document.body).display`);
      r.check(`${width}: separadores escondidos sem JS`, tablist === 'none', tablist);

      // Sem JS o menu móvel já está aberto (CSS .no-js) e o botão do menu fica escondido: é o esperado.
      const menu = await page.ev(`(() => { const t = document.querySelector('[data-menu-toggle]'); const m = document.querySelector('[data-mobile-menu]');
        const links = [...m.querySelectorAll('nav a')];
        return { toggleDisplay: getComputedStyle(t).display, menuDisplay: getComputedStyle(m).display,
          linksVisible: links.filter(a => a.getClientRects().length > 0).length, links: links.length }; })()`);
      if (width < 1024) {
        r.check(`${width}: menu móvel com os links visíveis sem JS (botão escondido, como esperado)`,
          menu.menuDisplay !== 'none' && menu.linksVisible === menu.links && menu.links > 0 && menu.toggleDisplay === 'none', JSON.stringify(menu));
      }

      const buys = await page.ev(`[...document.querySelectorAll('[data-checkout-open]')].map(a => ({ tag: a.tagName, href: a.getAttribute('href'), text: a.textContent.trim().slice(0, 30), visible: a.getClientRects().length > 0 }))`);
      const badHref = buys.filter((b) => !b.href || b.href === '#' || b.href.startsWith('javascript'));
      r.check(`${width}: botões de compra são links com href`, buys.length > 0 && badHref.length === 0, JSON.stringify(badHref.slice(0, 3)));
      await page.screenshot(path.join(OUT, `semjs-${width}.png`));
    } catch (error) {
      r.check(`${width}: sem JS sem exceções`, false, error.stack || error.message);
    } finally {
      await page.close();
    }
  }
  return r.save();
}

// ---------------------------------------------------------------- overflow

async function overflow() {
  const r = new Results('overflow');
  const widths = [320, 390, 768, 1024, 1440];
  const rows = [];
  for (const width of widths) {
    const page = await openBrowser({ profilesDir: PROFILES, name: `overflow-${width}`, width, height: 800 });
    try {
      for (const route of PAGES) {
        await page.navigate(BASE + route);
        await sleep(250);
        const m = await page.ev(`({ sw: document.documentElement.scrollWidth, iw: innerWidth })`);
        rows.push({ width, route, ...m });
        r.check(`${route} @${width} sem scroll horizontal`, m.sw <= m.iw, `scrollWidth=${m.sw} innerWidth=${m.iw}`);
      }
    } catch (error) {
      r.check(`@${width} sem exceções`, false, error.stack || error.message);
    } finally {
      await page.close();
    }
  }
  return r.save({ rows });
}

// ---------------------------------------------------------------- desempenho

const THROTTLE_4G = { offline: false, latency: 150, downloadThroughput: (1.6 * 1e6) / 8, uploadThroughput: (750 * 1e3) / 8 };
// Orçamento do vendor GSAP (gsap + ScrollTrigger + ScrollToPlugin, 3.12.5: ~120 KB sem compressão). Carregado depois do load.
const ORCAMENTO_VENDOR_KB = 130;

async function desempenho() {
  const r = new Results('desempenho');
  const page = await openBrowser({ profilesDir: PROFILES, name: 'desempenho', width: 390, height: 844 });
  const rows = [];
  const LCP_CLS = `(() => {
    window.__perf = { lcp: 0, cls: 0 };
    new PerformanceObserver((list) => { for (const e of list.getEntries()) window.__perf.lcp = e.startTime; })
      .observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver((list) => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.__perf.cls += e.value; })
      .observe({ type: 'layout-shift', buffered: true });
  })();`;
  try {
    await page.send('Page.addScriptToEvaluateOnNewDocument', { source: LCP_CLS });
    await page.send('Network.enable');
    let requests = new Map();
    let finished = [];
    page.on('Network.requestWillBeSent', ({ requestId, request }) => requests.set(requestId, { url: request.url }));
    page.on('Network.responseReceived', ({ requestId, type, response }) => {
      const q = requests.get(requestId) || {};
      q.type = type; q.status = response.status; q.url = response.url; requests.set(requestId, q);
    });
    page.on('Network.loadingFinished', ({ requestId, encodedDataLength }) => {
      const q = requests.get(requestId) || {};
      q.bytes = encodedDataLength;
      finished.push(q);
    });
    page.on('Network.loadingFailed', ({ requestId }) => {
      const q = requests.get(requestId) || {};
      q.failed = true;
      finished.push(q);
    });

    const reset = () => { requests = new Map(); finished = []; };
    const summarize = (route, label) => {
      const ok = finished.filter((q) => !q.failed);
      const bytes = ok.reduce((s, q) => s + (q.bytes || 0), 0);
      // O JS do site (jsKB) e o vendor do GSAP (vendorKB, carregado depois do load) contam à parte
      const doVendor = (u) => (u || '').includes('/assets/vendor/');
      const scripts = ok.filter((q) => q.type === 'Script');
      const js = scripts.filter((q) => !doVendor(q.url)).reduce((s, q) => s + (q.bytes || 0), 0);
      const vendor = scripts.filter((q) => doVendor(q.url)).reduce((s, q) => s + (q.bytes || 0), 0);
      const local = (u) => (u || '').startsWith(BASE);
      const external = ok.filter((q) => !local(q.url));
      return {
        route, label, requests: requests.size, bytesKB: Math.round(bytes / 1024), jsKB: Math.round(js / 1024),
        vendorKB: Math.round(vendor / 1024),
        externalKB: Math.round(external.reduce((s, q) => s + (q.bytes || 0), 0) / 1024),
        failed: finished.filter((q) => q.failed).map((q) => q.url).slice(0, 4),
        scripts: ok.filter((q) => q.type === 'Script').map((q) => `${q.url.replace(BASE, '')}:${Math.round((q.bytes || 0) / 1024)}K`),
      };
    };

    // 1.º passo: pesos com cache desligado, sem throttling (rede local)
    await page.send('Network.setCacheDisabled', { cacheDisabled: true });
    for (const route of PAGES) {
      reset();
      await page.navigate(BASE + route, 60000);
      await sleep(1500);
      rows.push(summarize(route, 'sem throttling'));
    }

    // 2.º passo: 390 com 4G simulado, LCP e CLS
    await page.send('Network.emulateNetworkConditions', THROTTLE_4G);
    for (const route of PAGES) {
      reset();
      await page.navigate(BASE + route, 90000);
      await sleep(3500);
      const perf = await page.ev('window.__perf');
      const s = summarize(route, '4G 390');
      s.lcpMs = Math.round(perf.lcp);
      s.cls = Math.round(perf.cls * 1000) / 1000;
      rows.push(s);
    }
  } catch (error) {
    r.check('desempenho sem exceções', false, error.stack || error.message);
  } finally {
    await page.close();
  }

  console.log('\nRota | passo | pedidos | KB total | JS KB | vendor GSAP KB | externo KB | LCP ms | CLS');
  for (const row of rows) {
    console.log(`${row.route} | ${row.label} | ${row.requests} | ${row.bytesKB} | ${row.jsKB} | ${row.vendorKB} | ${row.externalKB} | ${row.lcpMs ?? '-'} | ${row.cls ?? '-'}`);
  }
  for (const row of rows.filter((x) => x.label === '4G 390')) {
    r.check(`${row.route}: LCP < 2500 ms (4G 390)`, row.lcpMs < 2500, `${row.lcpMs} ms`);
    r.check(`${row.route}: CLS < 0,1 (4G 390)`, row.cls < 0.1, `${row.cls}`);
    r.check(`${row.route}: JS do site < 50 KB (sem o vendor)`, row.jsKB < 50, `${row.jsKB} KB`);
    // Orçamento do vendor (GSAP, carregado depois do load e só sem reduced-motion): ver dev/qa/README.md
    r.check(`${row.route}: vendor GSAP < ${ORCAMENTO_VENDOR_KB} KB (depois do load)`, row.vendorKB < ORCAMENTO_VENDOR_KB, `${row.vendorKB} KB`);
  }
  return r.save({ rows });
}

// ---------------------------------------------------------------- SEO e HTTP

async function seo() {
  const r = new Results('seo');
  for (const p of ['/robots.txt', '/sitemap.xml', '/llms.txt']) {
    const res = await fetch(BASE + p);
    r.check(`${p} responde 200`, res.status === 200, `${res.status} ${res.headers.get('content-type')}`);
  }
  for (const route of PAGES) {
    const res = await fetch(BASE + route, { redirect: 'manual' });
    const expected = route.startsWith('/404') ? 404 : 200;
    r.check(`${route} HTTP ${expected}`, res.status === expected, `${res.status}`);
  }
  return r.save();
}

// ---------------------------------------------------------------- fallback (simulador desligado)

async function fallback() {
  const r = new Results('fallback');
  const cacheFile = path.join(PROJECT, 'storage/cache/tickett-2001.json');
  const backupDir = path.join(OUT, 'backup');
  const backup = path.join(backupDir, 'tickett-2001.json');
  fs.mkdirSync(backupDir, { recursive: true });
  const stubPidFile = path.join(OUT, 'stub.pid');
  const stubPid = fs.existsSync(stubPidFile) ? Number(fs.readFileSync(stubPidFile, 'utf8')) : null;
  if (!stubPid) throw new Error(`pid do simulador em falta: ${stubPidFile} (arranca-o com dev/tickett-stub/serve e grava o pid)`);

  const stubIsUp = async () => fetch(`${STUB}/2001`).then((x) => x.status === 200).catch(() => false); // sem products.json: o checkout é a página /{evento}

  if (fs.existsSync(cacheFile)) fs.copyFileSync(cacheFile, backup);
  try {
    process.kill(stubPid, 'SIGTERM');
    await sleep(800);
    r.check('simulador parado (porta 8081 sem resposta)', !(await stubIsUp()));

    // A) Com cache: a página continua 200 e mostra os bilhetes
    const resA = await fetch(`${BASE}/bilhetes`);
    r.check('com cache: /bilhetes HTTP 200', resA.status === 200, String(resA.status));
    const pageA = await openBrowser({ profilesDir: PROFILES, name: 'fallback-a', width: 1440, height: 900 });
    try {
      await pageA.navigate(`${BASE}/bilhetes`);
      await sleep(300);
      const cards = await pageA.ev(`document.querySelectorAll('.fj-ticket').length`);
      r.check('com cache: cartões de bilhete renderizados', cards > 0, `${cards} cartões`);
      const fallbackLink = await pageA.ev(`!!document.body.textContent.includes('Ir para a bilheteira')`);
      r.check('com cache: sem o fallback "Ir para a bilheteira"', !fallbackLink);

      await pageA.navigate(`${BASE}/`);
      await sleep(300);
      const minPrice = await pageA.ev(`document.querySelector('.hero__facts')?.textContent || ''`);
      r.check('com cache: home mostra "desde …" nos factos', /desde/.test(minPrice), minPrice.replace(/\s+/g, ' '));

      // Abre o modal: sem simulador, o aviso de timeout aparece aos 8 s
      await sleep(2500);
      const t0 = Date.now();
      await pageA.click('.hero__actions [data-checkout-open]');
      const alertShown = await pageA.waitFor(`document.querySelector('[data-checkout-alert]').hidden === false`, 14000);
      const elapsed = Date.now() - t0;
      r.check('simulador desligado: aviso de timeout aparece', alertShown);
      r.check('aviso aparece por volta de 8 s (7,5–9,5 s)', elapsed >= 7500 && elapsed <= 9500, `${elapsed} ms`);
      r.check('loading escondido quando o aviso aparece', await pageA.ev(`document.querySelector('[data-checkout-loading]').hidden === true`));
      r.check('aviso tem link para abrir a bilheteira em página nova',
        await pageA.ev(`(() => { const a = document.querySelector('[data-checkout-alert] a'); return a.target === '_blank' && a.rel.includes('noopener'); })()`));
      await pageA.screenshot(path.join(OUT, 'fallback-modal-timeout.png'));
      await pageA.key('Escape');
    } finally {
      await pageA.close();
    }

    // B) Sem cache: fallback do botão geral
    if (fs.existsSync(cacheFile)) fs.rmSync(cacheFile);
    const resB = await fetch(`${BASE}/bilhetes`);
    r.check('sem cache: /bilhetes HTTP 200', resB.status === 200, String(resB.status));
    const html = await resB.text();
    r.check('sem cache: fallback "Ir para a bilheteira" com link para o checkout',
      html.includes('Ir para a bilheteira') && html.includes('href="http://127.0.0.1:8081/2001"'));
    const resHome = await fetch(`${BASE}/`);
    r.check('sem cache: home HTTP 200', resHome.status === 200, String(resHome.status));
    const resCart = await fetch(`${BASE}/cartaz`);
    r.check('sem cache: cartaz HTTP 200', resCart.status === 200, String(resCart.status));
    const pageB = await openBrowser({ profilesDir: PROFILES, name: 'fallback-b', width: 1440, height: 900 });
    try {
      await pageB.navigate(`${BASE}/bilhetes`);
      await sleep(300);
      r.check('sem cache: sem cartões e com o botão geral visível',
        (await pageB.ev(`document.querySelectorAll('.fj-ticket').length`)) === 0
        && (await pageB.ev(`[...document.querySelectorAll('a')].some(a => a.textContent.trim() === 'Ir para a bilheteira' && a.getClientRects().length > 0)`)));
      await pageB.screenshot(path.join(OUT, 'fallback-sem-cache-bilhetes.png'));
    } finally {
      await pageB.close();
    }
  } finally {
    // Repõe a cache e o simulador, mesmo se algo falhar
    if (fs.existsSync(backup)) fs.copyFileSync(backup, cacheFile);
    const { spawn } = await import('node:child_process');
    const logFile = path.join(OUT, 'stub-restart.log');
    const restart = spawn('/bin/bash', [path.join(STUB_DIR, 'serve')], { cwd: STUB_DIR, detached: true, stdio: ['ignore', fs.openSync(logFile, 'w'), 'ignore'] });
    restart.on('error', (error) => console.error('não arrancou o simulador:', error.message));
    restart.unref();
    fs.writeFileSync(stubPidFile, String(restart.pid)); // o próximo run usa este PID (é o php -S do simulador)
    await sleep(1200);
    r.check('cache reposta e simulador a correr de novo', (await stubIsUp()) && fs.existsSync(cacheFile));
  }
  return r.save();
}

// ---------------------------------------------------------------- checkout-layout (scroll do documento do iframe, sticky, gaveta)

// Medições dentro do iframe do checkout. Coordenadas do viewport do iframe (H = altura visível).
const LAYOUT_FRAME = `(() => {
  const W = document.documentElement.clientWidth, H = window.innerHeight;
  const vis = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
  // Texto direto ou dentro de <font>: com a tradução (Google Translate, en) o texto vem embrulhado em <font>
  const conteudo = (el) => [...el.childNodes].some((n) => (n.nodeType === 3 || n.nodeName === 'FONT') && n.textContent.trim() !== '')
    || /^(INPUT|SELECT|TEXTAREA|BUTTON|IMG)$/.test(el.nodeName.toUpperCase());
  // Fora do fluxo: absolutos e fixos (a gaveta do mobile e a decoração)
  const foraFluxo = (el) => { for (let n = el; n && n !== document.body; n = n.parentElement) { const p = getComputedStyle(n).position; if (p === 'absolute' || p === 'fixed') return true; } return false; };
  const nome = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\\s+/).slice(0, 2).join('.') : '');
  const itens = [...document.querySelectorAll('body *')].filter((el) => vis(el) && conteudo(el) && !foraFluxo(el) && !el.closest('script,style'));
  const alvo = itens.reduce((m, el) => { const r = el.getBoundingClientRect(); return !m || r.bottom > m.r.bottom ? { el, r } : m; }, null);
  const cont = [...document.querySelectorAll('#button-products')].find(vis);
  const rc = cont ? cont.getBoundingClientRect() : null;
  const gaveta = document.querySelector('.cart-sidebar');
  const rg = gaveta && vis(gaveta) ? gaveta.getBoundingClientRect() : null;
  const esq = Math.min(...itens.map((el) => el.getBoundingClientRect().left));
  const dir = W - Math.max(...itens.map((el) => el.getBoundingClientRect().right));
  const direita = itens.filter((el) => el.getBoundingClientRect().right > W + 0.5).map((el) => nome(el));
  const escondido = []; const semNecessidade = [];
  for (const el of document.querySelectorAll('body *')) {
    if (!vis(el) || el.closest('svg') || el.nodeName.toLowerCase() === 'svg') continue;
    const cs = getComputedStyle(el);
    const desenhado = el.clientHeight > 2; // ignora contentores de 1–2 px (select2 escondido)
    if (desenhado && /^(hidden|clip)$/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 1 && !el.classList.contains('select2-hidden-accessible') && !el.classList.contains('cart-sidebar')) escondido.push(nome(el) + ' sh=' + el.scrollHeight + ' ch=' + el.clientHeight);
    // barra vertical visível sem necessidade: 'scroll' (sempre visível), ou 'auto' com barra desenhada e sem overflow
    if (cs.overflowY === 'scroll' || (cs.overflowY === 'auto' && el.offsetWidth - el.clientWidth > 0 && el.scrollHeight <= el.clientHeight + 1)) semNecessidade.push(nome(el));
  }
  const pos = { y: window.scrollY, max: document.documentElement.scrollHeight - H };
  return JSON.stringify({
    W, H, pos,
    alvo: alvo ? { nome: nome(alvo.el), top: alvo.r.top, bottom: alvo.r.bottom } : null,
    continuar: rc ? { top: rc.top, bottom: rc.bottom, visivel: rc.height > 0 } : null,
    gaveta: rg ? { top: rg.top, bottom: rg.bottom, altura: rg.height, pos: getComputedStyle(gaveta).position, aberta: /\\bopen(Auto)?\\b/.test(gaveta.className) } : null,
    margemEsq: Math.round(esq), margemDir: Math.round(dir), direita, escondido, semNecessidade,
  });
})()`;

const TOL_MARGEM = 12; // px de diferença aceite entre margem esquerda e direita (padding interno dos formulários)

// Chaves em bruto: texto, placeholder, título, valor de botão ou opção que case ^[a-z]+(_[a-z]+)+$ (ex.: full_name).
const CHAVES_EM_BRUTO = `(() => {
  const achados = []; const re = /^[a-z]+(_[a-z]+)+$/;
  const vis = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let n; while ((n = w.nextNode())) { const t = n.textContent.trim(); if (t && re.test(t) && n.parentElement && vis(n.parentElement)) achados.push('texto:' + t); }
  for (const el of document.querySelectorAll('[placeholder],[title],[aria-label],input[type=submit],input[type=button],button,option')) {
    if (el.tagName !== 'OPTION' && !vis(el)) continue;
    for (const v of [el.getAttribute('placeholder'), el.getAttribute('title'), el.getAttribute('aria-label'), el.tagName === 'INPUT' && el.type !== 'hidden' ? el.value : null, el.tagName === 'OPTION' ? el.textContent : null]) if (v && re.test(v.trim())) achados.push('atributo:' + v.trim());
  }
  return JSON.stringify([...new Set(achados)]);
})()`;

// Telefone (login/site.volt): o placeholder cabe no campo, e o seletor de indicativo tem a mesma altura e topo que o campo.
const TELEFONE = `(() => {
  const out = [];
  for (const campo of document.querySelectorAll('.number_fone')) {
    const g = campo.closest('.input-group');
    const sel = g ? (g.querySelector('.select2-container') || g.querySelector('select')) : null;
    const c = getComputedStyle(campo);
    const ctx = document.createElement('canvas').getContext('2d');
    ctx.font = c.fontWeight + ' ' + c.fontSize + ' ' + c.fontFamily;
    const larg = ctx.measureText(campo.placeholder).width;
    const util = campo.clientWidth - parseFloat(c.paddingLeft) - parseFloat(c.paddingRight);
    const rs = sel ? sel.getBoundingClientRect() : null; const rc = campo.getBoundingClientRect();
    const empilhado = !!rs && rs.bottom <= rc.top + 1; // abaixo de 380 px o seletor fica por cima do campo (mesma largura)
    out.push({ id: campo.id, cabe: larg <= util, larg: Math.round(larg), util: Math.round(util), altSel: rs ? Math.round(rs.height) : null, altCampo: Math.round(rc.height), topoSel: rs ? Math.round(rs.top) : null, topoCampo: Math.round(rc.top), empilhado, larguraSel: rs ? Math.round(rs.width) : null, larguraCampo: Math.round(rc.width), esqSel: rs ? Math.round(rs.left) : null, esqCampo: Math.round(rc.left) });
  }
  return JSON.stringify(out);
})()`;

// Fundo da coluna do pedido (desktop): cor efetiva nos pontos da borda inferior do iframe e bottom da linha em relação ao documento.
const FUNDO_DIREITA = `(() => {
  const H = window.innerHeight;
  const sb = document.querySelector('.cart-sidebar'); const linha = document.querySelector('.cart-content');
  if (!sb || !linha || sb.getClientRects().length === 0) return JSON.stringify(null);
  const sunken = (() => { const p = document.createElement('div'); p.style.backgroundColor = 'var(--tk-sunken)'; document.body.appendChild(p); const c = getComputedStyle(p).backgroundColor; p.remove(); return c; })();
  const efetivo = (el) => { for (let n = el; n; n = n.parentElement) { const c = getComputedStyle(n).backgroundColor; if (c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent') return c; } return null; };
  const r = sb.getBoundingClientRect(); const x = (r.left + r.right) / 2;
  const pontos = [H - 3, Math.min(H - 3, r.bottom + 6)].map((y) => ({ y: Math.round(y), cor: efetivo(document.elementFromPoint(x, y)) }));
  return JSON.stringify({ sunken, pontos, linhaFim: linha.getBoundingClientRect().bottom + window.scrollY, docH: document.documentElement.scrollHeight });
})()`;

// Gaveta do pedido (mobile): chevron, Continuar, total e promo clicáveis (o elemento do centro é ele próprio), e último campo do formulário.
// Contentores com scroll próprio fora da gaveta (um só contexto de scroll: o documento do iframe)
const CONTEXTOS_SCROLL = `(() => {
  const g = document.querySelector('.cart-sidebar'); const out = [];
  for (const el of document.querySelectorAll('body *')) {
    if (g && g.contains(el)) continue;
    if (!/(auto|scroll)/.test(getComputedStyle(el).overflowY)) continue;
    if (el.getClientRects().length && el.clientHeight > 2 && el.scrollHeight > el.clientHeight + 1) out.push(el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + ' sh=' + el.scrollHeight + ' ch=' + el.clientHeight);
  }
  return JSON.stringify(out);
})()`;

const GAVETA_MOVEL = `(() => {
  const H = window.innerHeight;
  const vis = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
  const dentro = (el, x, y) => { const t = document.elementFromPoint(x, y); return !!t && (t === el || el.contains(t)); };
  const centro = (el) => { const r = el.getBoundingClientRect(); return [(r.left + r.right) / 2, (r.top + r.bottom) / 2]; };
  const ok = (el) => { const [x, y] = centro(el); return dentro(el, x, y); };
  const g = document.querySelector('.cart-sidebar');
  const gr = g && vis(g) ? g.getBoundingClientRect() : null;
  const seta = document.getElementById('button-cart-sider');
  const cont = [...document.querySelectorAll('#button-products')].find(vis);
  const tot = g ? [...g.querySelectorAll('table[id^="table-cart-resume-"]')].find(vis) : null;
  const promo = document.getElementById('promo-btn');
  const campos = [...document.querySelectorAll('input, select, textarea')].filter((e) => e.type !== 'hidden' && vis(e) && !(g && g.contains(e)));
  // Texto da gaveta que cruza a borda da gaveta (recolhida: nada deve espreitar por baixo da barra)
  const parciais = g && gr ? [...g.querySelectorAll('*')].filter((e) => vis(e) && [...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '')).filter((e) => { const r = e.getBoundingClientRect(); return r.bottom > gr.top + 0.5 && r.top < gr.bottom - 0.5 && (r.top < gr.top - 1 || r.bottom > gr.bottom + 1); }).map((e) => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + ' "' + e.textContent.trim().slice(0, 24) + '"') : [];
  const u0 = campos[campos.length - 1];
  // Select com select2: o que se vê e se clica é o contentor do select2 (irmão seguinte)
  const u = u0 && u0.nextElementSibling && u0.nextElementSibling.classList.contains('select2-container') ? u0.nextElementSibling : u0;
  return JSON.stringify({
    H,
    gaveta: gr ? { top: gr.top, bottom: gr.bottom, altura: gr.height, aberta: /\\bopen(Auto)?\\b/.test(g.className) } : null,
    seta: seta && vis(seta) ? { ok: ok(seta), top: seta.getBoundingClientRect().top } : null,
    continuar: cont ? { ok: ok(cont) } : null,
    total: tot ? { ok: ok(tot) && (() => { const r = tot.getBoundingClientRect(); return dentro(tot, (r.left + r.right) / 2, r.bottom - 4); })() } : null,
    promo: promo && vis(promo) ? { ok: ok(promo) } : null,
    parciais,
    ultimo: u ? { ok: ok(u), bottom: u.getBoundingClientRect().bottom } : null,
  });
})()`;

// Passo de entrega e pagamento: métodos de pagamento (tiles com radio de cart/payment.volt) visíveis e o select que recebe a escolha.
const METODOS_PAGAMENTO = `(() => {
  const radios = [...document.querySelectorAll("input[name='paymentRadio']")];
  const visiveis = radios.filter((r) => r.getClientRects().length > 0 && r.getBoundingClientRect().width > 0);
  return JSON.stringify({ total: radios.length, visiveis: visiveis.length });
})()`;
const SELECIONADO = `(() => {
  const s = document.getElementById('payment-method'); const o = s && s.selectedOptions[0];
  return JSON.stringify({ valor: s ? s.value : null, texto: o ? o.textContent.trim() : null });
})()`;

async function checkoutLayout() {
  const r = new Results('checkout-layout');
  // Duas línguas (pt é o predefinido; en entra por ?lang=en no iframe) × cinco larguras
  // Desktop a 900 px de altura; mobile nas alturas 844 e 700 e nas larguras 390 e 360
  const combinacoes = ['pt', 'en'].flatMap((idioma) => [[1440, 900], [1280, 900], [1024, 900], [768, 900], [390, 844], [360, 700], [390, 700], [360, 844]].map(([width, height]) => [idioma, width, height]));
  for (const [idioma, width, height] of combinacoes) {
    const page = await openBrowser({ profilesDir: PROFILES, name: `layout-${idioma}-${width}x${height}`, width, height });
    const tag = (passo, regra) => `${idioma} ${width}x${height} [${passo}] ${regra}`;
    let desktop = true; // definido abaixo pela largura do iframe (breakpoint da gaveta da Tickett: 900 px)
    try {
      await page.navigate(`${BASE}/`);
      await sleep(1800);
      if (idioma === 'en') await page.ev(`(() => { document.querySelector('[data-checkout] iframe').dataset.src = '/checkout?lang=en'; return true; })()`);
      await page.click('.hero__actions [data-checkout-open]');
      await page.waitFor(`document.querySelector('[data-checkout-loading]').hidden === true`, 15000);
      const fr = await page.frameContext(FRAME);
      const F = (expr) => page.ev(expr, fr);
      await page.waitFor(FRAME_VISIBLE('select.quantity-select'), 15000, fr);
      desktop = (await page.ev(`document.querySelector('[data-checkout] iframe').getBoundingClientRect().width`)) > 900;

      // Rola o documento do iframe (mesma origem) e mede. y: número ou 'fim'.
      async function rolar(y) {
        await page.ev(`(() => { const w = document.querySelector('[data-checkout] iframe').contentWindow; w.scrollTo(0, ${y === 'fim' ? 1e9 : y}); return true; })()`);
        await sleep(250);
        return JSON.parse(await F(LAYOUT_FRAME));
      }

      async function medir(passo) {
        await sleep(500);
        // Chaves em bruto em cada passo; no passo dados, telefone com o placeholder inteiro e seletor alinhado ao campo
        const brutas = JSON.parse(await F(CHAVES_EM_BRUTO));
        r.check(tag(passo, 'sem chaves em bruto no texto, placeholders e opções'), brutas.length === 0, brutas.slice(0, 5).join(' | '));
        if (passo === 'dados') {
          for (const c of JSON.parse(await F(TELEFONE))) {
            r.check(tag(passo, `telefone ${c.id}: placeholder inteiro no campo`), c.cabe, `${c.larg}px > ${c.util}px`);
            if (c.empilhado) {
              r.check(tag(passo, `telefone ${c.id}: empilhado (<380 px), seletor e campo com a mesma largura e alinhados`), Math.abs(c.larguraSel - c.larguraCampo) <= 1 && Math.abs(c.esqSel - c.esqCampo) <= 1,
                `seletor=${c.larguraSel}px@${c.esqSel} campo=${c.larguraCampo}px@${c.esqCampo}`);
            } else {
              r.check(tag(passo, `telefone ${c.id}: seletor e campo com a mesma altura e topo`), c.altSel !== null && Math.abs(c.altSel - c.altCampo) <= 1 && Math.abs(c.topoSel - c.topoCampo) <= 1,
                `seletor=${c.altSel}px@${c.topoSel} campo=${c.altCampo}px@${c.topoCampo}`);
            }
          }
        }
        // Altura do iframe = altura do corpo do modal (h)
        const h = JSON.parse(await page.ev(`(() => { const f = document.querySelector('[data-checkout] iframe').getBoundingClientRect(); const b = document.querySelector('[data-checkout] .fj-checkout__body').getBoundingClientRect(); return JSON.stringify({ iframe: Math.round(f.height), corpo: Math.round(b.height) }); })()`));
        r.check(tag(passo, '(h) iframe ocupa a altura do corpo do modal'), Math.abs(h.iframe - h.corpo) <= 1, `iframe=${h.iframe} corpo=${h.corpo}`);

        // Mobile: a gaveta recolhida (chevron real) para medir o estado que não pode tapar o último bilhete
        let m0 = await rolar(0);
        if (!desktop && m0.gaveta && m0.gaveta.aberta) {
          await F(FRAME_CLICK('#button-cart-sider'));
          await sleep(600);
        }
        const top = await rolar(0);
        const meio = await rolar(Math.max(0, Math.floor(top.pos.max / 2)));
        const fim = await rolar('fim');
        // (i) um só contexto de scroll: nenhum contentor interno (fora da gaveta) rola; só o documento do iframe
        const contextos = JSON.parse(await F(CONTEXTOS_SCROLL));
        r.check(tag(passo, '(i) um só contexto de scroll (só o documento do iframe rola)'), contextos.length === 0, contextos.slice(0, 3).join(' | '));
        // (h) desktop: o fundo creme da coluna do pedido chega ao fundo do iframe e a linha ao fim do documento (fim do scroll)
        if (desktop) {
          const fd = JSON.parse(await F(FUNDO_DIREITA));
          r.check(tag(passo, '(h) fundo da coluna do pedido até ao fundo do iframe (fim do scroll)'), !!fd && fd.pontos.every((p) => p.cor === fd.sunken), JSON.stringify(fd && fd.pontos));
          r.check(tag(passo, '(h) fundo da coluna do pedido até ao fim do documento (±2px, fim do scroll)'), !!fd && Math.abs(fd.linhaFim - fd.docH) <= 2, fd ? `linha=${Math.round(fd.linhaFim)} doc=${fd.docH}` : 'sem coluna');
        }

        // (f) desktop: Continue visível no topo, meio e fim do scroll
        if (desktop && top.continuar) { // sem botão Continue (passo de produtos sem carrinho) não se mede (f)
          for (const [nomePos, m] of [['topo', top], ['meio', meio], ['fim', fim]]) {
            const c = m.continuar;
            r.check(tag(passo, `(f) Continue visível no ${nomePos} do scroll`), !!c && c.visivel && c.top >= 0 && c.bottom <= m.H + 0.5,
              c ? `top=${Math.round(c.top)} bottom=${Math.round(c.bottom)} H=${m.H}` : 'sem Continue');
          }
        }
        // (a) último bilhete/elemento e Continue alcançáveis no fim do scroll (acima da gaveta, se houver)
        const limite = !desktop && fim.gaveta ? fim.gaveta.top : fim.H;
        const alvoOk = !!fim.alvo && fim.alvo.top >= 0 && fim.alvo.bottom <= limite + 0.5;
        const contOk = !fim.continuar || (fim.continuar.bottom <= fim.H + 0.5 && fim.continuar.top >= 0);
        r.check(tag(passo, '(a) último elemento e Continue alcançáveis rolando o documento do iframe'), alvoOk && contOk,
          `alvo=${fim.alvo ? fim.alvo.nome : '-'} bottom=${fim.alvo ? Math.round(fim.alvo.bottom) : '-'} limite=${Math.round(limite)}`);
        // (g) mobile: gaveta fixa em baixo e sem tapar o último elemento no fim do scroll
        if (!desktop) {
          const g = fim.gaveta;
          const fixa = !!g && g.pos === 'fixed' && Math.abs(g.bottom - fim.H) <= 2;
          r.check(tag(passo, '(g) gaveta visível, fixa em baixo, sem tapar o último elemento'), fixa && alvoOk,
            g ? `pos=${g.pos} bottom=${Math.round(g.bottom)} H=${fim.H} altura=${Math.round(g.altura)}` : 'sem gaveta');
        }
        // Mobile: chevron, Continuar e total na gaveta recolhida; último campo acima da gaveta; expandida até 85% com scroll interno
        if (!desktop && fim.gaveta) {
          const mc = JSON.parse(await F(GAVETA_MOVEL));
          r.check(tag(passo, '(m1) gaveta recolhida: chevron visível e clicável'), !!mc.seta && mc.seta.ok, JSON.stringify(mc.seta));
          if (mc.continuar) r.check(tag(passo, '(m2) gaveta recolhida: Continuar clicável'), mc.continuar.ok);
          r.check(tag(passo, '(m2) gaveta recolhida: sem texto parcial (só a barra e o Continuar)'), mc.parciais.length === 0, mc.parciais.slice(0, 3).join(' | '));
          if (['dados', 'entrega', 'pagamento'].includes(passo) && mc.ultimo) r.check(tag(passo, '(m3) último campo do formulário acima da gaveta e clicável'), mc.ultimo.ok && mc.ultimo.bottom <= mc.gaveta.top + 0.5, `bottom=${Math.round(mc.ultimo.bottom)} gaveta=${Math.round(mc.gaveta.top)} ok=${mc.ultimo.ok}`);
          await F(FRAME_CLICK('#button-cart-sider'));
          await sleep(600);
          const me = JSON.parse(await F(GAVETA_MOVEL));
          r.check(tag(passo, '(m4) gaveta expandida: aberta e até 85% da altura visível'), !!me.gaveta && me.gaveta.aberta && me.gaveta.altura <= 0.85 * me.H + 1, me.gaveta ? `altura=${Math.round(me.gaveta.altura)} H=${me.H}` : 'sem gaveta');
          r.check(tag(passo, '(m4) gaveta expandida: chevron clicável (recolhe)'), !!me.seta && me.seta.ok);
          if (me.promo) r.check(tag(passo, '(m4) gaveta expandida: cupão visível e clicável'), me.promo.ok);
          await F(`(() => { const g = document.querySelector('.cart-sidebar'); g.scrollTop = g.scrollHeight; return true; })()`);
          await sleep(200);
          const fe = JSON.parse(await F(GAVETA_MOVEL));
          if (fe.continuar) r.check(tag(passo, '(m4) gaveta expandida, fim do scroll: Continuar clicável'), fe.continuar.ok);
          if (fe.total) r.check(tag(passo, '(m4) gaveta expandida, fim do scroll: total visível, sem Continuar por cima'), fe.total.ok);
          await F(`(() => { const g = document.querySelector('.cart-sidebar'); g.scrollTop = 0; return true; })()`);
          await F(FRAME_CLICK('#button-cart-sider'));
          await sleep(600);
        }
        r.check(tag(passo, '(b) sem overflow escondido com conteúdo'), fim.escondido.length === 0, fim.escondido.slice(0, 3).join(' | '));
        r.check(tag(passo, '(c) sem barra de scroll sem necessidade'), fim.semNecessidade.length === 0, fim.semNecessidade.slice(0, 3).join(' | '));
        r.check(tag(passo, '(d) nada sai pela direita'), fim.direita.length === 0, fim.direita.slice(0, 3).join(' | '));
        const diff = Math.abs(top.margemEsq - top.margemDir);
        r.check(tag(passo, `(e) margens laterais iguais (±${TOL_MARGEM}px)`), diff <= TOL_MARGEM, `esq=${top.margemEsq} dir=${top.margemDir}`);
        await rolar(0);
        if (desktop) {
          const ft = JSON.parse(await F(FUNDO_DIREITA));
          r.check(tag(passo, '(h) fundo da coluna do pedido até ao fundo do iframe (topo do scroll)'), !!ft && ft.pontos.every((p) => p.cor === ft.sunken), JSON.stringify(ft && ft.pontos));
        }
        await page.screenshot(path.join(OUT, `layout-${idioma}-${width}x${height}-${passo}.png`));
      }

      await medir('produtos');
      await F(`(() => { const s = document.querySelector('select.quantity-select'); s.value = '1'; window.jQuery(s).change(); return true; })()`);
      await page.waitFor(`document.querySelectorAll('table.table-cart-resume tbody tr').length > 0`, 8000, fr);
      await medir('carrinho');

      await F(FRAME_CLICK('#button-products'));
      await page.waitFor(FRAME_VISIBLE('#tickets-form-register'), 15000, fr);
      // Os selects de país e cidade são preenchidos pelo JS do checkout (login/site.volt): medir só depois
      await page.waitFor(`document.querySelectorAll('#register_country option').length > 1`, 20000, fr);
      await medir('dados');
      await F(`(() => { const f = document.getElementById('tickets-form-register'); f.querySelectorAll('input').forEach((i) => { if (i.type === 'hidden' || i.type === 'checkbox' || i.type === 'radio') return; if (i.type === 'email' || (i.name && /mail/i.test(i.name))) i.value = 'teste@festeja.pt'; else if (!i.value) i.value = 'Teste'; }); return true; })()`);
      await F(FRAME_CLICK('#button-products'));
      await page.waitFor(`(() => { const b = document.getElementById('button-products'); return !!b && !b.disabled && b.getAttribute('onclick') === 'goNewFunction(3)'; })()`, 15000, fr);
      await page.waitFor(FRAME_VISIBLE('#tickets-form-delivery-payment'), 15000, fr);
      await medir('entrega');
      // Modo de pagamento: os métodos aparecem, e clicar num define o select (o que o template usa para submeter)
      const metodos = JSON.parse(await F(METODOS_PAGAMENTO));
      r.check(tag('entrega', 'modo de pagamento: métodos de pagamento visíveis (>0)'), metodos.visiveis > 0, JSON.stringify(metodos));
      if (metodos.visiveis > 0) {
        await F(`(() => { const r = document.querySelector("input[name='paymentRadio']"); r.click(); return true; })()`);
        await sleep(300);
        const sel = JSON.parse(await F(SELECIONADO));
        const primeiro = await F(`(() => document.querySelector("input[name='paymentRadio']").value)()`);
        r.check(tag('entrega', 'modo de pagamento: escolher um método define o select (sem o placeholder)'), !!sel.valor && sel.valor !== '0' && !!sel.texto && sel.texto.includes(primeiro), JSON.stringify(sel));
      }

      await F(FRAME_CLICK('#button-products'));
      await page.waitFor(FRAME_VISIBLE('#tickets-form-payment'), 15000, fr);
      await medir('pagamento');

      // Fim da compra (delivery/sent.volt): só se confere as chaves em bruto
      await F(FRAME_CLICK('#button-payment'));
      const fimOk = await page.waitFor(`!!document.querySelector('.cart-finish')`, 10000, fr);
      r.check(tag('fim', 'chega ao passo fim (.cart-finish)'), fimOk);
      const brutasFim = JSON.parse(await F(CHAVES_EM_BRUTO));
      r.check(tag('fim', 'sem chaves em bruto no texto, placeholders e opções'), brutasFim.length === 0, brutasFim.slice(0, 5).join(' | '));
    } catch (error) {
      r.check(`${width}: execução sem exceções`, false, error.stack || error.message);
    } finally {
      await page.close();
    }
  }
  return r.save();
}

// ---------------------------------------------------------------- i18n (pt e en)

// Pares pt ↔ en: o mesmo mapa de App\Helpers\Rotas. Os artistas usam o mesmo slug nos dois idiomas.
const PARES_I18N = [
  ['/', '/en'], ['/cartaz', '/en/line-up'], ['/bilhetes', '/en/tickets'], ['/informacoes', '/en/info'],
  ['/como-chegar', '/en/getting-there'], ['/artistas/paulo-flores', '/en/artists/paulo-flores'],
  ['/artistas/kizomba-na-rua', '/en/artists/kizomba-na-rua'], ['/acessibilidade', '/en/accessibility'],
  ['/privacidade', '/en/privacy'], ['/cookies', '/en/cookies'], ['/termos', '/en/terms'],
];
// Palavras-sinal do português. Só contam como palavras inteiras (sem letras coladas), e sem diferenciar maiúsculas
// nas palavras comuns. "PT" do seletor não está na lista. Nomes próprios (artistas, festival, local) não estão.
const SINAIS_PT = new RegExp('(?<![\\p{L}])(' + [
  'Comprar', 'bilhetes?', 'Cartaz', 'Informações', 'Como chegar', 'Perguntas', 'Esgotado', 'Acresce', 'Brevemente',
  'Últimos', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro',
  'novembro', 'dezembro', 'Sexta', 'Sábado', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Portas', 'Início',
  'Escolher', 'Saltar', 'Fechar', 'Abrir', 'Pausar', 'Biografia', 'Morada', 'Transportes', 'Rascunho', 'Também',
  'Venda', 'Taxas?', 'Desde', 'Próximo', 'Faltam', 'Falta', 'Hoje', 'Lisboa', 'Acessibilidade', 'Privacidade',
  'Política', 'Termos', 'Parceiros', 'Caminho', 'Rodapé', 'Idioma', 'Temas', 'Dúvidas', 'Segue', 'Local', 'Datas',
  'Organização', 'Pagamento', 'Dias', 'Dia', 'Horários', 'Ver', 'Factos', 'Novo', 'Mais', 'Em breve',
  'incluídas', 'Trocas', 'Devoluções', 'Entrada', 'Plateia', 'Recinto', 'Pulseira', 'Acesso',
].join('|') + ')(?![\\p{L}])', 'giu');

// Texto visível de uma página: sem scripts nem estilos, com título e descrição, e sem os nomes de bilhetes da Tickett
// (dados do catálogo, não texto do site). Devolve { texto, bilhetes } para o relatório.
function textoVisivel(html) {
  // Nomes e descrições dos bilhetes vêm do catálogo da Tickett (dados, não texto do site): ficam de fora.
  const bilhetes = [...html.matchAll(/class="fj-ticket__(?:name|desc)">([^<]*)</g)].map((m) => decodificar(m[1]));
  const titulo = (html.match(/<title>([^<]*)</) || [])[1] || '';
  const descricao = (html.match(/name="description" content="([^"]*)"/) || [])[1] || '';
  let corpo = html.replace(/<head\b[\s\S]*?<\/head>/i, '').replace(/<(script|style)\b[\s\S]*?<\/\1>/gi, ' ');
  corpo = corpo.replace(/<[^>]+>/g, ' ');
  // Valores de atributos visíveis (aria-label, alt, title, placeholder) entram como texto.
  const atributos = [...html.matchAll(/(?:aria-label|alt|title|placeholder)="([^"]*)"/g)].map((m) => m[1]).join(' ');
  let texto = [titulo, descricao, decodificar(corpo), decodificar(atributos)].join(' ');
  for (const nome of bilhetes) if (nome) texto = texto.split(nome).join(' ');
  return { texto, bilhetes };
}

function decodificar(s) {
  return s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;|&#39;/g, "'").replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>').replace(/&nbsp;/g, ' ').replace(/&hellip;/g, '…').replace(/&middot;/g, '·')
    .replace(/&mdash;/g, '—').replace(/&ndash;/g, '–').replace(/&copy;/g, '©').replace(/\s+/g, ' ');
}

async function i18n() {
  const r = new Results('i18n');
  const lerPagina = async (caminho) => {
    const res = await fetch(BASE + caminho, { redirect: 'manual' });
    return { status: res.status, html: await res.text() };
  };
  // Bloco do seletor (nav.site-header__idioma): os dois links com hreflang, lang e aria-current.
  const seletor = (html) => {
    const bloco = (html.match(/<nav class="site-header__idioma"[\s\S]*?<\/nav>/) || [''])[0];
    const link = (hreflang) => {
      const m = bloco.match(new RegExp(`<a href="([^"]*)" hreflang="${hreflang}" lang="${hreflang}"([^>]*)>`));
      return m ? { href: m[1], atual: /aria-current="true"/.test(m[2]) } : null;
    };
    return { pt: link('pt-PT'), en: link('en') };
  };
  const hreflangs = (html) => [...html.matchAll(/<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">/g)]
    .map((m) => [m[1], m[2].replace(BASE, '')]);
  const canonical = (html) => (html.match(/<link rel="canonical" href="([^"]+)"/) || [])[1]?.replace(BASE, '');
  const lang = (html) => (html.match(/<html lang="([^"]+)"/) || [])[1];

  const paginas = new Map();
  for (const [pt, en] of PARES_I18N) {
    paginas.set(pt, await lerPagina(pt));
    paginas.set(en, await lerPagina(en));
  }

  for (const [pt, en] of PARES_I18N) {
    const P = paginas.get(pt);
    const E = paginas.get(en);
    r.check(`${pt} HTTP 200`, P.status === 200, P.status);
    r.check(`${en} HTTP 200`, E.status === 200, E.status);
    if (P.status !== 200 || E.status !== 200) continue;

    r.check(`${pt} lang pt-PT`, lang(P.html) === 'pt-PT', lang(P.html));
    r.check(`${en} lang en`, lang(E.html) === 'en', lang(E.html));

    const hP = hreflangs(P.html);
    const hE = hreflangs(E.html);
    const recipPT = hP.some(([h, p]) => h === 'en' && p === en) && hE.some(([h, p]) => h === 'pt-PT' && p === pt);
    r.check(`${pt} ↔ ${en} hreflang recíprocos (pt-PT e en)`, recipPT, JSON.stringify({ pt: hP, en: hE }));
    const xdef = hP.some(([h, p]) => h === 'x-default' && p === pt) && hE.some(([h, p]) => h === 'x-default' && p === pt);
    r.check(`${pt} / ${en} x-default aponta para o português`, xdef);

    r.check(`${pt} canonical = própria página`, canonical(P.html) === pt, canonical(P.html));
    r.check(`${en} canonical = própria página`, canonical(E.html) === en, canonical(E.html));

    const sP = seletor(P.html);
    const sE = seletor(E.html);
    r.check(`${pt} seletor: PT marcado e EN aponta para ${en}`,
      sP.pt?.atual === true && sP.en?.href === en && sP.en?.atual === false, JSON.stringify(sP));
    r.check(`${en} seletor: EN marcado e PT aponta para ${pt}`,
      sE.en?.atual === true && sE.pt?.href === pt && sE.pt?.atual === false, JSON.stringify(sE));

    const textoE = textoVisivel(E.html);
    const sinais = [...new Set([...textoE.texto.matchAll(SINAIS_PT)].map((m) => m[0]))];
    r.check(`${en} sem texto em português visível`, sinais.length === 0,
      sinais.length ? `encontrado: ${sinais.join(', ')}` : `bilhetes da Tickett ignorados: ${textoE.bilhetes.length}`);
  }

  // 404 em inglês para rotas /en/* inexistentes
  const naoExiste = await lerPagina('/en/inexistente');
  r.check('/en/inexistente HTTP 404 com lang en', naoExiste.status === 404 && lang(naoExiste.html) === 'en', naoExiste.status);

  // O modal em inglês abre o checkout com ?lang=en e mostra o título em inglês.
  const page = await openBrowser({ profilesDir: PROFILES, name: 'i18n-modal', width: WIDTHS.desktop, height: 900 });
  try {
    await page.send('Page.addScriptToEvaluateOnNewDocument', { source: 'window.dataLayer = [];' });
    await page.navigate(`${BASE}/en`);
    await sleep(2000);
    await page.click('.hero__actions [data-checkout-open]');
    const src = await page.ev(`document.querySelector('[data-checkout] iframe').getAttribute('src')`);
    r.check('modal em /en abre /checkout?lang=en', src === '/checkout?lang=en', src);
    r.check('modal em inglês: título "Buy tickets"',
      (await page.ev(`document.querySelector('[data-checkout] h2').textContent.trim()`)) === 'Buy tickets');
    const pronto = await page.waitFor(`document.querySelector('[data-checkout-loading]').hidden === true`, 15000);
    r.check('simulador em inglês envia ready', pronto);
    const passo = await page.ev(`(() => { const dados = document.querySelector('[data-checkout]').dataset.textos; return JSON.parse(dados).passos.details; })()`);
    r.check('textos de passo vindos de data-textos (en)', passo === 'Your details', passo);
  } finally {
    await page.close();
  }

  return r.save();
}

// ---------------------------------------------------------------- sem evento (produção sem TICKETT_EVENT_ID)

const PORTA_SEM_EVENTO = Number(process.env.QA_SEM_EVENTO_PORTA || 8090);
const TEXTO_PT_EM_BREVE = 'A venda de bilhetes abre em breve. Segue-nos no Instagram para saberes primeiro.';
const TEXTO_EN_EM_BREVE = 'Ticket sales open soon. Follow us on Instagram to hear first.';

async function semEvento() {
  const r = new Results('sem-evento');
  const base = `http://127.0.0.1:${PORTA_SEM_EVENTO}`;
  if (await fetch(base + '/').then(() => true).catch(() => false)) {
    throw new Error(`a porta ${PORTA_SEM_EVENTO} já está em uso (QA_SEM_EVENTO_PORTA)`);
  }

  // Produção sem TICKETT_EVENT_ID e sem modo demonstração (TICKETT_DEMO=0): não há evento. TICKETT_BASE aponta a uma
  // porta fechada: se o site contactasse a Tickett, o erro aparece no log do servidor.
  const env = { ...process.env, SITE_ENV: 'prod', BASE_URL: base, TICKETT_BASE: 'http://127.0.0.1:9', TICKETT_DEMO: '0' };
  delete env.TICKETT_EVENT_ID;
  delete env.VERCEL;
  const logFile = path.join(OUT, 'sem-evento-servidor.log');
  const log = fs.openSync(logFile, 'w');
  const servidor = spawn('php', ['-S', `127.0.0.1:${PORTA_SEM_EVENTO}`, '-t', 'public', 'public/router-dev.php'],
    { cwd: PROJECT, env, stdio: ['ignore', log, log] });

  try {
    const no_ar = await esperarServidor(base);
    if (!r.check('servidor temporário responde', no_ar, base)) {
      return r.save();
    }

    const paginas = ['/', '/en', '/bilhetes', '/en/tickets', '/cartaz', '/en/line-up', '/informacoes', '/como-chegar',
      '/llms.txt', '/sitemap.xml', '/robots.txt'];
    const textos = {};
    for (const p of paginas) {
      const res = await fetch(base + p, { redirect: 'manual' });
      textos[p] = await res.text();
      r.check(`${p} HTTP 200`, res.status === 200, String(res.status));
      r.check(`${p} sem nenhum link para lebillet`, !/lebillet/i.test(textos[p]));
    }

    for (const [metodo, caminho] of [['GET', '/checkout'], ['GET', '/checkout/relay/2001/x'], ['POST', '/checkout/relay/2001/x']]) {
      const res = await fetch(base + caminho, { method: metodo, redirect: 'manual' });
      r.check(`${metodo} ${caminho} HTTP 404`, res.status === 404, String(res.status));
    }

    r.check('/bilhetes mostra a mensagem em pt', textos['/bilhetes'].includes(TEXTO_PT_EM_BREVE));
    r.check('/en/tickets mostra a mensagem em en', textos['/en/tickets'].includes(TEXTO_EN_EM_BREVE));
    r.check('/bilhetes com link para o Instagram', textos['/bilhetes'].includes('href="https://www.instagram.com/festeja_pt"'));

    for (const p of ['/', '/en', '/bilhetes', '/en/tickets']) {
      r.check(`${p} sem data-checkout-open nem modal`, !/data-checkout/.test(textos[p]));
    }
    r.check('JSON-LD da home sem Offers', !textos['/'].includes('"@type":"Offer"'));
    r.check('JSON-LD de /bilhetes sem Offers', !textos['/bilhetes'].includes('"@type":"Offer"'));
    r.check('llms.txt diz venda em breve', textos['/llms.txt'].includes('venda em breve'));

    // Qualquer aviso PHP ou tentativa de contacto à Tickett fica no log do servidor.
    await sleep(200);
    const problemas = fs.readFileSync(logFile, 'utf8').split('\n')
      .filter((l) => /PHP (Warning|Notice|Fatal|Deprecated)|TickettService|Texto em falta|Erro/i.test(l));
    r.check('sem avisos PHP nem contacto à Tickett no log', problemas.length === 0, problemas.slice(0, 3).join(' | '));
  } finally {
    servidor.kill('SIGTERM');
    fs.closeSync(log);
  }

  return r.save();
}

async function esperarServidor(base, ms = 10000) {
  const fim = Date.now() + ms;
  while (Date.now() < fim) {
    if (await fetch(base + '/').then(() => true).catch(() => false)) return true;
    await sleep(200);
  }
  return false;
}

// ---------------------------------------------------------------- main

const commands = { compra, escolher, 'checkout-layout': checkoutLayout, a11y, teclado, movimento, semjs, overflow, desempenho, seo, i18n, fallback, 'sem-evento': semEvento };
const [cmd, arg] = process.argv.slice(2);

async function main() {
  if (!cmd || cmd === 'help') {
    console.log(fs.readFileSync(fileURLToPath(import.meta.url), 'utf8').split('\n').slice(1, 17).join('\n'));
    return 0;
  }
  if (cmd === 'compra') {
    return (await compra(Number(arg) || WIDTHS.desktop)) + (arg ? 0 : (await compra(WIDTHS.mobile)));
  }
  if (cmd === 'all') {
    let fails = 0;
    for (const name of ['compra', 'escolher', 'checkout-layout', 'a11y', 'teclado', 'movimento', 'semjs', 'overflow', 'desempenho', 'seo', 'i18n', 'sem-evento']) {
      fails += name === 'compra' ? await compra(WIDTHS.desktop) + await compra(WIDTHS.mobile) : await commands[name]();
    }
    return fails;
  }
  if (!commands[cmd]) {
    console.error(`comando desconhecido: ${cmd}`);
    return 2;
  }
  return commands[cmd]();
}

const fails = await main();
console.log(`\nFALHAS TOTAIS: ${fails}`);
process.exit(fails ? 1 : 0);
