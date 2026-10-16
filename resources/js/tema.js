// Tema: "sistema" (padrão), "claro" ou "escuro". A escolha fica no navegador.
const ORDEM = ['sistema', 'claro', 'escuro'];
const ROTULO = { sistema: 'Tema do sistema', claro: 'Tema claro', escuro: 'Tema escuro' };

function lerTema() {
    try { return localStorage.getItem('fantinvest.tema') || 'sistema'; } catch { return 'sistema'; }
}

function aplicar(tema) {
    const raiz = document.documentElement;
    if (tema === 'sistema') raiz.removeAttribute('data-theme');
    else raiz.setAttribute('data-theme', tema === 'escuro' ? 'dark' : 'light');
    document.querySelectorAll('[data-tema-rotulo]').forEach((el) => (el.textContent = ROTULO[tema]));
    window.dispatchEvent(new Event('fantinvest:tema'));
}

export function iniciarTema() {
    aplicar(lerTema());
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => window.dispatchEvent(new Event('fantinvest:tema')));
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-alternar-tema]')) return;
        const proximo = ORDEM[(ORDEM.indexOf(lerTema()) + 1) % ORDEM.length];
        try { localStorage.setItem('fantinvest.tema', proximo); } catch {}
        aplicar(proximo);
    });
}
