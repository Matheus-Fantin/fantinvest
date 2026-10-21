@php
    $grupos = config('financas.grupos');
    $categoriasDespesa = config('financas.categorias_despesa');
    $base = $resumo['receitas'] ?: 1;
    $corGrupo = ['ess' => 's1', 'des' => 's2', 'inv' => 's3'];
    $hoje = now()->format('Y-m');
    $categoriasGrafico = collect($resumo['categorias'])->map(fn ($v, $c) => ['nome' => $c, 'valor' => $v, 'grupo' => $categoriasDespesa[$c] ?? 'des'])->values();
    $temCompromissos = collect($compromissos)->sum(fn ($m) => $m['parcelas'] + $m['financiamento']) > 0;

    // Valores iniciais do formulário: depois de criar um cartão/banco, ele já vem selecionado.
    $origem = (string) (session('origem_nova') ?? old('origem', request('origem', '')));
    [$origemTipo, $origemId] = array_pad(explode(':', $origem, 2), 2, '');
    $form = [
        'tipo' => old('tipo', session('origem_nova') ? 'despesa' : $tipo),
        'como' => $origemTipo === 'cartao' ? 'cartao' : 'pix',
        'cartao' => $origemTipo === 'cartao' ? $origemId : (string) ($cartoes->first()?->id ?? ''),
        'banco' => $origemTipo === 'conta' ? $origemId : '',
        'parcelas' => (int) old('parcelas', 1),
        'valor' => old('valor', ''),
        'categoria' => old('categoria'),
    ];
    $abrirForm = session('origem_nova') || $errors->any();
