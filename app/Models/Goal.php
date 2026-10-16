<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Goal extends Model
{
    protected $fillable = ['user_id', 'nome', 'valor_alvo', 'valor_atual', 'data_alvo', 'rendimento_anual'];

    protected function casts(): array
    {
        return [
            'valor_alvo' => 'float',
            'valor_atual' => 'float',
            'data_alvo' => 'date',
            'rendimento_anual' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function percentual(): float
    {
        return $this->valor_alvo > 0 ? min(100, $this->valor_atual / $this->valor_alvo * 100) : 0;
    }

    public function concluido(): bool
    {
        return $this->valor_atual >= $this->valor_alvo;
    }

    /** Meses inteiros até a data alvo (mínimo 1), ou null se não há prazo. */
    public function mesesRestantes(?Carbon $hoje = null): ?int
    {
        if (! $this->data_alvo) {
            return null;
        }
        $hoje ??= now();

        return max(1, (int) floor($hoje->copy()->startOfDay()->diffInMonths($this->data_alvo->copy()->startOfDay(), false)));
    }

    public function prazoVencido(?Carbon $hoje = null): bool
    {
        return $this->data_alvo !== null && $this->data_alvo->lt(($hoje ?? now())->startOfDay()) && ! $this->concluido();
    }

    /**
     * Quanto guardar por mês para chegar ao alvo na data, considerando o rendimento
     * anual informado sobre o que já foi guardado e sobre cada depósito.
     */
    public function aporteMensalNecessario(?Carbon $hoje = null): ?float
    {
        $n = $this->mesesRestantes($hoje);
        if ($n === null || $this->concluido()) {
            return null;
        }

        $i = (1 + $this->rendimento_anual / 100) ** (1 / 12) - 1;
        if ($i <= 0) {
            return max(0, ($this->valor_alvo - $this->valor_atual) / $n);
        }
        $fator = (1 + $i) ** $n;

        return max(0, ($this->valor_alvo - $this->valor_atual * $fator) * $i / ($fator - 1));
    }
}
