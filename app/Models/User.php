<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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
}
