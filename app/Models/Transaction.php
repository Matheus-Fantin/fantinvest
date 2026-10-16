<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'data', 'tipo', 'categoria', 'descricao', 'valor',
        'account_id', 'card_id', 'fatura', 'parcelamento_id', 'parcela', 'parcelas_total', 'external_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'float',
            'parcela' => 'integer',
            'parcelas_total' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** "3/10" para parcelas; null para lançamentos à vista. */
    public function rotuloParcela(): ?string
    {
        return $this->parcelas_total > 1 ? "{$this->parcela}/{$this->parcelas_total}" : null;
    }

    /** Lançamentos de um mês no formato "2026-10". */
    public function scopeDoMes(Builder $query, string $mes): Builder
    {
        $inicio = $mes.'-01';

        // As datas ficam gravadas com hora (00:00:00); o fim do intervalo vai até 23:59:59 do último dia.
        return $query->whereBetween('data', [$inicio, date('Y-m-t', strtotime($inicio)).' 23:59:59']);
    }

    /** Grupo da regra 50/30/20: ess, des ou inv (receitas não têm grupo). */
    public function grupo(): ?string
    {
        return $this->tipo === 'despesa'
            ? config("financas.categorias_despesa.{$this->categoria}", 'des')
            : null;
    }
}
