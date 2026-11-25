// Cliente CDP mínimo para Chrome headless. Só módulos nativos do Node (22+).
// Lança um Chrome com perfil próprio, liga-se à página e expõe navegação, JS (também dentro de iframes
// do mesmo processo), teclado e rato reais, rede e captura de ecrã.
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

export const CHROME = process.env.QA_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const KEYS = {
  Tab: { code: 'Tab', vk: 9 },
  Enter: { code: 'Enter', vk: 13, text: '\r' },
  Escape: { code: 'Escape', vk: 27 },
  ArrowLeft: { code: 'ArrowLeft', vk: 37 },
  ArrowUp: { code: 'ArrowUp', vk: 38 },
  ArrowRight: { code: 'ArrowRight', vk: 39 },
  ArrowDown: { code: 'ArrowDown', vk: 40 },
  Home: { code: 'Home', vk: 36 },
  End: { code: 'End', vk: 35 },
  ' ': { code: 'Space', vk: 32, text: ' ' },
};

export class Page {
  constructor(wsUrl, proc) {
    this.proc = proc;
    this.seq = 0;
    this.pending = new Map();
    this.listeners = new Map();
    this.contexts = [];
    this.ws = new WebSocket(wsUrl);
    this.opened = new Promise((resolve, reject) => {
      this.ws.onopen = resolve;
      this.ws.onerror = reject;
    });
    this.ws.onmessage = (message) => this.#dispatch(JSON.parse(message.data));
    this.on('Runtime.executionContextCreated', ({ context }) => this.contexts.push(context));
    this.on('Runtime.executionContextDestroyed', ({ executionContextId }) => {
      this.contexts = this.contexts.filter((c) => c.id !== executionContextId);
    });
    this.on('Runtime.executionContextsCleared', () => { this.contexts = []; });
  }

