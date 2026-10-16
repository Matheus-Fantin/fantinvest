<x-app-layout titulo="Visão geral">
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ mes_extenso($mes) }}</div>
            <h1>Visão geral</h1>
            <p>Seu patrimônio, o mês corrente e para onde vai o próximo aporte.</p>
        </div>
    </div>

    @if ($quantidadeAtivos === 0 && $mesAtual['receitas'] == 0)
        <div class="banner">
            <span><strong>Comece por aqui.</strong> Cadastre seus ativos na Carteira e lance as receitas e gastos do mês no Orçamento.</span>
            <span class="row">
                <a class="btn sm" href="{{ route('orcamento.index') }}">Abrir orçamento</a>
                <a class="btn sm primary" href="{{ route('carteira.index') }}">Cadastrar ativos</a>
            </span>
        </div>
    @endif

    <div class="grid g4">
        <div class="card kpi">
            <span class="label">Patrimônio investido</span>
            <span class="value">{{ brl($resumo['total'], 0) }}</span>
            <span class="sub">{{ $quantidadeAtivos }} {{ $quantidadeAtivos === 1 ? 'ativo' : 'ativos' }} na carteira</span>
        </div>
        <div class="card kpi">
            <span class="label">Resultado da carteira</span>
            <span class="value {{ $resumo['resultado'] >= 0 ? 'up' : 'down' }}">{{ $resumo['resultado'] >= 0 ? '+' : '' }}{{ brl($resumo['resultado'], 0) }}</span>
            <span class="sub">
                @if ($resumo['custo'] > 0)
                    {{ $resumo['resultado'] >= 0 ? '+' : '' }}{{ pct($resumo['resultado'] / $resumo['custo'] * 100) }} sobre o custo
                @else
                    sem custo registrado
                @endif
            </span>
        </div>
        <div class="card kpi">
            <span class="label">Sobra do mês</span>
            <span class="value {{ $mesAtual['sobra'] < 0 ? 'down' : '' }}">{{ brl($mesAtual['sobra'], 0) }}</span>
            <span class="sub">Receitas {{ brl($mesAtual['receitas'], 0) }} · gastos {{ brl($mesAtual['gastos'], 0) }}</span>
        </div>
        <div class="card kpi">
            <span class="label">Taxa de poupança</span>
            <span class="value">{{ pct($mesAtual['taxa_poupanca'], 0) }}</span>
            <span class="sub">{{ $mesAtual['taxa_poupanca'] >= 20 ? 'Acima da meta de 20%' : 'Meta sugerida: 20% da renda' }}</span>
        </div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head">
                <div><h2>Alocação atual × meta</h2><p>Percentual de cada classe na carteira.</p></div>
                <a class="btn sm ghost" href="{{ route('carteira.index') }}">Ajustar metas</a>
            </div>
            <x-legenda :itens="[['s1', 'Atual', true], ['faint', 'Meta', true]]" />
            <div class="chart-box short">
                <canvas data-grafico="alocacao" data-dados='@json($classes)' role="img" aria-label="Alocação atual comparada à meta por classe"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-head">
                <div><h2>Receitas × gastos</h2><p>Últimos 6 meses. Aportes não entram como gasto.</p></div>
                <a class="btn sm ghost" href="{{ route('orcamento.index') }}">Abrir orçamento</a>
            </div>
            <x-legenda :itens="[['s1', 'Receitas', true], ['s2', 'Gastos', true]]" />
            <div class="chart-box short">
                <canvas data-grafico="fluxo" data-dados='@json($historico)' role="img" aria-label="Receitas e gastos dos últimos seis meses"></canvas>
            </div>
        </div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head">
                <div><h2>Onde aportar agora</h2><p>Classes mais abaixo da meta.</p></div>
                <a class="btn sm primary" href="{{ route('aporte.index') }}">Calcular aporte</a>
            </div>
            <div class="stack">
                @forelse ($faltam as $c)
                    @php $dif = $c['meta'] - $c['atual']; @endphp
                    <div class="stack" style="gap:6px">
                        <div class="row between small">
                            <span><span class="dot" style="background:var(--{{ $c['cor'] }})"></span>{{ $c['nome'] }}</span>
                            <span class="num">
                                {{ pct($c['atual']) }} <span class="faint">/ meta {{ pct($c['meta'], 0) }}</span>
                                @if ($dif > 0.5)
                                    <span class="chip warn">faltam {{ pct($dif) }}</span>
                                @else
                                    <span class="chip good"><x-icone nome="check" />ok</span>
                                @endif
                            </span>
                        </div>
                        <div class="meter">
                            <div style="width:{{ min(100, $c['atual']) }}%;background:var(--{{ $c['cor'] }})"></div>
                            <span class="mark" style="left:calc({{ min(100, $c['meta']) }}% - 1px)"></span>
                        </div>
                    </div>
                @empty
                    <p class="muted">Defina metas de alocação na Carteira.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div><h2>Reserva de emergência</h2><p>Meta: {{ $reserva['meses'] }} meses de gastos essenciais do mês atual.</p></div>
            </div>
            <div class="stack">
                <div class="row between">
                    <span class="kpi"><span class="label">Guardado</span><span class="value">{{ brl($reserva['atual'], 0) }}</span></span>
                    <span class="kpi" style="text-align:right"><span class="label">Meta</span><span class="value">{{ brl($reserva['meta'], 0) }}</span></span>
                </div>
                <div class="meter"><div style="width:{{ $reserva['percentual'] }}%"></div></div>
                <div class="row between small">
                    <span class="muted">
                        {{ pct($reserva['percentual'], 0) }} da meta
                        @if ($reserva['meta'] > $reserva['atual']) · faltam {{ brl($reserva['meta'] - $reserva['atual'], 0) }} @endif
                    </span>
                    @if ($reserva['percentual'] >= 100)
                        <span class="chip good"><x-icone nome="check" />Completa</span>
                    @else
                        <span class="chip warn"><x-icone nome="alert" />Em construção</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('reserva.update') }}" class="form-grid">
                    @csrf
                    @method('PUT')
                    <x-campo nome="reserva_atual" rotulo="Valor guardado" pre="R$" :valor="old('reserva_atual', valor_input($reserva['atual']))" />
                    <x-campo nome="reserva_meses" rotulo="Meses de cobertura" suf="meses" :valor="old('reserva_meses', $reserva['meses'])" />
                    <div class="full"><button class="btn" type="submit">Salvar reserva</button></div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
