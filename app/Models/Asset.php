<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $fillable = [
        'user_id', 'ticker', 'classe', 'quantidade', 'preco_medio', 'cotacao', 'nota', 'criterios',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'float',
            'preco_medio' => 'float',
            'cotacao' => 'float',
            'nota' => 'float',
            'criterios' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Valor de mercado: quantidade × cotação. */
    public function valor(): float
    {
        return $this->quantidade * $this->cotacao;
    }

    /** Quanto foi pago: quantidade × preço médio. */
    public function custo(): float
    {
        return $this->quantidade * $this->preco_medio;
    }

    public function resultadoPercentual(): float
    {
        return $this->custo() > 0 ? ($this->valor() / $this->custo() - 1) * 100 : 0;
    }

    public function nomeClasse(): string
    {
        return config("financas.classes.{$this->classe}.nome", $this->classe);
    }

    public function cor(): string
    {
        return config("financas.classes.{$this->classe}.cor", 's1');
    }
}
