@php
    $totalCompromissos = collect($compromissos)->sum(fn ($m) => $m['parcelas'] + $m['financiamento']);
    $faltaPasso = in_array(false, $passos, true);
    $gastoPct = $mesAtual['receitas'] > 0 ? min(100, ($mesAtual['gastos'] + $mesAtual['inv']) / $mesAtual['receitas'] * 100) : 0;
    $proximaClasse = $faltam->first(fn ($c) => $c['meta'] - $c['atual'] > 0.5);
@endphp
<x-app-layout titulo="Início">
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ mes_extenso($mes) }}</div>
            <h1>Olá, {{ $primeiroNome }}</h1>
            <p>Seu resumo do mês. Para lançar um gasto ou uma receita, toque em "Lançar".</p>
        </div>
        <div class="row">
            <a class="btn" href="{{ route('orcamento.index', ['tipo' => 'receita']) }}#novo"><x-icone nome="plus" />Receita</a>
            <a class="btn primary" href="{{ route('orcamento.index') }}#novo"><x-icone nome="plus" />Lançar gasto</a>
        </div>
    </div>

    @if ($faltaPasso)
        <div class="card">
            <div class="card-head"><div><h2>Primeiros passos</h2><p>Três coisas para o FantInvest começar a trabalhar por você.</p></div></div>
            <div class="passos">
                @foreach ([
                    ['lancamento', 'Lance seus gastos e receitas', 'Salário, aluguel, mercado, compras no cartão em X vezes.', route('orcamento.index').'#novo', 'Lançar'],
                    ['investimento', 'Cadastre seus investimentos', 'Ações, FIIs, Tesouro, cripto. As cotações atualizam sozinhas.', route('carteira.index'), 'Cadastrar'],
                    ['objetivo', 'Crie um objetivo', 'Viagem, carro, casa: veja quanto guardar por mês.', route('objetivos.index'), 'Criar'],
                ] as $i => [$chave, $titulo, $texto, $link, $botao])
                    <div class="passo {{ $passos[$chave] ? 'feito' : '' }}">
                        <span class="passo-num">@if ($passos[$chave])<x-icone nome="check" />@else{{ $i + 1 }}@endif</span>
                        <span class="passo-texto"><strong>{{ $titulo }}</strong><span class="small muted">{{ $texto }}</span></span>
                        @unless ($passos[$chave])<a class="btn sm primary" href="{{ $link }}">{{ $botao }}</a>@endunless
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Este mês --}}
    <div class="card">
        <div class="card-head"><div><h2>Este mês</h2></div><a class="btn sm ghost" href="{{ route('orcamento.index') }}">Ver lançamentos</a></div>
        <div class="grid g3">
            <div class="kpi"><span class="label">Entrou</span><span class="value up">{{ brl($mesAtual['receitas']) }}</span></div>
            <div class="kpi"><span class="label">Gastou e investiu</span><span class="value">{{ brl($mesAtual['gastos'] + $mesAtual['inv']) }}</span><span class="sub">{{ brl($mesAtual['inv']) }} foi para investimentos</span></div>
            <div class="kpi"><span class="label">Sobrou</span><span class="value {{ $mesAtual['sobra'] < 0 ? 'down' : '' }}">{{ brl($mesAtual['sobra']) }}</span>
                <span class="sub">{{ $mesAtual['sobra'] < 0 ? 'Gastou mais do que entrou' : 'Guardou '.pct($mesAtual['taxa_poupanca'], 0).' do que entrou' }}</span></div>
        </div>
        @if ($mesAtual['receitas'] > 0)
            <div class="meter" style="margin-top:14px;height:10px"><div style="width:{{ $gastoPct }}%;background:var({{ $gastoPct >= 100 ? '--bad' : ($gastoPct > 85 ? '--warn' : '--accent') }})"></div></div>
            <p class="note" style="margin-top:6px">{{ pct($gastoPct, 0) }} do que entrou já tem destino.</p>
        @endif
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>Cartões</h2><p>Fatura que está aberta agora.</p></div><a class="btn sm ghost" href="{{ route('cartoes.index') }}">Faturas</a></div>
            @if ($cartoes->isEmpty())
                <div class="empty"><p>Usa cartão de crédito? Ao lançar um gasto, escolha "Cartão de crédito" e cadastre o cartão ali mesmo.</p></div>
            @else
                @foreach ($cartoes as $k)
                    <a class="list-row" href="{{ route('cartoes.show', $k['cartao']) }}">
                        <span><strong>{{ $k['cartao']->nome }}</strong><span class="small faint" style="display:block">{{ mes_extenso($k['atual']['fatura']) }} · vence {{ $k['atual']['vencimento']->format('d/m') }}</span></span>
                        <strong class="num">{{ brl($k['atual']['total']) }}</strong>
                    </a>
                @endforeach
            @endif
        </div>
        <div class="card">
            <div class="card-head">
                <div><h2>Já comprometido</h2><p>Parcelas e financiamento dos próximos 12 meses.</p></div>
                @if ($totalCompromissos > 0)<span class="chip warn">{{ brl($totalCompromissos) }}</span>@endif
            </div>
            @if ($totalCompromissos > 0)
                <x-legenda :itens="[['s2', 'Parcelas de compras', true], ['s1', 'Financiamento', true]]" />
                <div class="chart-box short"><canvas data-grafico="compromissos" data-dados='@json($compromissos)' role="img" aria-label="Valores já comprometidos por mês"></canvas></div>
            @else
                <div class="empty"><p>Nenhuma parcela futura. Compras parceladas aparecem aqui.</p></div>
            @endif
        </div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>Seus investimentos</h2></div><a class="btn sm ghost" href="{{ route('carteira.index') }}">Ver carteira</a></div>
            <div class="row between" style="align-items:flex-end">
                <span class="kpi"><span class="label">Total investido</span><span class="value">{{ brl($resumo['total']) }}</span></span>
                <span class="kpi" style="text-align:right"><span class="label">Resultado</span>
                    <span class="value {{ $resumo['resultado'] >= 0 ? 'up' : 'down' }}">{{ $resumo['resultado'] >= 0 ? '+' : '' }}{{ brl($resumo['resultado']) }}</span>
                    @if ($resumo['custo'] > 0)<span class="sub">{{ $resumo['resultado'] >= 0 ? '+' : '' }}{{ pct($resumo['resultado'] / $resumo['custo'] * 100) }}</span>@endif</span>
            </div>
            <x-cotacoes-status />
            @if ($proximaClasse)
                <div class="banner" style="margin-top:12px"><span class="small">Próximo aporte: <strong>{{ $proximaClasse['nome'] }}</strong> está {{ pct($proximaClasse['meta'] - $proximaClasse['atual']) }} abaixo da meta.</span><a class="btn sm primary" href="{{ route('aporte.index') }}">Quanto comprar</a></div>
            @endif
            <div class="row" style="margin-top:12px"><a class="btn sm" href="{{ route('bolsa.index') }}"><x-icone nome="candles" />Ver a bolsa hoje</a></div>
        </div>
        <div class="card">
            <div class="card-head"><div><h2>Entrou × saiu</h2><p>Últimos 6 meses.</p></div></div>
            <x-legenda :itens="[['s1', 'Receitas', true], ['s2', 'Gastos', true]]" />
            <div class="chart-box short"><canvas data-grafico="fluxo" data-dados='@json($historico)' role="img" aria-label="Receitas e gastos dos últimos seis meses"></canvas></div>
        </div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>Objetivos</h2></div><a class="btn sm ghost" href="{{ route('objetivos.index') }}">Ver todos</a></div>
            @if ($objetivos->isEmpty())
                <div class="empty"><p>Crie um objetivo (viagem, carro, casa) e veja quanto guardar por mês.</p><a class="btn sm primary" href="{{ route('objetivos.index') }}"><x-icone nome="plus" />Criar objetivo</a></div>
            @else
                <div class="stack">
                    @foreach ($objetivos as $g)
                        @php $mensal = $g->aporteMensalNecessario(); @endphp
                        <div class="stack" style="gap:6px">
                            <div class="row between small"><strong>{{ $g->nome }}</strong><span class="num">{{ brl($g->valor_atual) }} <span class="faint">/ {{ brl($g->valor_alvo) }}</span></span></div>
                            <div class="meter"><div style="width:{{ $g->percentual() }}%"></div></div>
                            <span class="small muted">@if ($g->concluido()) Concluído @elseif ($mensal !== null) Guardar {{ brl($mensal) }}/mês até {{ $g->data_alvo->translatedFormat('M/Y') }} @else Sem data definida @endif</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><div><h2>Reserva de emergência</h2><p>{{ $reserva['meses'] }} meses dos seus gastos essenciais.</p></div></div>
            <div class="stack">
                <div class="row between">
                    <span class="kpi"><span class="label">Guardado</span><span class="value">{{ brl($reserva['atual']) }}</span></span>
                    <span class="kpi" style="text-align:right"><span class="label">Meta</span><span class="value">{{ brl($reserva['meta']) }}</span></span>
                </div>
                <div class="meter"><div style="width:{{ $reserva['percentual'] }}%"></div></div>
                <span class="small muted">{{ pct($reserva['percentual'], 0) }} da meta @if ($reserva['meta'] > $reserva['atual']) · faltam {{ brl($reserva['meta'] - $reserva['atual']) }} @endif</span>
                <details>
                    <summary>Atualizar valor guardado</summary>
                    <form method="POST" action="{{ route('reserva.update') }}" class="form-grid" style="margin-top:10px">
                        @csrf
                        @method('PUT')
                        <x-campo nome="reserva_atual" rotulo="Valor guardado" pre="R$" :valor="old('reserva_atual', moeda_input($reserva['atual']))" />
                        <x-campo nome="reserva_meses" rotulo="Meses de cobertura" suf="meses" :valor="old('reserva_meses', $reserva['meses'])" />
                        <div class="full"><button class="btn" type="submit">Salvar</button></div>
                    </form>
                </details>
            </div>
        </div>
    </div>
</x-app-layout>
