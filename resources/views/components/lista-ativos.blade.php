@props(['itens', 'vazio' => 'Sem dados agora.'])

{{-- Lista de ativos da B3 (ranking ou busca). Cada linha abre a página da ação. --}}
@if (empty($itens))
    <div class="empty"><p>{{ $vazio }}</p></div>
@else
    <div class="lista-ativos">
        @foreach ($itens as $i => $r)
            <a class="ativo-linha" href="{{ route('bolsa.show', $r['ticker']) }}">
                <span class="ativo-pos num">{{ $i + 1 }}</span>
                @if ($r['logo'])<img class="ativo-logo" src="{{ $r['logo'] }}" alt="" loading="lazy" referrerpolicy="no-referrer">@else<span class="ativo-logo"></span>@endif
                <span class="ativo-nome"><strong>{{ $r['ticker'] }}</strong><span class="small faint">{{ $r['nome'] }}</span></span>
                <span class="ativo-preco num">
                    <strong>{{ $r['preco'] !== null ? brl($r['preco']) : '—' }}</strong>
                    @if ($r['variacao'] !== null)<span class="chip {{ $r['variacao'] >= 0 ? 'good' : 'bad' }}">{{ $r['variacao'] >= 0 ? '▲' : '▼' }} {{ pct(abs($r['variacao']), 2) }}</span>@endif
                </span>
            </a>
        @endforeach
    </div>
@endif
