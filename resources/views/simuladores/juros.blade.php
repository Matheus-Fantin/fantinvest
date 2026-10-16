<x-app-layout titulo="Juros compostos">
    <div class="page-head">
        <div>
            <div class="eyebrow">Simulador</div>
            <h1>Juros compostos com inflação</h1>
            <p>Veja quanto o dinheiro cresce no papel e quanto ele vale em poder de compra de hoje, já descontando inflação e imposto.</p>
        </div>
    </div>

    <div class="grid g-side" x-data="simuladorJuros" x-on:input.debounce.300ms="calcular()">
        <div class="card stack">
            <div class="form-grid">
                @foreach ([
                    ['inicial', 'Valor inicial', 'R$', null, null],
                    ['aporte', 'Aporte mensal', 'R$', null, null],
                    ['taxa', 'Rentabilidade', null, '% a.a.', null],
                    ['inflacao', 'Inflação (IPCA)', null, '% a.a.', null],
                    ['anos', 'Prazo', null, 'anos', null],
                    ['reajuste', 'Reajuste do aporte', null, '% a.a.', 'Aumento do aporte a cada ano'],
                ] as [$chave, $rotulo, $pre, $suf, $dica])
                    <x-campo :nome="'j_'.$chave" :rotulo="$rotulo" :pre="$pre" :suf="$suf" :dica="$dica" x-model="campos.{{ $chave }}" />
                @endforeach
                <x-campo nome="j_ir" rotulo="Imposto sobre o ganho" suf="%" classe="full" dica="15% para renda fixa acima de 2 anos; 0% para LCI, LCA e poupança" x-model="campos.ir" />
            </div>
            <div class="field">
                <span class="small muted">Taxas de referência (ajuste aos valores atuais)</span>
                <div class="presets">
                    <button type="button" x-on:click="preset('6.17')">Poupança ≈ 6,2%</button>
                    <button type="button" x-on:click="preset('10.5')">100% CDI ≈ 10,5%</button>
                    <button type="button" x-on:click="preset('ipca6')">IPCA + 6%</button>
                    <button type="button" x-on:click="preset('12')">Ações ≈ 12%</button>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="grid g4">
                <div class="card kpi"><span class="label">Valor final (bruto)</span><span class="value" x-text="fmt.brl(r.saldo)"></span><span class="sub" x-text="'em ' + fmt.numf(o.anos, 1) + ' anos'"></span></div>
                <div class="card kpi"><span class="label">Poder de compra hoje</span><span class="value" style="color:var(--accent)" x-text="fmt.brl(r.realLiq)"></span><span class="sub">líquido de IR e inflação</span></div>
                <div class="card kpi"><span class="label">Total investido</span><span class="value" x-text="fmt.brl(r.inv)"></span><span class="sub">do seu bolso</span></div>
                <div class="card kpi"><span class="label">Juros ganhos</span><span class="value up" x-text="fmt.brl(r.ganho)"></span><span class="sub" x-text="r.inv ? fmt.numf(r.saldo / r.inv, 2) + '× o que você investiu' : ''"></span></div>
            </div>

            <div class="card">
                <div class="card-head"><div><h2>Evolução do patrimônio</h2><p x-text="'Rentabilidade real: ' + fmt.pct(r.rReal * 100, 2) + ' a.a. acima da inflação.'"></p></div></div>
                <x-legenda :itens="[['s1', 'Saldo nominal'], ['s3', 'Poder de compra (hoje)'], ['s2', 'Total investido']]" />
                <div class="chart-box tall"><canvas x-ref="grafico" role="img" aria-label="Evolução do saldo nominal, real e total investido por ano"></canvas></div>
            </div>

            <div class="grid g2">
                <div class="card stack">
                    <h2>Renda passiva no final</h2>
                    <div class="row between"><span class="muted small">Vivendo só do ganho real</span><strong class="num" x-text="fmt.brl(r.renda) + '/mês'"></strong></div>
                    <div class="row between"><span class="muted small">Regra dos 4% ao ano</span><strong class="num" x-text="fmt.brl(r.renda4) + '/mês'"></strong></div>
                    <p class="note">Valores em reais de hoje, sem consumir o patrimônio.</p>
                </div>
                <div class="card stack">
                    <h2>Quanto aportar para chegar lá</h2>
                    <x-campo nome="j_meta" rotulo="Quero ter (em reais de hoje)" pre="R$" x-model="campos.meta" />
                    <div class="row between"><span class="muted small">Aporte mensal necessário</span><strong class="num" style="color:var(--accent)" x-text="r.apMeta <= 0 ? 'Seu valor inicial já basta' : fmt.brl(r.apMeta) + '/mês'"></strong></div>
                    <p class="note">Aporte fixo em reais de hoje (corrija pela inflação todo ano), sem imposto.</p>
                </div>
            </div>

            <div class="card">
                <details>
                    <summary><x-icone nome="list" width="15" height="15" style="vertical-align:-2px;margin-right:6px" />Tabela ano a ano</summary>
                    <div class="table-wrap" style="margin-top:12px">
                        <table>
                            <thead><tr><th>Ano</th><th class="n">Investido</th><th class="n">Juros acumulados</th><th class="n">Saldo nominal</th><th class="n">Poder de compra</th></tr></thead>
                            <tbody>
                                <template x-for="x in r.rows" :key="x.ano">
                                    <tr>
                                        <td x-text="fmt.numf(x.ano, 1)"></td>
                                        <td class="n" x-text="fmt.brl(x.inv)"></td>
                                        <td class="n up" x-text="fmt.brl(x.saldo - x.inv)"></td>
                                        <td class="n"><strong x-text="fmt.brl(x.saldo)"></strong></td>
                                        <td class="n" x-text="fmt.brl(x.real)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>
</x-app-layout>
