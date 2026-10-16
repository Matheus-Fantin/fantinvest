<x-app-layout titulo="Bolsa">
    <div class="page-head">
        <div>
            <div class="eyebrow">Investimentos</div>
            <h1>Bolsa de valores</h1>
            <p>Pesquise ações, FIIs, ETFs e BDRs da B3, veja o gráfico de cada uma e registre suas compras e vendas. A carteira, o preço médio e o lucro saem das operações.</p>
        </div>
    </div>

    @if ($semToken)
        <div class="banner warn"><span>Para pesquisar e ver cotações, coloque seu token gratuito da brapi.dev em <strong>BRAPI_TOKEN</strong> no arquivo .env.</span></div>
    @endif

    <form method="GET" action="{{ route('bolsa.index') }}" class="card stack">
        <div class="search-box">
            <div class="in-group"><span class="affix"><x-icone nome="search" width="16" height="16" /></span><input class="in" name="q" value="{{ $termo }}" placeholder="Código ou nome: PETR4, Itaúsa, HGLG11, IVVB11…" autofocus aria-label="Pesquisar ativo"></div>
            <button class="btn primary" type="submit">Pesquisar</button>
        </div>
        @if ($buscou)
            @if (empty($resultados))
                <p class="muted small">Nada encontrado para "{{ $termo }}". Confira o código ou tente abrir direto: <a class="link" href="{{ route('bolsa.show', $termo) }}">{{ mb_strtoupper($termo) }}</a>.</p>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>Ativo</th><th>Nome</th><th>Setor</th><th class="n">Preço</th><th class="n">Dia</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($resultados as $r)
                            <tr>
                                <td><a class="ticker link" href="{{ route('bolsa.show', $r['ticker']) }}">{{ $r['ticker'] }}</a></td>
                                <td class="muted" style="white-space:normal;min-width:160px">{{ $r['nome'] }}</td>
                                <td class="faint small">{{ $r['setor'] }}</td>
                                <td class="n">{{ $r['preco'] !== null ? brl($r['preco']) : '—' }}</td>
                                <td class="n {{ ($r['variacao'] ?? 0) >= 0 ? 'up' : 'down' }}">@if ($r['variacao'] !== null){{ $r['variacao'] >= 0 ? '▲' : '▼' }} {{ pct(abs($r['variacao']), 2) }}@endif</td>
                                <td><a class="btn sm" href="{{ route('bolsa.show', $r['ticker']) }}">Abrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        @endif
    </form>

    <div class="grid g4">
        <div class="card kpi"><span class="label">Em ações, FIIs e exterior</span><span class="value">{{ brl($total) }}</span><span class="sub">{{ $posicoes->count() }} ativos</span></div>
        <div class="card kpi"><span class="label">Resultado não realizado</span><span class="value {{ $total - $custo >= 0 ? 'up' : 'down' }}">{{ brl($total - $custo) }}</span><span class="sub">{{ $custo > 0 ? pct(($total / $custo - 1) * 100) : '' }} sobre o custo</span></div>
        <div class="card kpi"><span class="label">Lucro realizado em {{ now()->year }}</span><span class="value {{ $lucroRealizadoAno >= 0 ? 'up' : 'down' }}">{{ brl($lucroRealizadoAno) }}</span><span class="sub">vendas já feitas</span></div>
        <div class="card kpi"><span class="label">Vendas de ações no mês</span><span class="value">{{ brl($vendasAcoesMes) }}</span>
            <span class="sub">@if ($vendasAcoesMes > 20000)<span class="down">Acima de R$ 20.000,00: lucro tem IR</span>@else Isento de IR até R$ 20.000,00 @endif</span></div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>Sua carteira na bolsa</h2><p>Cotações atualizadas automaticamente.</p></div><a class="btn sm ghost" href="{{ route('carteira.index') }}">Ver carteira completa</a></div>
            @if ($posicoes->isEmpty())
                <div class="empty"><p>Pesquise um ativo e registre a primeira compra.</p></div>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>Ativo</th><th class="n">Qtd</th><th class="n">Preço médio</th><th class="n">Cotação</th><th class="n">Resultado</th></tr></thead>
                    <tbody>
                        @foreach ($posicoes as $a)
                            @php $r = $a->resultadoPercentual(); @endphp
                            <tr>
                                <td><a class="ticker link" href="{{ route('bolsa.show', $a->ticker) }}">{{ $a->ticker }}</a></td>
                                <td class="n">{{ numero($a->quantidade, 8) }}</td>
                                <td class="n">{{ brl($a->preco_medio) }}</td>
                                <td class="n">{{ brl($a->cotacao) }}@if ($a->variacao_dia !== null)<span class="var-dia {{ $a->variacao_dia >= 0 ? 'up' : 'down' }}">{{ $a->variacao_dia >= 0 ? '▲' : '▼' }} {{ pct(abs($a->variacao_dia), 2) }}</span>@endif</td>
                                <td class="n {{ $r >= 0 ? 'up' : 'down' }}">{{ $r >= 0 ? '+' : '' }}{{ pct($r) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
        <div class="card">
            <div class="card-head"><div><h2>Últimas operações</h2><p>Compras e vendas registradas.</p></div></div>
            @if ($operacoes->isEmpty())
                <div class="empty"><p>Nenhuma operação ainda.</p></div>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>Data</th><th>Ativo</th><th>Tipo</th><th class="n">Qtd</th><th class="n">Preço</th><th class="n">Resultado</th></tr></thead>
                    <tbody>
                        @foreach ($operacoes as $o)
                            <tr>
                                <td class="num faint">{{ $o->data->format('d/m/Y') }}</td>
                                <td><a class="ticker link" href="{{ route('bolsa.show', $o->asset->ticker) }}">{{ $o->asset->ticker }}</a></td>
                                <td><span class="chip {{ $o->tipo === 'compra' ? 'good' : 'warn' }}">{{ $o->tipo === 'compra' ? 'Compra' : 'Venda' }}</span></td>
                                <td class="n">{{ numero($o->quantidade, 8) }}</td>
                                <td class="n">{{ brl($o->preco) }}</td>
                                <td class="n {{ ($o->resultado ?? 0) >= 0 ? 'up' : 'down' }}">{{ $o->resultado !== null ? brl($o->resultado) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>
</x-app-layout>
