@props(['contas', 'cartoes', 'id' => 'origem', 'rotulo' => 'Conta ou cartão'])

{{-- Escolha de onde o dinheiro sai/entra: valor "conta:ID", "cartao:ID" ou vazio. --}}
<div class="field">
    <label for="{{ $id }}">{{ $rotulo }}</label>
    <select class="in" id="{{ $id }}" name="origem" {{ $attributes }}>
        <option value="">Sem conta</option>
        @if ($contas->isNotEmpty())
            <optgroup label="Contas">
                @foreach ($contas as $c)<option value="conta:{{ $c->id }}">{{ $c->nome }}</option>@endforeach
            </optgroup>
        @endif
        @if ($cartoes->isNotEmpty())
            <optgroup label="Cartões de crédito">
                @foreach ($cartoes as $c)<option value="cartao:{{ $c->id }}">Cartão {{ $c->nome }}</option>@endforeach
            </optgroup>
        @endif
    </select>
</div>
