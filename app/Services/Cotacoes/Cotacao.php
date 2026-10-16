<?php

namespace App\Services\Cotacoes;

/** Cotação de um ativo retornada por uma fonte externa. */
final class Cotacao
{
    public function __construct(
        public readonly string $ticker,
        public readonly float $preco,
        public readonly ?float $variacaoDia = null,
    ) {}
}
