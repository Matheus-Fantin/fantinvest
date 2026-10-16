import './bootstrap';

import Alpine from 'alpinejs';
import { iniciarGraficos } from './graficos';
import { iniciarTema } from './tema';
import * as formato from './formato';
import simuladorJuros from './simuladores/juros';
import simuladorFinanciamento from './simuladores/financiamento';
import cotacoesAoVivo from './cotacoes';

window.Alpine = Alpine;
window.fmt = formato;

Alpine.data('simuladorJuros', simuladorJuros);
Alpine.data('simuladorFinanciamento', simuladorFinanciamento);
Alpine.data('cotacoesAoVivo', cotacoesAoVivo);

// Campos em reais: ao sair do campo, mostra 1.234,56 (ponto no milhar, vírgula nos centavos).
document.addEventListener('focusout', (e) => {
    const campo = e.target;
    if (!campo.matches?.('[data-moeda]') || campo.value.trim() === '') return;
    const formatado = formato.moeda(formato.parseNum(campo.value));
    if (formatado !== campo.value) {
        campo.value = formatado;
        campo.dispatchEvent(new Event('input', { bubbles: true }));
    }
});

iniciarTema();
Alpine.start();
iniciarGraficos();