  #dispatch(data) {
    if (data.id) {
      const waiter = this.pending.get(data.id);
      if (!waiter) return;
      this.pending.delete(data.id);
      if (data.error) waiter.reject(new Error(`${data.error.message} (${data.id})`));
      else waiter.resolve(data.result);
      return;
    }
    for (const fn of this.listeners.get(data.method) || []) fn(data.params);
  }

  async ready() {
    await this.opened;
    await this.send('Page.enable');
    await this.send('Runtime.enable');
  }

  send(method, params = {}) {
    const id = ++this.seq;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }

  on(method, fn) {
    if (!this.listeners.has(method)) this.listeners.set(method, []);
    this.listeners.get(method).push(fn);
  }

  // Avalia JS na página principal (ou no contexto indicado). Devolve o valor por cópia.
  async ev(expression, contextId) {
    const result = await this.send('Runtime.evaluate', {
      expression, awaitPromise: true, returnByValue: true, contextId,
    });
    if (result.exceptionDetails) {
      const e = result.exceptionDetails.exception;
      throw new Error(`JS: ${e ? e.description : result.exceptionDetails.text}`.slice(0, 500));
    }
    return result.result.value;
  }

  // Lista os frames da página (inclui iframes do mesmo processo).
  async frames() {
    const { frameTree } = await this.send('Page.getFrameTree');
    const out = [];
    const walk = (node) => {
      out.push({ id: node.frame.id, url: node.frame.url });
      (node.childFrames || []).forEach(walk);
    };
    walk(frameTree);
    return out;
  }

  // Contexto JS (execution context) do iframe cujo URL contém `urlPart`.
  async frameContext(urlPart, timeout = 8000) {
    const end = Date.now() + timeout;
    while (Date.now() < end) {
      const frame = (await this.frames()).find((f) => f.url.includes(urlPart));
      const ctx = frame && this.contexts.find((c) => c.auxData && c.auxData.frameId === frame.id && c.auxData.isDefault);
      if (ctx) return ctx.id;
      await sleep(150);
    }
    throw new Error(`contexto do frame não encontrado: ${urlPart}`);
  }

  async evIn(urlPart, expression) {
    return this.ev(expression, await this.frameContext(urlPart));
  }

  async waitFor(expression, timeout = 12000, contextId) {
    const end = Date.now() + timeout;
    while (Date.now() < end) {
      try {
        if (await this.ev(expression, contextId)) return true;
      } catch (error) {
        // Ainda não existe (ex.: iframe a carregar). Tenta de novo.
      }
      await sleep(100);
    }
    return false;
  }

  // Navega e espera pelo load.
  async navigate(url, timeout = 20000) {
    const loaded = this.waitEvent('Page.loadEventFired', timeout);
    await this.send('Page.navigate', { url });
    await loaded;
    await sleep(150);
  }

  waitEvent(method, timeout = 20000) {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error(`timeout à espera de ${method}`)), timeout);
      this.on(method, (params) => { clearTimeout(timer); resolve(params); });
    });
  }

  async viewport(width, height, mobile = width < 768) {
    await this.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
  }

  async key(name) {
    const k = KEYS[name] || { code: name, vk: 0 };
    const base = { key: name, code: k.code, windowsVirtualKeyCode: k.vk, nativeVirtualKeyCode: k.vk };
    await this.send('Input.dispatchKeyEvent', { ...base, type: 'keyDown', text: k.text });
    await this.send('Input.dispatchKeyEvent', { ...base, type: 'keyUp' });
    await sleep(60);
  }

  async clickXY(x, y) {
    await this.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
    // buttons: 1 é obrigatório para o Chrome disparar o clique (sem ele o press não conta).
    await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', buttons: 1, clickCount: 1 });
    await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', buttons: 0, clickCount: 1 });
    await sleep(80);
  }

  // Centro de um seletor na página principal, depois de o pôr à vista.
  async centerOf(selector) {
    return this.ev(`(() => {
      const el = document.querySelector(${JSON.stringify(selector)});
      if (!el) return null;
      el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
      const r = el.getBoundingClientRect();
      return [r.left + r.width / 2, r.top + r.height / 2];
    })()`);
  }

  // Clique real (rato) num seletor da página principal.
  async click(selector) {
    const point = await this.centerOf(selector);
    if (!point) throw new Error(`sem elemento: ${selector}`);
    await sleep(120);
    const fresh = await this.centerOf(selector);
    await this.clickXY(fresh[0], fresh[1]);
  }

  // Clique real num seletor dentro de um iframe (coordenadas do iframe + offset do elemento <iframe>).
  async clickInFrame(urlPart, selector, iframeSelector = '[data-checkout] iframe') {
    const ctx = await this.frameContext(urlPart);
    await this.ev(`document.querySelector(${JSON.stringify(iframeSelector)}).scrollIntoView({ block: 'center', behavior: 'instant' })`);
    await sleep(100);
    const inner = await this.ev(`(() => {
      const el = document.querySelector(${JSON.stringify(selector)});
      if (!el) return null;
      el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
      const r = el.getBoundingClientRect();
      return [r.left + r.width / 2, r.top + r.height / 2];
    })()`, ctx);
    if (!inner) throw new Error(`sem elemento no frame: ${selector}`);
    await sleep(120);
    const inner2 = await this.ev(`(() => {
      const r = document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect();
      return [r.left + r.width / 2, r.top + r.height / 2];
    })()`, ctx);
    const offset = await this.ev(`(() => {
      const r = document.querySelector(${JSON.stringify(iframeSelector)}).getBoundingClientRect();
      return [r.left, r.top];
    })()`);
    await this.clickXY(offset[0] + inner2[0], offset[1] + inner2[1]);
  }

  // Escreve texto num campo do iframe, com clique real para o focar.
  async typeInFrame(urlPart, selector, text) {
    await this.clickInFrame(urlPart, selector);
    await this.send('Input.insertText', { text });
    await sleep(80);
  }

  async screenshot(file, clip) {
    const params = { format: 'png' };
    if (clip) params.clip = { ...clip, scale: 1 };
    const shot = await this.send('Page.captureScreenshot', params);
    fs.writeFileSync(file, Buffer.from(shot.data, 'base64'));
    return file;
  }

  async close() {
    try { this.ws.close(); } catch (error) { /* já fechado */ }
    killChrome(this.proc);
    await sleep(300);
  }
}

// Termina o Chrome e os processos filhos (grupo próprio, criado com detached). Não deixa processos órfãos.
function killChrome(proc) {
  try { process.kill(-proc.pid, 'SIGTERM'); } catch (error) { /* já terminado */ }
  try { proc.kill('SIGTERM'); } catch (error) { /* já terminado */ }
}

// Lança um Chrome com perfil novo (dentro de profilesDir) e devolve uma Page ligada.
// Se a ligação falhar, o Chrome é terminado antes de o erro sair (try/catch), para não ficar órfão.
export async function openBrowser({ profilesDir, name, width = 1440, height = 900 }) {
  const profileDir = path.join(profilesDir, `${name}-${Date.now()}`);
  fs.mkdirSync(profileDir, { recursive: true });
  const proc = spawn(CHROME, [
    '--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profileDir}`,
    '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--hide-scrollbars',
    '--disable-background-networking', 'about:blank',
  ], { stdio: 'ignore', detached: true });
  try {
    return await connectPage(proc, profileDir, width, height);
  } catch (error) {
    killChrome(proc);
    throw error;
  }
}

async function connectPage(proc, profileDir, width, height) {

  const portFile = path.join(profileDir, 'DevToolsActivePort');
  let port = null;
  for (let i = 0; i < 200 && !port; i++) {
    await sleep(100);
    if (fs.existsSync(portFile)) {
      const [p] = fs.readFileSync(portFile, 'utf8').split('\n');
      if (p) port = Number(p);
    }
  }
  if (!port) {
    throw new Error('Chrome não arrancou (sem DevToolsActivePort)');
  }

  const target = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' }).then((r) => r.json());
  const page = new Page(target.webSocketDebuggerUrl, proc);
  await page.ready();
  await page.viewport(width, height);
  return page;
}
