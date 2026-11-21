<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceUpdateRequest;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{/** 日次勤怠一覧（管理者）。?date=YYYY-MM-DD で日付を指定し、未指定は今日（FN034・FN035） */

    public function index(Request $request): View
    {
        // ?date=YYYY-MM-DD。未指定・不正な値は今日にする
        $date = Validator::make($request->query(), ['date' => 'required|date_format:Y-m-d'])->passes()
            ? Carbon::createFromFormat('!Y-m-d', $request->query('date'))
            : today();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            'users' => User::where('role', User::ROLE_USER)->orderBy('id')->get(),
            'attendanceRecords' => Attendance::with('breakTimes')->whereDate('work_date', $date)->get(),
        ]);
    }
    /** 勤怠詳細（管理者）（PG09・FN037） */

    public function show(Request $request, string $id): View
    {
        $attendance = Attendance::with(['user', 'breakTimes'])->findOrFail($id);

        $user = $attendance->user;

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $attendance->work_date->format('Y年'),
            'date' => $attendance->work_date->format('n月j日'),
            'clock_in' => substr($attendance->clock_in, 0, 5),
            'clock_out' => substr($attendance->clock_out ?? '', 0, 5),
            'breaks' => $attendance->breakTimes
                ->map(fn($break) => [
                    'break_in' => substr($break->break_in, 0, 5),
                    'break_out' => $break->break_out ? substr($break->break_out, 0, 5) : '',
                ])
                ->values()
                ->all(),
            'comment' => $attendance->note,
        ];

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }
    /** 勤怠を直接修正し、休憩は入力内容で置き換える（FN040） */

    public function update(AttendanceUpdateRequest $request, string $id): RedirectResponse
    {
        $attendance = Attendance::findOrFail($id);

        DB::transaction(function () use ($request, $attendance) {
            $attendance->update([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'note' => $request->input('comment'),
            ]);

            // 休憩は入力内容で置き換える（開始・終了の両方が入力された行のみ保存）
            $attendance->breakTimes()->delete();
            $attendance->breakTimes()->createMany($request->breakTimes()->all());

        });

        return redirect()->route('attendance.show', $attendance->id);
    }
}
