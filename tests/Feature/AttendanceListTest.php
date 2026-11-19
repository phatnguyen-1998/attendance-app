<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 現在日時を固定する（2026-11-18 は水曜日）
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:00:00'));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // 他のテストに影響しないよう現在日時を元に戻す
        parent::tearDown();
    }

    private function createAttendance(string $date, array $attributes = [], ?User $user = null): Attendance
    {
        return Attendance::factory()->for($user ?? $this->user)->create(['work_date' => $date, ...$attributes]);
    }

    /** 自分が行った勤怠情報が全て表示され、他人の勤怠は表示されないこと */
    public function test_自分が行った勤怠情報が全て表示されている(): void
    {
        $this->createAttendance('2026-11-02', ['clock_in' => '09:01:00', 'clock_out' => '18:01:00']);
        $this->createAttendance('2026-11-03', ['clock_in' => '09:02:00', 'clock_out' => '18:02:00']);
        $this->createAttendance('2026-11-04', ['clock_in' => '09:03:00', 'clock_out' => '18:03:00']);
        // 他のユーザーの勤怠（表示されないこと）
        $this->createAttendance('2026-11-05', ['clock_in' => '07:07:00'], User::factory()->create());

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSeeInOrder(['11/02(月)', '09:01', '18:01', '11/03(火)', '09:02', '18:02', '11/04(水)', '09:03', '18:03'])
            ->assertDontSee('07:07');
    }

    /** 勤怠一覧を開いた際に、現在の月が表示されること */
    public function test_勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        $this->actingAs($this->user)->get('/attendance/list')
            ->assertOk()
            ->assertSee('2026/11');
    }

    /** 「前月」リンクを開くと、前月（2026/10）の勤怠情報が表示されること */
    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        $this->createAttendance('2026-10-15', ['clock_in' => '09:10:00']);
        $this->createAttendance('2026-11-10', ['clock_in' => '09:20:00']);

        // 「前月」ボタンのリンク先
        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('href="?date=2026-10"', false);

        $this->actingAs($this->user)->get('/attendance/list?date=2026-10')
            ->assertSee('2026/10')
            ->assertSee('10/15(木)')
            ->assertSee('09:10')
            ->assertDontSee('09:20');
    }

    /** 「翌月」リンクを開くと、翌月（2026/12）の勤怠情報が表示されること */
    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        $this->createAttendance('2026-12-07', ['clock_in' => '09:30:00']);
        $this->createAttendance('2026-11-10', ['clock_in' => '09:20:00']);

        // 「翌月」ボタンのリンク先
        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('href="?date=2026-12"', false);

        $this->actingAs($this->user)->get('/attendance/list?date=2026-12')
            ->assertSee('2026/12')
            ->assertSee('12/07(月)')
            ->assertSee('09:30')
            ->assertDontSee('09:20');
    }

    /** 「詳細」リンクが、その日の勤怠詳細画面（/attendance/{id}）を指し、開けること */
    public function test_詳細を押下すると_その日の勤怠詳細画面に遷移する(): void
    {
        $attendance = $this->createAttendance('2026-11-10');

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('href="'.url('/attendance/'.$attendance->id).'"', false);

        $this->actingAs($this->user)->get('/attendance/'.$attendance->id)
            ->assertOk()
            ->assertSee('11月10日');
    }
}
