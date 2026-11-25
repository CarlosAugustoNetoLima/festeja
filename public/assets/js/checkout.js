/*
 * Modal do checkout Tickett: abre o iframe, gere o foco e traduz as mensagens do checkout.
 * Contrato: docs/contrato-tickett.md (secções 2 e 3). Marcação: app/Views/Components/CheckoutModal.php.
 */

const SOURCE = 'tickett';
const VERSION = 1;
const READY_TIMEOUT_MS = 8000;
const CART_KEY = 'festeja-cart-count';
const PURCHASES_KEY = 'festeja-purchases';
const PURCHASE_DEDUPE_MS = 5000;
const SITE_ORDER_KEY = 'festeja-site-order';
const FOCUSABLE = 'a[href], button:not([disabled]), iframe, input, select, textarea, [tabindex]:not([tabindex="-1"])';
const PRODUCT_RE = /^\d+_\d+$/;

// Textos no idioma da página: data-textos do modal (CheckoutModal.php), com passos (títulos) e mensagem de compra.
function lerTextos(elemento) {
  try {
    const dados = JSON.parse(elemento.dataset.textos || '{}');
    return { passos: dados.passos || {}, compra: dados.compra || '' };
  } catch (error) {
    return { passos: {}, compra: '' };
  }
}

export default function init(root = document) {
  restoreCartCount(root);

  const modal = root.querySelector('[data-checkout]');
  if (!modal || modal.dataset.checkoutReady === '1') return;
  modal.dataset.checkoutReady = '1';
  const textos = lerTextos(modal);

  // O iframe é do próprio domínio (proxy /checkout): origem e destino das mensagens são os do site.
  const origin = window.location.origin;
  const iframe = modal.querySelector('iframe');
  // Lido em cada abertura (não ao iniciar): o data-src traz o idioma (?lang=) e pode ser trocado antes de abrir.
  const frameSrc = () => (iframe ? iframe.dataset.src || '' : '');
  const closeButton = modal.querySelector('[data-checkout-close]');
  const title = modal.querySelector('h2');
  const loading = modal.querySelector('[data-checkout-loading]');
  const alertBox = modal.querySelector('[data-checkout-alert]');
  const live = modal.querySelector('[data-checkout-live]');

  const purchases = new Set();

  const state = {
    started: false,
    ready: false,
    timer: 0,
    product: null,
    returnFocus: null,
    inerted: [],
    bodyOverflow: '',
  };

  function canOpen() {
    return frameSrc() !== '' && iframe !== null && closeButton !== null;
  }

  function pushEvent(payload) {
    const layer = window.dataLayer;
    if (layer && typeof layer.push === 'function') layer.push(payload);
  }

  function applyCartCount(count) {
    for (const badge of root.querySelectorAll('[data-cart-count]')) {
      badge.hidden = count <= 0;
      const label = badge.querySelector('span') || badge;
      label.textContent = String(count);
    }
  }

  function saveCartCount(count) {
    try {
      sessionStorage.setItem(CART_KEY, String(count));
    } catch (error) {
      // sessionStorage indisponível: o contador fica só nesta página.
    }
  }

  function restoreCartCount() {
    let count = 0;
    try {
      count = Number(sessionStorage.getItem(CART_KEY)) || 0;
    } catch (error) {
      count = 0;
    }
    applyCartCount(count);
  }

  function savedPurchases() {
    try {
      const saved = JSON.parse(sessionStorage.getItem(PURCHASES_KEY) || '[]');
      return Array.isArray(saved) ? saved : [];
    } catch (error) {
      return [];
    }
  }

  // Devolve true se esta compra já foi enviada ao dataLayer (nesta página ou antes, no sessionStorage).
  function isDuplicatePurchase(orderId) {
    if (purchases.has(orderId)) return true;
    if (savedPurchases().includes(orderId)) {
      purchases.add(orderId);
      return true;
    }
    purchases.add(orderId);
    try {
      sessionStorage.setItem(PURCHASES_KEY, JSON.stringify([...savedPurchases(), orderId]));
    } catch (error) {
      // sessionStorage indisponível: fica só o Set em memória.
    }
    return false;
  }

  function lastSiteOrder() {
    try {
      return JSON.parse(sessionStorage.getItem(SITE_ORDER_KEY) || 'null');
    } catch (error) {
      return null;
    }
  }

  function saveSiteOrder(value) {
    try {
      sessionStorage.setItem(SITE_ORDER_KEY, JSON.stringify(value));
    } catch (error) {
      // sessionStorage indisponível: a deduplicação fica só nesta página.
    }
  }

  function setTitle(step) {
    if (Object.hasOwn(textos.passos, step)) title.textContent = textos.passos[step];
  }

  // Produto no formato id_product_id_type (ex.: 9001_1), o mesmo do ?produto= do checkout.
  function parseProduct(raw) {
    return PRODUCT_RE.test(raw || '') ? raw : null;
  }

  // O data-src já pode ter query (?lang=pt): o produto entra com & nesse caso.
  function checkoutUrl(product) {
    const base = frameSrc();
    if (product === null) return base;
    return `${base}${base.includes('?') ? '&' : '?'}produto=${encodeURIComponent(product)}`;
  }

  function loadFrame(product) {
    state.ready = false;
    state.product = product;
    loading.hidden = false;
    alertBox.hidden = true;
    iframe.src = checkoutUrl(product);
    startTimer();
  }

  function startTimer() {
    clearTimeout(state.timer);
    state.timer = window.setTimeout(() => {
      if (state.ready) return;
      loading.hidden = true;
      alertBox.hidden = false;
    }, READY_TIMEOUT_MS);
  }

  function lockPage() {
    state.inerted = [];
    for (let node = modal; node.parentElement; node = node.parentElement) {
      for (const sibling of node.parentElement.children) {
        if (sibling !== node && !sibling.inert) {
          sibling.inert = true;
          state.inerted.push(sibling);
        }
      }
    }
    state.bodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
  }

  function unlockPage() {
    for (const element of state.inerted) element.inert = false;
    state.inerted = [];
    document.body.style.overflow = state.bodyOverflow;
  }

  function open(trigger, product) {
    if (!modal.hidden) return;

    if (!state.started) {
      state.started = true;
      pushEvent({ event: 'begin_checkout' });
    }
    if (product !== null) {
      pushEvent({ event: 'select_item', ecommerce: { items: [{ item_id: String(product) }] } });
    }

    state.returnFocus = trigger;
    modal.hidden = false;
    lockPage();
    closeButton.focus();

    // Primeira abertura carrega o checkout; com outro bilhete, recarrega-o com ?produto= (a Tickett pré-seleciona
    // só no carregamento da página). Sem produto novo, o checkout já aberto fica como está.
    if (!iframe.getAttribute('src')) {
      loadFrame(product);
    } else if (product !== null && product !== state.product) {
      loadFrame(product);
    }
  }

  function close() {
    if (modal.hidden) return;
    modal.hidden = true;
    unlockPage();
    const target = state.returnFocus;
    state.returnFocus = null;
    if (target && target.isConnected) target.focus();
  }

  function focusables() {
    return Array.from(modal.querySelectorAll(FOCUSABLE))
      .filter((element) => !element.disabled && !element.closest('[hidden]'));
  }

  function onKeydown(event) {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;

    const items = focusables();
    if (items.length === 0) return;
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    if (event.shiftKey && (active === first || !modal.contains(active))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (active === last || !modal.contains(active))) {
      event.preventDefault();
      first.focus();
    }
  }

  function onMessage(event) {
    if (event.origin !== origin || event.source !== iframe.contentWindow) return;
    const message = event.data;
    if (!message || typeof message !== 'object') return;
    if (message.source !== SOURCE || message.version !== VERSION) return;
    const data = message.data && typeof message.data === 'object' ? message.data : {};

    switch (message.type) {
      case 'ready': {
        state.ready = true;
        clearTimeout(state.timer);
        loading.hidden = true;
        alertBox.hidden = true;
        break;
      }
      case 'resize': {
        // Mensagem do contrato (docs/contrato-tickett.md), ignorada de propósito: o iframe ocupa a altura do corpo
        // do modal (checkout.css) e é o documento do iframe que rola. A ponte não comanda a altura.
        break;
      }
      case 'step': {
        if (!Object.hasOwn(textos.passos, data.step)) break;
        setTitle(data.step);
        pushEvent({ event: 'checkout_step', step: data.step });
        break;
      }
      case 'cart': {
        const count = Math.max(0, Math.floor(Number(data.count)));
        if (!Number.isFinite(count)) break;
        applyCartCount(count);
        saveCartCount(count);
        break;
      }
      case 'purchase': {
        let orderId = data.order_id === null || data.order_id === undefined || data.order_id === '' ? null : String(data.order_id);
        let source = 'tickett';
        if (orderId === null) {
          // O checkout não expõe o número da encomenda (sem ele, a conclusão não se identifica). O site gera um id
          // próprio, guardado em sessionStorage: a mesma conclusão repetida dentro de 5 s não se conta duas vezes.
          const last = lastSiteOrder();
          if (last && Date.now() - last.at < PURCHASE_DEDUPE_MS) break;
          orderId = `fj-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
          saveSiteOrder({ id: orderId, at: Date.now() });
          source = 'site';
        } else if (isDuplicatePurchase(orderId)) {
          break;
        }
        const items = Array.isArray(data.items) ? data.items : [];
        pushEvent({
          event: 'purchase',
          order_id_source: source,
          ecommerce: {
            transaction_id: orderId,
            value: Number(data.total),
            currency: data.currency,
            items: items
              .filter((item) => item && typeof item === 'object')
              .map((item) => ({
                item_id: String(item.id),
                item_name: item.name,
                quantity: item.quantity,
                price: item.price,
              })),
          },
        });
        if (live) live.textContent = textos.compra;
        break;
      }
      case 'close': {
        close();
        break;
      }
      default:
        break;
    }
  }

  root.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!(event.target instanceof Element)) return;

    const closeTarget = event.target.closest('[data-checkout-close]');
    if (closeTarget && modal.contains(closeTarget)) {
      event.preventDefault();
      close();
      return;
    }

    const trigger = event.target.closest('[data-checkout-open], [data-cart-count]');
    if (!trigger || !canOpen()) return;

    event.preventDefault();
    const product = trigger.matches('[data-checkout-open]') ? parseProduct(trigger.dataset.product) : null;
    open(trigger, product);
  });

  document.addEventListener('keydown', onKeydown);
  window.addEventListener('message', onMessage);
}
