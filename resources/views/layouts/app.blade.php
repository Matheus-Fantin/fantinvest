@php
    $menu = [
        ['rota' => 'dashboard', 'nome' => 'Visão geral', 'icone' => 'home'],
        ['rota' => 'carteira.index', 'nome' => 'Carteira', 'icone' => 'wallet'],
        ['rota' => 'aporte.index', 'nome' => 'Regra de aporte', 'icone' => 'target'],
        ['rota' => 'orcamento.index', 'nome' => 'Orçamento', 'icone' => 'receipt', 'ativa' => 'orcamento.*'],
        ['rota' => 'objetivos.index', 'nome' => 'Objetivos', 'icone' => 'flag'],
        ['rota' => 'simuladores.juros', 'nome' => 'Juros compostos', 'icone' => 'growth'],
        ['rota' => 'simuladores.financiamento', 'nome' => 'Financiamento', 'icone' => 'house'],
    ];
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
        <nav class="nav" aria-label="Módulos">
            @foreach ($menu as $item)
                <a href="{{ route($item['rota']) }}" @if (request()->routeIs($item['ativa'] ?? $item['rota'])) aria-current="page" @endif>
                    <x-icone :nome="$item['icone']" /><span>{{ $item['nome'] }}</span>
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
            <span>Valores em reais. Cotações da B3 via brapi.dev e de cripto via CoinGecko.</span>
        </div>
    </aside>

    <main>
        <div class="wrap">
            {{ $slot }}
        </div>
    </main>
</div>

@if (session('sucesso') || session('status') === 'profile-updated' || session('status') === 'password-updated')
    <div class="flash" role="status" x-data="{ v: true }" x-show="v" x-init="setTimeout(() => v = false, 3200)" x-transition.opacity>
        {{ session('sucesso') ?? 'Alterações salvas.' }}
    </div>
@endif
</body>
</html>
