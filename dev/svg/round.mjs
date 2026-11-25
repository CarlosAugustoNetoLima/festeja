#!/usr/bin/env node
// Reduz o peso de um SVG exportado do potrace (logótipos e símbolos da linha gráfica).
// Aplica o transform do <g> às coordenadas, converte cada path em comandos M/L/C/Z relativos
// e arredonda as coordenadas (já no espaço do viewBox) às casas decimais pedidas. Sem dependências.
//
// Uso: node dev/svg/round.mjs <entrada.svg> <saída.svg> [casas=0]
//   node dev/svg/round.mjs public/assets/img/logo/logo-vertical.svg /tmp/x.svg 0
//
// Porque 0 casas por omissão: o potrace já escreve as coordenadas com 0,1 unidades do viewBox
// (inteiros com escala 0,1). Com 1 casa decimal o ficheiro cresce (+9 % no logótipo vertical);
// com 0 casas (unidades inteiras do viewBox) cai cerca de 32 %. Confirmar visualmente antes de usar.
//
// Limites: trata M, L, H, V, C, S, Q, T e Z (o que o potrace usa). Com A (arcos) ou com transforms
// diferentes entre grupos, o script para com erro. O viewBox não muda.
import fs from 'node:fs';

const [, , input, output, decArg] = process.argv;
if (!input || !output) {
  console.error('Uso: node dev/svg/round.mjs <entrada.svg> <saída.svg> [casas=0]');
  process.exit(2);
}
const DEC = decArg === undefined ? 0 : Number(decArg);
if (!Number.isInteger(DEC) || DEC < 0 || DEC > 3) {
  console.error('As casas decimais têm de ser um inteiro de 0 a 3.');
  process.exit(2);
}
const SCALE = 10 ** DEC;

const src = fs.readFileSync(input, 'utf8');

