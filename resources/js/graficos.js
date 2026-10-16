import Chart from 'chart.js/auto';
import { brl, brlK, pct } from './formato';

// Cores vêm dos tokens CSS, então os gráficos acompanham o tema claro/escuro.
export const css = (nome) => getComputedStyle(document.documentElement).getPropertyValue(nome).trim();

export function opcoesBase(extra = {}) {
    const tick = css('--faint');
    const base = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 350 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: css('--surface'),
                titleColor: css('--fg'),
                bodyColor: css('--muted'),
                borderColor: css('--line'),
                borderWidth: 1,
                padding: 10,
                cornerRadius: 10,
                boxPadding: 4,
                usePointStyle: true,
                titleFont: { family: 'Figtree', weight: '700' },
                bodyFont: { family: 'Figtree' },
            },
        },
        scales: {
            x: { grid: { display: false }, border: { color: css('--line') }, ticks: { color: tick, font: { family: 'IBM Plex Mono', size: 11 }, maxRotation: 0, autoSkipPadding: 12 } },
            y: { grid: { color: css('--grid') }, border: { display: false }, ticks: { color: tick, font: { family: 'IBM Plex Mono', size: 11 }, callback: (v) => brlK(v) } },
        },
    };
    return { ...base, ...extra, plugins: { ...base.plugins, ...(extra.plugins || {}), tooltip: { ...base.plugins.tooltip, ...(extra.plugins?.tooltip || {}) } } };
}

