<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use function Laravel\Prompts\Support\from;

class Reservation extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;
    const STATUS_CANCELLED = 2;

    protected $casts = [
        'price' => 'integer',
        'status' => 'integer',
        'start_date' => 'date',
        'end_date' => 'immutable_date'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function scopeActiveBetween(Builder $query, $from, $to): Builder
    {
        return $query
            ->whereStatus(Reservation::STATUS_ACTIVE)
            ->betweenDates($from, $to);
    }

    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->where(function ($query) use ($from, $to) {
            $query
                ->whereBetween('start_date', [$from, $to])
                ->orWhereBetween('end_date', [$from, $to])
                ->orWhere(function ($query) use ($from, $to){
                    $query
                        ->whereDate('start_date', '<', $from)
                        ->whereDate('end_date', '>', $to);
            });
        });
    }
}
