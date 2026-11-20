<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** テストケース ID 14：ユーザー情報取得機能（管理者） */
class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-18 20:00:00'));

        $this->admin = User::factory()->admin()->create(['name' => '管理者太郎', 'email' => 'admin-only@example.com']);
        $this->staff = User::factory()->create(['name' => 'テスト太郎', 'email' => 'taro@example.com']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** 全一般ユーザーの氏名とメールアドレスが表示され、管理者は含まれないこと */
    public function test_管理者ユーザーが全一般ユーザーの氏名とメールアドレスを確認できる(): void
    {
        User::factory()->create(['name' => 'テスト花子', 'email' => 'hanako@example.com']);

        $this->actingAs($this->admin)->get('/admin/staff/list')
            ->assertOk()
            ->assertSee('テスト太郎')
            ->assertSee('taro@example.com')
            ->assertSee('テスト花子')
            ->assertSee('hanako@example.com')
            ->assertDontSee('admin-only@example.com');
    }

    public function test_ユーザーの勤怠情報が正しく表示される(): void
    {
        $attendance = Attendance::factory()->for($this->staff)->create(['work_date' => '2026-11-10', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        $attendance->breakTimes()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        // 他ユーザーの勤怠は表示されない
        Attendance::factory()->for(User::factory()->create())->create(['work_date' => '2026-11-11', 'clock_in' => '07:07:00', 'clock_out' => '16:16:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertOk()
            ->assertSee('テスト太郎さんの勤怠')
            ->assertSee('2026/11')
            ->assertSeeInOrder(['11/10', '09:00', '18:00', '1:00', '8:00'])
            ->assertDontSee('07:07');
    }

    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        Attendance::factory()->for($this->staff)->create(['work_date' => '2026-10-05', 'clock_in' => '08:30:00', 'clock_out' => '17:30:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="?date=2026-10"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-10')
            ->assertSee('2026/10')
            ->assertSee('10/05')
            ->assertSee('08:30');
    }

    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        Attendance::factory()->for($this->staff)->create(['work_date' => '2026-12-05', 'clock_in' => '08:40:00', 'clock_out' => '17:40:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="?date=2026-12"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-12')
            ->assertSee('2026/12')
            ->assertSee('12/05')
            ->assertSee('08:40');
    }

    public function test_詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $attendance = Attendance::factory()->for($this->staff)->create(['work_date' => '2026-11-10']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="'.url('/attendance/'.$attendance->id).'"', false);

        $this->actingAs($this->admin)->get('/attendance/'.$attendance->id)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('value="11月10日"', false);
    }
}
