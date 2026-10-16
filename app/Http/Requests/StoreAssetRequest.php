<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaNumeros;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    use NormalizaNumeros;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarNumeros(['quantidade', 'preco_medio', 'cotacao', 'nota']);
        $this->merge(['ticker' => mb_strtoupper(trim((string) $this->input('ticker')))]);
    }

    public function rules(): array
    {
        return [
            'ticker' => ['required', 'string', 'max:40'],
            'classe' => ['required', Rule::in(array_keys(config('financas.classes')))],
            'quantidade' => ['required', 'numeric', 'min:0'],
            'preco_medio' => ['required', 'numeric', 'min:0'],
            'cotacao' => ['nullable', 'numeric', 'min:0'],
            'nota' => ['required', 'numeric', 'between:0,10'],
        ];
    }

    public function attributes(): array
    {
        return ['ticker' => 'ativo', 'preco_medio' => 'preço médio', 'cotacao' => 'cotação'];
    }
}