export const tooltipValor = (formato = brl, eixo = 'y') => ({ plugins: { tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${formato(c.parsed[eixo])}` } } } });

const escalasHorizontais = (fmtX) => ({
    x: { grid: { color: css('--grid') }, border: { display: false }, ticks: { color: css('--faint'), font: { family: 'IBM Plex Mono', size: 11 }, callback: fmtX } },
    y: { grid: { display: false }, border: { color: css('--line') }, ticks: { color: css('--muted'), font: { family: 'Figtree', size: 12 } } },
});

// Gráficos das páginas (dados vêm do servidor em data-dados).
const construtores = {
    alocacao(canvas, d) {
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.map((c) => c.nome),
                datasets: [
                    { label: 'Atual', data: d.map((c) => c.atual), backgroundColor: css('--s1'), borderRadius: 4, borderSkipped: 'start', barPercentage: 0.8, categoryPercentage: 0.7 },
                    { label: 'Meta', data: d.map((c) => c.meta), backgroundColor: css('--faint'), borderRadius: 4, borderSkipped: 'start', barPercentage: 0.8, categoryPercentage: 0.7 },
                ],
            },
            options: opcoesBase({ indexAxis: 'y', scales: escalasHorizontais((v) => v + '%'), ...tooltipValor((v) => pct(v), 'x') }),
        });
    },
    fluxo(canvas, d) {
        const mes = (m) => { const [y, mo] = m.split('-').map(Number); return new Date(y, mo - 1, 1).toLocaleDateString('pt-BR', { month: 'short' }).replace('.', ''); };
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.map((x) => mes(x.mes)),
                datasets: [
                    { label: 'Receitas', data: d.map((x) => x.receitas), backgroundColor: css('--s1'), borderRadius: 4, borderSkipped: 'start', categoryPercentage: 0.6 },
                    { label: 'Gastos', data: d.map((x) => x.gastos), backgroundColor: css('--s2'), borderRadius: 4, borderSkipped: 'start', categoryPercentage: 0.6 },
                ],
            },
            options: opcoesBase(tooltipValor()),
        });
    },
    categorias(canvas, d) {
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.itens.map((c) => c.nome),
                datasets: [{ label: 'Gasto', data: d.itens.map((c) => c.valor), backgroundColor: d.itens.map((c) => css(c.grupo === 'ess' ? '--s1' : '--s2')), borderRadius: 4, borderSkipped: 'start', barPercentage: 0.75 }],
            },
            options: opcoesBase({
                indexAxis: 'y',
                scales: escalasHorizontais((v) => brlK(v)),
                plugins: { tooltip: { callbacks: { label: (c) => ` ${brl(c.parsed.x)} · ${pct(d.receitas ? (c.parsed.x / d.receitas) * 100 : 0)} da renda` } } },
            }),
        });
    },
};

const nomeMes = (m) => { const [y, mo] = m.split('-').map(Number); return new Date(y, mo - 1, 1).toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' }).replace('.', ''); };
const empilhado = (extra = {}) => {
    const base = opcoesBase({ ...tooltipValor(), ...extra });
    return { ...base, scales: { x: { ...base.scales.x, stacked: true }, y: { ...base.scales.y, stacked: true } } };
};
const barra = (label, data, cor, extra = {}) => ({ label, data, backgroundColor: css(cor), borderColor: css('--surface'), borderWidth: { top: 2 }, borderSkipped: false, stack: 's', ...extra });

Object.assign(construtores, {
    // Parcelas futuras (compras) + financiamento, por mês.
    compromissos(canvas, d) {
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.map((x) => nomeMes(x.mes)),
                datasets: [
                    barra('Financiamento', d.map((x) => x.financiamento), '--s1'),
                    barra('Parcelas de compras', d.map((x) => x.parcelas), '--s2', { borderRadius: { topLeft: 4, topRight: 4 } }),
                ],
            },
            options: empilhado({ plugins: { tooltip: { callbacks: {
                label: (c) => ` ${c.dataset.label}: ${brl(c.parsed.y)}`,
                footer: (itens) => `Total: ${brl(itens.reduce((s, i) => s + i.parsed.y, 0))}`,
            } } } }),
        });
    },
    dividendos(canvas, d) {
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.map((x) => nomeMes(x.mes)),
                datasets: [
                    barra('Dividendos', d.map((x) => x.dividendo), '--s1'),
                    barra('JCP', d.map((x) => x.jcp), '--s2'),
                    barra('Rendimentos (FII)', d.map((x) => x.rendimento), '--s3', { borderRadius: { topLeft: 4, topRight: 4 } }),
                ],
            },
            options: empilhado({ plugins: { tooltip: { callbacks: {
                label: (c) => ` ${c.dataset.label}: ${brl(c.parsed.y)}`,
                footer: (itens) => `Total: ${brl(itens.reduce((s, i) => s + i.parsed.y, 0))}`,
            } } } }),
        });
    },
    evolucao(canvas, d) {
        const s1 = css('--s1');
        return new Chart(canvas, {
            type: 'line',
            data: {
                labels: d.map((x) => nomeMes(x.mes)),
                datasets: [
                    { label: 'Patrimônio líquido', data: d.map((x) => x.liquido), borderColor: s1, backgroundColor: s1 + '1f', fill: true, borderWidth: 2, pointRadius: d.length < 3 ? 4 : 0, pointHoverRadius: 5, tension: 0.25 },
                    { label: 'Dívidas', data: d.map((x) => x.dividas), borderColor: css('--s2'), borderWidth: 2, borderDash: [5, 4], pointRadius: d.length < 3 ? 4 : 0, pointHoverRadius: 5, tension: 0.25 },
                ],
            },
            options: opcoesBase(tooltipValor()),
        });
    },
    composicao(canvas, d) {
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.map((x) => x.nome),
                datasets: [{ label: 'Valor', data: d.map((x) => x.valor), backgroundColor: d.map((x) => css(x.divida ? '--s2' : '--s1')), borderRadius: 4, borderSkipped: 'start', barPercentage: 0.75 }],
            },
            options: opcoesBase({ indexAxis: 'y', scales: escalasHorizontais((v) => brlK(v)), ...tooltipValor(brl, 'x') }),
        });
    },
    historico(canvas, d) {
        const subiu = d.length > 1 && d[d.length - 1].fechamento >= d[0].fechamento;
        const cor = css(subiu ? '--good' : '--bad');
        return new Chart(canvas, {
            type: 'line',
            data: {
                labels: d.map((x) => x.data.split('-').reverse().slice(0, 2).join('/')),
                datasets: [{ label: 'Fechamento', data: d.map((x) => x.fechamento), borderColor: cor, backgroundColor: cor + '1a', fill: true, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.2 }],
            },
            options: opcoesBase({ ...tooltipValor(), scales: { ...opcoesBase().scales, y: { ...opcoesBase().scales.y, ticks: { ...opcoesBase().scales.y.ticks, callback: (v) => brl(v) } } } }),
        });
    },
    obra(canvas, d) {
        return new Chart(canvas, {
            type: 'bar',
            data: { labels: d.map((x) => x.nome), datasets: [{ label: 'Gasto', data: d.map((x) => x.valor), backgroundColor: css('--s4'), borderRadius: 4, borderSkipped: 'start', barPercentage: 0.75 }] },
            options: opcoesBase({ indexAxis: 'y', scales: escalasHorizontais((v) => brlK(v)), ...tooltipValor(brl, 'x') }),
        });
    },
    // Financiamento: por ano, quanto da parcela é juros e quanto é amortização, mais o saldo devedor.
    financiamentoAnos(canvas, d) {
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.map((x) => String(x.ano)),
                datasets: [
                    barra('Amortização', d.map((x) => x.amortizacao), '--s1'),
                    barra('Juros', d.map((x) => x.juros), '--s2'),
                    barra('Seguro e taxa', d.map((x) => x.encargos), '--s4', { borderRadius: { topLeft: 4, topRight: 4 } }),
                ],
            },
            options: empilhado({ plugins: { tooltip: { callbacks: {
                label: (c) => ` ${c.dataset.label}: ${brl(c.parsed.y)}`,
                footer: (itens) => `Pago no ano: ${brl(itens.reduce((s, i) => s + i.parsed.y, 0))}`,
            } } } }),
        });
    },
    saldoDevedor(canvas, d) {
        const s1 = css('--s1');
        return new Chart(canvas, {
            type: 'line',
            data: { labels: d.map((x) => String(x.ano)), datasets: [{ label: 'Saldo devedor no fim do ano', data: d.map((x) => x.saldo), borderColor: s1, backgroundColor: s1 + '1f', fill: true, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.2 }] },
            options: opcoesBase(tooltipValor()),
        });
    },
});

const ativos = new Map();

export function desenharGraficos() {
    document.querySelectorAll('canvas[data-grafico]').forEach((canvas) => {
        const fn = construtores[canvas.dataset.grafico];
        if (!fn) return;
        ativos.get(canvas)?.destroy();
        ativos.set(canvas, fn(canvas, JSON.parse(canvas.dataset.dados || '[]')));
    });
}

// Avisa os simuladores para redesenhar quando o tema muda.
export function aoMudarTema(callback) {
    window.addEventListener('fantinvest:tema', callback);
}

export function iniciarGraficos() {
    desenharGraficos();
    aoMudarTema(desenharGraficos);
    document.fonts?.ready.then(desenharGraficos);
}

export { Chart };
