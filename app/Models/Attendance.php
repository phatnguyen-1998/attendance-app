<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'work_date', 'clock_in', 'clock_out', 'note'];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    public function stampRequests(): HasMany
    {
        return $this->hasMany(StampRequest::class);
    }

    protected function totalBreakTime(): Attribute
    {
        return Attribute::get(function () {
            $seconds = $this->breakSeconds();

            return $seconds > 0 ? gmdate('H:i:s', $seconds) : null;
        })->shouldCache();
    }

    protected function totalTime(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->clock_in || ! $this->clock_out) {
                return null;
            }

            $worked = Carbon::parse($this->clock_in)->diffInSeconds(Carbon::parse($this->clock_out)) - $this->breakSeconds();

            return gmdate('H:i:s', max($worked, 0));
        })->shouldCache();
    }

    /** 終了済みの休憩時間の合計（秒） */
    private function breakSeconds(): int
    {
        return $this->breakTimes
            ->filter(fn ($break) => $break->break_end)
            ->sum(fn ($break) => Carbon::parse($break->break_start)->diffInSeconds(Carbon::parse($break->break_end)));
    }
}
