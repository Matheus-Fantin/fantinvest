// Cálculos dos simuladores (puros, sem DOM). Taxas sempre em % ao ano, exceto quando indicado.
import { brl0, pct, prazo } from '../formato';

export function simJuros(o) {
  const n = Math.max(1, Math.round(o.anos * 12));
  const im = Math.pow(1 + o.taxa / 100, 1 / 12) - 1;
  const fm = Math.pow(1 + o.inflacao / 100, 1 / 12) - 1;
  let saldo = o.inicial, inv = o.inicial, ap = o.aporte;
  const rows = [{ ano: 0, saldo, inv, real: saldo, juros: 0 }];
  for (let m = 1; m <= n; m++) {
    saldo = saldo * (1 + im) + ap; inv += ap;
    if (m % 12 === 0) { const idx = Math.pow(1 + fm, m); rows.push({ ano: m / 12, saldo, inv, real: saldo / idx, juros: saldo - inv, aporteMes: ap }); ap *= 1 + o.reajuste / 100; }
  }
  if (n % 12 !== 0) { const idx = Math.pow(1 + fm, n); rows.push({ ano: n / 12, saldo, inv, real: saldo / idx, juros: saldo - inv }); }
  const idx = Math.pow(1 + fm, n);
  const ganho = saldo - inv;
  const liq = saldo - Math.max(0, ganho) * o.ir / 100;
  const realLiq = liq / idx;
  const rReal = (1 + o.taxa / 100) / (1 + o.inflacao / 100) - 1;
  const rRealM = Math.pow(1 + rReal, 1 / 12) - 1;
  // meta: aporte mensal constante (em R$ de hoje) para chegar ao valor desejado em poder de compra atual
  let apMeta = null;
  if (rRealM > 0) { const g = Math.pow(1 + rRealM, n); apMeta = (o.meta - o.inicial * g) * rRealM / (g - 1); }
  else { apMeta = (o.meta - o.inicial) / n; }
  return { rows, saldo, inv, ganho, liq, realLiq, idx, rReal, renda: realLiq * Math.max(0, rRealM), renda4: realLiq * 0.04 / 12, apMeta, n };
}

export function amort(pv, i, n, sis, taxas) {
  const rows = []; let saldo = pv;
  const pmt = i > 0 ? pv * i / (1 - Math.pow(1 + i, -n)) : pv / n;
  for (let k = 1; k <= n; k++) {
    const j = saldo * i;
    const a = sis === "price" ? pmt - j : pv / n;
    saldo = Math.max(0, saldo - a);
    rows.push({ k, juros: j, amort: a, parcela: a + j + taxas, saldo });
  }
  const total = rows.reduce((s, r) => s + r.parcela, 0);
  const juros = rows.reduce((s, r) => s + r.juros, 0);
  return { rows, total, juros, first: rows[0]?.parcela || 0, last: rows[rows.length - 1]?.parcela || 0 };
}

export function irr(pv, flows) {
  let lo = 0, hi = 0.2;
  const f = r => flows.reduce((s, c, k) => s + c / Math.pow(1 + r, k + 1), 0) - pv;
  if (f(0) < 0) return 0;
  for (let t = 0; t < 80; t++) { const mid = (lo + hi) / 2; if (f(mid) > 0) lo = mid; else hi = mid; }
  return (lo + hi) / 2;
}

