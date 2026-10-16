<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'user_id', 'ticker', 'classe', 'quantidade', 'preco_medio', 'cotacao', 'nota', 'criterios',
        'atualizacao_automatica', 'variacao_dia', 'cotacao_atualizada_em',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'float',
            'preco_medio' => 'float',
            'cotacao' => 'float',
            'nota' => 'float',
            'criterios' => 'array',
            'atualizacao_automatica' => 'boolean',
            'variacao_dia' => 'float',
            'cotacao_atualizada_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    public function dividends(): HasMany
    {
        return $this->hasMany(Dividend::class);
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

    /** A cotação deste ativo pode vir de uma API (B3 ou cripto)? */
    public function temFonteAutomatica(): bool
    {
        return isset(config('cotacoes.fontes')[$this->classe]);
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
