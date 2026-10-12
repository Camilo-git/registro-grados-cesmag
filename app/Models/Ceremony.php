<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Ceremony extends Model
{
    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
    ];

    public function graduates(): HasMany
    {
        return $this->hasMany(Graduate::class);
    }

    // hasManyThrough: una ceremonia no tiene invitados directamente, llega a
    // ellos "a través de" sus graduados (Ceremony -> Graduate -> Guest).
    // Laravel arma el JOIN solo, usando ceremony_id en graduates y
    // graduate_id en guests. Esto es lo que permite que CeremoniesTable
    // pueda contar "guests" y "guests_attended_count" con withCount().
    public function guests(): HasManyThrough
    {
        return $this->hasManyThrough(Guest::class, Graduate::class);
    }
}
