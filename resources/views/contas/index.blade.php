@php
    $tipos = \App\Models\Account::TIPOS;
    $vazio = ['id' => null, 'nome' => '', 'tipo' => 'corrente', 'saldo_inicial' => '0,00', 'arquivada' => false];
    $ativas = $contas->where('arquivada', false);
@endphp
<x-app-layout titulo="Contas">
    <div class="stack" style="gap:24px"
         x-data="{ conta: @js($vazio), vazio: @js($vazio),
                   nova() { this.conta = { ...this.vazio }; $dispatch('open-modal', 'conta') },
                   editar(c) { this.conta = { ...c }; $dispatch('open-modal', 'conta') } }">
        <div class="page-head">
            <div>
                <div class="eyebrow">Dinheiro</div>
                <h1>Contas</h1>
                <p>Conta corrente, poupança, corretora, carteira de cripto e dinheiro em espécie. O saldo de cada conta soma o saldo inicial, as receitas e gastos lançados nela, as transferências e os pagamentos de fatura.</p>
            </div>
            <div class="row">
                @if ($ativas->count() >= 2)
                    <button type="button" class="btn" x-on:click="$dispatch('open-modal', 'transferencia')"><x-icone nome="swap" />Transferir</button>
                @endif
                <button type="button" class="btn primary" x-on:click="nova()"><x-icone nome="plus" />Nova conta</button>
            </div>
        </div>

        @if ($contas->isEmpty())
            <div class="card">
                <div class="empty">
                    <x-icone nome="bank" width="28" height="28" />
                    <p>Cadastre suas contas para acompanhar o saldo de cada uma. Comece pela conta onde cai o seu salário, com o saldo que ela tem hoje.</p>
                    <button type="button" class="btn primary" x-on:click="nova()"><x-icone nome="plus" />Nova conta</button>
                </div>
            </div>
        @else
            <div class="grid g3">
                <div class="card kpi"><span class="label">Saldo em contas</span><span class="value">{{ brl($total) }}</span><span class="sub">{{ $ativas->count() }} {{ $ativas->count() === 1 ? 'conta ativa' : 'contas ativas' }}</span></div>
                <div class="card kpi"><span class="label">Faturas em aberto</span><span class="value {{ $cartoes->sum('em_aberto') > 0 ? 'down' : '' }}">{{ brl($cartoes->sum('em_aberto')) }}</span><span class="sub">{{ $cartoes->count() }} {{ $cartoes->count() === 1 ? 'cartão' : 'cartões' }}</span></div>
                <div class="card kpi"><span class="label">Saldo depois das faturas</span><span class="value">{{ brl($total - $cartoes->sum('em_aberto')) }}</span><span class="sub">quanto sobra se pagar tudo hoje</span></div>
            </div>

            <div class="card">
                <div class="card-head"><div><h2>Suas contas</h2><p>Saldos até hoje. Lançamentos com data futura entram no dia deles.</p></div></div>
                @foreach ($contas as $c)
                    <div class="list-row" @if ($c->arquivada) style="opacity:.55" @endif>
                        <div style="min-width:0">
                            <strong>{{ $c->nome }}</strong> @if ($c->arquivada)<span class="chip">arquivada</span>@endif
                            <div class="small muted">{{ $c->nomeTipo() }} · saldo inicial {{ brl($c->saldo_inicial) }}</div>
                        </div>
                        <div class="row">
                            <span class="big-num {{ $c->saldo < 0 ? 'down' : '' }}" style="font-size:1.3rem">{{ brl($c->saldo) }}</span>
                            <a class="btn sm ghost" href="{{ route('orcamento.index') }}">Lançar</a>
                            <button type="button" class="btn icon ghost" aria-label="Editar {{ $c->nome }}"
                                    x-on:click="editar(@js(['id' => $c->id, 'nome' => $c->nome, 'tipo' => $c->tipo, 'saldo_inicial' => moeda_input($c->saldo_inicial), 'arquivada' => $c->arquivada]))"><x-icone nome="edit" /></button>
                            <form method="POST" action="{{ route('contas.destroy', $c) }}">@csrf @method('DELETE')
                                <button class="btn icon ghost" aria-label="Excluir {{ $c->nome }}" title="Exclui (ou arquiva, se já tiver movimentos)"><x-icone nome="trash" /></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card">
                <div class="card-head"><div><h2>Transferências e pagamentos de fatura</h2><p>Não contam como receita nem como gasto no orçamento.</p></div></div>
                @if ($transferencias->isEmpty())
                    <div class="empty"><p>Nenhuma transferência ainda.</p></div>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>Data</th><th>De</th><th>Para</th><th>Descrição</th><th class="n">Valor</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($transferencias as $t)
                                <tr>
                                    <td class="num faint">{{ $t->data->format('d/m/Y') }}</td>
                                    <td>{{ $t->de?->nome ?? '—' }}</td>
                                    <td>{{ $t->card ? 'Fatura '.$t->card->nome.' ('.mes_extenso($t->fatura).')' : ($t->para?->nome ?? '—') }}</td>
                                    <td class="muted">{{ $t->descricao }}</td>
                                    <td class="n"><strong>{{ brl($t->valor) }}</strong></td>
                                    <td><form method="POST" action="{{ route('transferencias.destroy', $t) }}" class="row-actions">@csrf @method('DELETE')<button class="btn icon ghost" aria-label="Excluir movimento"><x-icone nome="trash" /></button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        @endif

        <x-modal name="conta">
            <form method="POST" :action="conta.id ? '{{ url('contas') }}/' + conta.id : '{{ route('contas.store') }}'" class="stack" autocomplete="off">
                @csrf
                <template x-if="conta.id"><input type="hidden" name="_method" value="PUT"></template>
                <div class="row between"><h2 x-text="conta.id ? 'Editar conta' : 'Nova conta'"></h2>
                    <button type="button" class="btn icon ghost" x-on:click="$dispatch('close-modal', 'conta')" aria-label="Fechar"><x-icone nome="x" /></button></div>
                <div class="form-grid">
                    <div class="field full"><label for="cNome">Nome</label><input class="in" id="cNome" name="nome" x-model="conta.nome" placeholder="Ex.: Nubank, Caixa, XP" maxlength="40" required></div>
                    <div class="field"><label for="cTipo">Tipo</label>
                        <select class="in" id="cTipo" name="tipo" x-model="conta.tipo">@foreach ($tipos as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <x-campo nome="saldo_inicial" rotulo="Saldo de hoje" id="cSaldo" pre="R$" x-model="conta.saldo_inicial" dica="O saldo que a conta tem agora." />
                    <label class="check full" x-show="conta.id"><input type="checkbox" name="arquivada" value="1" x-model="conta.arquivada">Arquivar (some das listas, mas mantém o histórico)</label>
                </div>
                <div class="row" style="justify-content:flex-end">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'conta')">Cancelar</button>
                    <button class="btn primary" type="submit" x-text="conta.id ? 'Salvar' : 'Criar conta'"></button>
                </div>
            </form>
        </x-modal>

        <x-modal name="transferencia" titulo="Transferir entre contas">
            <form method="POST" action="{{ route('transferencias.store') }}" class="stack" autocomplete="off">
                @csrf
                <div class="form-grid">
                    <div class="field"><label for="tDe">De</label><select class="in" id="tDe" name="de_account_id">@foreach ($ativas as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach</select></div>
                    <div class="field"><label for="tPara">Para</label><select class="in" id="tPara" name="para_account_id">@foreach ($ativas as $i => $c)<option value="{{ $c->id }}" @selected($loop->index === 1)>{{ $c->nome }}</option>@endforeach</select></div>
                    <x-campo nome="valor" rotulo="Valor" id="tValor" pre="R$" required />
                    <div class="field"><label for="tData">Data</label><input class="in" type="date" id="tData" name="data" value="{{ now()->toDateString() }}" required></div>
                    <div class="field full"><label for="tDesc">Descrição</label><input class="in" id="tDesc" name="descricao" maxlength="80" placeholder="Ex.: guardar na poupança"></div>
                </div>
                @error('para_account_id')<span class="erro">{{ $message }}</span>@enderror
                <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">Transferir</button></div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
