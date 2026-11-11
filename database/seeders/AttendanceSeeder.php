<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * 一般ユーザーの勤怠・休憩ダミーデータを作成する。
     * 直近60日間の平日が対象。今日の分は作らない（勤怠登録画面で打刻を試せるようにするため）。
     */
    public function run(): void
    {
        $users = User::where('role', User::ROLE_USER)->get();

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
    }
}
