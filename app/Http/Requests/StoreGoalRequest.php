<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaNumeros;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    use NormalizaNumeros;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarNumeros(['valor_alvo', 'valor_atual', 'rendimento_anual']);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:60'],
            'valor_alvo' => ['required', 'numeric', 'gt:0'],
            'valor_atual' => ['nullable', 'numeric', 'min:0'],
            'data_alvo' => ['nullable', 'date'],
            'rendimento_anual' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function attributes(): array
    {
        return ['valor_alvo' => 'valor do objetivo', 'valor_atual' => 'valor guardado', 'data_alvo' => 'data', 'rendimento_anual' => 'rendimento'];
    }
}
