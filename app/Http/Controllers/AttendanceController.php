<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $now = now();

        return view('user.attendance-register', [
            'user' => $request->user(),
            'formattedDate' => $now->isoFormat('YYYY年M月D日(ddd)'),
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $status = $user->attendance_status;
        $time = now()->toTimeString();   // 'H:i:s'

        $attendance = $user->attendances()->whereDate('work_date', today())->first();

        match ($request->input('action')) {
            'clock_in' => $status === '勤務外'
                ? $user->attendances()->create(['work_date' => today(), 'clock_in' => $time])
                : null,
            'clock_out' => $status === '出勤中'
                ? $attendance->update(['clock_out' => $time])
                : null,
            'break_in' => $status === '出勤中'
                ? $attendance->breakTimes()->create(['break_start' => $time])
                : null,
            'break_out' => $status === '休憩中'
                ? $attendance->breakTimes()->whereNull('break_end')->latest('id')->first()?->update(['break_end' => $time])
                : null,
            default => null,
        };

        return redirect()->route('attendance.index');
    }
}
