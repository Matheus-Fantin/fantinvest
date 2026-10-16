<x-app-layout titulo="Financiamento">
    <div x-data="simuladorFinanciamento" x-on:input.debounce.300ms="calcular()" class="stack" style="gap:24px">
        <div class="page-head">
            <div>
                <div class="eyebrow">Simulador</div>
                <h1>Financiamento</h1>
                <p>Compare Price e SAC, veja quanto vai em juros e se compensa mais financiar ou juntar investindo.</p>
            </div>
            <div class="seg" role="group" aria-label="Sistema de amortização">
                <button type="button" x-on:click="usarSistema('price')" :aria-pressed="sistema === 'price'">Price (parcela fixa)</button>
                <button type="button" x-on:click="usarSistema('sac')" :aria-pressed="sistema === 'sac'">SAC (parcela cai)</button>
            </div>
        </div>

        <div class="grid g-side">
            <div class="card stack">
                <div class="form-grid">
                    <x-campo nome="f_valor" rotulo="Valor do bem" pre="R$" x-model="campos.valor" />
                    <div class="field">
                        <label for="f-entrada">Entrada</label>
                        <div class="in-group"><span class="affix">R$</span><input class="in" id="f-entrada" inputmode="decimal" x-model="campos.entrada"></div>
                        <span class="hint" x-text="o.valor ? fmt.pct(o.entrada / o.valor * 100, 0) + ' do valor' : ''"></span>
                    </div>
                    <div class="field">
                        <label for="f-taxa">Taxa de juros</label>
                        <div class="in-group">
                            <input class="in" id="f-taxa" inputmode="decimal" x-model="campos.taxa">
                            <select class="affix" x-model="unid" x-on:change="calcular()" aria-label="Unidade da taxa"><option value="aa">% a.a.</option><option value="am">% a.m.</option></select>
                        </div>
                    </div>
                    <div class="field">
                        <label for="f-prazo">Prazo</label>
                        <div class="in-group"><input class="in" id="f-prazo" inputmode="numeric" x-model="campos.prazo"><span class="affix">meses</span></div>
                        <span class="hint" x-text="fmt.prazo(Math.round(o.prazo))"></span>
                    </div>
                    <div class="field">
                        <label for="f-taxas">Seguros e tarifas</label>
                        <div class="in-group"><span class="affix">R$</span><input class="in" id="f-taxas" inputmode="decimal" x-model="campos.taxas"><span class="affix">/mês</span></div>
                    </div>
                    <x-campo nome="f_renda" rotulo="Sua renda mensal" pre="R$" x-model="campos.renda" />
                    <x-campo nome="f_inflacao" rotulo="Inflação esperada" suf="% a.a." x-model="campos.inflacao" />
                    <x-campo nome="f_alt" rotulo="Rendimento se investir" suf="% a.a." x-model="campos.alt" />
                </div>
                <div class="field">
                    <span class="small muted">Exemplos</span>
                    <div class="presets">
                        <button type="button" x-on:click="exemplo('imovel')">Imóvel</button>
                        <button type="button" x-on:click="exemplo('carro')">Carro</button>
                        <button type="button" x-on:click="exemplo('moto')">Moto</button>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="verdict" :class="v.level">
                    <span class="chip" :class="v.level" style="padding:6px">
                        <template x-if="v.level === 'good'"><x-icone nome="check" style="width:18px;height:18px" /></template>
                        <template x-if="v.level !== 'good'"><x-icone nome="alert" style="width:18px;height:18px" /></template>
                    </span>
                    <div style="min-width:0">
                        <div class="big" x-text="v.title"></div>
                        <ul>
                            <template x-for="(item, i) in v.items" :key="i">
                                <li>
                                    <span class="chip" :class="item[0]" x-text="{ good: '✓ Bom', warn: '! Atenção', bad: '✕ Ruim' }[item[0]]"></span>
                                    <span><strong x-text="item[1] + '.'"></strong> <span x-text="item[2]"></span></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <div class="grid g4">
                    <div class="card kpi"><span class="label" x-text="sistema === 'price' ? 'Parcela' : '1ª parcela'"></span><span class="value" x-text="fmt.brl0(r.sel.first)"></span><span class="sub" x-text="sistema === 'sac' ? 'última ' + fmt.brl0(r.sel.last) : 'fixa por ' + r.n + ' meses'"></span></div>
                    <div class="card kpi"><span class="label">Total pago</span><span class="value" x-text="fmt.brl0(r.sel.total + o.entrada)"></span><span class="sub" x-text="o.valor ? fmt.numf((r.sel.total + o.entrada) / o.valor, 2) + '× o valor do bem' : ''"></span></div>
                    <div class="card kpi"><span class="label">Só de juros</span><span class="value down" x-text="fmt.brl0(r.sel.juros)"></span><span class="sub" x-text="'taxa ' + fmt.pct(r.iA, 2) + ' a.a. · ' + fmt.pct(r.i * 100, 2) + ' a.m.'"></span></div>
                    <div class="card kpi"><span class="label">CET aproximado</span><span class="value" x-text="fmt.pct(r.cetA, 2)"></span><span class="sub">a.a., com seguros e tarifas</span></div>
                </div>

                <div class="card">
                    <div class="card-head"><div><h2>Price × SAC</h2><p x-text="'Valor da parcela ao longo do tempo. Price: total ' + fmt.brl0(r.price.total) + ' · SAC: total ' + fmt.brl0(r.sac.total) + ' · diferença ' + fmt.brl0(Math.abs(r.price.total - r.sac.total)) + '.'"></p></div></div>
                    <x-legenda :itens="[['s1', 'Price'], ['s2', 'SAC']]" />
                    <div class="chart-box"><canvas x-ref="parcelas" role="img" aria-label="Parcela mensal nos sistemas Price e SAC"></canvas></div>
                </div>

                <div class="card">
                    <div class="card-head"><div><h2>Para onde vai seu dinheiro</h2><p x-text="'Por ano, quanto da parcela paga juros e quanto abate a dívida (' + (sistema === 'price' ? 'Price' : 'SAC') + ').'"></p></div></div>
                    <x-legenda :itens="[['s1', 'Amortização', true], ['s2', 'Juros', true]]" />
                    <div class="chart-box"><canvas x-ref="composicao" role="img" aria-label="Juros e amortização pagos por ano"></canvas></div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <div><h2>Financiar ou investir e comprar à vista?</h2><p x-text="'Simula guardar a entrada e o valor da parcela todo mês a ' + fmt.pct(o.alt) + ' a.a., enquanto o preço do bem sobe com a inflação.'"></p></div>
                        <span class="chip" :class="r.mes ? (r.mes < r.n ? 'good' : 'warn') : 'bad'" x-text="r.mes ? 'À vista em ' + fmt.prazo(r.mes) : 'Não alcança'"></span>
                    </div>
                    <x-legenda :itens="[['s1', 'Dinheiro investido'], ['s2', 'Preço do bem (corrigido)']]" />
                    <div class="chart-box"><canvas x-ref="alternativa" role="img" aria-label="Saldo investido comparado ao preço do bem corrigido"></canvas></div>
                    <div class="grid g3" style="margin-top:14px">
                        <div class="kpi"><span class="label">Financiando</span><span class="value" style="font-size:1.2rem" x-text="fmt.brl0(r.sel.total + o.entrada)"></span><span class="sub" x-text="'em ' + fmt.prazo(r.n)"></span></div>
                        <div class="kpi"><span class="label">Investindo antes</span><span class="value" style="font-size:1.2rem" x-text="r.gastoAlt ? fmt.brl0(r.gastoAlt) : '—'"></span><span class="sub" x-text="r.mes ? 'em ' + fmt.prazo(r.mes) : 'não alcança em 50 anos'"></span></div>
                        <div class="kpi"><span class="label">Diferença</span><span class="value" style="font-size:1.2rem" :class="r.economia > 0 ? 'up' : 'down'" x-text="r.economia !== null ? fmt.brl0(r.economia) : '—'"></span><span class="sub" x-text="r.economia > 0 ? 'a mais para quem financia' : ''"></span></div>
                    </div>
                    <p class="note" style="margin-top:10px" x-text="'Custo real das parcelas em reais de hoje: ' + fmt.brl0(r.vpReal) + ' (descontando inflação de ' + fmt.pct(o.inflacao) + ' a.a.). A simulação não considera aluguel ou uso do bem enquanto junta.'"></p>
                </div>

                <div class="card">
                    <details>
                        <summary><x-icone nome="list" width="15" height="15" style="vertical-align:-2px;margin-right:6px" />Tabela de amortização ano a ano</summary>
                        <div class="table-wrap" style="margin-top:12px">
                            <table>
                                <thead><tr><th>Ano</th><th class="n">Pago no ano</th><th class="n">Juros</th><th class="n">Amortização</th><th class="n">Saldo devedor</th></tr></thead>
                                <tbody>
                                    <template x-for="a in anos" :key="a.ano">
                                        <tr>
                                            <td x-text="a.ano"></td>
                                            <td class="n" x-text="fmt.brl0(a.pago)"></td>
                                            <td class="n down" x-text="fmt.brl0(a.juros)"></td>
                                            <td class="n" x-text="fmt.brl0(a.amort)"></td>
                                            <td class="n"><strong x-text="fmt.brl0(a.saldo)"></strong></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
