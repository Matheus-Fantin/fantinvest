<x-app-layout titulo="Regra de aporte">
    <div class="page-head">
        <div>
            <div class="eyebrow">Regra de aporte</div>
            <h1>Quanto comprar de cada ativo</h1>
            <p>O aporte vai primeiro para as classes mais abaixo da meta. Dentro de cada classe, cada ativo tem peso proporcional à nota, e quem está mais abaixo do peso recebe primeiro. Nada é vendido.</p>
        </div>
    </div>


    <x-cotacoes-status />

    @if (abs($somaMetas - 100) > 0.01)
        <div class="banner warn">
            <span>As metas por classe somam {{ pct($somaMetas, 0) }}. Ajuste para 100% na Carteira para uma sugestão precisa.</span>
            <a class="btn sm" href="{{ route('carteira.index') }}">Ajustar metas</a>
        </div>
    @endif

    <div class="grid g-side">
        <div class="card stack">
            <form method="GET" action="{{ route('aporte.index') }}" class="stack">
                <x-campo nome="valor" rotulo="Valor do aporte" pre="R$" :valor="valor_input($valor)" dica="Quanto você vai investir agora." />
                <div class="presets">
                    @foreach ([500, 1000, 2000, 3000, 5000] as $v)
                        <a href="{{ route('aporte.index', ['valor' => $v]) }}">{{ brl($v, 0) }}</a>
                    @endforeach
                </div>
                <button class="btn" type="submit">Calcular</button>
            </form>
            <div class="divider"></div>
            <div class="kpi">
                <span class="label">Vai ser investido</span>
                <span class="value">{{ brl($plano['investido']) }}</span>
                <span class="sub">Sobra de {{ brl($plano['sobra']) }} por causa do preço das cotas</span>
            </div>
            <form method="POST" action="{{ route('aporte.store') }}">
                @csrf
                <input type="hidden" name="valor" value="{{ valor_input($valor) }}">
                <button class="btn primary" type="submit" style="width:100%" @disabled(empty($plano['compras']))><x-icone nome="check" />Registrar compras na carteira</button>
            </form>
            <p class="note">Registrar atualiza a quantidade e o preço médio de cada ativo e lança o aporte no orçamento deste mês.</p>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-head"><div><h2>Por classe</h2><p>Antes e depois do aporte.</p></div></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Classe</th><th class="n">Meta</th><th class="n">Atual</th><th class="n">Depois</th><th class="n">Aportar</th></tr></thead>
                        <tbody>
                            @foreach ($plano['classes'] as $c)
                                @continue($c['meta'] <= 0 && $c['atual'] <= 0)
                                <tr>
                                    <td>
                                        <span class="dot" style="background:var(--{{ $c['cor'] }})"></span>{{ $c['nome'] }}
                                        @if (! $c['elegivel'] && $c['meta'] > 0) <span class="chip warn">sem ativo com nota</span> @endif
                                    </td>
                                    <td class="n">{{ pct($c['meta'] * 100, 0) }}</td>
                                    <td class="n">{{ pct($c['pct_atual']) }}</td>
                                    <td class="n"><strong>{{ pct($c['pct_depois']) }}</strong></td>
                                    <td class="n">@if ($c['real'] > 0)<strong>{{ brl($c['real']) }}</strong>@else<span class="faint">—</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div><h2>Ordem de compra</h2><p>Da maior para a menor compra.</p></div></div>
                @if (empty($plano['compras']))
                    <div class="empty"><p>Nenhuma compra possível com esse valor. Aumente o aporte ou confira se os ativos têm nota e cotação.</p></div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Ativo</th><th>Classe</th><th class="n">Nota</th><th class="n">Cotação</th><th class="n">Comprar</th><th class="n">Valor</th></tr></thead>
                            <tbody>
                                @foreach ($plano['compras'] as $x)
                                    <tr>
                                        <td><span class="ticker">{{ $x['ativo']->ticker }}</span></td>
                                        <td><span class="dot" style="background:var(--{{ $x['ativo']->cor() }})"></span>{{ $x['ativo']->nomeClasse() }}</td>
                                        <td class="n">{{ numero($x['ativo']->nota, 1) }}</td>
                                        <td class="n">{{ brl($x['ativo']->cotacao) }}</td>
                                        <td class="n"><strong>{{ $x['cotas'] === null ? 'valor livre' : $x['cotas'].($x['cotas'] === 1 ? ' cota' : ' cotas') }}</strong></td>
                                        <td class="n">{{ brl($x['valor']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
