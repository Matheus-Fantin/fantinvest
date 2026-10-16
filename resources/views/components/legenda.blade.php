@props(['itens'])

{{-- Legenda de gráfico: [['s1', 'Receitas', true], ...] — o terceiro item indica marcador quadrado (barras). --}}
<div class="legend">
    @foreach ($itens as $item)
        <span><i class="{{ ($item[2] ?? false) ? 'box' : '' }}" style="background:var(--{{ $item[0] }})"></i>{{ $item[1] }}</span>
    @endforeach
</div>
