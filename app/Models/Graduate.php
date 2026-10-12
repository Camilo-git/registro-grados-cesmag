<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Graduate extends Model
{
    // Al guardar datos del estudiante usa siempre campos explícitos/validados, nunca $request->all().
    protected $guarded = [];

    protected $casts = [
        'birth_date' => 'date',
        'profile_confirmed_at' => 'datetime',
        'attended_at' => 'datetime',
        'removed_from_list_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class)->orderBy('slot');
    }

    /** Cupo de invitados: excepción individual > cupo de la ceremonia > valor por defecto. */
    public function guestQuota(): int
    {
        return $this->guest_quota_override
            ?? $this->ceremony?->max_guests
            ?? config('graduation.default_max_guests');
    }
}
