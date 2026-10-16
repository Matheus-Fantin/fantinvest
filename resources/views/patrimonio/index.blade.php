@php
    $composicao = collect($p['bens'])->map(fn ($b) => ['nome' => $b['nome'], 'valor' => round($b['valor'], 2), 'divida' => false])
        ->merge(collect($p['dividas'])->map(fn ($d) => ['nome' => $d['nome'], 'valor' => round($d['valor'], 2), 'divida' => true]))
        ->filter(fn ($x) => abs($x['valor']) > 0.009)->values();
@endphp
<x-app-layout titulo="Patrimônio">
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ mes_extenso(now()->format('Y-m')) }}</div>
            <h1>Patrimônio</h1>
            <p>Tudo o que você tem (contas, investimentos, cripto, imóvel) menos tudo o que deve (faturas, parcelas e financiamento). A foto do mês é guardada para acompanhar a evolução.</p>
        </div>
    </div>

    <div class="card">
        <div class="grid g4" style="align-items:end">
            <div class="kpi"><span class="label">Patrimônio líquido</span><span class="big-num {{ $p['liquido'] < 0 ? 'down' : '' }}">{{ brl($p['liquido']) }}</span></div>
            <div class="kpi"><span class="label">Tudo o que você tem</span><span class="value">{{ brl($p['total_bens']) }}</span></div>
            <div class="kpi"><span class="label">Tudo o que você deve</span><span class="value down">{{ brl($p['total_dividas']) }}</span></div>
            <div class="kpi"><span class="label">Renda passiva (média 12 meses)</span><span class="value up">{{ brl($p['renda_passiva_mes']) }}<span class="small muted">/mês</span></span><span class="sub">{{ brl($p['dividendos_12m']) }} em proventos</span></div>
        </div>
    </div>

    <div class="grid g4">
        <div class="card kpi"><span class="label">Em contas</span><span class="value">{{ brl($p['liquidez']) }}</span><span class="sub">corrente, poupança e corretora</span></div>
        <div class="card kpi"><span class="label">Investido</span><span class="value">{{ brl($p['investido']) }}</span><span class="sub">ações, FIIs, renda fixa, cripto</span></div>
        <div class="card kpi"><span class="label">Reserva de emergência</span><span class="value">{{ brl($p['reserva']) }}</span><span class="sub">informativo, já está em alguma conta</span></div>
        <div class="card kpi"><span class="label">Guardado em objetivos</span><span class="value">{{ brl($p['objetivos']) }}</span><span class="sub">informativo, já está em alguma conta</span></div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>Composição</h2><p>Em azul, o que você tem; em laranja, o que deve.</p></div></div>
            @if ($composicao->isEmpty())
                <div class="empty"><p>Cadastre contas, ativos, cartões ou seu financiamento para ver a composição.</p></div>
            @else
                <x-legenda :itens="[['s1', 'Bens', true], ['s2', 'Dívidas', true]]" />
                <div class="chart-box" style="height:{{ max(220, $composicao->count() * 34) }}px"><canvas data-grafico="composicao" data-dados='@json($composicao)' role="img" aria-label="Composição do patrimônio"></canvas></div>
            @endif
        </div>
        <div class="card">
            <div class="card-head"><div><h2>Evolução</h2><p>Uma foto por mês, atualizada sempre que você abre esta página.</p></div></div>
            <x-legenda :itens="[['s1', 'Patrimônio líquido'], ['s2', 'Dívidas']]" />
            <div class="chart-box"><canvas data-grafico="evolucao" data-dados='@json($evolucao)' role="img" aria-label="Evolução do patrimônio líquido"></canvas></div>
            @if (count($evolucao) < 2)<p class="note">O gráfico ganha forma a partir do próximo mês.</p>@endif
        </div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>O que você tem</h2></div><span class="num"><strong>{{ brl($p['total_bens']) }}</strong></span></div>
            @forelse (collect($p['bens'])->groupBy('grupo') as $grupo => $itens)
                <div class="eyebrow" style="margin-top:6px">{{ $grupo }}</div>
                @foreach ($itens as $b)
                    <div class="list-row"><span>{{ $b['nome'] }}</span><strong class="num {{ $b['valor'] < 0 ? 'down' : '' }}">{{ brl($b['valor']) }}</strong></div>
                @endforeach
            @empty
                <div class="empty"><p>Nada cadastrado ainda. <a class="link" href="{{ route('contas.index') }}">Cadastrar contas</a></p></div>
            @endforelse
        </div>
        <div class="card">
            <div class="card-head"><div><h2>O que você deve</h2></div><span class="num down"><strong>{{ brl($p['total_dividas']) }}</strong></span></div>
            @forelse (collect($p['dividas'])->groupBy('grupo') as $grupo => $itens)
                <div class="eyebrow" style="margin-top:6px">{{ $grupo }}</div>
                @foreach ($itens as $d)
                    <div class="list-row"><span>{{ $d['nome'] }}</span><strong class="num down">{{ brl($d['valor']) }}</strong></div>
                @endforeach
            @empty
                <div class="empty"><p>Nenhuma dívida registrada.</p></div>
            @endforelse
        </div>
    </div>
</x-app-layout>
