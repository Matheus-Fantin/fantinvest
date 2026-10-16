<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Campo "origem" dos formulários de lançamento: "conta:3", "cartao:2" ou vazio.
 * Vira account_id / card_id, sempre limitados às contas e cartões do próprio usuário.
 */
trait LeOrigem
{
    protected function lerOrigem(): void
    {
        $origem = (string) $this->input('origem', '');
        [$tipo, $id] = array_pad(explode(':', $origem, 2), 2, null);

        $this->merge([
            'account_id' => $tipo === 'conta' ? (int) $id : null,
            'card_id' => $tipo === 'cartao' ? (int) $id : null,
        ]);
    }

    protected function regrasOrigem(): array
    {
        $usuario = $this->user()->id;

        return [
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $usuario)],
            'card_id' => ['nullable', 'integer', Rule::exists('cards', 'id')->where('user_id', $usuario)],
        ];
    }
}
