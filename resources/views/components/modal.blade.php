@props(['name', 'show' => false, 'titulo' => null])

{{-- Janela modal. Abrir: $dispatch('open-modal', 'nome'). Fechar: $dispatch('close-modal', 'nome'). --}}
<div
    x-data="{ show: @js($show) }"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    x-transition.opacity
    class="modal-back"
    x-on:click.self="show = false"
>
    <div class="modal" role="dialog" aria-modal="true" {{ $attributes }}
         x-init="$watch('show', v => v && $nextTick(() => $el.querySelector('input:not([type=hidden]),select,button.primary')?.focus()))">
        @if ($titulo)
            <div class="row between">
                <h2>{{ $titulo }}</h2>
                <button type="button" class="btn icon ghost" x-on:click="show = false" aria-label="Fechar"><x-icone nome="x" /></button>
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
