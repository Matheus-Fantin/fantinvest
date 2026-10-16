// Atualização automática das cotações nas páginas da carteira.
// Pede novas cotações ao abrir a página (e a cada minuto, com a aba visível) quando a
// mais antiga passou do limite. Para não entrar em ciclo quando algum ticker falha,
// a tentativa automática acontece no máximo uma vez por janela de tempo.
const CHAVE_TENTATIVA = 'fantinvest.cotacoes.tentativa';

function minutosDesde(iso) {
    return iso ? (Date.now() - new Date(iso).getTime()) / 60000 : Infinity;
}

export default (cfg) => ({
    carregando: false,
    pendente: false,
    maisRecente: cfg.maisRecente,
    agora: Date.now(),

    init() {
        this.talvezAtualizar();
        setInterval(() => {
            this.agora = Date.now();
            if (document.visibilityState === 'visible') this.talvezAtualizar();
        }, 60000);
    },

    get recente() {
        return minutosDesde(this.maisRecente) <= cfg.idadeMaxima;
    },

    get texto() {
        void this.agora;
        if (this.carregando) return 'Atualizando cotações…';
        const m = minutosDesde(this.maisRecente);
        if (m === Infinity) return 'Cotações ainda não atualizadas';
        if (m < 1) return 'Cotações atualizadas agora';
        if (m < 60) return `Cotações atualizadas há ${Math.round(m)} min`;
        return `Cotações atualizadas há ${Math.round(m / 60)} h`;
    },

    talvezAtualizar() {
        if (minutosDesde(cfg.maisAntiga) <= cfg.idadeMaxima) return;
        let ultima = 0;
        try { ultima = Number(sessionStorage.getItem(CHAVE_TENTATIVA) || 0); } catch {}
        if ((Date.now() - ultima) / 60000 < cfg.idadeMaxima) return;
        try { sessionStorage.setItem(CHAVE_TENTATIVA, String(Date.now())); } catch {}
        this.atualizar(false);
    },

    async atualizar(manual) {
        this.carregando = true;
        try {
            const { data } = await window.axios.post(cfg.url, manual ? { forcar: 1 } : {});
            if (data.atualizados > 0) {
                this.maisRecente = new Date().toISOString();
                cfg.maisAntiga = data.falhas.length ? cfg.maisAntiga : this.maisRecente;
                // Recarrega para mostrar os valores novos, a menos que haja uma janela aberta.
                const janelaAberta = [...document.querySelectorAll('.modal-back')].some((el) => el.offsetParent !== null);
                const digitando = document.activeElement?.matches('input, select, textarea');
                if (janelaAberta || digitando) this.pendente = true;
                else location.reload();
            } else if (manual) {
                alertaSimples(data.falhas.length ? `Sem cotação para: ${data.falhas.join(', ')}` : 'Nenhuma cotação nova.');
            }
        } catch (e) {
            if (manual) alertaSimples(e.response?.status === 429 ? 'Muitas atualizações seguidas. Aguarde um minuto.' : 'Não foi possível consultar as cotações agora.');
        } finally {
            this.carregando = false;
        }
    },
});

function alertaSimples(texto) {
    const el = document.createElement('div');
    el.className = 'flash';
    el.setAttribute('role', 'status');
    el.textContent = texto;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}
