@php
    $vazio = ['id' => null, 'nome' => '', 'limite' => '', 'dia_fechamento' => 1, 'dia_vencimento' => 8, 'account_id' => ''];
    $situacao = ['aberta' => ['Aberta', ''], 'fechada' => ['Fechada', 'warn'], 'atrasada' => ['Atrasada', 'bad'], 'paga' => ['Paga', 'good'], 'futura' => ['Futura', '']];
@endphp
<x-app-layout titulo="Cartões">
    <div class="stack" style="gap:24px" x-data="{ cartao: @js($vazio), novo() { this.cartao = { ...@js($vazio) }; $dispatch('open-modal', 'cartao') } }">
        <div class="page-head">
            <div>
                <div class="eyebrow">Dinheiro</div>
                <h1>Cartões de crédito</h1>
                <p>Cada compra no cartão entra no orçamento como gasto na categoria certa e vai para a fatura pelo dia de fechamento. Pagar a fatura tira o dinheiro da conta, mas não conta como gasto de novo.</p>
            </div>
            <button type="button" class="btn primary" x-on:click="novo()"><x-icone nome="plus" />Novo cartão</button>
        </div>

        @if ($cartoes->isEmpty())
            <div class="card"><div class="empty">
                <x-icone nome="card" width="28" height="28" />
                <p>Cadastre seus cartões com limite, dia de fechamento e vencimento. Depois, ao lançar um gasto no Orçamento, escolha o cartão e o número de parcelas.</p>
                <button type="button" class="btn primary" x-on:click="novo()"><x-icone nome="plus" />Novo cartão</button>
            </div></div>
        @else
            <div class="goal-grid">
                @foreach ($cartoes as $item)
                    @php $c = $item['cartao']; $atual = $item['atual']; $livre = max(0, $c->limite - $item['usado']); @endphp
                    <div class="card goal">
                        <div class="row between" style="align-items:flex-start">
                            <div><h2>{{ $c->nome }}</h2><span class="small muted">Fecha dia {{ $c->dia_fechamento }} · vence dia {{ $c->dia_vencimento }}@if ($c->account) · paga pela {{ $c->account->nome }}@endif</span></div>
                            <a class="btn sm" href="{{ route('cartoes.show', $c) }}">Ver faturas</a>
                        </div>
                        <div class="row between">
                            <span class="kpi"><span class="label">Fatura atual ({{ mes_extenso($atual['fatura']) }})</span><span class="value">{{ brl($atual['total']) }}</span></span>
                            <span class="kpi" style="text-align:right"><span class="label">Vence</span><span class="value" style="font-size:1.1rem">{{ $atual['vencimento']->format('d/m') }}</span></span>
                        </div>
                        @if ($c->limite > 0)
                            <div class="stack" style="gap:6px">
                                <div class="meter"><div style="width:{{ min(100, $item['usado'] / $c->limite * 100) }}%;background:var({{ $item['usado'] / $c->limite > .8 ? '--bad' : '--accent' }})"></div></div>
                                <div class="row between small"><span class="muted">Usado {{ brl($item['usado']) }}</span><span class="muted">Livre <strong>{{ brl($livre) }}</strong> de {{ brl($c->limite) }}</span></div>
                            </div>
                        @endif
                        @foreach ($item['pendentes'] as $p)
                            <div class="banner {{ $p['situacao'] === 'atrasada' ? 'warn' : '' }}" style="padding:10px 12px">
                                <span class="small">Fatura de {{ mes_extenso($p['fatura']) }} <span class="chip {{ $situacao[$p['situacao']][1] }}">{{ $situacao[$p['situacao']][0] }}</span> · falta {{ brl($p['falta']) }}</span>
                                <a class="btn sm primary" href="{{ route('cartoes.show', [$c, 'fatura' => $p['fatura']]) }}">Pagar</a>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        <x-modal name="cartao" titulo="Novo cartão">
            <form method="POST" action="{{ route('cartoes.store') }}" class="stack" autocomplete="off">
                @csrf
                @include('cartoes._form')
                <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">Criar cartão</button></div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
