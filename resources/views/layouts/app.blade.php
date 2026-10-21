@php
    // Menu enxuto: cada item é uma seção; as telas relacionadas viram abas dentro dela.
    $secoes = [
        'inicio' => ['rota' => 'dashboard', 'nome' => 'Início', 'icone' => 'home', 'ativa' => ['dashboard', 'patrimonio.*']],
        'lancamentos' => ['rota' => 'orcamento.index', 'nome' => 'Lançamentos', 'icone' => 'receipt', 'ativa' => ['orcamento.*', 'cartoes.*', 'contas.*'],
            'abas' => [
                ['rota' => 'orcamento.index', 'nome' => 'Gastos e receitas', 'ativa' => ['orcamento.index']],
                ['rota' => 'cartoes.index', 'nome' => 'Cartões e faturas', 'ativa' => ['cartoes.*']],
                ['rota' => 'contas.index', 'nome' => 'Bancos', 'ativa' => ['contas.*']],
                ['rota' => 'orcamento.importar', 'nome' => 'Importar extrato', 'ativa' => ['orcamento.importar*']],
            ]],
        'investimentos' => ['rota' => 'carteira.index', 'nome' => 'Investimentos', 'icone' => 'growth', 'curto' => 'Investir', 'ativa' => ['carteira.*', 'bolsa.*', 'dividendos.*', 'aporte.*'],
            'abas' => [
                ['rota' => 'carteira.index', 'nome' => 'Minha carteira', 'ativa' => ['carteira.*']],
                ['rota' => 'bolsa.index', 'nome' => 'Bolsa hoje', 'ativa' => ['bolsa.*']],
                ['rota' => 'dividendos.index', 'nome' => 'Dividendos', 'ativa' => ['dividendos.*']],
                ['rota' => 'aporte.index', 'nome' => 'Quanto aportar', 'ativa' => ['aporte.*']],
            ]],
        'objetivos' => ['rota' => 'objetivos.index', 'nome' => 'Objetivos', 'icone' => 'flag', 'ativa' => ['objetivos.*']],
        'casa' => ['rota' => 'imovel.index', 'nome' => 'Minha casa', 'icone' => 'house', 'ativa' => ['imovel.*']],
        'simuladores' => ['rota' => 'simuladores.juros', 'nome' => 'Simuladores', 'icone' => 'calc', 'ativa' => ['simuladores.*'],
            'abas' => [
                ['rota' => 'simuladores.juros', 'nome' => 'Juros compostos', 'ativa' => ['simuladores.juros']],
                ['rota' => 'simuladores.financiamento', 'nome' => 'Financiamento', 'ativa' => ['simuladores.financiamento']],
            ]],
    ];
    if (Auth::user()->is_admin) {
        $secoes['admin'] = ['rota' => 'admin.convites.index', 'nome' => 'Convites', 'icone' => 'user', 'ativa' => ['admin.*']];
    }
    $atual = collect($secoes)->search(fn ($s) => request()->routeIs(...$s['ativa']));
    $abas = $atual ? ($secoes[$atual]['abas'] ?? []) : [];
    $barraCelular = ['inicio', 'lancamentos', 'investimentos'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" translate="no">
<head>
    @include('partials.head', ['titulo' => $titulo ?? null])
</head>
<body>
<div class="app">
    <aside class="side">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark"><x-icone nome="logo" width="16" height="16" stroke-width="2.4" /></span>
            <span class="brand-name">FantInvest</span>
        </a>
        <a href="{{ route('orcamento.index') }}#novo" class="btn primary side-cta"><x-icone nome="plus" />Lançar gasto ou receita</a>
        <nav class="nav" aria-label="Seções">
            @foreach ($secoes as $chave => $s)
                <a href="{{ route($s['rota']) }}" @if ($atual === $chave) aria-current="page" @endif>
                    <x-icone :nome="$s['icone']" /><span>{{ $s['nome'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="side-foot">
            <div class="side-user">
                <span class="side-name">{{ Auth::user()->name }}</span>
                <a href="{{ route('profile.edit') }}"><x-icone nome="user" />Meu perfil</a>
                <button type="button" data-alternar-tema><x-icone nome="theme" /><span data-tema-rotulo>Tema do sistema</span></button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><x-icone nome="logout" />Sair</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Celular: topo simples --}}
    <header class="topo-celular">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark"><x-icone nome="logo" width="16" height="16" stroke-width="2.4" /></span>
            <span class="brand-name">FantInvest</span>
        </a>
        <span class="small muted">{{ $atual ? $secoes[$atual]['nome'] : '' }}</span>
    </header>

    <main>
        <div class="wrap">
            @if (count($abas) > 1)
                <nav class="secao-abas" aria-label="{{ $secoes[$atual]['nome'] }}">
                    @foreach ($abas as $aba)
                        <a href="{{ route($aba['rota']) }}" @if (request()->routeIs(...$aba['ativa'])) aria-current="page" @endif>{{ $aba['nome'] }}</a>
                    @endforeach
                </nav>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>

{{-- Celular: barra fixa no rodapé com o botão de lançar no meio --}}
<nav class="barra-celular" aria-label="Navegação" x-data="{ mais: false }">
    @foreach (array_slice($barraCelular, 0, 2) as $chave)
        <a href="{{ route($secoes[$chave]['rota']) }}" @if ($atual === $chave) aria-current="page" @endif><x-icone :nome="$secoes[$chave]['icone']" /><span>{{ $secoes[$chave]['curto'] ?? $secoes[$chave]['nome'] }}</span></a>
    @endforeach
    <a href="{{ route('orcamento.index') }}#novo" class="barra-mais" aria-label="Lançar gasto ou receita"><x-icone nome="plus" /></a>
    <a href="{{ route($secoes['investimentos']['rota']) }}" @if ($atual === 'investimentos') aria-current="page" @endif><x-icone nome="growth" /><span>Investir</span></a>
    <button type="button" x-on:click="mais = true" @if (! in_array($atual, $barraCelular, true) && $atual) aria-current="page" @endif><x-icone nome="list" /><span>Mais</span></button>

    <div class="folha-fundo" x-show="mais" x-cloak x-transition.opacity x-on:click="mais = false"></div>
    <div class="folha" x-show="mais" x-cloak x-transition x-on:keydown.escape.window="mais = false">
        <div class="row between"><strong>Mais opções</strong><button type="button" class="btn icon ghost" x-on:click="mais = false" aria-label="Fechar"><x-icone nome="x" /></button></div>
        @foreach ($secoes as $chave => $s)
            @continue(in_array($chave, $barraCelular, true))
            <a href="{{ route($s['rota']) }}"><x-icone :nome="$s['icone']" />{{ $s['nome'] }}</a>
        @endforeach
        <div class="divider"></div>
        <a href="{{ route('profile.edit') }}"><x-icone nome="user" />Meu perfil</a>
        <button type="button" data-alternar-tema><x-icone nome="theme" /><span data-tema-rotulo>Tema do sistema</span></button>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-icone nome="logout" />Sair</button></form>
    </div>
</nav>

@if (session('sucesso') || session('status') === 'profile-updated' || session('status') === 'password-updated')
    <div class="flash" role="status" x-data="{ v: true }" x-show="v" x-init="setTimeout(() => v = false, 3200)" x-transition.opacity>
        {{ session('sucesso') ?? 'Alterações salvas.' }}
    </div>
@endif
@if (session('aviso'))
    <div class="flash" role="status" x-data="{ v: true }" x-show="v" x-init="setTimeout(() => v = false, 6000)" x-transition.opacity>{{ session('aviso') }}</div>
@endif
</body>
</html>
