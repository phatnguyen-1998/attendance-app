<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 出勤・退勤が正しいときだけ、休憩を出勤・退勤の範囲と比較する
        $clockIsValid = $this->clockRangeIsValid();

        return [
            'new_clock_in' => ['required'],
            'new_clock_out' => ['required', 'after:new_clock_in'],
            'new_break_in.*' => ['nullable', Rule::when($clockIsValid, ['after:new_clock_in', 'before:new_clock_out'])],
            'new_break_out.*' => ['nullable', 'after:new_break_in.*', Rule::when($clockIsValid, 'before:new_clock_out')],
            'comment' => ['required'],
        ];
    }

    public function messages(): array
    {
        $clock = '出勤時間もしくは退勤時間が不適切な値です';
        $break = '休憩時間が不適切な値です';

        return [
            'new_clock_in.required' => $clock,
            'new_clock_out.required' => $clock,
            'new_clock_out.after' => $clock,
            'new_break_in.*.after' => $break,
            'new_break_in.*.before' => $break,
            'new_break_out.*.after' => $break,
            'new_break_out.*.before' => '休憩時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
        ];
    }

    private function clockRangeIsValid(): bool
    {
        $in = strtotime((string) $this->new_clock_in);
        $out = strtotime((string) $this->new_clock_out);

        return $in && $out && $in < $out;
    }
}
