<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\StampRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StampRequest>
 */
class StampRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [

            'attendance_id' => Attendance::factory(),
            // 申請者は勤怠の持ち主にそろえる

            'user_id' => fn (array $attributes) => Attendance::find($attributes['attendance_id'])->user_id,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '電車遅延のため',
            'status' => StampRequest::STATUS_PENDING,
        ];
    }

    /** 承認済み */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StampRequest::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }
}
