<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaNumeros;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    use NormalizaNumeros;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarNumeros(['valor']);
    }

    public function rules(): array
    {
        $categorias = $this->input('tipo') === 'receita'
            ? config('financas.categorias_receita')
            : array_keys(config('financas.categorias_despesa'));

        return [
            'tipo' => ['required', Rule::in(['receita', 'despesa'])],
            'data' => ['required', 'date'],
            'categoria' => ['required', Rule::in($categorias)],
            'descricao' => ['nullable', 'string', 'max:80'],
            'valor' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
