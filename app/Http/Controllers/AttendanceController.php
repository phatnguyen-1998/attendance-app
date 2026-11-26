<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Requests\AttendanceUpdateRequest;
use App\Models\StampRequest;
use App\Models\User;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /** 打刻画面を表示する（現在の日時とステータスを渡す）（FN018・FN019） */
    public function index(Request $request): View
    {
        $now = now();

        return view('user.attendance-register', [
            'user' => $request->user(),
            'formattedDate' => $now->isoFormat('YYYY年M月D日(ddd)'),
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 打刻処理（出勤・休憩入・休憩戻・退勤）を行う（FN020〜FN022）
     *
     * ステータスに合わない操作（例：勤務外での退勤）は何もしない。
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $status = $user->attendance_status;
        $time = now()->format('H:i:00'); // 秒は切り捨てる
        $attendance = $user->attendances()->whereDate('work_date', today())->first();

        match ($request->input('action')) {
            'clock_in' => $status === '勤務外'
                ? $user->attendances()->create(['work_date' => today(), 'clock_in' => $time])
                : null,
            'clock_out' => $status === '出勤中'
                ? $attendance->update(['clock_out' => $time])
                : null,
            'break_in' => $status === '出勤中'
                ? $attendance->breakTimes()->create(['break_in' => $time])
                : null,
            'break_out' => $status === '休憩中'
                ? $attendance->breakTimes()->whereNull('break_out')->latest('id')->first()?->update(['break_out' => $time])
                : null,
            default => null,
        };

        return redirect()->route('attendance.index');
    }

    /** ログインユーザーの月次勤怠一覧を表示する。?date=YYYY-MM で月を指定（FN023・FN024） */
    public function list(Request $request): View
    {
        $month = $this->parseMonth($request->query('date'));

        $attendances = $request->user()->attendances()
            ->with('breakTimes')
            ->whereBetween('work_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn ($attendance) => $attendance->work_date->toDateString());

        $records = collect(range(1, $month->daysInMonth))->map(function (int $day) use ($month, $attendances) {
            $date = $month->copy()->day($day);
            $attendance = $attendances->get($date->toDateString());

            return [
                'id' => $attendance?->id,
                'date' => $date->isoFormat('MM/DD(ddd)'),
                'clock_in' => $attendance?->clock_in ? substr($attendance->clock_in, 0, 5) : '',
                'clock_out' => $attendance?->clock_out ? substr($attendance->clock_out, 0, 5) : '',
                'total_break_time' => $attendance?->total_break_time,
                'total_time' => $attendance?->total_time,
            ];
        });

        return view('user.user-attendance-list', [
            'date' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $records,
        ]);
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

    /**
     * 勤怠詳細を表示する（FN026）
     *
     * 管理者は管理者用の詳細（PG09）に委譲する。一般ユーザーは自分の勤怠のみ（他人の ID は 404）。
     *
     * @param  string  $id  勤怠ID
     */
    public function show(Request $request, string $id): View
    {
        // 管理者は管理者用の勤怠詳細（PG09）で処理する
        if ($request->user()->role === User::ROLE_ADMIN) {
            return app(AdminAttendanceController::class)->show($request, $id);
        }
        $user = $request->user();
        // 自分の勤怠のみ閲覧可（他人の ID は 404）
        $attendance = $user->attendances()->with('breakTimes')->findOrFail($id);

        // 承認待ちの申請があれば、その内容を閲覧のみで表示する
        $pending = $attendance->stampRequests()
            ->where('status', StampRequest::STATUS_PENDING)
            ->with('proposalBreaks')
            ->latest('id')
            ->first();

        $breaks = ($pending ? $pending->proposalBreaks : $attendance->breakTimes)
            ->map(fn ($break) => [
                'break_in' => substr($break->break_in, 0, 5),
                'break_out' => $break->break_out ? substr($break->break_out, 0, 5) : '',
            ])
            ->values()
            ->all();

        return view('user.user-detail', [
            'user' => $user,
            'data' => [
                'id' => $attendance->id,
                'year' => $attendance->work_date->format('Y年'),
                'date' => $attendance->work_date->format('n月j日'),
                'clock_in' => substr($pending?->new_clock_in ?? $attendance->clock_in, 0, 5),
                'clock_out' => substr($pending?->new_clock_out ?? $attendance->clock_out ?? '', 0, 5),
                'breaks' => $breaks,
                'comment' => $pending?->comment ?? $attendance->note,
                'application' => $pending,
            ],
        ]);
    }

    /**
     * 勤怠の修正を行う（FN030）
     *
     * 管理者は直接修正、一般ユーザーは承認待ちの修正申請を作成する。承認待ちの申請がある間は何もしない。
     *
     * @param  string  $id  勤怠ID
     */
    public function update(AttendanceUpdateRequest $request, string $id): RedirectResponse
    {
        if ($request->user()->role === User::ROLE_ADMIN) {
            return app(AdminAttendanceController::class)->update($request, $id);
        }
        $attendance = $request->user()->attendances()->findOrFail($id);

        // 承認待ちの申請がある間は修正できない（FN027）
        if ($attendance->stampRequests()->where('status', StampRequest::STATUS_PENDING)->exists()) {
            return redirect()->route('attendance.show', $attendance->id);
        }

        DB::transaction(function () use ($request, $attendance) {
            $stampRequest = $attendance->stampRequests()->create([
                'user_id' => $request->user()->id,
                'new_clock_in' => $request->input('new_clock_in'),
                'new_clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
                'status' => StampRequest::STATUS_PENDING,
            ]);

            // 開始・終了の両方が入力された休憩のみ保存する
            $stampRequest->proposalBreaks()->createMany($request->breakTimes()->all());
        });

        return redirect()->route('attendance.show', $attendance->id);
    }
}
