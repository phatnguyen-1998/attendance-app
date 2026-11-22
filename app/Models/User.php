<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => 'integer',
    ];

    public const ROLE_USER = 0;

    public const ROLE_ADMIN = 1;

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function stampRequests(): HasMany
    {
        return $this->hasMany(StampRequest::class);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function adminStatus(): Attribute
    {
        return Attribute::get(fn () => $this->role === self::ROLE_ADMIN);
    }

    /** 今日の勤怠から打刻ステータス（勤務外／出勤中／休憩中／退勤済）を返す */
    protected function attendanceStatus(): Attribute
    {
        return Attribute::get(function () {
            $attendance = $this->attendances()
                ->whereDate('work_date', today())
                ->with('breakTimes')
                ->first();

            if (! $attendance || ! $attendance->clock_in) {
                return '勤務外';
            }

            if ($attendance->clock_out) {
                return '退勤済';
            }

            // 終了していない休憩があれば休憩中
            return $attendance->breakTimes->whereNull('break_out')->isNotEmpty() ? '休憩中' : '出勤中';
        })->shouldCache();
    }
}
