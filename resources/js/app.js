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

iniciarTema();
Alpine.start();
iniciarGraficos();
