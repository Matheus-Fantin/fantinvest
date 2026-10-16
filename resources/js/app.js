import './bootstrap';

import Alpine from 'alpinejs';
import { iniciarGraficos } from './graficos';
import { iniciarTema } from './tema';
import * as formato from './formato';
import simuladorJuros from './simuladores/juros';
import simuladorFinanciamento from './simuladores/financiamento';

window.Alpine = Alpine;
window.fmt = formato;

Alpine.data('simuladorJuros', simuladorJuros);
Alpine.data('simuladorFinanciamento', simuladorFinanciamento);

iniciarTema();
Alpine.start();
iniciarGraficos();
