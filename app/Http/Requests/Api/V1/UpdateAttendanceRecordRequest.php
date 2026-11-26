<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends StoreAttendanceRecordRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $attendance = $this->route('attendanceRecord');
        // clock_in が未送信なら、登録済みの出勤時刻と比較する
        $clockIn = $this->has('clock_in') ? 'clock_in' : $attendance->clock_in;

        $clockInRules = ['bail', 'sometimes', 'required', 'date_format:H:i:s'];

        // clock_out が未送信なら、登録済みの退勤時刻より前であることを確認する
        if (! $this->has('clock_out') && $attendance->clock_out) {
            $clockInRules[] = "before:{$attendance->clock_out}";
        }

        return [
            'date' => ['sometimes', 'required', 'date_format:Y-m-d', Rule::unique('attendances', 'work_date')->where('user_id', $attendance->user_id)->ignore($attendance)],
            'clock_in' => $clockInRules,
            'clock_out' => ['nullable', 'date_format:H:i:s', $clockIn ? "after:{$clockIn}" : 'nullable'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'clock_in.before' => '出勤時刻は退勤時刻より前の時刻を指定してください。',
        ];
    }
}
