<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationTarget extends Model
{
    protected $fillable = ['user_id', 'classe', 'percentual'];

    protected function casts(): array
    {
        return ['percentual' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
