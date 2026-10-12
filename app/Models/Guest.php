<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'birth_date' => 'date',
        'has_disability' => 'boolean',
        'added_at_door' => 'boolean',
        'attended_at' => 'datetime',
    ];

    public function graduate(): BelongsTo
    {
        return $this->belongsTo(Graduate::class);
    }
}
