<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Card extends Model
{
    protected $fillable = ['user_id', 'nome', 'limite', 'dia_fechamento', 'dia_vencimento', 'account_id'];

    protected function casts(): array
    {
        return ['limite' => 'float', 'dia_fechamento' => 'integer', 'dia_vencimento' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    /**
     * Fatura em que cai uma compra feita na data informada, identificada pelo mês
     * de vencimento ("2026-11"). Compras a partir do dia de fechamento vão para a fatura seguinte.
     */
    public function faturaDaCompra(Carbon|string $data): string
    {
        $data = Carbon::parse($data);
        $fechamento = $data->copy()->startOfMonth();
        if ($data->day >= $this->dia_fechamento) {
            $fechamento->addMonth();
        }
        // Se o vencimento é antes (ou no) dia de fechamento, ele cai no mês seguinte ao fechamento.
        $vencimento = $this->dia_vencimento > $this->dia_fechamento ? $fechamento : $fechamento->copy()->addMonth();

        return $vencimento->format('Y-m');
    }

    public function dataVencimento(string $fatura): Carbon
    {
        $mes = Carbon::createFromFormat('Y-m-d', $fatura.'-01');

        return $mes->day(min($this->dia_vencimento, $mes->daysInMonth));
    }

    public function dataFechamento(string $fatura): Carbon
    {
        $mes = Carbon::createFromFormat('Y-m-d', $fatura.'-01');
        if ($this->dia_vencimento <= $this->dia_fechamento) {
            $mes->subMonth();
        }

        return $mes->day(min($this->dia_fechamento, $mes->daysInMonth));
    }
}
