import { Chart, aoMudarTema, css, opcoesBase, tooltipValor } from '../graficos';
import { brl, moeda, numf, parseNum } from '../formato';
import { finVerdict, simFin } from './calculos';

const PADRAO = { valor: 400000, entrada: 80000, taxa: 11.5, prazo: 360, taxas: 120, renda: 15000, inflacao: 4.5, alt: 11 };
const EXEMPLOS = {
    imovel: { valor: 400000, entrada: 80000, taxa: 11.5, unid: 'aa', prazo: 360, taxas: 120 },
    carro: { valor: 90000, entrada: 20000, taxa: 1.8, unid: 'am', prazo: 48, taxas: 0 },
    moto: { valor: 22000, entrada: 4000, taxa: 2.2, unid: 'am', prazo: 36, taxas: 0 },
};
const CHAVE = 'fantinvest.sim.financiamento';
const DINHEIRO = ['valor', 'entrada', 'taxas', 'renda'];
const texto = (v, k) => (DINHEIRO.includes(k) ? moeda(v) : numf(v, 2).replace(/\./g, ''));

export default () => ({
    campos: {},
    unid: 'aa',
    sistema: 'price',
    r: null,
    v: null,
    anos: [],
    graficos: {},

    init() {
        let salvo = {};
        try { salvo = JSON.parse(localStorage.getItem(CHAVE) || '{}'); } catch {}
        this.unid = salvo.unid || 'aa';
        this.sistema = salvo.sistema || 'price';
        const o = { ...PADRAO, ...salvo };
        this.campos = Object.fromEntries(Object.keys(PADRAO).map((k) => [k, texto(o[k], k)]));
        this.calcular();
        aoMudarTema(() => this.desenhar());
    },

    get o() {
        return { ...Object.fromEntries(Object.entries(this.campos).map(([k, v]) => [k, parseNum(v)])), unid: this.unid, sistema: this.sistema };
    },

    calcular() {
        const o = this.o;
        this.r = simFin(o);
        this.v = finVerdict(o, this.r);
        const rows = this.r.sel.rows;
        this.anos = [];
        for (let y = 0; y * 12 < rows.length; y++) {
            const sl = rows.slice(y * 12, y * 12 + 12);
            this.anos.push({
                ano: y + 1,
                juros: sl.reduce((s, x) => s + x.juros, 0),
                amort: sl.reduce((s, x) => s + x.amort, 0),
                pago: sl.reduce((s, x) => s + x.parcela, 0),
                saldo: sl[sl.length - 1].saldo,
            });
        }
        try { localStorage.setItem(CHAVE, JSON.stringify(o)); } catch {}
        this.$nextTick(() => this.desenhar());
    },

    usarSistema(s) {
        this.sistema = s;
        this.calcular();
    },

    exemplo(nome) {
        const e = EXEMPLOS[nome];
        Object.keys(e).forEach((k) => { if (k !== 'unid') this.campos[k] = texto(e[k], k); });
        this.unid = e.unid;
        this.calcular();
    },

    desenhar() {
        const r = this.r;
        const passo = Math.max(1, Math.ceil(r.n / 120));
        const idx = r.price.rows.filter((_, i) => i % passo === 0 || i === r.n - 1).map((x) => x.k);
        const rotulo = (k) => (k % 12 === 0 ? 'Ano ' + k / 12 : 'Mês ' + k);
        const linha = (label, data, cor, tracejado) => ({ label, data, borderColor: cor, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0, borderDash: tracejado || [] });
        const opcoes = () => opcoesBase(tooltipValor(brl));
        const criar = (nome, config) => { this.graficos[nome]?.destroy(); this.graficos[nome] = new Chart(this.$refs[nome], config); };

        criar('parcelas', {
            type: 'line',
            data: { labels: idx.map(rotulo), datasets: [linha('Price', idx.map((k) => r.price.rows[k - 1].parcela), css('--s1')), linha('SAC', idx.map((k) => r.sac.rows[k - 1].parcela), css('--s2'))] },
            options: opcoes(),
        });

        const base = opcoes();
        criar('composicao', {
            type: 'bar',
            data: {
                labels: this.anos.map((a) => 'Ano ' + a.ano),
                datasets: [
                    { label: 'Amortização', data: this.anos.map((a) => a.amort), backgroundColor: css('--s1'), borderColor: css('--surface'), borderWidth: { top: 2 }, borderSkipped: false, stack: 's' },
                    { label: 'Juros', data: this.anos.map((a) => a.juros), backgroundColor: css('--s2'), borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: false, stack: 's' },
                ],
            },
            options: { ...base, scales: { x: { ...base.scales.x, stacked: true }, y: { ...base.scales.y, stacked: true } } },
        });

        const c = r.curve;
        const st = Math.max(1, Math.ceil(c.length / 120));
        const cs = c.filter((_, i) => i % st === 0 || i === c.length - 1);
        criar('alternativa', {
            type: 'line',
            data: { labels: cs.map((x) => rotulo(x.k)), datasets: [linha('Dinheiro investido', cs.map((x) => x.saldo), css('--s1')), linha('Preço do bem', cs.map((x) => x.preco), css('--s2'), [5, 4])] },
            options: opcoes(),
        });
    },
});