@endphp
<x-app-layout titulo="Lançamentos">
    <div class="page-head">
        <div>
            <h1>Gastos e receitas</h1>
            <p>Lance aqui tudo o que entra e sai. Gasto no cartão em várias vezes? Escolha "Cartão de crédito" e o número de parcelas: cada parcela cai no mês certo.</p>
        </div>
        <div class="month-nav">
            <a class="btn icon" href="{{ route('orcamento.index', ['mes' => $anterior]) }}" aria-label="Mês anterior"><x-icone nome="left" /></a>
            <strong>{{ mes_extenso($mes) }}</strong>
            <a class="btn icon" href="{{ route('orcamento.index', ['mes' => $proximo]) }}" aria-label="Próximo mês"><x-icone nome="right" /></a>
        </div>
    </div>

    <div class="grid g4">
        <div class="card kpi"><span class="label">Entrou</span><span class="value up">{{ brl($resumo['receitas']) }}</span></div>
        <div class="card kpi"><span class="label">Gastou</span><span class="value">{{ brl($resumo['gastos']) }}</span><span class="sub">@if ($resumo['receitas'] > 0){{ pct($resumo['gastos'] / $resumo['receitas'] * 100, 0) }} do que entrou @endif</span></div>
        <div class="card kpi"><span class="label">Investiu</span><span class="value">{{ brl($resumo['inv']) }}</span></div>
        <div class="card kpi"><span class="label">Sobrou</span><span class="value {{ $resumo['sobra'] < 0 ? 'down' : '' }}">{{ brl($resumo['sobra']) }}</span><span class="sub">{{ $resumo['sobra'] < 0 ? 'Gastou mais do que entrou' : 'Pode investir' }}</span></div>
    </div>

    <div class="grid g-side">
        <div class="stack">
            {{-- ============ NOVO LANÇAMENTO ============ --}}
            <form id="novo" class="card stack" method="POST" action="{{ route('orcamento.store') }}" autocomplete="off"
                  x-data="{ ...@js($form),
                            cats: { receita: @js(config('financas.categorias_receita')), despesa: @js(array_keys($categoriasDespesa)) },
                            get origem() {
                                if (this.tipo === 'despesa' && this.como === 'cartao') return this.cartao ? 'cartao:' + this.cartao : '';
                                return this.banco ? 'conta:' + this.banco : '';
                            },
                            get parcelasEnviadas() { return this.tipo === 'despesa' && this.como === 'cartao' ? this.parcelas : 1 } }"
                  x-init="if (!cats[tipo].includes(categoria)) categoria = cats[tipo][0];
                          $watch('tipo', t => { if (!cats[t].includes(categoria)) categoria = cats[t][0] });
                          if (location.hash === '#novo' || @js($abrirForm)) $nextTick(() => { $el.scrollIntoView({ block: 'start' }); $refs.valor?.focus() })">
                @csrf
                <h2>Novo lançamento</h2>
                <input type="hidden" name="tipo" :value="tipo">
                <input type="hidden" name="origem" :value="origem">
                <input type="hidden" name="parcelas" :value="parcelasEnviadas">

                <div class="seg seg-grande" role="group" aria-label="Tipo">
                    <button type="button" x-on:click="tipo = 'despesa'" :aria-pressed="tipo === 'despesa'">Gasto</button>
                    <button type="button" x-on:click="tipo = 'receita'" :aria-pressed="tipo === 'receita'">Receita</button>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="txValor" x-text="tipo === 'despesa' && como === 'cartao' && parcelas > 1 ? 'Valor total da compra' : 'Valor'">Valor</label>
                        <div class="in-group"><span class="affix">R$</span><input class="in @error('valor') invalido @enderror" id="txValor" name="valor" inputmode="decimal" data-moeda x-ref="valor" x-model="valor" placeholder="0,00" required></div>
                        @error('valor')<span class="erro">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="txData">Data</label>
                        <input class="in" type="date" id="txData" name="data" value="{{ old('data', $mes === $hoje ? now()->toDateString() : $mes.'-01') }}" required>
                    </div>
                    <div class="field full">
                        <label for="txCat">Categoria</label>
                        <select class="in" id="txCat" name="categoria" x-model="categoria">
                            <template x-for="c in cats[tipo]" :key="tipo + c"><option :value="c" x-text="c" :selected="c === categoria"></option></template>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="txDesc">Descrição <span class="faint">(opcional)</span></label>
                        <input class="in" id="txDesc" name="descricao" value="{{ old('descricao') }}" placeholder="Ex.: mercado, viagem, salário" maxlength="80">
                    </div>
                </div>

                {{-- Como pagou: só para gastos --}}
                <div class="field" x-show="tipo === 'despesa'">
                    <span class="small muted" style="font-weight:500">Como pagou?</span>
                    <div class="seg" role="group" aria-label="Forma de pagamento">
                        <button type="button" x-on:click="como = 'pix'" :aria-pressed="como === 'pix'">Pix, débito ou dinheiro</button>
                        <button type="button" x-on:click="como = 'cartao'" :aria-pressed="como === 'cartao'">Cartão de crédito</button>
                    </div>
                </div>

                <div class="form-grid" x-show="tipo === 'despesa' && como === 'cartao'" x-cloak>
                    @if ($cartoes->isEmpty())
                        <div class="field full">
                            <p class="small muted">Você ainda não tem cartão cadastrado. Leva 10 segundos: nome, dia que fecha e dia que vence.</p>
                            <button type="button" class="btn" x-on:click="$dispatch('open-modal', 'novo-cartao')"><x-icone nome="plus" />Adicionar cartão</button>
                        </div>
                    @else
                        <div class="field">
                            <label for="txCartao">Qual cartão?</label>
                            <select class="in" id="txCartao" x-model="cartao">
                                @foreach ($cartoes as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach
                            </select>
                            <button type="button" class="link-btn small" x-on:click="$dispatch('open-modal', 'novo-cartao')">+ outro cartão</button>
                        </div>
                        <div class="field">
                            <label for="txParcelas">Em quantas vezes?</label>
                            <div class="in-group"><input class="in" type="number" min="1" max="120" id="txParcelas" x-model.number="parcelas"><span class="affix">×</span></div>
                            <span class="hint" x-show="parcelas > 1" x-text="parcelas + '× de ' + fmt.brl(fmt.parseNum(valor) / parcelas) + ', uma por mês'"></span>
                        </div>
                    @endif
                </div>

                <div class="field" x-show="tipo === 'receita' || como === 'pix'">
                    <label for="txBanco" x-text="tipo === 'receita' ? 'Em qual banco caiu?' : 'De qual banco saiu?'">Banco</label>
                    <select class="in" id="txBanco" x-model="banco">
                        <option value="">Não informar</option>
                        @foreach ($contas as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach
                    </select>
                    <button type="button" class="link-btn small" x-on:click="$dispatch('open-modal', 'novo-banco')">+ adicionar banco</button>
                    @error('account_id')<span class="erro">{{ $message }}</span>@enderror
                </div>

                <button class="btn primary btn-grande" type="submit"><x-icone nome="plus" /><span x-text="tipo === 'despesa' ? 'Lançar gasto' : 'Lançar receita'">Lançar</span></button>
            </form>

            {{-- ============ FATURAS ============ --}}
            @if ($faturas->isNotEmpty())
                <div class="card">
                    <div class="card-head"><div><h2>Faturas do cartão</h2></div><a class="btn sm ghost" href="{{ route('cartoes.index') }}">Ver todas</a></div>
                    @foreach ($faturas as $f)
                        <a class="list-row" href="{{ route('cartoes.show', [$f['cartao'], 'fatura' => $f['atual']['fatura']]) }}">
                            <span><strong>{{ $f['cartao']->nome }}</strong><span class="small faint" style="display:block">{{ mes_extenso($f['atual']['fatura']) }} · vence {{ $f['atual']['vencimento']->format('d/m') }}</span></span>
                            <span style="text-align:right"><strong class="num">{{ brl($f['atual']['total']) }}</strong>
                                @if ($f['em_aberto'] > $f['atual']['falta'] + 0.009)<span class="chip bad" style="display:block;margin-top:2px">pagar {{ brl($f['em_aberto'] - $f['atual']['falta']) }}</span>@endif</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============ LISTA ============ --}}
        <div class="card" x-data="{
                alvo: null,
                edicao: { id: null, tipo: 'despesa', data: '', categoria: '', descricao: '', valor: '', origem: '', parcela: null },
                cats: { receita: @js(config('financas.categorias_receita')), despesa: @js(array_keys($categoriasDespesa)) },
                editar(t) { this.edicao = { ...t }; $dispatch('open-modal', 'editar-lancamento') },
                trocarTipo(tipo) { this.edicao.tipo = tipo; if (!this.cats[tipo].includes(this.edicao.categoria)) this.edicao.categoria = this.cats[tipo][0] },
             }">
            <div class="card-head"><div><h2>Lançamentos de {{ mes_extenso($mes) }}</h2><p>{{ $lancamentos->count() }} no mês</p></div></div>
            @if ($lancamentos->isEmpty())
                <div class="empty">
                    <x-icone nome="receipt" width="28" height="28" />
                    <p>Nada lançado em {{ mes_extenso($mes) }} ainda. Comece pelo salário e pelos gastos fixos, como aluguel e contas.</p>
                </div>
            @else
                <div class="lista-lanc">
                    @foreach ($lancamentos as $t)
                        @php $cor = $t->tipo === 'receita' ? 's1' : ($corGrupo[$t->grupo()] ?? 's2'); @endphp
                        <div class="lanc">
                            <span class="lanc-dia num">{{ $t->data->format('d') }}<small>{{ $t->data->translatedFormat('M') }}</small></span>
                            <span class="lanc-info">
                                <strong>{{ $t->descricao ?: $t->categoria }}@if ($t->rotuloParcela())<span class="badge-parcela">{{ $t->rotuloParcela() }}</span>@endif</strong>
                                <span class="small faint"><span class="tx-type" style="background:var(--{{ $cor }})"></span>{{ $t->categoria }}@if ($t->card) · cartão {{ $t->card->nome }}@elseif ($t->account) · {{ $t->account->nome }}@endif</span>
                            </span>
                            <strong class="lanc-valor num {{ $t->tipo === 'receita' ? 'up' : '' }}">{{ $t->tipo === 'receita' ? '+' : '−' }}{{ brl($t->valor) }}</strong>
                            <span class="row-actions">
                                <button type="button" class="btn icon ghost" aria-label="Editar lançamento"
                                        x-on:click="editar(@js(['id' => $t->id, 'tipo' => $t->tipo, 'data' => $t->data->format('Y-m-d'), 'categoria' => $t->categoria, 'descricao' => $t->descricao, 'valor' => moeda_input($t->valor), 'origem' => $t->card_id ? 'cartao:'.$t->card_id : ($t->account_id ? 'conta:'.$t->account_id : ''), 'parcela' => $t->rotuloParcela()]))"><x-icone nome="edit" /></button>
                                <button type="button" class="btn icon ghost" aria-label="Excluir lançamento"
                                        x-on:click="alvo = @js(['id' => $t->id, 'texto' => ($t->descricao ?: $t->categoria).' · '.brl($t->valor), 'parcelas' => $t->parcelas_total]); $dispatch('open-modal', 'excluir-lancamento')"><x-icone nome="trash" /></button>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            <x-modal name="editar-lancamento">
                <form method="POST" :action="'{{ url('orcamento') }}/' + edicao.id" class="stack" autocomplete="off">
                    @csrf
                    @method('PUT')
                    <div class="row between">
                        <h2>Editar lançamento</h2>
                        <button type="button" class="btn icon ghost" x-on:click="$dispatch('close-modal', 'editar-lancamento')" aria-label="Fechar"><x-icone nome="x" /></button>
                    </div>
                    <input type="hidden" name="tipo" :value="edicao.tipo">
                    <div class="seg" role="group" aria-label="Tipo">
                        <button type="button" x-on:click="trocarTipo('despesa')" :aria-pressed="edicao.tipo === 'despesa'">Gasto</button>
                        <button type="button" x-on:click="trocarTipo('receita')" :aria-pressed="edicao.tipo === 'receita'">Receita</button>
                    </div>
                    <div class="form-grid">
                        <div class="field"><label for="eData">Data</label><input class="in" type="date" id="eData" name="data" x-model="edicao.data" required></div>
                        <div class="field"><label for="eValor">Valor</label><div class="in-group"><span class="affix">R$</span><input class="in" data-moeda id="eValor" name="valor" inputmode="decimal" x-model="edicao.valor" required></div></div>
                        <div class="field full">
                            <label for="eCat">Categoria</label>
                            <select class="in" id="eCat" name="categoria" x-model="edicao.categoria">
                                <template x-for="c in cats[edicao.tipo]" :key="edicao.tipo + c"><option :value="c" x-text="c" :selected="c === edicao.categoria"></option></template>
                            </select>
                        </div>
                        <div class="field full"><label for="eDesc">Descrição</label><input class="in" id="eDesc" name="descricao" x-model="edicao.descricao" maxlength="80"></div>
                        <x-select-origem :contas="$contas" :cartoes="$cartoes" id="eOrigem" rotulo="Banco ou cartão" x-model="edicao.origem" />
                    </div>
                    <p class="note" x-show="edicao.parcela" x-text="'Esta é a parcela ' + edicao.parcela + '. A alteração vale só para ela.'"></p>
                    <div class="row" style="justify-content:flex-end">
                        <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'editar-lancamento')">Cancelar</button>
                        <button class="btn primary" type="submit">Salvar</button>
                    </div>
                </form>
            </x-modal>

            <x-modal name="excluir-lancamento">
                <form method="POST" :action="'{{ url('orcamento') }}/' + alvo?.id" class="stack">
                    @csrf
                    @method('DELETE')
                    <h2>Excluir lançamento?</h2>
                    <p class="muted small" x-text="alvo?.texto"></p>
                    <label class="check" x-show="alvo?.parcelas > 1"><input type="checkbox" name="todas" value="1"><span x-text="'Excluir todas as ' + alvo?.parcelas + ' parcelas desta compra'"></span></label>
                    <div class="row" style="justify-content:flex-end">
                        <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'excluir-lancamento')">Cancelar</button>
                        <button class="btn danger" type="submit">Excluir</button>
                    </div>
                </form>
            </x-modal>
        </div>
    </div>

    {{-- ============ RESUMOS ============ --}}
    <div class="card">
        <div class="card-head">
            <div><h2>Já comprometido nos próximos meses</h2><p>Parcelas de compras e do financiamento que ainda vão cair.</p></div>
            @if ($temCompromissos)<span class="chip warn">{{ brl(collect($compromissos)->sum(fn ($m) => $m['parcelas'] + $m['financiamento'])) }} em 12 meses</span>@endif
        </div>
        @if ($temCompromissos)
            <x-legenda :itens="[['s2', 'Parcelas de compras', true], ['s1', 'Financiamento', true]]" />
            <div class="chart-box short"><canvas data-grafico="compromissos" data-dados='@json($compromissos)' role="img" aria-label="Valores já comprometidos por mês"></canvas></div>
        @else
            <div class="empty"><p>Nada comprometido. Quando você lançar uma compra parcelada, as próximas parcelas aparecem aqui.</p></div>
        @endif
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>Como está o mês</h2><p>Regra 50/30/20: até 50% da renda no essencial, até 30% no estilo de vida e pelo menos 20% investindo.</p></div></div>
            <div class="stack">
                @foreach ($grupos as $g => $info)
                    @php
                        $v = $resumo[$g] / $base * 100;
                        $ok = $g === 'inv' ? $v >= $info['alvo'] : $v <= $info['alvo'];
                        $perto = $g === 'inv' ? $v >= $info['alvo'] * 0.75 : $v <= $info['alvo'] * 1.15;
                        $estado = $ok ? 'good' : ($perto ? 'warn' : 'bad');
                    @endphp
                    <div class="stack" style="gap:6px">
                        <div class="row between small">
                            <span><strong>{{ $info['nome'] }}</strong> <span class="faint">{{ $g === 'inv' ? 'mínimo' : 'máximo' }} {{ $info['alvo'] }}%</span></span>
                            <span class="num">
                                {{ brl($resumo[$g]) }} · {{ pct($v, 0) }}
                                <span class="chip {{ $estado }}">
                                    @if ($estado === 'good')<x-icone nome="check" />ok
                                    @elseif ($estado === 'warn')<x-icone nome="alert" />perto
                                    @else<x-icone nome="alert" />{{ $g === 'inv' ? 'abaixo' : 'acima' }}@endif
                                </span>
                            </span>
                        </div>
                        <div class="meter">
                            <div style="width:{{ min(100, $v) }}%;background:var(--{{ $corGrupo[$g] }})"></div>
                            <span class="mark" style="left:calc({{ $info['alvo'] }}% - 1px)"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <div class="card-head"><div><h2>Onde foi o dinheiro</h2><p>Gastos por categoria, sem investimentos.</p></div></div>
            @if ($categoriasGrafico->isEmpty())
                <div class="empty"><p>Sem gastos neste mês.</p></div>
            @else
                <x-legenda :itens="[['s1', 'Essenciais', true], ['s2', 'Estilo de vida', true]]" />
                <div class="chart-box" style="height:{{ max(200, $categoriasGrafico->count() * 30) }}px">
                    <canvas data-grafico="categorias" data-dados='@json(['itens' => $categoriasGrafico, 'receitas' => $resumo['receitas']])' role="img" aria-label="Gastos por categoria"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Cadastro rápido de cartão e banco, sem sair da página --}}
    <x-modal name="novo-cartao" titulo="Adicionar cartão">
        <form method="POST" action="{{ route('cartoes.store') }}" class="stack" autocomplete="off">
            @csrf
            <div class="form-grid">
                <div class="field full"><label for="ncNome">Nome do cartão</label><input class="in" id="ncNome" name="nome" placeholder="Ex.: Nubank" maxlength="40" required></div>
                <div class="field"><label for="ncFech">Dia que fecha</label><input class="in" id="ncFech" name="dia_fechamento" type="number" min="1" max="31" value="1" required></div>
                <div class="field"><label for="ncVenc">Dia que vence</label><input class="in" id="ncVenc" name="dia_vencimento" type="number" min="1" max="31" value="8" required></div>
                <x-campo nome="limite" rotulo="Limite (opcional)" id="ncLimite" pre="R$" classe="full" />
            </div>
            <p class="note">O dia de fechamento está no app do banco. Compras a partir desse dia vão para a fatura do mês seguinte.</p>
            <button class="btn primary" type="submit">Adicionar cartão</button>
        </form>
    </x-modal>

    <x-modal name="novo-banco" titulo="Adicionar banco">
        <form method="POST" action="{{ route('contas.store') }}" class="stack" autocomplete="off">
            @csrf
            <div class="form-grid">
                <div class="field full"><label for="nbNome">Nome</label><input class="in" id="nbNome" name="nome" placeholder="Ex.: Inter, Caixa, Itaú" maxlength="40" required></div>
                <div class="field"><label for="nbTipo">Tipo</label>
                    <select class="in" id="nbTipo" name="tipo">@foreach (\App\Models\Account::TIPOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <x-campo nome="saldo_inicial" rotulo="Saldo de hoje (opcional)" id="nbSaldo" pre="R$" valor="0,00" />
            </div>
            <button class="btn primary" type="submit">Adicionar banco</button>
        </form>
    </x-modal>
</x-app-layout>
