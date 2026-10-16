<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Financing extends Model
{
    protected $fillable = [
        'user_id', 'nome', 'banco', 'valor_imovel', 'valor_financiado', 'recursos_proprios', 'taxa_juros_anual',
        'sistema', 'prazo_meses', 'data_contrato', 'primeira_parcela', 'seguro_mensal', 'taxa_administracao', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor_imovel' => 'float',
            'valor_financiado' => 'float',
            'recursos_proprios' => 'float',
            'taxa_juros_anual' => 'float',
            'prazo_meses' => 'integer',
            'data_contrato' => 'date',
            'primeira_parcela' => 'date',
            'seguro_mensal' => 'float',
            'taxa_administracao' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function liberacoes(): HasMany
    {
        return $this->hasMany(FinancingRelease::class)->orderBy('data');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(FinancingExpense::class)->orderByDesc('data');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(FinancingPayment::class)->orderBy('data')->orderBy('id');
    }

    /** Taxa mensal equivalente à taxa anual efetiva informada. */
    public function taxaMensal(): float
    {
        return (1 + $this->taxa_juros_anual / 100) ** (1 / 12) - 1;
    }
}
