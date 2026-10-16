<x-app-layout titulo="Visão geral">
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ mes_extenso($mes) }}</div>
            <h1>Visão geral</h1>
            <p>Seu patrimônio, o mês corrente e para onde vai o próximo aporte.</p>
        </div>
    </div>


    <x-cotacoes-status />

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
            <span class="value">{{ brl($resumo['total']) }}</span>
            <span class="sub">{{ $quantidadeAtivos }} {{ $quantidadeAtivos === 1 ? 'ativo' : 'ativos' }} na carteira</span>
        </div>
        <div class="card kpi">
            <span class="label">Resultado da carteira</span>
            <span class="value {{ $resumo['resultado'] >= 0 ? 'up' : 'down' }}">{{ $resumo['resultado'] >= 0 ? '+' : '' }}{{ brl($resumo['resultado']) }}</span>
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
            <span class="value {{ $mesAtual['sobra'] < 0 ? 'down' : '' }}">{{ brl($mesAtual['sobra']) }}</span>
            <span class="sub">Receitas {{ brl($mesAtual['receitas']) }} · gastos {{ brl($mesAtual['gastos']) }}</span>
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

    @php $totalCompromissos = collect($compromissos)->sum(fn ($m) => $m['parcelas'] + $m['financiamento']); @endphp
    <div class="grid g2">
        <div class="card">
            <div class="card-head">
                <div><h2>Contas e cartões</h2><p>Saldo de hoje e faturas do mês.</p></div>
                <a class="btn sm ghost" href="{{ route('contas.index') }}">Ver contas</a>
            </div>
            @if ($contas->isEmpty() && $cartoes->isEmpty())
                <div class="empty"><p>Cadastre suas contas e cartões para ver o saldo e as faturas aqui.</p><a class="btn sm primary" href="{{ route('contas.index') }}"><x-icone nome="plus" />Cadastrar conta</a></div>
            @else
                @foreach ($contas as $c)
                    <div class="list-row"><span><strong>{{ $c->nome }}</strong> <span class="small faint">{{ $c->nomeTipo() }}</span></span><strong class="num {{ $c->saldo < 0 ? 'down' : '' }}">{{ brl($c->saldo) }}</strong></div>
                @endforeach
                @foreach ($cartoes as $k)
                    <div class="list-row">
                        <span><a class="link" href="{{ route('cartoes.show', $k['cartao']) }}">Cartão {{ $k['cartao']->nome }}</a> <span class="small faint">fatura de {{ mes_extenso($k['atual']['fatura']) }}, vence {{ $k['atual']['vencimento']->format('d/m') }}</span></span>
                        <strong class="num down">{{ brl($k['atual']['total']) }}</strong>
                    </div>
                @endforeach
                <div class="list-row"><span class="muted">Saldo em contas − faturas em aberto</span><strong class="num">{{ brl($contas->sum('saldo') - $cartoes->sum('em_aberto')) }}</strong></div>
            @endif
        </div>
        <div class="card">
            <div class="card-head">
                <div><h2>Já comprometido</h2><p>Parcelas e financiamento nos próximos 12 meses.</p></div>
                @if ($totalCompromissos > 0)<span class="chip warn">{{ brl($totalCompromissos) }}</span>@endif
            </div>
            @if ($totalCompromissos > 0)
                <x-legenda :itens="[['s2', 'Parcelas de compras', true], ['s1', 'Financiamento', true]]" />
                <div class="chart-box short"><canvas data-grafico="compromissos" data-dados='@json($compromissos)' role="img" aria-label="Valores já comprometidos por mês"></canvas></div>
            @else
                <div class="empty"><p>Nenhuma parcela futura. Compras parceladas e o financiamento aparecem aqui.</p></div>
            @endif
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
                    <span class="kpi"><span class="label">Guardado</span><span class="value">{{ brl($reserva['atual']) }}</span></span>
                    <span class="kpi" style="text-align:right"><span class="label">Meta</span><span class="value">{{ brl($reserva['meta']) }}</span></span>
                </div>
                <div class="meter"><div style="width:{{ $reserva['percentual'] }}%"></div></div>
                <div class="row between small">
                    <span class="muted">
                        {{ pct($reserva['percentual'], 0) }} da meta
                        @if ($reserva['meta'] > $reserva['atual']) · faltam {{ brl($reserva['meta'] - $reserva['atual']) }} @endif
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
                    <x-campo nome="reserva_atual" rotulo="Valor guardado" pre="R$" :valor="old('reserva_atual', moeda_input($reserva['atual']))" />
                    <x-campo nome="reserva_meses" rotulo="Meses de cobertura" suf="meses" :valor="old('reserva_meses', $reserva['meses'])" />
                    <div class="full"><button class="btn" type="submit">Salvar reserva</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div><h2>Objetivos</h2><p>Quanto falta e quanto guardar por mês.</p></div>
            <a class="btn sm ghost" href="{{ route('objetivos.index') }}">Ver objetivos</a>
        </div>
        @if ($objetivos->isEmpty())
            <div class="empty"><p>Crie objetivos como viagem, carro ou troca do celular para acompanhar aqui.</p><a class="btn sm primary" href="{{ route('objetivos.index') }}"><x-icone nome="plus" />Criar objetivo</a></div>
        @else
            <div class="grid g2">
                @foreach ($objetivos as $g)
                    @php $mensal = $g->aporteMensalNecessario(); @endphp
                    <div class="stack" style="gap:6px">
                        <div class="row between small">
                            <strong>{{ $g->nome }}</strong>
                            <span class="num">{{ brl($g->valor_atual) }} <span class="faint">/ {{ brl($g->valor_alvo) }}</span></span>
                        </div>
                        <div class="meter"><div style="width:{{ $g->percentual() }}%"></div></div>
                        <span class="small muted">
                            @if ($g->concluido()) Concluído
                            @elseif ($mensal !== null) Guardar {{ brl($mensal) }}/mês até {{ $g->data_alvo->translatedFormat('M/Y') }}
                            @else Sem data definida @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
