<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    public const TIPOS = [
        'corrente' => 'Conta corrente',
        'poupanca' => 'Poupança',
        'corretora' => 'Corretora',
        'cripto' => 'Carteira de cripto',
        'dinheiro' => 'Dinheiro em espécie',
    ];

    protected $fillable = ['user_id', 'nome', 'tipo', 'saldo_inicial', 'arquivada'];

    protected function casts(): array
    {
        return ['saldo_inicial' => 'float', 'arquivada' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function nomeTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
