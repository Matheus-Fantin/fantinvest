<?php

if (! function_exists('brl')) {
    /** Formata um valor em reais: R$ 1.234,56 (ou sem centavos com $casas = 0). */
    function brl(float|int|string|null $valor, int $casas = 2): string
    {
        $valor = (float) $valor;
        $texto = 'R$ '.number_format(abs($valor), $casas, ',', '.');

        return $valor < 0 ? '−'.$texto : $texto;
    }
}

if (! function_exists('pct')) {
    /** Formata um percentual: 12,5%. */
    function pct(float|int|string|null $valor, int $casas = 1): string
    {
        return number_format((float) $valor, $casas, ',', '.').'%';
    }
}

if (! function_exists('numero')) {
    /** Formata um número sem zeros à direita: 1.234,5. */
    function numero(float|int|string|null $valor, int $casas = 2): string
    {
        $texto = number_format((float) $valor, $casas, ',', '.');

        return $casas > 0 ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }
}

if (! function_exists('valor_input')) {
    /** Valor para preencher um campo de formulário: 1.234,5 (ponto no milhar, sem zeros sobrando). */
    function valor_input(float|int|string|null $valor, int $casas = 2): string
    {
        $texto = number_format((float) $valor, $casas, ',', '.');

        return $casas > 0 ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }
}

if (! function_exists('mes_extenso')) {
    /** "2026-10" → "outubro de 2026". */
    function mes_extenso(string $mes): string
    {
        $nomes = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        [$ano, $m] = array_map('intval', explode('-', $mes));

        return $nomes[$m - 1].' de '.$ano;
    }
}

if (! function_exists('ler_numero')) {
    /**
     * Lê um número digitado em formato brasileiro: "1.234,56", "1.000", "R$ 10", "12,5%".
     * Também aceita "1234.56". Retorna a string normalizada ("1234.56") para a validação.
     */
    function ler_numero(string $texto): string
    {
        $texto = preg_replace('/[R$\s%]/u', '', $texto);

        if (str_contains($texto, ',')) {
            return str_replace(['.', ','], ['', '.'], $texto);
        }

        // Só pontos no padrão de milhar (1.000 / 12.345.678): são separadores, não decimais.
        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $texto)) {
            return str_replace('.', '', $texto);
        }

        return $texto;
    }
}

if (! function_exists('moeda_input')) {
    /** Valor em reais para preencher um campo: 1.234,56 (sempre com centavos). */
    function moeda_input(float|int|string|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}
