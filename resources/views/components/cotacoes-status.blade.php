{{-- Linha de status das cotações automáticas + botão "Atualizar". A página pede
     atualização sozinha quando a cotação mais antiga passou do limite configurado. --}}
@php
    $ativos = app(\App\Services\Cotacoes\CotacaoService::class)->atualizaveis(auth()->user()->assets()->get());
    $datas = $ativos->pluck('cotacao_atualizada_em');
    $maisAntiga = $datas->contains(null) ? null : $datas->min();
    $maisRecente = $datas->filter()->max();
    $semToken = blank(config('cotacoes.brapi.token')) && $ativos->contains(fn ($a) => config('cotacoes.fontes')[$a->classe] === 'brapi');
@endphp
@if ($ativos->isNotEmpty())
    <div class="cotacoes-status"
         x-data="cotacoesAoVivo(@js([
             'url' => route('cotacoes.atualizar'),
             'maisAntiga' => $maisAntiga?->toIso8601String(),
             'maisRecente' => $maisRecente?->toIso8601String(),
             'idadeMaxima' => config('cotacoes.idade_maxima_minutos'),
         ]))">
        <span class="live-dot" :class="{ busy: carregando, ok: !carregando && recente }"></span>
        <span x-text="texto">Cotações automáticas</span>
        <span class="faint">· {{ $ativos->count() }} {{ $ativos->count() === 1 ? 'ativo' : 'ativos' }} via API (B3 com até 30 min de atraso no plano gratuito)</span>
        <button type="button" class="btn sm ghost" x-on:click="atualizar(true)" :disabled="carregando"><x-icone nome="refresh" />Atualizar agora</button>
        <template x-if="pendente">
            <button type="button" class="btn sm primary" x-on:click="location.reload()">Ver valores novos</button>
        </template>
        @if ($semToken)
            <span class="chip warn"><x-icone nome="alert" />Configure BRAPI_TOKEN no .env</span>
        @endif
    </div>
@endif
