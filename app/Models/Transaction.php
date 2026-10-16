<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['user_id', 'data', 'tipo', 'categoria', 'descricao', 'valor'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Lançamentos de um mês no formato "2026-10". */
    public function scopeDoMes(Builder $query, string $mes): Builder
    {
        $inicio = $mes.'-01';

        return $query->whereBetween('data', [$inicio, date('Y-m-t', strtotime($inicio))]);
    }

    /** Grupo da regra 50/30/20: ess, des ou inv (receitas não têm grupo). */
    public function grupo(): ?string
    {
        return $this->tipo === 'despesa'
            ? config("financas.categorias_despesa.{$this->categoria}", 'des')
            : null;
    }
}
