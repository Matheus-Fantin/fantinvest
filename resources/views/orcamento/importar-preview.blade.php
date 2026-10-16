@php
    $receitas = config('financas.categorias_receita');
    $despesas = array_keys(config('financas.categorias_despesa'));
@endphp
<x-app-layout titulo="Revisar importação">
    <form method="POST" action="{{ route('orcamento.importar.store') }}" class="stack" style="gap:24px"
          x-data="{ linhas: @js($linhas), cats: { receita: @js($receitas), despesa: @js($despesas) },
                    get marcadas() { return this.linhas.filter(l => l.incluir) },
                    get total() { return this.marcadas.reduce((s, l) => s + (l.tipo === 'receita' ? 1 : -1) * Number(l.valor), 0) },
                    todas(v) { this.linhas.forEach(l => l.incluir = v) } }">
        @csrf
        <div class="page-head">
            <div>
                <div class="eyebrow">Importar extrato</div>
                <h1>Revise antes de salvar</h1>
                <p>Ajuste a categoria das linhas e desmarque o que não deve entrar no orçamento.</p>
            </div>
            <div class="row">
                <a class="btn" href="{{ route('orcamento.importar') }}">Escolher outro arquivo</a>
                <button class="btn primary" type="submit" :disabled="marcadas.length === 0"><x-icone nome="check" /><span x-text="'Importar ' + marcadas.length + (marcadas.length === 1 ? ' lançamento' : ' lançamentos')"></span></button>
            </div>
        </div>

        @if ($errors->any())
            <div class="banner warn"><span>{{ $errors->first() }}</span></div>
        @endif

        <div class="grid g3">
            <div class="card kpi"><span class="label">Linhas lidas</span><span class="value">{{ count($linhas) }}</span></div>
            <div class="card kpi"><span class="label">Selecionadas</span><span class="value" x-text="marcadas.length"></span></div>
            <div class="card kpi"><span class="label">Saldo selecionado</span><span class="value" :class="total < 0 ? 'down' : 'up'" x-text="fmt.brl(total)"></span></div>
        </div>

        <div class="card">
            <div class="card-head">
                <div><h2>Lançamentos</h2><p>As categorias foram sugeridas pela descrição.</p></div>
                <div class="row"><button type="button" class="btn sm ghost" x-on:click="todas(true)">Marcar todas</button><button type="button" class="btn sm ghost" x-on:click="todas(false)">Desmarcar todas</button></div>
            </div>
            <div class="table-wrap">
                <table class="import-table">
                    <thead><tr><th></th><th>Data</th><th>Descrição</th><th>Tipo</th><th>Categoria</th><th class="n">Valor</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(l, i) in linhas" :key="i">
                            <tr :class="{ off: !l.incluir }">
                                <td>
                                    <input type="checkbox" value="1" x-model="l.incluir" :name="`linhas[${i}][incluir]`" style="accent-color:var(--accent);width:16px;height:16px" :aria-label="'Importar ' + l.descricao">
                                    <input type="hidden" :name="`linhas[${i}][data]`" :value="l.data">
                                    <input type="hidden" :name="`linhas[${i}][valor]`" :value="l.valor">
                                </td>
                                <td class="num faint" x-text="l.data.split('-').reverse().join('/')"></td>
                                <td style="min-width:200px"><input class="in" :name="`linhas[${i}][descricao]`" x-model="l.descricao" maxlength="80"></td>
                                <td>
                                    <select class="in" :name="`linhas[${i}][tipo]`" x-model="l.tipo" x-on:change="if (!cats[l.tipo].includes(l.categoria)) l.categoria = cats[l.tipo][cats[l.tipo].length - 1]">
                                        <option value="despesa">Gasto</option><option value="receita">Receita</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="in" :name="`linhas[${i}][categoria]`" x-model="l.categoria">
                                        <template x-for="c in cats[l.tipo]" :key="l.tipo + c"><option :value="c" x-text="c" :selected="c === l.categoria"></option></template>
                                    </select>
                                </td>
                                <td class="n" :class="l.tipo === 'receita' ? 'up' : ''"><strong x-text="(l.tipo === 'receita' ? '+' : '−') + fmt.brl(Number(l.valor))"></strong></td>
                                <td><span class="chip warn" x-show="l.aviso" x-text="l.aviso"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</x-app-layout>
