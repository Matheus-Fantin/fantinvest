// Busca da Bolsa com sugestões enquanto digita.
// 1) Filtra na hora uma lista local de ativos conhecidos (sem gastar a API).
// 2) A partir de 2 letras, completa com a busca da brapi (com pequena espera entre teclas).
const normalizar = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default (cfg) => ({
    q: cfg.termo || '',
    aberto: false,
    ativo: -1,
    carregando: false,
    remotos: [],
    timer: null,
    ultimaBusca: '',

    get sugestoes() {
        const termo = normalizar(this.q.trim());
        if (!termo) return [];

        const pontuar = (a) => {
            const t = normalizar(a.ticker);
            const n = normalizar(a.nome);
            if (t.startsWith(termo)) return 0;
            if (n.split(/[\s/()-]+/).some((p) => p.startsWith(termo))) return 1;
            if (t.includes(termo) || n.includes(termo)) return 2;
            return null;
        };

        // Ordem: melhor correspondência, depois o que está na sua carteira, depois os mais populares
        // (a lista em config/ativos_b3.php já está em ordem de popularidade).
        const locais = cfg.conhecidos
            .map((a, posicao) => ({ ...a, posicao, pontos: pontuar(a) }))
            .filter((a) => a.pontos !== null)
            .sort((x, y) => x.pontos - y.pontos || (cfg.meus.includes(y.ticker) - cfg.meus.includes(x.ticker)) || x.posicao - y.posicao);

        const vistos = new Set();
        const lista = [];
        for (const a of [...locais, ...this.remotos]) {
            if (vistos.has(a.ticker)) {
                // Completa preço/variação de quem veio da lista local.
                const existente = lista.find((x) => x.ticker === a.ticker);
                if (existente && a.preco != null) Object.assign(existente, { preco: a.preco, variacao: a.variacao, logo: a.logo });
                continue;
            }
            vistos.add(a.ticker);
            lista.push({ ...a, meu: cfg.meus.includes(a.ticker) });
        }
        return lista.slice(0, 8);
    },

    digitar() {
        this.aberto = this.q.trim() !== '';
        this.ativo = -1;
        clearTimeout(this.timer);
        const termo = this.q.trim();
        if (termo.length < 2) { this.remotos = []; return; }
        this.timer = setTimeout(() => this.buscarRemoto(termo), 300);
    },

    async buscarRemoto(termo) {
        if (termo === this.ultimaBusca) return;
        this.ultimaBusca = termo;
        this.carregando = true;
        try {
            const { data } = await window.axios.get(cfg.url, { params: { q: termo } });
            if (this.q.trim() === termo) this.remotos = (data || []).map((a) => ({ ...a, tipo: a.tipo || '' }));
        } catch {
            // Sem API: ficam só as sugestões locais.
        } finally {
            this.carregando = false;
        }
    },

    mover(passo) {
        if (!this.aberto || !this.sugestoes.length) return;
        this.ativo = (this.ativo + passo + this.sugestoes.length) % this.sugestoes.length;
    },

    escolher(a) {
        window.location.href = cfg.rotaAtivo.replace('__T__', encodeURIComponent(a.ticker));
    },

    enviar(evento) {
        if (this.aberto && this.ativo >= 0 && this.sugestoes[this.ativo]) {
            evento.preventDefault();
            this.escolher(this.sugestoes[this.ativo]);
        }
    },

    destacar(texto) {
        const termo = this.q.trim();
        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
        if (!termo) return esc(texto);
        const i = normalizar(texto).indexOf(normalizar(termo));
        if (i < 0) return esc(texto);
        return esc(texto.slice(0, i)) + '<mark>' + esc(texto.slice(i, i + termo.length)) + '</mark>' + esc(texto.slice(i + termo.length));
    },
});
