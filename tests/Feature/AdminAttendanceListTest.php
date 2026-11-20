<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** テストケース ID 12：勤怠一覧情報取得機能（管理者） */
class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-18 20:00:00'));
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** その日の全ユーザーの勤怠情報が正確な値で表示されること */
    public function test_その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
    {
        $taro = User::factory()->create(['name' => 'テスト太郎']);
        $hanako = User::factory()->create(['name' => 'テスト花子']);

        $a1 = Attendance::factory()->for($taro)->create(['work_date' => '2026-11-18', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        $a1->breakTimes()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        $a2 = Attendance::factory()->for($hanako)->create(['work_date' => '2026-11-18', 'clock_in' => '10:00:00', 'clock_out' => '19:30:00']);
        // 別の日の勤怠は表示されない
        Attendance::factory()->for($taro)->create(['work_date' => '2026-11-17', 'clock_in' => '07:07:00', 'clock_out' => '16:16:00']);

        $response = $this->actingAs($this->admin)->get('/admin/attendance/list')->assertOk();

        $response->assertSeeInOrder(['テスト太郎', '09:00', '18:00', '1:00', '8:00'], false);
        $response->assertSeeInOrder(['テスト花子', '10:00', '19:30'], false);
        $response->assertSee('/attendance/'.$a1->id, false);
        $response->assertSee('/attendance/'.$a2->id, false);
        $response->assertDontSee('07:07');
    }

    /** 遷移した際に現在の日付が表示されること */
    public function test_遷移した際に現在の日付が表示される(): void
    {
        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertOk()
            ->assertSee('2026/11/18')
            ->assertSee('2026年11月18日の勤怠');
    }

    /** 「前日」を押下すると前日の勤怠情報が表示されること */
    public function test_前日を押下した時に前の日の勤怠情報が表示される(): void
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);
        Attendance::factory()->for($user)->create(['work_date' => '2026-11-17', 'clock_in' => '08:15:00', 'clock_out' => '17:45:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/list')->assertSee('href="?date=2026-11-17"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/list?date=2026-11-17')
            ->assertSee('2026/11/17')
            ->assertSee('テスト太郎')
            ->assertSee('08:15')
            ->assertSee('17:45');
    }

    /** 「翌日」を押下すると翌日の勤怠情報が表示されること */
    public function test_翌日を押下した時に次の日の勤怠情報が表示される(): void
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);
        Attendance::factory()->for($user)->create(['work_date' => '2026-11-19', 'clock_in' => '08:20:00', 'clock_out' => '17:50:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/list')->assertSee('href="?date=2026-11-19"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/list?date=2026-11-19')
            ->assertSee('2026/11/19')
            ->assertSee('テスト太郎')
            ->assertSee('08:20')
            ->assertSee('17:50');
    }
}
