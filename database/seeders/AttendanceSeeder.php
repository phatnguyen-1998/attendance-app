<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceSeeder extends Seeder
{
    /** ★ user1 のレポート確認用データ（過去5ヶ月 + 当月） */
    private function seedReportData(User $user): void
    {
        $normal = ['09:00:00', '18:00:00'];

        // 過去5ヶ月：各月の平日の先頭15日を通常勤務にする（15日 × 5ヶ月 = 75日）
        foreach (range(5, 1) as $monthsAgo) {
            $this->weekdays(today()->startOfMonth()->subMonths($monthsAgo))
                ->take(15)
                ->each(fn (Carbon $date) => $this->createAttendance($user, $date, ...$normal));
        }

        // 当月17日：通常10・残業3・遅刻2・早退1・長時間労働1
        $patterns = array_merge(
            array_fill(0, 10, $normal),
            array_fill(0, 3, ['09:00:00', '20:00:00']),
            array_fill(0, 2, ['09:30:00', '18:00:00']),
            [['09:00:00', '17:00:00']],
            [['08:00:00', '21:00:00']],
        );

        // 今日は打刻を試せるように作らない
        $this->weekdays(today()->startOfMonth())
            ->reject(fn (Carbon $date) => $date->isToday())
            ->values()
            ->take(17)
            ->each(fn (Carbon $date, int $i) => $this->createAttendance($user, $date, ...$patterns[$i]));
    }

    /** 指定月の平日（月〜金）を日付順で返す */
    private function weekdays(Carbon $month): Collection
    {
        return collect(range(1, $month->daysInMonth))
            ->map(fn (int $day) => $month->copy()->day($day))
            ->reject(fn (Carbon $date) => $date->isWeekend())
            ->values();
    }

    /** 勤怠1件と固定休憩（12:00-13:00）を作成する */
    private function createAttendance(User $user, Carbon $date, string $clockIn, string $clockOut): void
    {
        $attendance = Attendance::updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $date->toDateString()],
            ['clock_in' => $clockIn, 'clock_out' => $clockOut, 'note' => null],
        );

        $attendance->breakTimes()->delete();
        $attendance->breakTimes()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
    }

    /**
     * 一般ユーザーの勤怠・休憩ダミーデータを作成する。
     * 直近60日間の平日が対象。今日の分は作らない（勤怠登録画面で打刻を試せるようにするため）。
     */
    public function run(): void
    {
        $users = User::where('role', User::ROLE_USER)
            ->where('email', '!=', 'user1@example.com')
            ->get();

        if ($users->isEmpty()) {
            $this->command?->warn('一般ユーザーが存在しないためスキップしました。先に UserSeeder を実行してください。');

            return;
        }

        $today = Carbon::today();
        foreach ($users as $user) {

            for ($date = $today->copy()->subDays(60); $date->lt($today); $date->addDay()) {
                // 土日は勤務なし
                if ($date->isWeekend()) {
                    continue;
                }

                $day = $date->day + $user->id;

                $attendance = Attendance::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'work_date' => $date->toDateString(),
                    ],
                    [
                        // 出勤 9:00〜9:15 / 退勤 18:00〜18:20（日付で変化をつける）
                        'clock_in' => $date->copy()->setTime(9, ($day % 4) * 5)->format('H:i:s'),
                        'clock_out' => $date->copy()->setTime(18, ($day % 3) * 10)->format('H:i:s'),
                        'note' => $day % 10 === 0 ? '電車遅延のため' : null,
                    ]
                );

                // 再実行しても休憩が重複しないよう作り直す
                $attendance->breakTimes()->delete();

                // 昼休憩
                $attendance->breakTimes()->create([
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                // 5の倍数の日は午後にもう1回休憩
                if ($day % 5 === 0) {
                    $attendance->breakTimes()->create([
                        'break_in' => '15:00:00',
                        'break_out' => '15:15:00',
                    ]);
                }
            }
        }
        $user1 = User::where('email', 'user1@example.com')->first();
        if ($user1) {
            $this->seedReportData($user1);
        }
    }
}
