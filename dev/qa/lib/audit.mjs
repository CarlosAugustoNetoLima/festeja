// Auditoria de acessibilidade e SEO no DOM. A função `auditPage` corre dentro da página (via Runtime.evaluate).
// Os nomes acessíveis vêm da árvore de acessibilidade do Chrome (Accessibility.getFullAXTree).

// Corre dentro da página. Devolve um objeto com os problemas encontrados.
export function auditPage() {
  // targets: alvos com menos de 24 × 24 px (falha, WCAG 2.2 AA 2.5.8). targetsInfo: entre 24 e 44 px (só aviso, AAA 2.5.5).
  const issues = { headings: [], images: [], ids: [], names: [], contrast: [], targets: [], targetsInfo: [], forms: [] };
  const counts = { contrastChecked: 0, contrastSkipped: 0, targetsChecked: 0 };

  const visible = (el) => {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    if (el.closest('[hidden],[aria-hidden="true"]')) return false;
    return el.getClientRects().length > 0;
  };
  const label = (el) => {
    const id = el.id ? `#${el.id}` : '';
    const cls = typeof el.className === 'string' && el.className.trim() ? `.${el.className.trim().split(/\s+/)[0]}` : '';
    return `${el.tagName.toLowerCase()}${id}${cls}`;
  };

  // Títulos
  const headingEls = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter(visible);
  const h1Count = headingEls.filter((h) => h.tagName === 'H1').length;
  const levels = headingEls.map((h) => Number(h.tagName[1]));
  for (let i = 1; i < levels.length; i++) {
    if (levels[i] > levels[i - 1] + 1) {
      issues.headings.push(`salto h${levels[i - 1]} → h${levels[i]} em "${headingEls[i].textContent.trim().slice(0, 60)}"`);
    }
  }

  // Imagens
  for (const img of document.querySelectorAll('img')) {
    if (!img.hasAttribute('alt')) {
      issues.images.push(`sem alt: ${img.getAttribute('src')}`);
    } else if (img.getAttribute('alt') === '' && img.getAttribute('aria-hidden') !== 'true' && visible(img)) {
      issues.images.push(`alt="" sem aria-hidden (aceitável, mas confirmar): ${img.getAttribute('src')}`);
    }
  }

  // Ids duplicados
  const idCount = new Map();
  for (const el of document.querySelectorAll('[id]')) idCount.set(el.id, (idCount.get(el.id) || 0) + 1);
  for (const [id, n] of idCount) if (n > 1) issues.ids.push(`#${id} ×${n}`);

  // Campos sem rótulo
  for (const input of document.querySelectorAll('input:not([type=hidden]),select,textarea')) {
    if (!visible(input)) continue;
    const named = input.labels?.length || input.getAttribute('aria-label') || input.getAttribute('aria-labelledby') || input.title;
    if (!named) issues.forms.push(`campo sem rótulo: ${label(input)}`);
  }

  // Contraste (texto visível; ignora texto sobre imagem/fundo com imagem)
  const parse = (s) => {
    const m = s && s.match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const p = m[1].replace('/', ' ').split(/[\s,]+/).filter(Boolean).map(Number);
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const lum = ({ r, g, b }) => {
    const f = (c) => { const v = c / 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const ratio = (a, b) => {
    const [l1, l2] = [lum(a), lum(b)];
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };
  const over = (top, bot) => ({
    r: top.r * top.a + bot.r * (1 - top.a),
    g: top.g * top.a + bot.g * (1 - top.a),
    b: top.b * top.a + bot.b * (1 - top.a),
    a: 1,
  });
  const opacityOf = (el) => {
    let op = 1;
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) op *= Number(getComputedStyle(n).opacity);
    return op;
  };
  // Fundo efetivo: sobe na árvore até um fundo opaco. Null se houver imagem de fundo.
  const backdrop = (el) => {
    const layers = [];
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
      const cs = getComputedStyle(n);
      if (cs.backgroundImage !== 'none') return null;
      const c = parse(cs.backgroundColor);
      if (c && c.a > 0) {
        layers.push(c);
        if (c.a >= 1) break;
      }
    }
    let base = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = layers.length - 1; i >= 0; i--) base = over(layers[i], base);
    return base;
  };
  const textNodes = (el) => [...el.childNodes].filter((n) => n.nodeType === 3 && n.textContent.trim());

  for (const el of document.querySelectorAll('body *')) {
    if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'SVG', 'PATH'].includes(el.tagName)) continue;
    if (textNodes(el).length === 0 || !visible(el)) continue;
    const rect = el.getBoundingClientRect();
    if (rect.width <= 2 || rect.height <= 2 || rect.bottom <= 0 || rect.top >= innerHeight * 4) continue;
    if (el.closest('.skip-link')) continue;

    const cs = getComputedStyle(el);
    const fg = parse(cs.color);
    const bg = backdrop(el);
    if (!fg || !bg) {
      counts.contrastSkipped++;
      continue;
    }
    // Imagem por baixo do texto (ex.: texto sobre foto): ignora.
    const cx = rect.left + rect.width / 2;
    const cy = Math.min(Math.max(rect.top + rect.height / 2, 0), innerHeight - 1);
    const overImage = document.elementsFromPoint(cx, cy).some((n) => n !== el && !n.contains(el) && !el.contains(n)
      && (['IMG', 'PICTURE', 'VIDEO', 'CANVAS'].includes(n.tagName)
        || (getComputedStyle(n).backgroundImage !== 'none')));
    if (overImage) {
      counts.contrastSkipped++;
      continue;
    }

    const alpha = fg.a * opacityOf(el);
    const blended = over({ ...fg, a: alpha }, bg);
    const r = ratio(blended, bg);
    const size = parseFloat(cs.fontSize);
    const bold = Number(cs.fontWeight) >= 700;
    const large = size >= 24 || (size >= 18.66 && bold);
    const needed = large ? 3 : 4.5;
    counts.contrastChecked++;
    if (r < needed) {
      const text = textNodes(el).map((n) => n.textContent.trim()).join(' ').slice(0, 50);
      issues.contrast.push({
        el: label(el), text, ratio: Math.round(r * 100) / 100, needed,
        size, bold, fg: cs.color, bg: `rgb(${Math.round(bg.r)},${Math.round(bg.g)},${Math.round(bg.b)})`,
      });
    }
  }

  // Alvos interativos (fora de texto corrido). Critério AA: 24 × 24 px (falha). Entre 24 e 44 px: aviso informativo.
  const TARGETS = 'a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button], [role=tab], [role=link]';
  for (const el of document.querySelectorAll(TARGETS)) {
    if (el.disabled || !visible(el) || el.closest('.skip-link')) continue;
    const rect = el.getBoundingClientRect();
    if (rect.width <= 2 || rect.height <= 2) continue;
    counts.targetsChecked++;
    if (rect.width >= 44 && rect.height >= 44) continue;
    const prose = el.closest('p, li, dd, td, blockquote, figcaption, h1, h2, h3, h4, h5, h6');
    const isProse = el.tagName === 'A' && prose && prose.textContent.replace(el.textContent, '').trim().length > 0;
    if (isProse) continue;
    const alvo = {
      el: label(el), text: (el.getAttribute('aria-label') || el.textContent || el.value || '').trim().slice(0, 40),
      w: Math.round(rect.width), h: Math.round(rect.height),
    };
    if (rect.width >= 24 && rect.height >= 24) issues.targetsInfo.push(alvo);
    else issues.targets.push(alvo);
  }

  // Metadados
  const ld = [];
  for (const s of document.querySelectorAll('script[type="application/ld+json"]')) {
    try {
      const data = JSON.parse(s.textContent);
      ld.push({ ok: true, type: Array.isArray(data['@graph']) ? 'graph' : data['@type'] });
    } catch (error) {
      ld.push({ ok: false, error: error.message });
    }
  }
  const ogs = {};
  for (const m of document.querySelectorAll('meta[property^="og:"]')) ogs[m.getAttribute('property')] = m.getAttribute('content');

  const skip = document.querySelector('.skip-link');
  const main = document.querySelector('main');

  return {
    title: document.title,
    description: document.querySelector('meta[name="description"]')?.getAttribute('content') ?? null,
    descriptionCount: document.querySelectorAll('meta[name="description"]').length,
    canonical: document.querySelector('link[rel="canonical"]')?.getAttribute('href') ?? null,
    lang: document.documentElement.lang,
    og: ogs,
    jsonld: ld,
    h1Count,
    headingLevels: levels,
    skipLink: skip ? { href: skip.getAttribute('href'), text: skip.textContent.trim() } : null,
    mainId: main ? main.id : null,
    landmarks: {
      header: document.querySelectorAll('header').length,
      footer: document.querySelectorAll('footer').length,
      main: document.querySelectorAll('main').length,
      navNoLabel: [...document.querySelectorAll('nav')].filter((n) => !n.getAttribute('aria-label') && !n.getAttribute('aria-labelledby')).length,
      navCount: document.querySelectorAll('nav').length,
    },
    scroll: { scrollWidth: document.documentElement.scrollWidth, innerWidth },
    counts,
    issues,
  };
}

// Papéis da árvore de acessibilidade que precisam de nome.
const NAMED_ROLES = new Set(['button', 'link', 'textbox', 'combobox', 'checkbox', 'radio', 'switch', 'tab', 'menuitem', 'searchbox', 'slider', 'spinbutton']);

// Lê a árvore de acessibilidade: nomes em falta e landmarks.
export async function axReport(page) {
  const { nodes } = await page.send('Accessibility.getFullAXTree');
  const missingNames = [];
  const landmarks = [];
  for (const node of nodes) {
    if (node.ignored) continue;
    const role = node.role?.value;
    const name = (node.name?.value || '').trim();
    if (NAMED_ROLES.has(role) && name === '' && node.backendDOMNodeId) {
      const html = await page.send('DOM.getOuterHTML', { backendNodeId: node.backendDOMNodeId }).then((r) => r.outerHTML).catch(() => '?');
      missingNames.push(`${role}: ${html.replace(/\s+/g, ' ').slice(0, 120)}`);
    }
    if (['banner', 'navigation', 'main', 'contentinfo', 'complementary', 'region'].includes(role)) {
      landmarks.push(`${role}${name ? `(${name})` : ''}`);
    }
  }
  return { missingNames, landmarks };
}