// Um só transform: "translate(tx,ty) scale(sx,sy)" (ou nenhum, e então é identidade).
const transforms = [...new Set([...src.matchAll(/<g\b[^>]*?\stransform="([^"]*)"/g)].map((m) => m[1]))];
if (transforms.length > 1) {
  console.error(`Há ${transforms.length} transforms diferentes. O script só trata um.`);
  process.exit(1);
}
let [tx, ty, sx, sy] = [0, 0, 1, 1];
if (transforms.length === 1) {
  const m = transforms[0].match(/^translate\(([-\d.]+)[ ,]([-\d.]+)\)\s*scale\(([-\d.]+)[ ,]([-\d.]+)\)$/);
  if (!m) {
    console.error(`Transform não suportado: ${transforms[0]}`);
    process.exit(1);
  }
  [tx, ty, sx, sy] = m.slice(1).map(Number);
}

// Caminho para uma lista de operações absolutas (M, L, C, Z), no espaço original.
function absolutize(d) {
  const t = d.match(/[A-Za-z]|[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/g) || [];
  const isCmd = (token) => /^[A-Za-z]$/.test(token);
  const ops = [];
  let i = 0;
  let cmd = null;
  let cx = 0;
  let cy = 0;
  let startX = 0;
  let startY = 0;
  let last = null; // tipo do comando anterior ('C' ou 'Q' para S/T)
  let ctrl = null; // último ponto de controlo (para S/T)
  const num = () => {
    if (i >= t.length || isCmd(t[i])) throw new Error(`número em falta no path: ${d.slice(0, 60)}`);
    return Number(t[i++]);
  };
  while (i < t.length) {
    if (isCmd(t[i])) cmd = t[i++];
    else if (cmd === null) throw new Error('path sem comando inicial');
    const rel = cmd === cmd.toLowerCase();
    const type = cmd.toUpperCase();
    const ox = rel ? cx : 0;
    const oy = rel ? cy : 0;
    switch (type) {
      case 'M': {
        cx = num() + ox;
        cy = num() + oy;
        startX = cx;
        startY = cy;
        ops.push({ t: 'M', x: cx, y: cy });
        cmd = rel ? 'l' : 'L'; // pares seguintes são linhas
        break;
      }
      case 'L': {
        cx = num() + ox;
        cy = num() + oy;
        ops.push({ t: 'L', x: cx, y: cy });
        break;
      }
      case 'H': {
        cx = num() + ox;
        ops.push({ t: 'L', x: cx, y: cy });
        break;
      }
      case 'V': {
        cy = num() + oy;
        ops.push({ t: 'L', x: cx, y: cy });
        break;
      }
      case 'C': {
        const x1 = num() + ox;
        const y1 = num() + oy;
        const x2 = num() + ox;
        const y2 = num() + oy;
        cx = num() + ox;
        cy = num() + oy;
        ops.push({ t: 'C', x1, y1, x2, y2, x: cx, y: cy });
        ctrl = { x: x2, y: y2 };
        break;
      }
      case 'S': {
        const [x1, y1] = last === 'C' ? [2 * cx - ctrl.x, 2 * cy - ctrl.y] : [cx, cy];
        const x2 = num() + ox;
        const y2 = num() + oy;
        cx = num() + ox;
        cy = num() + oy;
        ops.push({ t: 'C', x1, y1, x2, y2, x: cx, y: cy });
        ctrl = { x: x2, y: y2 };
        break;
      }
      case 'Q': {
        const qx = num() + ox;
        const qy = num() + oy;
        const x = num() + ox;
        const y = num() + oy;
        const [sxp, syp] = [cx, cy];
        ops.push({
          t: 'C',
          x1: sxp + (2 / 3) * (qx - sxp), y1: syp + (2 / 3) * (qy - syp),
          x2: x + (2 / 3) * (qx - x), y2: y + (2 / 3) * (qy - y),
          x, y,
        });
        cx = x;
        cy = y;
        ctrl = { x: qx, y: qy };
        break;
      }
      case 'T': {
        const [qx, qy] = last === 'Q' ? [2 * cx - ctrl.x, 2 * cy - ctrl.y] : [cx, cy];
        const x = num() + ox;
        const y = num() + oy;
        const [sxp, syp] = [cx, cy];
        ops.push({
          t: 'C',
          x1: sxp + (2 / 3) * (qx - sxp), y1: syp + (2 / 3) * (qy - syp),
          x2: x + (2 / 3) * (qx - x), y2: y + (2 / 3) * (qy - y),
          x, y,
        });
        cx = x;
        cy = y;
        ctrl = { x: qx, y: qy };
        break;
      }
      case 'Z': {
        ops.push({ t: 'Z' });
        cx = startX;
        cy = startY;
        break;
      }
      default:
        throw new Error(`comando de path não suportado: ${cmd}`);
    }
    last = type;
  }
  return ops;
}

// Número inteiro em unidades de 1/SCALE para texto com DEC casas (sem zeros à direita, sem "0." inicial).
function fmt(n) {
  if (n === 0) return '0';
  const neg = n < 0;
  let a = String(Math.abs(n));
  if (DEC > 0) {
    a = a.padStart(DEC + 1, '0');
    const ip = a.slice(0, -DEC);
    const fp = a.slice(-DEC).replace(/0+$/, '');
    a = fp === '' ? ip : `${ip === '0' ? '' : ip}.${fp}`;
  }
  return (neg ? '-' : '') + a;
}

// Junta os tokens do path com o mínimo de espaços: "1.5-2" e "-.5.5" não precisam de separador.
function join(tokens) {
  let s = '';
  let prev = '';
  for (const tok of tokens) {
    if (prev === '' || /^[A-Za-z]$/.test(prev) || /^[A-Za-z]$/.test(tok) || tok.startsWith('-')) {
      s += tok;
    } else {
      s += ' ' + tok;
    }
    prev = tok;
  }
  return s;
}

function convert(d) {
  const toInt = (x, y) => [Math.round((sx * x + tx) * SCALE), Math.round((sy * y + ty) * SCALE)];
  const tokens = [];
  let px = 0;
  let py = 0;
  let startX = 0;
  let startY = 0;
  for (const op of absolutize(d)) {
    if (op.t === 'Z') {
      tokens.push('z');
      px = startX;
      py = startY;
      continue;
    }
    const [x, y] = toInt(op.x, op.y);
    if (op.t === 'M') {
      tokens.push('m', fmt(x - px), fmt(y - py));
      px = x;
      py = y;
      startX = x;
      startY = y;
    } else if (op.t === 'L') {
      const dx = x - px;
      const dy = y - py;
      if (dx === 0 && dy === 0) continue;
      if (dy === 0) tokens.push('h', fmt(dx));
      else if (dx === 0) tokens.push('v', fmt(dy));
      else tokens.push('l', fmt(dx), fmt(dy));
      px = x;
      py = y;
    } else {
      const [x1, y1] = toInt(op.x1, op.y1);
      const [x2, y2] = toInt(op.x2, op.y2);
      tokens.push('c', fmt(x1 - px), fmt(y1 - py), fmt(x2 - px), fmt(y2 - py), fmt(x - px), fmt(y - py));
      px = x;
      py = y;
    }
  }
  return join(tokens);
}

// Cada <path d="..."> passa a ter o d convertido. Os outros atributos ficam como estão.
let paths = 0;
let out = src.replace(/<path\b([^>]*?)\bd="([^"]*)"([^>]*?)\s*\/?>/g, (_, pre, d, post) => {
  paths++;
  const attrs = `${pre.trim()} d="${convert(d)}"${post.trimEnd()}`.replace(/\s+/g, ' ').trim();
  return `<path ${attrs}/>`;
});

// O transform sai dos grupos (já está nas coordenadas). Os fill e stroke dos grupos ficam.
out = out.replace(/(<g\b[^>]*?)\s+transform="[^"]*"/g, '$1').replace(/<g\s+/g, '<g ');

// Uma linha por grupo e uma por path mantém o ficheiro legível e fácil de comparar em diff.
out = out.replace(/>\s*</g, '>\n<').replace(/\n\s*\n/g, '\n');

fs.writeFileSync(output, out.endsWith('\n') ? out : `${out}\n`);

const before = fs.statSync(input).size;
const after = fs.statSync(output).size;
console.log(`${paths} paths · casas decimais: ${DEC} · ${before} → ${after} bytes (${Math.round((1 - after / before) * 100)}% menos)`);
