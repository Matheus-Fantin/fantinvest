<x-app-layout titulo="Bolsa">
    <div class="page-head">
        <div>
            <h1>Bolsa hoje</h1>
            <p>As ações que mais estão sendo negociadas, as que mais subiram e as que mais caíram hoje na B3. Toque em uma para ver o gráfico e registrar compra ou venda.</p>
        </div>
    </div>

    @if ($semToken)
        <div class="banner warn"><span>Para ver a bolsa, coloque seu token gratuito da brapi.dev em <strong>BRAPI_TOKEN</strong> nas variáveis de ambiente.</span></div>
    @endif

    <x-busca-ativos :termo="$termo" />

    @if ($buscou)
        <div class="card">
            <div class="card-head"><div><h2>Resultado para "{{ $termo }}"</h2></div><a class="btn sm ghost" href="{{ route('bolsa.index') }}">Voltar aos rankings</a></div>
            <x-lista-ativos :itens="$resultados" vazio="Nada encontrado. Confira o nome ou o código." />
        </div>
    @else
        <div class="card" x-data="{ aba: 'negociadas' }">
            <div class="seg ranking-abas" role="tablist" aria-label="Rankings">
                <button type="button" role="tab" x-on:click="aba = 'negociadas'" :aria-pressed="aba === 'negociadas'">Mais negociadas</button>
                <button type="button" role="tab" x-on:click="aba = 'altas'" :aria-pressed="aba === 'altas'">Maiores altas</button>
                <button type="button" role="tab" x-on:click="aba = 'baixas'" :aria-pressed="aba === 'baixas'">Maiores baixas</button>
                <button type="button" role="tab" x-on:click="aba = 'fiis'" :aria-pressed="aba === 'fiis'">FIIs</button>
            </div>
            @foreach (['negociadas' => 'As 20 ações com maior volume negociado hoje.', 'altas' => 'As 20 ações que mais subiram hoje.', 'baixas' => 'As 20 ações que mais caíram hoje.', 'fiis' => 'Os 20 fundos imobiliários mais negociados hoje.'] as $chave => $texto)
                <div x-show="aba === '{{ $chave }}'" @if ($chave !== 'negociadas') x-cloak @endif>
                    <p class="small muted" style="margin:12px 0 4px">{{ $texto }} Atualiza a cada 10 minutos (cerca de 30 min de atraso no plano gratuito).</p>
                    <x-lista-ativos :itens="$rankings[$chave] ?? []" vazio="Não consegui carregar agora. Confira o BRAPI_TOKEN ou tente em alguns minutos." />
                </div>
            @endforeach
        </div>
    @endif

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
