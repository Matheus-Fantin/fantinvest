@props(['nome', 'rotulo', 'valor' => '', 'pre' => null, 'suf' => null, 'dica' => null, 'classe' => ''])

{{-- Campo numérico/texto no padrão visual (rótulo, prefixo/sufixo, dica e erro). --}}
@php $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $nome)); @endphp
<div class="field {{ $classe }}">
    <label for="{{ $id }}">{{ $rotulo }}</label>
    @if ($pre || $suf)
        <div class="in-group">
            @if ($pre)<span class="affix">{{ $pre }}</span>@endif
            <input class="in @error($nome) invalido @enderror" id="{{ $id }}" name="{{ $nome }}" value="{{ $valor }}" inputmode="decimal" autocomplete="off" {{ $attributes->except('id') }}>
            @if ($suf)<span class="affix">{{ $suf }}</span>@endif
        </div>
    @else
        <input class="in @error($nome) invalido @enderror" id="{{ $id }}" name="{{ $nome }}" value="{{ $valor }}" inputmode="decimal" autocomplete="off" {{ $attributes->except('id') }}>
    @endif
    @if ($dica)<span class="hint">{{ $dica }}</span>@endif
    @error($nome)<span class="erro">{{ $message }}</span>@enderror
</div>
