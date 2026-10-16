// Formatação em pt-BR usada pelos gráficos e simuladores.
const fBRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const fBRL0 = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });

export const brl = (v) => fBRL.format(Number.isFinite(v) ? v : 0);
export const brl0 = (v) => fBRL0.format(Number.isFinite(v) ? v : 0);
export const pct = (v, d = 1) => (Number.isFinite(v) ? v : 0).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d }) + '%';
export const numf = (v, d = 2) => (Number.isFinite(v) ? v : 0).toLocaleString('pt-BR', { maximumFractionDigits: d });

/** Valor para campo de formulário: 1.234,56. */
export const moeda = (v) => (Number.isFinite(v) ? v : 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Rótulo curto para eixos: R$ 3,5 mil, R$ 1,2 mi. */
export function brlK(v) {
    const a = Math.abs(v);
    if (a >= 1e6) return 'R$ ' + (v / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi';
    if (a >= 1e3) return 'R$ ' + (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: a < 1e4 ? 1 : 0 }) + ' mil';
    return 'R$ ' + Math.round(v);
}

/** Lê número digitado em formato brasileiro ("1.234,56") ou internacional ("1234.56"). */
export function parseNum(s) {
    if (typeof s === 'number') return s;
    s = String(s ?? '').replace(/[R$\s%]/g, '');
    if (!s) return 0;
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
    const n = parseFloat(s);
    return Number.isFinite(n) ? n : 0;
}

/** "6 anos e 11 meses" a partir de uma quantidade de meses. */
export function prazo(m) {
    const a = Math.floor(m / 12);
    const r = m % 12;
    return [a ? a + (a === 1 ? ' ano' : ' anos') : '', r ? r + (r === 1 ? ' mês' : ' meses') : ''].filter(Boolean).join(' e ') || '0 meses';
}

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
