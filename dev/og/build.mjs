#!/usr/bin/env node
// Gera os ficheiros de marca a partir de dev/og/og.html, com o Chrome headless (CDP, dev/qa/lib/cdp.mjs):
//   public/assets/img/og.jpg                 imagem de partilha, 1200 × 630, JPEG qualidade 85
//   public/assets/img/apple-touch-icon.png   ícone de ecrã inicial, 180 × 180
// A data, o nome do festival e o local vêm de content/festival.json (o HTML tem os mesmos valores como exemplo).
// Uso (a partir de qualquer pasta): node dev/og/build.mjs
// Precisa de Google Chrome e de rede (as fontes vêm do Google Fonts). Ver dev/og/README.md.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { openBrowser, sleep } from '../qa/lib/cdp.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../..');
const OG_HTML = path.join(HERE, 'og.html');
const OUT_OG = path.join(ROOT, 'public/assets/img/og.jpg');
const OUT_ICON = path.join(ROOT, 'public/assets/img/apple-touch-icon.png');
const PROFILES = path.join(os.tmpdir(), 'festeja-og');

const MESES = ['JANEIRO', 'FEVEREIRO', 'MARÇO', 'ABRIL', 'MAIO', 'JUNHO',
  'JULHO', 'AGOSTO', 'SETEMBRO', 'OUTUBRO', 'NOVEMBRO', 'DEZEMBRO'];

const festival = JSON.parse(fs.readFileSync(path.join(ROOT, 'content/festival.json'), 'utf8'));

// Data em duas linhas, em maiúsculas, como o hero: "23 E 24" / "JULHO".
// Meses diferentes: "23 JULHO" / "A 1 AGOSTO" (mesma lógica de Format::range).
function dataDoFestival({ inicio, fim }) {
  const [, m1, d1] = inicio.split('-').map(Number);
  const [, m2, d2] = fim.split('-').map(Number);
  if (m1 === m2 && d1 === d2) return { dias: String(d1), mes: MESES[m1 - 1] };
  if (m1 === m2) return { dias: `${d1} E ${d2}`, mes: MESES[m1 - 1] };
  return { dias: `${d1} ${MESES[m1 - 1]}`, mes: `A ${d2} ${MESES[m2 - 1]}` };
}

// Largura e altura de um JPEG (marcador SOF). Só para confirmar o tamanho do ficheiro gerado.
function jpegSize(buf) {
  let i = 2;
  while (i < buf.length) {
    if (buf[i] !== 0xff) i++;
    const marker = buf[i + 1];
    if (marker >= 0xc0 && marker <= 0xc3) {
      return { altura: buf.readUInt16BE(i + 5), largura: buf.readUInt16BE(i + 7) };
    }
    i += 2 + buf.readUInt16BE(i + 2);
  }
  return null;
}

const { dias, mes } = dataDoFestival(festival.datas);
const textos = {
  eyebrow: `${festival.nome} · ${festival.marca}`,
  dias,
  mes,
  local: festival.local.nome,
  zona: festival.local.zona,
};

fs.mkdirSync(PROFILES, { recursive: true });
const page = await openBrowser({ profilesDir: PROFILES, name: 'og', width: 1200, height: 630 });

try {
  await page.navigate(pathToFileURL(OG_HTML).href, 60000);
  await page.ev(`(async () => {
    const t = ${JSON.stringify(textos)};
    const set = (slot, texto) => { document.querySelector('[data-slot="' + slot + '"]').textContent = texto; };
    set('eyebrow', t.eyebrow);
    set('data-dias', t.dias);
    set('data-mes', t.mes);
    set('local', t.local);
    set('zona', t.zona);
    await document.fonts.ready;
    await Promise.all([...document.images].map((img) => img.complete ? null : new Promise((ok) => { img.onload = img.onerror = ok; })));
    return true;
  })()`);
  await sleep(300);

  // Imagem de partilha (JPEG direto do Chrome)
  const og = await page.send('Page.captureScreenshot', {
    format: 'jpeg',
    quality: 85,
    clip: { x: 0, y: 0, width: 1200, height: 630, scale: 1 },
  });
  fs.writeFileSync(OUT_OG, Buffer.from(og.data, 'base64'));
  const tamanhoOg = jpegSize(fs.readFileSync(OUT_OG));

  // Ícone de ecrã inicial (PNG, 180 × 180, fundo creme)
  await page.ev(`document.body.dataset.modo = 'icone'`);
  await page.viewport(180, 180);
  await sleep(200);
  const icon = await page.send('Page.captureScreenshot', {
    format: 'png',
    clip: { x: 0, y: 0, width: 180, height: 180, scale: 1 },
  });
  fs.writeFileSync(OUT_ICON, Buffer.from(icon.data, 'base64'));

  console.log(`og.jpg: ${tamanhoOg.largura} × ${tamanhoOg.altura}, ${fs.statSync(OUT_OG).size} bytes → ${path.relative(ROOT, OUT_OG)}`);
  console.log(`apple-touch-icon.png: 180 × 180, ${fs.statSync(OUT_ICON).size} bytes → ${path.relative(ROOT, OUT_ICON)}`);
  console.log(`textos: ${JSON.stringify(textos)}`);
} finally {
  await page.close();
}
