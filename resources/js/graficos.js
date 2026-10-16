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
