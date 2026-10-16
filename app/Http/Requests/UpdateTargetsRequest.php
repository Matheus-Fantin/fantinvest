<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaNumeros;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTargetsRequest extends FormRequest
{
    use NormalizaNumeros;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarNumeros(array_map(fn ($c) => "metas.$c", array_keys(config('financas.classes'))));
    }

    public function rules(): array
    {
        $regras = ['metas' => ['required', 'array']];
        foreach (array_keys(config('financas.classes')) as $classe) {
            $regras["metas.$classe"] = ['required', 'numeric', 'between:0,100'];
        }

        return $regras;
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $soma = array_sum(array_map('floatval', (array) $this->input('metas')));
                if (abs($soma - 100) > 0.01) {
                    $validator->errors()->add('metas', 'As metas precisam somar 100%. Hoje somam '.pct($soma, 0).'.');
                }
            },
        ];
    }
}
