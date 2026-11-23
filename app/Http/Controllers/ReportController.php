<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /** 1日の所定労働分数。これを超えた分を残業とする（FN053） */
    private const STANDARD_WORK_MINUTES = 480;

    /**
     * マイ勤怠レポートを表示する（FN052・FN053）
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

        // 月次推移・異常検知は次のステップで実装する
        $monthlyTrend = [];
        $anomalies = ['late_count' => 0, 'early_leave_count' => 0, 'long_work_count' => 0];

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
