<?php

namespace App\Http\Requests\Concerns;

trait NormalizaNumeros
{
    /**
     * Converte números digitados no formato brasileiro ("1.234,56", "R$ 10") para "1234.56"
     * antes da validação.
     *
     * @param  array<int, string>  $campos
     */
    protected function normalizarNumeros(array $campos): void
    {
        $normalizados = [];

        foreach ($campos as $campo) {
            $valor = data_get($this->all(), $campo);
            if (! is_string($valor)) {
                continue;
            }
            data_set($normalizados, $campo, ler_numero($valor));
        }

        $this->merge($normalizados);
    }
}
