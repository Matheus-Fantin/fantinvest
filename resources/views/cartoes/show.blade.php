@php
    $situacao = ['aberta' => ['Aberta', ''], 'fechada' => ['Fechada', 'warn'], 'atrasada' => ['Atrasada', 'bad'], 'paga' => ['Paga', 'good'], 'futura' => ['Futura', '']];
    $dadosCartao = ['id' => $cartao->id, 'nome' => $cartao->nome, 'limite' => moeda_input($cartao->limite), 'dia_fechamento' => $cartao->dia_fechamento, 'dia_vencimento' => $cartao->dia_vencimento, 'account_id' => (string) ($cartao->account_id ?? '')];
@endphp
<x-app-layout titulo="Cartão {{ $cartao->nome }}">
    <div class="stack" style="gap:24px" x-data="{ cartao: @js($dadosCartao) }">
        <div class="page-head">
            <div>
                <div class="eyebrow"><a href="{{ route('cartoes.index') }}" class="link">Cartões</a> / {{ $cartao->nome }}</div>
                <h1>Fatura de {{ mes_extenso($fatura['fatura']) }}</h1>
                <p>Fecha em {{ $fatura['fechamento']->format('d/m/Y') }} e vence em {{ $fatura['vencimento']->format('d/m/Y') }}. Limite usado no cartão: {{ brl($usado) }}@if ($cartao->limite > 0) de {{ brl($cartao->limite) }}@endif.</p>
            </div>
            <div class="row">
                <button type="button" class="btn" x-on:click="$dispatch('open-modal', 'editar-cartao')"><x-icone nome="edit" />Editar cartão</button>
                <a class="btn primary" href="{{ route('orcamento.index', ['origem' => 'cartao:'.$cartao->id]) }}"><x-icone nome="plus" />Lançar compra</a>
            </div>
        </div>

        <nav class="fatura-tabs" aria-label="Faturas">
            @foreach ($faturas as $f)
                <a href="{{ route('cartoes.show', [$cartao, 'fatura' => $f['fatura']]) }}" @if ($f['fatura'] === $fatura['fatura']) aria-current="page" @endif>
                    <span class="muted">{{ mes_extenso($f['fatura']) }}</span>
                    <strong class="num">{{ brl($f['total']) }}</strong>
                    <span class="chip {{ $situacao[$f['situacao']][1] }}" style="align-self:flex-start">{{ $situacao[$f['situacao']][0] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="grid g4">
            <div class="card kpi"><span class="label">Total da fatura</span><span class="value">{{ brl($fatura['total']) }}</span><span class="sub">{{ $fatura['compras'] }} {{ $fatura['compras'] === 1 ? 'lançamento' : 'lançamentos' }}</span></div>
            <div class="card kpi"><span class="label">Pago</span><span class="value up">{{ brl($fatura['pago']) }}</span></div>
            <div class="card kpi"><span class="label">Falta pagar</span><span class="value {{ $fatura['falta'] > 0 ? 'down' : '' }}">{{ brl($fatura['falta']) }}</span></div>
            <div class="card kpi"><span class="label">Situação</span><span class="value" style="font-size:1.15rem"><span class="chip {{ $situacao[$fatura['situacao']][1] }}" style="font-size:14px">{{ $situacao[$fatura['situacao']][0] }}</span></span><span class="sub">vence {{ $fatura['vencimento']->format('d/m/Y') }}</span></div>
        </div>

        <div class="grid g-side">
            <form class="card stack" method="POST" action="{{ route('cartoes.pagar', $cartao) }}" autocomplete="off">
                @csrf
                <h2>Pagar esta fatura</h2>
                <input type="hidden" name="fatura" value="{{ $fatura['fatura'] }}">
                <x-campo nome="valor" rotulo="Valor pago" pre="R$" :valor="moeda_input($fatura['falta'])" dica="Pode ser parcial; o restante continua em aberto." />
                <div class="field"><label for="pData">Data do pagamento</label><input class="in" type="date" id="pData" name="data" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label for="pConta">Saiu da conta</label>
                    <select class="in" id="pConta" name="account_id">
                        <option value="">Nenhuma (não controlo saldo)</option>
                        @foreach ($contas as $c)<option value="{{ $c->id }}" @selected($c->id === $cartao->account_id)>{{ $c->nome }}</option>@endforeach
                    </select></div>
                <button class="btn primary" type="submit" @disabled($fatura['total'] <= 0)><x-icone nome="check" />Registrar pagamento</button>
                <p class="note">O pagamento é uma transferência da conta para o cartão: baixa o saldo da conta e marca a fatura como paga, sem contar as compras duas vezes no orçamento.</p>
                @if ($pagamentos->isNotEmpty())
                    <div class="divider"></div>
                    @foreach ($pagamentos as $p)
                        <div class="row between small"><span>{{ $p->data->format('d/m/Y') }} · {{ $p->de?->nome ?? 'sem conta' }}</span><strong class="num">{{ brl($p->valor) }}</strong></div>
                    @endforeach
                @endif
            </form>

            <div class="card">
                <div class="card-head"><div><h2>Lançamentos da fatura</h2><p>Parcelas aparecem com o número, ex.: 3/10.</p></div></div>
                @if ($compras->isEmpty())
                    <div class="empty"><p>Nenhuma compra nesta fatura.</p></div>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="n">Valor</th></tr></thead>
                        <tbody>
                            @foreach ($compras as $t)
                                <tr>
                                    <td class="num faint">{{ $t->data->format('d/m') }}</td>
                                    <td>{{ $t->descricao ?: $t->categoria }}@if ($t->rotuloParcela())<span class="badge-parcela">{{ $t->rotuloParcela() }}</span>@endif</td>
                                    <td class="muted">{{ $t->categoria }}</td>
                                    <td class="n {{ $t->tipo === 'receita' ? 'up' : '' }}"><strong>{{ $t->tipo === 'receita' ? '−' : '' }}{{ brl($t->valor) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>

        <x-modal name="editar-cartao" titulo="Editar cartão">
            <form method="POST" action="{{ route('cartoes.update', $cartao) }}" class="stack" autocomplete="off">
                @csrf @method('PUT')
                @include('cartoes._form')
                <div class="row between">
                    <button class="btn danger" type="submit" form="excluirCartao">Excluir cartão</button>
                    <button class="btn primary" type="submit">Salvar</button>
                </div>
            </form>
            <form id="excluirCartao" method="POST" action="{{ route('cartoes.destroy', $cartao) }}">@csrf @method('DELETE')</form>
        </x-modal>
    </div>
</x-app-layout>
