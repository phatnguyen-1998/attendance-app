<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffController extends Controller
{/** スタッフ一覧（管理者）。一般ユーザーのみ表示する（FN041） */

    public function index(): View
    {
        // 一般ユーザーのみ表示する（管理者は含めない）
        $users = User::where('role', User::ROLE_USER)->orderBy('id')->get();

        return view('admin.staff-list', compact('users'));
    }
    /**
     * スタッフ別の月次勤怠一覧を1日1行で表示する（FN043・FN044）
     *
     * 一般ユーザー以外・存在しない ID は 404。
     */
    public function staff(Request $request, string $id): View
    {
        // 一般ユーザーのみ対象（管理者・存在しない ID は 404）
        $user = User::where('role', User::ROLE_USER)->findOrFail($id);

        $date = $this->parseMonth($request->query('date'));
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendances = $user->attendances()
            ->with('breakTimes')
            ->whereBetween('work_date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn($attendance) => $attendance->work_date->toDateString());

        // 1日1行で表示する（勤怠がない日は空欄）
        $formattedAttendanceRecords = collect(range(1, $date->daysInMonth))->map(function (int $day) use ($date, $attendances) {
            $targetDate = $date->copy()->day($day);
            $attendance = $attendances->get($targetDate->toDateString());

            return [
                'id' => $attendance?->id,
                'date' => $targetDate->isoFormat('MM/DD(ddd)'),
                'clock_in' => $attendance?->clock_in ? substr($attendance->clock_in, 0, 5) : '',
                'clock_out' => $attendance?->clock_out ? substr($attendance->clock_out, 0, 5) : '',
                'total_break_time' => $attendance?->total_break_time,
                'total_time' => $attendance?->total_time,
            ];
        });

        return view('admin.staff-attendance-list', compact('user', 'date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    /** ?date=YYYY-MM を月初の Carbon にする。未指定・不正な値は今月にする */
    private function parseMonth(?string $value): Carbon
    {
        try {
            $month = Carbon::createFromFormat('!Y-m', (string) $value);
        } catch (InvalidFormatException) {
            return today()->startOfMonth();
        }

        // 2026-13 のようにあふれた値は不正として扱う
        return $month->format('Y-m') === $value ? $month : today()->startOfMonth();
    }
}
