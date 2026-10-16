@php
    $grupos = config('financas.grupos');
    $categoriasDespesa = config('financas.categorias_despesa');
    $base = $resumo['receitas'] ?: 1;
    $corGrupo = ['ess' => 's1', 'des' => 's2', 'inv' => 's3'];
    $hoje = now()->format('Y-m');
    $origemPadrao = old('origem', request('origem', ''));
    $temCompromissos = collect($compromissos)->sum(fn ($m) => $m['parcelas'] + $m['financiamento']) > 0;
    $categoriasGrafico = collect($resumo['categorias'])->map(fn ($v, $c) => ['nome' => $c, 'valor' => $v, 'grupo' => $categoriasDespesa[$c] ?? 'des'])->values();
@endphp
<x-app-layout titulo="Orçamento">
    <div class="page-head">
        <div>
            <div class="eyebrow">Orçamento</div>
            <h1>Controle do mês</h1>
            <p>Lance receitas e gastos. A regra 50/30/20 compara seus gastos com a renda: 50% essenciais, 30% estilo de vida, 20% investimentos.</p>
        </div>
        <div class="row">
        <a class="btn" href="{{ route('orcamento.importar') }}"><x-icone nome="upload" />Importar extrato</a>
        <div class="month-nav">
            <a class="btn icon" href="{{ route('orcamento.index', ['mes' => $anterior]) }}" aria-label="Mês anterior"><x-icone nome="left" /></a>
            <strong>{{ mes_extenso($mes) }}</strong>
            <a class="btn icon" href="{{ route('orcamento.index', ['mes' => $proximo]) }}" aria-label="Próximo mês"><x-icone nome="right" /></a>
        </div>
        </div>
    </div>

    <div class="grid g4">
        <div class="card kpi"><span class="label">Receitas</span><span class="value">{{ brl($resumo['receitas']) }}</span></div>
        <div class="card kpi"><span class="label">Gastos</span><span class="value">{{ brl($resumo['gastos']) }}</span><span class="sub">@if ($resumo['receitas'] > 0){{ pct($resumo['gastos'] / $resumo['receitas'] * 100, 0) }} da renda @endif</span></div>
        <div class="card kpi"><span class="label">Investido</span><span class="value">{{ brl($resumo['inv']) }}</span><span class="sub">@if ($resumo['receitas'] > 0){{ pct($resumo['inv'] / $resumo['receitas'] * 100, 0) }} da renda @endif</span></div>
        <div class="card kpi"><span class="label">Sobra</span><span class="value {{ $resumo['sobra'] < 0 ? 'down' : '' }}">{{ brl($resumo['sobra']) }}</span><span class="sub">{{ $resumo['sobra'] < 0 ? 'Gastou mais do que recebeu' : 'Disponível para investir' }}</span></div>
    </div>

    <div class="grid g2">
        <div class="card">
            <div class="card-head"><div><h2>Regra 50/30/20</h2><p>Percentual da renda do mês.</p></div></div>
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
            <div class="card-head"><div><h2>Gastos por categoria</h2><p>Sem contar investimentos.</p></div></div>
            @if ($categoriasGrafico->isEmpty())
                <div class="empty"><p>Sem gastos neste mês.</p></div>
            @else
                <x-legenda :itens="[['s1', 'Essenciais', true], ['s2', 'Estilo de vida', true]]" />
                <div class="chart-box short">
                    <canvas data-grafico="categorias" data-dados='@json(['itens' => $categoriasGrafico, 'receitas' => $resumo['receitas']])' role="img" aria-label="Gastos por categoria"></canvas>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div><h2>Já comprometido nos próximos meses</h2><p>Parcelas de compras (cartão e boleto) e do financiamento que ainda vão cair. É dinheiro da renda futura que já tem destino.</p></div>
            @if ($temCompromissos)<span class="chip warn">{{ brl(collect($compromissos)->sum(fn ($m) => $m['parcelas'] + $m['financiamento'])) }} em 12 meses</span>@endif
        </div>
        @if ($temCompromissos)
            <x-legenda :itens="[['s2', 'Parcelas de compras', true], ['s1', 'Financiamento', true]]" />
            <div class="chart-box short"><canvas data-grafico="compromissos" data-dados='@json($compromissos)' role="img" aria-label="Valores já comprometidos por mês"></canvas></div>
        @else
            <div class="empty"><p>Nada comprometido. Ao lançar uma compra parcelada ou cadastrar seu financiamento, as parcelas futuras aparecem aqui.</p></div>
        @endif
    </div>

    <div class="grid g-side">
        <form class="card stack" method="POST" action="{{ route('orcamento.store') }}" autocomplete="off"
              x-data="{ tipo: @js(old('tipo', $tipo)), cats: { receita: @js(config('financas.categorias_receita')), despesa: @js(array_keys($categoriasDespesa)) }, categoria: @js(old('categoria')),
                        origem: @js($origemPadrao), parcelas: @js((int) old('parcelas', 1)), valor: @js(old('valor', '')) }"
              x-init="if (!cats[tipo].includes(categoria)) categoria = cats[tipo][0]; $watch('tipo', t => { if (!cats[t].includes(categoria)) categoria = cats[t][0] })">
            @csrf
            <h2>Novo lançamento</h2>
            <input type="hidden" name="tipo" :value="tipo">
            <div class="seg" role="group" aria-label="Tipo">
                <button type="button" x-on:click="tipo = 'despesa'" :aria-pressed="tipo === 'despesa'">Gasto</button>
                <button type="button" x-on:click="tipo = 'receita'" :aria-pressed="tipo === 'receita'">Receita</button>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="txData">Data</label>
                    <input class="in @error('data') invalido @enderror" type="date" id="txData" name="data" value="{{ old('data', $mes === $hoje ? now()->toDateString() : $mes.'-01') }}" required>
                    @error('data')<span class="erro">{{ $message }}</span>@enderror
                </div>
                <x-campo nome="valor" rotulo="Valor" pre="R$" :valor="old('valor')" id="txValor" x-model="valor" required />
                <x-select-origem :contas="$contas" :cartoes="$cartoes" id="txOrigem" rotulo="Conta ou cartão" x-model="origem" />
                <div class="field" x-show="tipo === 'despesa'">
                    <label for="txParcelas">Parcelas</label>
                    <div class="in-group"><input class="in" type="number" min="1" max="120" id="txParcelas" name="parcelas" x-model.number="parcelas"><span class="affix">×</span></div>
                    <span class="hint" x-show="parcelas > 1" x-text="'O valor é o total da compra: ' + parcelas + '× de ' + fmt.brl(fmt.parseNum(valor) / parcelas)"></span>
                    @error('parcelas')<span class="erro">{{ $message }}</span>@enderror
                </div>
                <div class="field full">
                    <label for="txCat">Categoria</label>
                    <select class="in" id="txCat" name="categoria" x-model="categoria">
                        <template x-for="c in cats[tipo]" :key="tipo + c"><option :value="c" x-text="c" :selected="c === categoria"></option></template>
                    </select>
                    @error('categoria')<span class="erro">{{ $message }}</span>@enderror
                </div>
                <div class="field full">
                    <label for="txDesc">Descrição</label>
                    <input class="in" id="txDesc" name="descricao" value="{{ old('descricao') }}" placeholder="Ex.: conta de luz" maxlength="80">
                </div>
            </div>
            <button class="btn primary" type="submit"><x-icone nome="plus" />Lançar</button>
        </form>

        <div class="card" x-data="{
                alvo: null,
                edicao: { id: null, tipo: 'despesa', data: '', categoria: '', descricao: '', valor: '', origem: '', parcela: null },
                cats: { receita: @js(config('financas.categorias_receita')), despesa: @js(array_keys($categoriasDespesa)) },
                editar(t) { this.edicao = { ...t }; $dispatch('open-modal', 'editar-lancamento') },
                trocarTipo(tipo) { this.edicao.tipo = tipo; if (!this.cats[tipo].includes(this.edicao.categoria)) this.edicao.categoria = this.cats[tipo][0] },
             }">
            <div class="card-head"><div><h2>Lançamentos</h2><p>{{ $lancamentos->count() }} no mês</p></div></div>
            @if ($lancamentos->isEmpty())
                <div class="empty">
                    <x-icone nome="receipt" width="28" height="28" />
                    <p>Nenhum lançamento em {{ mes_extenso($mes) }}. Use o formulário ao lado para lançar o primeiro.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Conta / cartão</th><th class="n">Valor</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($lancamentos as $t)
                                @php $cor = $t->tipo === 'receita' ? 's1' : ($corGrupo[$t->grupo()] ?? 's2'); @endphp
                                <tr>
                                    <td class="num faint">{{ $t->data->format('d/m') }}</td>
                                    <td>{{ $t->descricao ?: $t->categoria }}@if ($t->rotuloParcela())<span class="badge-parcela">{{ $t->rotuloParcela() }}</span>@endif</td>
                                    <td><span class="tx-type" style="background:var(--{{ $cor }})"></span>{{ $t->categoria }}</td>
                                    <td class="origem">
                                        @if ($t->card)
                                            <a class="link" href="{{ route('cartoes.show', [$t->card, 'fatura' => $t->fatura]) }}">Cartão {{ $t->card->nome }}</a>
                                        @else
                                            {{ $t->account?->nome ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="n {{ $t->tipo === 'receita' ? 'up' : '' }}"><strong>{{ $t->tipo === 'receita' ? '+' : '−' }}{{ brl($t->valor) }}</strong></td>
                                    <td>
                                        <div class="row-actions">
                                            <button type="button" class="btn icon ghost" aria-label="Editar lançamento"
                                                    x-on:click="editar(@js(['id' => $t->id, 'tipo' => $t->tipo, 'data' => $t->data->format('Y-m-d'), 'categoria' => $t->categoria, 'descricao' => $t->descricao, 'valor' => moeda_input($t->valor), 'origem' => $t->card_id ? 'cartao:'.$t->card_id : ($t->account_id ? 'conta:'.$t->account_id : ''), 'parcela' => $t->rotuloParcela()]))">
                                                <x-icone nome="edit" />
                                            </button>
                                            <button type="button" class="btn icon ghost" aria-label="Excluir lançamento"
                                                    x-on:click="alvo = @js(['id' => $t->id, 'texto' => ($t->descricao ?: $t->categoria).' · '.brl($t->valor), 'parcelas' => $t->parcelas_total]); $dispatch('open-modal', 'excluir-lancamento')">
                                                <x-icone nome="trash" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
                        <x-select-origem :contas="$contas" :cartoes="$cartoes" id="eOrigem" x-model="edicao.origem" />
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
</x-app-layout>
