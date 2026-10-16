<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    protected $fillable = ['codigo', 'email', 'criado_por', 'usado_por', 'usado_em', 'expira_em'];

    protected function casts(): array
    {
        return ['usado_em' => 'datetime', 'expira_em' => 'datetime'];
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usado_por');
    }

    public function valido(?string $email = null): bool
    {
        if ($this->usado_em || ($this->expira_em && $this->expira_em->isPast())) {
            return false;
        }

        return ! $this->email || ! $email || strcasecmp($this->email, $email) === 0;
    }
}