export function simFin(o) {
  const pv = Math.max(0, o.valor - o.entrada);
  const n = Math.max(1, Math.round(o.prazo));
  const i = o.unid === "am" ? o.taxa / 100 : Math.pow(1 + o.taxa / 100, 1 / 12) - 1;
  const price = amort(pv, i, n, "price", o.taxas);
  const sac = amort(pv, i, n, "sac", o.taxas);
  const sel = o.sistema === "sac" ? sac : price;
  const cetM = irr(pv, sel.rows.map(r => r.parcela));
  const cetA = (Math.pow(1 + cetM, 12) - 1) * 100;
  const fm = Math.pow(1 + o.inflacao / 100, 1 / 12) - 1;
  const vpReal = sel.rows.reduce((s, r) => s + r.parcela / Math.pow(1 + fm, r.k), 0);
  // alternativa: guardar a mesma parcela (e a entrada) investindo até poder comprar à vista
  const am = Math.pow(1 + o.alt / 100, 1 / 12) - 1;
  let saldo = o.entrada, mes = null; const curve = [{ k: 0, saldo, preco: o.valor }];
  const horizon = Math.min(600, n);
  for (let k = 1; k <= 600; k++) {
    const dep = sel.rows[Math.min(k, n) - 1].parcela;
    saldo = saldo * (1 + am) + dep;
    const preco = o.valor * Math.pow(1 + fm, k);
    if (k <= Math.max(horizon, 1)) curve.push({ k, saldo, preco });
    if (mes === null && saldo >= preco) { mes = k; if (k > horizon) break; }
    if (mes !== null && k >= horizon) break;
  }
  const gastoAlt = mes ? o.entrada + sel.rows.slice(0, Math.min(mes, n)).reduce((s, r) => s + r.parcela, 0) : null;
  const economia = mes ? (o.entrada + sel.total) - (gastoAlt) : null;
  return { pv, n, i, iA: (Math.pow(1 + i, 12) - 1) * 100, price, sac, sel, cetM, cetA, vpReal, mes, curve, economia, gastoAlt };
}

export function finVerdict(o, r) {
  const items = [];
  const jr = r.pv ? r.sel.juros / r.pv * 100 : 0;
  items.push(r.cetA <= o.alt
    ? ["good", "Custo efetivo", `CET de ${pct(r.cetA)} a.a. está abaixo do que seu dinheiro rende (${pct(o.alt)} a.a.). Financiar pode compensar.`]
    : ["bad", "Custo efetivo", `CET de ${pct(r.cetA)} a.a. é maior que o rendimento de ${pct(o.alt)} a.a. Juntar e comprar à vista sai mais barato.`]);
  items.push(jr < 30 ? ["good", "Peso dos juros", `Você paga ${pct(jr, 0)} do valor financiado em juros.`]
    : jr < 80 ? ["warn", "Peso dos juros", `Você paga ${pct(jr, 0)} do valor financiado em juros (${brl0(r.sel.juros)}).`]
    : ["bad", "Peso dos juros", `Os juros somam ${pct(jr, 0)} do valor financiado: ${brl0(r.sel.juros)} só de juros.`]);
  if (o.renda > 0) {
    const c = r.sel.first / o.renda * 100;
    items.push(c <= 30 ? ["good", "Comprometimento da renda", `A 1ª parcela usa ${pct(c, 0)} da renda (limite saudável: 30%).`]
      : c <= 40 ? ["warn", "Comprometimento da renda", `A 1ª parcela usa ${pct(c, 0)} da renda. Acima de 30% aperta o orçamento.`]
      : ["bad", "Comprometimento da renda", `A 1ª parcela usa ${pct(c, 0)} da renda. Risco alto de aperto.`]);
  }
  if (r.mes) items.push(r.mes < r.n ? ["warn", "Alternativa", `Investindo o mesmo valor da parcela a ${pct(o.alt)} a.a., você compraria à vista em ${prazo(r.mes)}, em vez de ${prazo(r.n)} pagando.`]
    : ["good", "Alternativa", `Investindo a parcela você levaria ${prazo(r.mes)} para comprar à vista: financiar antecipa o uso do bem.`]);
  const bad = items.filter(x => x[0] === "bad").length, warn = items.filter(x => x[0] === "warn").length;
  const level = bad >= 2 ? "bad" : bad || warn ? "warn" : "good";
  const title = { good: "Financiamento saudável", warn: "Dá para fazer, com atenção", bad: "Financiamento caro" }[level];
  return { level, title, items };
}
