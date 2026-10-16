@php $tipos = \App\Models\Dividend::TIPOS; @endphp
<x-app-layout titulo="Dividendos">
    <div class="page-head">
        <div>
            <div class="eyebrow">Investimentos</div>
            <h1>Dividendos e proventos</h1>
            <p>Registre dividendos, JCP e rendimentos de FIIs. Cada provento entra no orçamento como receita (categoria Dividendos) e soma na sua renda passiva.</p>
        </div>
    </div>

    <div class="grid g4">
        <div class="card kpi"><span class="label">Neste mês</span><span class="value up">{{ brl($totalMes) }}</span></div>
        <div class="card kpi"><span class="label">Últimos 12 meses</span><span class="value">{{ brl($total12) }}</span><span class="sub">média de {{ brl($total12 / 12) }} por mês</span></div>
        <div class="card kpi"><span class="label">Em {{ now()->year }}</span><span class="value">{{ brl($totalAno) }}</span></div>
        <div class="card kpi"><span class="label">Yield on cost (12 meses)</span><span class="value">{{ pct($yocCarteira, 2) }}</span><span class="sub">proventos ÷ quanto você pagou</span></div>
    </div>

    <div class="grid g-side">
        <form class="card stack" method="POST" action="{{ route('dividendos.store') }}" autocomplete="off">
            @csrf
            <h2>Registrar provento</h2>
            @if ($ativos->isEmpty())
                <p class="muted small">Cadastre o ativo na Carteira ou na Bolsa antes de registrar proventos.</p>
            @else
                <div class="form-grid">
                    <div class="field"><label for="dAtivo">Ativo</label>
                        <select class="in" id="dAtivo" name="asset_id">@foreach ($ativos as $a)<option value="{{ $a->id }}" @selected(old('asset_id') == $a->id)>{{ $a->ticker }}</option>@endforeach</select>
                        @error('asset_id')<span class="erro">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="dTipo">Tipo</label>
                        <select class="in" id="dTipo" name="tipo">@foreach ($tipos as $k => $v)<option value="{{ $k }}" @selected(old('tipo') === $k)>{{ $v }}</option>@endforeach</select></div>
                    <x-campo nome="valor" rotulo="Valor recebido" pre="R$" :valor="old('valor')" required dica="O total que caiu na conta." />
                    <div class="field"><label for="dData">Data do pagamento</label><input class="in" type="date" id="dData" name="data" value="{{ old('data', now()->toDateString()) }}" required></div>
                    <div class="field full"><label for="dConta">Caiu na conta</label>
                        <select class="in" id="dConta" name="account_id"><option value="">Nenhuma</option>
                            @foreach ($contas as $c)<option value="{{ $c->id }}" @selected($c->tipo === 'corretora')>{{ $c->nome }}</option>@endforeach</select></div>
                </div>
                <button class="btn primary" type="submit"><x-icone nome="plus" />Registrar</button>
            @endif
            <p class="note">Dica: na Área do Investidor da B3 (investidor.b3.com.br), em Extratos → Proventos, você vê tudo o que recebeu. A busca automática de proventos pela brapi exige plano pago.</p>
        </form>

        <div class="stack">
            <div class="card">
                <div class="card-head"><div><h2>Proventos por mês</h2><p>Últimos 12 meses.</p></div></div>
                <x-legenda :itens="[['s1', 'Dividendos', true], ['s2', 'JCP', true], ['s3', 'Rendimentos (FII)', true]]" />
                <div class="chart-box short"><canvas data-grafico="dividendos" data-dados='@json($meses)' role="img" aria-label="Proventos recebidos por mês"></canvas></div>
            </div>
            <div class="card">
                <div class="card-head"><div><h2>Por ativo</h2><p>Últimos 12 meses.</p></div></div>
                @if ($porAtivo->isEmpty())
                    <div class="empty"><p>Nenhum provento nos últimos 12 meses.</p></div>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>Ativo</th><th class="n">Pagamentos</th><th class="n">Total</th><th class="n">Yield on cost</th></tr></thead>
                        <tbody>@foreach ($porAtivo as $p)
                            <tr><td><span class="ticker">{{ $p['ticker'] }}</span></td><td class="n">{{ $p['pagamentos'] }}</td><td class="n"><strong>{{ brl($p['total']) }}</strong></td><td class="n">{{ $p['yoc'] !== null ? pct($p['yoc'], 2) : '—' }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div><h2>Histórico</h2><p>Excluir um provento também remove a receita do orçamento.</p></div></div>
        @if ($proventos->isEmpty())
            <div class="empty"><x-icone nome="coins" width="28" height="28" /><p>Nenhum provento registrado.</p></div>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Data</th><th>Ativo</th><th>Tipo</th><th>Conta</th><th class="n">Valor</th><th></th></tr></thead>
                <tbody>@foreach ($proventos as $d)
                    <tr>
                        <td class="num faint">{{ $d->data->format('d/m/Y') }}</td>
                        <td><span class="ticker">{{ $d->ticker }}</span></td>
                        <td>{{ $tipos[$d->tipo] ?? $d->tipo }}</td>
                        <td class="origem">{{ $d->account?->nome ?? '—' }}</td>
                        <td class="n up"><strong>{{ brl($d->valor) }}</strong></td>
                        <td><form method="POST" action="{{ route('dividendos.destroy', $d) }}" class="row-actions">@csrf @method('DELETE')<button class="btn icon ghost" aria-label="Excluir provento"><x-icone nome="trash" /></button></form></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </div>
</x-app-layout>
