<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /** 始業・終業時刻。遅刻・早退の判定基準（FN056） */
    private const WORK_START = '09:00:00';

    private const WORK_END = '18:00:00';

    /** 長時間労働の基準。1日10時間（600分）を超えた日 */
    private const LONG_WORK_MINUTES = 600;

    /** 1日の所定労働分数。これを超えた分を残業とする（FN053） */
    private const STANDARD_WORK_MINUTES = 480;

    /**
     * マイ勤怠レポートを表示する（FN052〜FN054・FN056）
     *
     * 今月を含む直近6ヶ月の勤怠から、総労働時間・総残業時間・1日あたりの平均労働時間を集計する。
     */
    public function index(): View
    {
        // 今月を含む直近6ヶ月（今月 + 前の5ヶ月）
        $from = today()->startOfMonth()->subMonths(5);

        $attendances = Auth::user()->attendances()
            ->with('breakTimes')
            ->where('work_date', '>=', $from->toDateString())
            ->get();

        // 出勤・退勤が揃っている日だけを対象にする
        $workMinutes = $attendances
            ->map(fn (Attendance $attendance) => $this->workMinutes($attendance))
            ->filter(fn (?int $minutes) => $minutes !== null);

        $summary = [
            'total_work_minutes' => $workMinutes->sum(),
            'total_overtime_minutes' => $workMinutes->sum(fn (int $minutes) => max($minutes - self::STANDARD_WORK_MINUTES, 0)),
            'avg_work_minutes' => $workMinutes->isEmpty() ? 0 : (int) round($workMinutes->avg()),
        ];
        // 労働時間が計算できる勤怠を月（Y-m）ごとにまとめる
        $byMonth = $attendances
            ->filter(fn (Attendance $attendance) => $this->workMinutes($attendance) !== null)
            ->groupBy(fn (Attendance $attendance) => $attendance->work_date->format('Y-m'));

        // 6ヶ月分を古い順に並べる。勤怠がない月は 0 にする
        $monthlyTrend = collect(range(0, 5))->map(function (int $i) use ($from, $byMonth) {
            $month = $from->copy()->addMonths($i)->format('Y-m');
            $minutes = $byMonth->get($month, collect())
                ->map(fn (Attendance $attendance) => $this->workMinutes($attendance));

            return [
                'month' => $month,
                'work_minutes' => $minutes->sum(),
                'overtime_minutes' => $minutes->sum(fn (int $workedMinutes) => max($workedMinutes - self::STANDARD_WORK_MINUTES, 0)),
            ];
        })->all();
        $thisMonth = $attendances->filter(fn (Attendance $attendance) => $attendance->work_date->gte(today()->startOfMonth()));

        $anomalies = [
            'late_count' => $thisMonth
                ->filter(fn (Attendance $attendance) => $attendance->clock_in && $attendance->clock_in > self::WORK_START)
                ->count(),
            'early_leave_count' => $thisMonth
                ->filter(fn (Attendance $attendance) => $attendance->clock_out && $attendance->clock_out < self::WORK_END)
                ->count(),
            'long_work_count' => $thisMonth
                ->filter(fn (Attendance $attendance) => ($this->workMinutes($attendance) ?? 0) > self::LONG_WORK_MINUTES)
                ->count(),
        ];

        return view('reports.index', compact('summary', 'monthlyTrend', 'anomalies'));
    }

    /**
     * 勤怠1件の労働時間（分）を返す。休憩は差し引き済み
     *
     * 出勤・退勤のどちらかが未入力なら null。
     */
    private function workMinutes(Attendance $attendance): ?int
    {
        if (! $attendance->total_time) {
            return null;
        }

        [$hours, $minutes] = explode(':', $attendance->total_time);

        return (int) $hours * 60 + (int) $minutes;
    }
}
