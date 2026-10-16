@php
    $classes = config('financas.classes');
    $precoSugerido = $detalhe['preco'] ?? $ativo?->cotacao;
@endphp
<x-app-layout :titulo="$ticker">
    <div class="page-head">
        <div style="min-width:0">
            <div class="eyebrow"><a href="{{ route('bolsa.index') }}" class="link">Bolsa</a> / {{ $ticker }}</div>
            <div class="row">
                @if (! empty($detalhe['logo']))<img class="ativo-logo" src="{{ $detalhe['logo'] }}" alt="" referrerpolicy="no-referrer">@endif
                <h1>{{ $ticker }}</h1>
            </div>
            <p>{{ $detalhe['nome'] ?? 'Sem dados da API para este ativo agora.' }}</p>
        </div>
        @if ($detalhe)
            <div style="text-align:right">
                <div class="big-num">{{ brl($detalhe['preco']) }}</div>
                @if ($detalhe['variacao'] !== null)
                    <span class="chip {{ $detalhe['variacao'] >= 0 ? 'good' : 'bad' }}">{{ $detalhe['variacao'] >= 0 ? '▲' : '▼' }} {{ pct(abs($detalhe['variacao']), 2) }} hoje</span>
                @endif
            </div>
        @endif
    </div>

    @if (! $detalhe)
        <div class="banner warn"><span>Não consegui buscar a cotação de {{ $ticker }}. Confira o código e se o BRAPI_TOKEN está no .env. Você ainda pode registrar operações com o preço que pagou.</span></div>
    @endif

    <div class="grid g-side">
        <div class="stack">
            @if ($ativo && $ativo->quantidade > 0)
                <div class="card stack">
                    <h2>Sua posição</h2>
                    <div class="row between"><span class="muted small">Quantidade</span><strong class="num">{{ numero($ativo->quantidade, 8) }}</strong></div>
                    <div class="row between"><span class="muted small">Preço médio</span><strong class="num">{{ brl($ativo->preco_medio) }}</strong></div>
                    <div class="row between"><span class="muted small">Valor hoje</span><strong class="num">{{ brl($ativo->valor()) }}</strong></div>
                    <div class="row between"><span class="muted small">Resultado</span><strong class="num {{ $ativo->resultadoPercentual() >= 0 ? 'up' : 'down' }}">{{ brl($ativo->valor() - $ativo->custo()) }} ({{ pct($ativo->resultadoPercentual()) }})</strong></div>
                    @if ($proventos->isNotEmpty())
                        <div class="row between"><span class="muted small">Proventos recebidos (últimos)</span><strong class="num up">{{ brl($proventos->sum('valor')) }}</strong></div>
                    @endif
                </div>
            @endif

            <form class="card stack" method="POST" action="{{ route('bolsa.operacao', $ticker) }}" autocomplete="off" x-data="{ tipo: @js(old('tipo', 'compra')) }">
                @csrf
                <h2>Registrar operação</h2>
                <input type="hidden" name="tipo" :value="tipo">
                <div class="seg" role="group" aria-label="Tipo">
                    <button type="button" x-on:click="tipo = 'compra'" :aria-pressed="tipo === 'compra'">Compra</button>
                    <button type="button" x-on:click="tipo = 'venda'" :aria-pressed="tipo === 'venda'" @disabled(! $ativo || $ativo->quantidade <= 0)>Venda</button>
                </div>
                <div class="form-grid">
                    <x-campo nome="quantidade" rotulo="Quantidade" :valor="old('quantidade')" required />
                    <x-campo nome="preco" rotulo="Preço por cota" pre="R$" :valor="old('preco', $precoSugerido ? moeda_input($precoSugerido) : '')" required />
                    <div class="field"><label for="oData">Data</label><input class="in" type="date" id="oData" name="data" value="{{ old('data', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                    <x-campo nome="taxas" rotulo="Taxas e emolumentos" pre="R$" :valor="old('taxas', '0,00')" />
                    <div class="field"><label for="oClasse">Classe</label>
                        <select class="in" id="oClasse" name="classe" @disabled($ativo !== null)>
                            @foreach (['acoes', 'fii', 'exterior'] as $k)<option value="{{ $k }}" @selected($classeSugerida === $k)>{{ $classes[$k]['nome'] }}</option>@endforeach
                        </select>
                        @if ($ativo)<input type="hidden" name="classe" value="{{ $ativo->classe }}">@endif
                    </div>
                    <div class="field"><label for="oConta">Conta (corretora)</label>
                        <select class="in" id="oConta" name="account_id">
                            <option value="">Nenhuma</option>
                            @foreach ($contas as $c)<option value="{{ $c->id }}" @selected($c->tipo === 'corretora')>{{ $c->nome }}</option>@endforeach
                        </select></div>
                </div>
                <button class="btn primary" type="submit" x-text="tipo === 'compra' ? 'Registrar compra' : 'Registrar venda'"></button>
                <p class="note">A compra recalcula o preço médio e entra no orçamento como Investimentos. A venda guarda o lucro ou prejuízo e credita o valor na conta escolhida.</p>
            </form>
        </div>

        <div class="stack">
            @if ($detalhe && count($detalhe['historico']) > 1)
                <div class="card">
                    <div class="card-head"><div><h2>Últimos 3 meses</h2><p>Fechamento diário. Plano gratuito da brapi: até 3 meses e cerca de 30 min de atraso.</p></div></div>
                    <div class="chart-box tall"><canvas data-grafico="historico" data-dados='@json($detalhe['historico'])' role="img" aria-label="Preço de fechamento nos últimos 3 meses"></canvas></div>
                    <div class="grid g4" style="margin-top:14px">
                        <div class="kpi"><span class="label">Abertura</span><span class="value" style="font-size:1.05rem">{{ $detalhe['abertura'] !== null ? brl($detalhe['abertura']) : '—' }}</span></div>
                        <div class="kpi"><span class="label">Máxima do dia</span><span class="value" style="font-size:1.05rem">{{ $detalhe['maxima'] !== null ? brl($detalhe['maxima']) : '—' }}</span></div>
                        <div class="kpi"><span class="label">Mínima do dia</span><span class="value" style="font-size:1.05rem">{{ $detalhe['minima'] !== null ? brl($detalhe['minima']) : '—' }}</span></div>
                        <div class="kpi"><span class="label">Volume</span><span class="value" style="font-size:1.05rem">{{ $detalhe['volume'] !== null ? numero($detalhe['volume'], 0) : '—' }}</span></div>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-head"><div><h2>Suas operações em {{ $ticker }}</h2></div></div>
                @if ($operacoes->isEmpty())
                    <div class="empty"><p>Nenhuma operação registrada neste ativo.</p></div>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>Data</th><th>Tipo</th><th class="n">Qtd</th><th class="n">Preço</th><th class="n">Taxas</th><th class="n">Total</th><th class="n">Resultado</th></tr></thead>
                        <tbody>
                            @foreach ($operacoes as $o)
                                <tr>
                                    <td class="num faint">{{ $o->data->format('d/m/Y') }}</td>
                                    <td><span class="chip {{ $o->tipo === 'compra' ? 'good' : 'warn' }}">{{ $o->tipo === 'compra' ? 'Compra' : 'Venda' }}</span></td>
                                    <td class="n">{{ numero($o->quantidade, 8) }}</td>
                                    <td class="n">{{ brl($o->preco) }}</td>
                                    <td class="n">{{ brl($o->taxas) }}</td>
                                    <td class="n"><strong>{{ brl($o->total()) }}</strong></td>
                                    <td class="n {{ ($o->resultado ?? 0) >= 0 ? 'up' : 'down' }}">{{ $o->resultado !== null ? brl($o->resultado) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
