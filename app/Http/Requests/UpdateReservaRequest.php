<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaNumeros;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReservaRequest extends FormRequest
{
    use NormalizaNumeros;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarNumeros(['reserva_atual', 'reserva_meses']);
    }

    public function rules(): array
    {
        return [
            'reserva_atual' => ['required', 'numeric', 'min:0'],
            'reserva_meses' => ['required', 'integer', 'between:1,36'],
        ];
    }
}
