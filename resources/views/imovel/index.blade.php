<x-app-layout titulo="Meu financiamento">
    <div class="page-head">
        <div>
            <div class="eyebrow">Imóvel</div>
            <h1>Meu financiamento</h1>
            <p>Cadastre o contrato da Caixa (ou de outro banco) para acompanhar parcelas, saldo devedor, juros, amortizações extras, liberações do banco e todos os gastos da obra.</p>
        </div>
    </div>

    @if ($financiamentos->isNotEmpty())
        <div class="goal-grid">
            @foreach ($financiamentos as $f)
                <a class="card goal" href="{{ route('imovel.show', $f) }}">
                    <h2>{{ $f->nome }}</h2>
                    <span class="small muted">{{ $f->banco }} · {{ strtoupper($f->sistema) }} · {{ $f->prazo_meses }} meses · {{ pct($f->taxa_juros_anual, 2) }} a.a.</span>
                    <span class="kpi"><span class="label">Valor financiado</span><span class="value">{{ brl($f->valor_financiado) }}</span></span>
                </a>
            @endforeach
        </div>
    @endif

    <form class="card stack" method="POST" action="{{ route('imovel.store') }}" autocomplete="off">
        @csrf
        <div class="card-head"><div><h2>{{ $financiamentos->isEmpty() ? 'Cadastrar o contrato' : 'Cadastrar outro financiamento' }}</h2><p>Os dados estão no contrato e no app Habitação Caixa. Dá para ajustar depois.</p></div></div>
        @include('imovel._contrato', ['f' => null])
        <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit"><x-icone nome="check" />Salvar contrato</button></div>
    </form>
</x-app-layout>
