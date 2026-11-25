/*
 * Ponte entre o checkout da Tickett (servido pelo proxy /checkout, mesmo domínio) e o site que o embebe.
 * Contrato: docs/contrato-tickett.md, secção 3. Sem dependências; ES5 (o checkout usa jQuery antigo).
 * Injetada pelo proxy com data-product="ID_TIPO" (ex.: 9001_1), que é a pré-seleção inicial.
 */
(function () {
	'use strict';

	var script = document.currentScript || null;
	var initialProduct = script ? script.getAttribute('data-product') || '' : '';
	var origin = window.location.origin;
	var inFrame = window.parent !== window;

	var lastStep = null;
	var lastHeight = 0;
	var purchaseSent = false;
	var lastSummary = null; // resumo do carrinho antes da compra (o carrinho é limpo ao concluir)
	var knownIds = {}; // nome do bilhete -> id_product, visto enquanto os blocos de bilhete estão no DOM

	/* Envia só ao site que embebe (mesma origem). Nunca "*". */
	function send(type, data) {
		if (!inFrame) return;
		window.parent.postMessage({ source: 'tickett', version: 1, type: type, data: data || {} }, origin);
	}

	/* ---------- utilitários ---------- */

	function isVisible(el) {
		return !!el && el.getClientRects().length > 0;
	}

	function firstVisible(selector) {
		var all = document.querySelectorAll(selector);
		for (var i = 0; i < all.length; i++) {
			if (isVisible(all[i])) return all[i];
		}
		return null;
	}

	/* "31,50 €" ou "1.234,50 €" -> 31.5 / 1234.5 */
	function parseMoney(text) {
		var clean = String(text || '').replace(/[^0-9,.\-]/g, '');
		if (clean === '') return null;
		if (clean.indexOf(',') !== -1) {
			clean = clean.replace(/\./g, '').replace(',', '.');
		}
		var value = Number(clean);
		return isNaN(value) ? null : value;
	}

	function text(el) {
		return el ? String(el.textContent || '').replace(/\s+/g, ' ').trim() : '';
	}

	/* ---------- resize ---------- */

	/* O scrollHeight do body fica preso à altura atual do iframe (overflow escondido pelo tema), por isso a
	   altura real é a base mais baixa do conteúdo: texto, controlos e tabelas. Contentores não contam (min-height
	   em vh ou 100% acompanham o próprio iframe e a altura nunca estabilizaria). Ignora absolutos/fixos (decoração). */
	var CONTENT_TAGS = /^(INPUT|SELECT|TEXTAREA|BUTTON|IMG|SVG|TABLE)$/;

	function hasOwnText(el) {
		for (var n = el.firstChild; n; n = n.nextSibling) {
			if (n.nodeType === 3 && n.textContent.trim() !== '') return true;
		}
		return false;
	}

	/* Dentro de um contentor absoluto/fixo (decoração, gaveta do carrinho no mobile) não conta para a altura do fluxo. */
	function outOfFlow(el) {
		for (var n = el; n && n !== document.body; n = n.parentElement) {
			var position = window.getComputedStyle(n).position;
			if (position === 'absolute' || position === 'fixed') return true;
		}
		return false;
	}

	function pageHeight() {
		var body = document.body;
		var bottom = 0;
		var all = body.getElementsByTagName('*');
		for (var i = 0; i < all.length; i++) {
			var el = all[i];
			if (el.getClientRects().length === 0) continue;
			if (!hasOwnText(el) && !CONTENT_TAGS.test(el.nodeName.toUpperCase())) continue;
			if (outOfFlow(el)) continue;
			var b = el.getBoundingClientRect().bottom + window.pageYOffset;
			if (b > bottom) bottom = b;
		}
		// Só a base do conteúdo: scrollHeight/offsetHeight ficam presos à altura anterior e a altura nunca encolheria.
		return Math.ceil(bottom + 16);
	}

	/* A mensagem resize continua no contrato, mas o site não a usa para a altura: o iframe ocupa a altura do corpo
	   do modal (checkout.css) e é o documento do iframe que rola (checkout.js ignora 'resize'). */
	function sendResize() {
		var height = pageHeight();
		if (height !== lastHeight) {
			lastHeight = height;
			send('resize', { height: height });
		}
	}

	function startResize() {
		if (typeof ResizeObserver !== 'undefined') {
			var observer = new ResizeObserver(function () { sendResize(); });
			observer.observe(document.body);
		} else {
			setInterval(sendResize, 500);
		}
		sendResize();
	}

	/* ---------- passo (step) ---------- */

	/* products | details | payment | done, deduzido do DOM visível */
	function currentStep() {
		if (firstVisible('.cart-finish')) return 'done';
		if (firstVisible('#tickets-form-delivery-payment, #tickets-form-payment, #button-delivery, #button-payment')) return 'payment';
		if (firstVisible('#tickets-form-register, #tickets-form-login, #tickets-form-forgot')) return 'details';
		return 'products';
	}

	/* Número da encomenda: "#123" nas views de conclusão (ex.: finishedMbWay); no pagamento myPOS vem no script fbq */
	function orderIdFromFinish() {
		var finish = firstVisible('.cart-finish');
		var match = finish ? text(finish).match(/#\s*(\d+)/) : null;
		if (match) return match[1];

		var scripts = document.getElementsByTagName('script');
		for (var i = 0; i < scripts.length; i++) {
			var body = scripts[i].textContent || '';
			var fbq = body.match(/order_id:\s*['"](\d+)['"]/);
			if (fbq) return fbq[1];
		}
		return null;
	}

	/* ---------- carrinho ---------- */

	/* ID do bilhete a partir do nome do tipo (a linha do carrinho) e do select product_ID_TIPO do seu ticket-box.
	   O nome do tipo ("Early Entry") pode não ser o nome do produto (h5), por isso procura-se no texto do bloco. */
	function productIdByName(name) {
		var boxes = document.querySelectorAll('table.ticket-box');
		for (var i = 0; i < boxes.length; i++) {
			if (text(boxes[i]).indexOf(name) === -1) continue;
			var select = boxes[i].querySelector('select[id^="product_"]');
			var match = select ? select.id.match(/^product_(\d+)_\d+$/) : null;
			if (match) return Number(match[1]);
		}
		return null;
	}

	/* Lê o carrinho do DOM: linhas "Nx nome" do resumo e a linha total-all */
	function readCart() {
		var items = [];
		var rows = document.querySelectorAll('table.table-cart-resume tbody tr');
		for (var i = 0; i < rows.length; i++) {
			var cells = rows[i].cells;
			if (!cells || cells.length < 2 || rows[i].closest('.sugestion')) continue;
			var match = text(cells[0]).match(/^(\d+)x\s+(.+)$/);
			if (!match) continue;
			var quantity = parseInt(match[1], 10);
			var lineTotal = parseMoney(text(cells[cells.length - 1]));
			var name = match[2];
			// Os blocos de bilhete só existem no passo de produtos: guarda-se o id quando se vê.
			var id = productIdByName(name);
			if (id !== null) knownIds[name] = id;
			items.push({
				id: id !== null ? id : (knownIds.hasOwnProperty(name) ? knownIds[name] : null),
				name: name,
				quantity: quantity,
				price: lineTotal !== null && quantity > 0 ? Math.round((lineTotal / quantity) * 100) / 100 : null
			});
		}

		var totalRow = document.querySelector('tr.total-all');
		var total = null;
		if (totalRow && totalRow.cells.length) {
			total = parseMoney(text(totalRow.cells[totalRow.cells.length - 1]));
		}
		return { items: items, total: total };
	}

	function sendCart() {
		var cart = readCart();
		var count = 0;
		for (var i = 0; i < cart.items.length; i++) count += cart.items[i].quantity;

		if (cart.items.length > 0) {
			lastSummary = cart;
		}
		send('cart', { count: count, total: cart.total, currency: 'EUR' });
	}

	/* ---------- compra ---------- */

	function sendPurchaseOnce() {
		if (purchaseSent) return;
		purchaseSent = true;

		var summary = lastSummary || { items: [], total: null };
		send('purchase', {
			order_id: orderIdFromFinish(),
			total: summary.total,
			currency: 'EUR',
			items: summary.items
		});
	}

	/* ---------- passo: envio e observação ---------- */

	/* Troca do .cart (payment → products → payment): o DOM passa por estados transitórios. O passo só é emitido
	   depois de estabilizar STEP_STABLE_MS sem mutações. Um regresso a products a partir de payment/details/done só
	   conta se a lista de bilhetes estiver visível (navegação real, ex.: "Voltar"); "Wait..." do .cart não conta. */
	var STEP_STABLE_MS = 150;
	var stepTimer = null;

	function commitStep() {
		stepTimer = null;
		var step = currentStep();
		if (step === lastStep) return;
		if (step === 'products' && (lastStep === 'payment' || lastStep === 'details' || lastStep === 'done')
			&& !firstVisible('#tickets-form-products')) return;
		lastStep = step;
		send('step', { step: step });
		if (step === 'done') sendPurchaseOnce();
	}

	function scheduleStep() {
		clearTimeout(stepTimer);
		stepTimer = setTimeout(commitStep, STEP_STABLE_MS);
	}

	function checkStep() {
		if (initialProduct !== '') highlightProduct(initialProduct);
		scheduleStep();
		sendResize();
	}

	function observeSteps() {
		var target = document.getElementById('tickets-container') || document.body;
		if (typeof MutationObserver !== 'undefined') {
			var observer = new MutationObserver(function () { checkStep(); });
			observer.observe(target, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
		} else {
			setInterval(checkStep, 1000);
		}
	}

	/* ---------- bilhete escolhido (realce, sem alterar o layout) ---------- */

	/* Realça o bilhete (table.ticket-box com select product_ID_TIPO) e leva-o à vista, uma vez. Não filtra nem esconde nada.
	   Chamado a cada mutação: os bilhetes chegam por AJAX depois do carregamento. */
	function highlightProduct(produto) {
		var select = document.querySelector('select[id="product_' + produto + '"]');
		var box = select && select.closest ? select.closest('table.ticket-box') : null;
		if (!box || box.className.indexOf('fj-produto-selecionado') !== -1) return;

		var previous = document.querySelectorAll('.fj-produto-selecionado');
		for (var i = 0; i < previous.length; i++) {
			previous[i].className = previous[i].className.replace(/\s*\bfj-produto-selecionado\b/g, '');
		}
		box.className += ' fj-produto-selecionado';
		box.scrollIntoView(true);
	}

	/* Mensagem select vinda do site. A Tickett só pré-seleciona no carregamento da página (?produto=),
	   por isso, se o bilhete for outro, a ponte recarrega a própria página com o novo ?produto=. */
	function selectProduct(produto) {
		if (!/^[0-9]+_[0-9]+$/.test(String(produto))) return;
		if (produto === initialProduct) {
			highlightProduct(produto);
			return;
		}
		// Mantém os outros parâmetros (?l= do idioma, gravado na sessão da Tickett) e troca só o produto.
		var query = new URLSearchParams(window.location.search);
		query.set('produto', produto);
		window.location.replace(window.location.pathname + '?' + query.toString());
	}

	window.addEventListener('message', function (event) {
		if (event.origin !== origin || event.source !== window.parent) return;

		var msg = event.data;
		if (!msg || msg.source !== 'site' || msg.version !== 1 || msg.type !== 'select' || !msg.data) return;

		selectProduct(String(msg.data.product));
	});

	/* ---------- fecho ---------- */

	// O único elemento que chama closeFrameCheckout() é o botão .close de partials/header.volt,
	// que só aparece para vendedores. Este listener cobre-o se aparecer.
	if (window.jQuery) {
		window.jQuery(document).on('click', '.button-close .close', function () {
			send('close', {});
		});
	}

	/* Esc dentro do iframe não chega ao site. Ignora se o evento já foi tratado (select ou autocomplete abertos) */
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape' && e.key !== 'Esc') return;
		if (e.defaultPrevented) return;
		send('close', {});
	});

	/* ---------- arranque ---------- */

	function start() {
		send('ready', {});
		startResize();
		observeSteps();
		checkStep();
		sendCart();
	}

	// Carrinho depois de cada resposta AJAX (jQuery do checkout). setTimeout: o .done do checkout corre antes.
	if (window.jQuery) {
		window.jQuery(document).ajaxComplete(function () {
			setTimeout(sendCart, 0);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
