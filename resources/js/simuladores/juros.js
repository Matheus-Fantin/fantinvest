import { Chart, aoMudarTema, css, opcoesBase, tooltipValor } from '../graficos';
import { brl, moeda, numf, parseNum } from '../formato';
import { simJuros } from './calculos';

const PADRAO = { inicial: 10000, aporte: 1500, taxa: 11, inflacao: 4.5, anos: 20, reajuste: 4.5, ir: 15, meta: 1000000 };
const CHAVE = 'fantinvest.sim.juros';

export default () => ({
    campos: {},
    r: simJuros(PADRAO),
    grafico: null,

    init() {
        let salvo = {};
        try { salvo = JSON.parse(localStorage.getItem(CHAVE) || '{}'); } catch {}
        const o = { ...PADRAO, ...salvo };
        const dinheiro = ['inicial', 'aporte', 'meta'];
        this.campos = Object.fromEntries(Object.entries(o).map(([k, v]) => [k, dinheiro.includes(k) ? moeda(v) : numf(v, 2).replace(/\./g, '')]));
        this.calcular();
        aoMudarTema(() => this.desenhar());
    },

    get o() {
        return Object.fromEntries(Object.entries(this.campos).map(([k, v]) => [k, parseNum(v)]));
    },

    calcular() {
        const o = this.o;
        this.r = simJuros(o);
        try { localStorage.setItem(CHAVE, JSON.stringify(o)); } catch {}
        this.$nextTick(() => this.desenhar());
    },

    preset(tipo) {
        const infl = parseNum(this.campos.inflacao);
        const taxa = tipo === 'ipca6' ? Math.round(((1 + infl / 100) * 1.06 - 1) * 10000) / 100 : Number(tipo);
        this.campos.taxa = numf(taxa, 2).replace(/\./g, '');
        if (tipo === '6.17') this.campos.ir = '0';
        this.calcular();
    },

    desenhar() {
        const rows = this.r.rows;
        const s1 = css('--s1');
        const dados = {
            labels: rows.map((x) => 'Ano ' + numf(x.ano, 1)),
            datasets: [
                { label: 'Saldo nominal', data: rows.map((x) => x.saldo), borderColor: s1, backgroundColor: s1 + '1f', fill: true, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.25 },
                { label: 'Poder de compra', data: rows.map((x) => x.real), borderColor: css('--s3'), borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.25 },
                { label: 'Total investido', data: rows.map((x) => x.inv), borderColor: css('--s2'), borderWidth: 2, borderDash: [5, 4], pointRadius: 0, pointHoverRadius: 5, tension: 0 },
            ],
        };
        this.grafico?.destroy();
        this.grafico = new Chart(this.$refs.grafico, { type: 'line', data: dados, options: opcoesBase(tooltipValor(brl)) });
    },
});
