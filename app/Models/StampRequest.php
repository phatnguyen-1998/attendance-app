<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'approved_at' => 'datetime',
    ];

    public const STATUS_PENDING = 0;    // 承認待ち

    public const STATUS_APPROVED = 1;   // 承認済み

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 提供Bladeの `$application->AttendanceRecord` に合わせたリレーション名 */
    public function AttendanceRecord(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /** 提供Bladeの `$application->proposalBreaks` に合わせたリレーション名 */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(StampBreak::class);
    }

    /** 承認状態の表示用文字列（status: 0 = 承認待ち, 1 = 承認済み） */
    protected function approvalStatus(): Attribute
    {
        return Attribute::get(fn () => $this->status === self::STATUS_APPROVED ? '承認済み' : '承認待ち');
    }

    /** 申請の対象日（元の勤怠の日付） */
    protected function newDate(): Attribute
    {
        return Attribute::get(fn () => $this->AttendanceRecord->work_date);
    }

    /** 申請日（申請レコードの作成日時） */
    protected function applicationDate(): Attribute
    {
        return Attribute::get(fn () => $this->created_at);
    }
}
