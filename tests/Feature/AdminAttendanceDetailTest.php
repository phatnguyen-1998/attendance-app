<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** テストケース ID 13：勤怠詳細情報取得・修正機能（管理者） */
class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-18 20:00:00'));

        $this->admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'テスト太郎']);
        $this->attendance = Attendance::factory()->for($user)->create([
            'work_date' => '2026-11-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'note' => '既存の備考',
        ]);
        $this->attendance->breakTimes()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00', ''],
            'new_break_out' => ['13:00', ''],
            'comment' => '管理者による修正',
        ], $overrides);
    }

    private function submit(array $overrides = []): TestResponse
    {
        $url = '/attendance/'.$this->attendance->id;

        return $this->actingAs($this->admin)->from($url)->post($url, $this->validData($overrides));
    }

    /** 詳細画面の内容が選択した勤怠情報と一致すること */
    public function test_勤怠詳細画面に表示されるデータが選択したものになっている(): void
    {
        $this->actingAs($this->admin)->get('/attendance/'.$this->attendance->id)
            ->assertOk()
            ->assertSee('value="テスト太郎"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月10日"', false)
            ->assertSeeInOrder(['name="new_clock_in"', 'value="09:00"'], false)
            ->assertSeeInOrder(['name="new_clock_out"', 'value="18:00"'], false)
            ->assertSeeInOrder(['name="new_break_in[0]"', 'value="12:00"'], false)
            ->assertSeeInOrder(['name="new_break_out[0]"', 'value="13:00"'], false)
            ->assertSee('既存の備考');
    }

    public function test_出勤時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_clock_in' => '19:00', 'new_clock_out' => '18:00'])
            ->assertRedirect('/attendance/'.$this->attendance->id)
            ->assertSessionHasErrors(['new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です']);

        $this->get('/attendance/'.$this->attendance->id)->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    public function test_休憩開始時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => ['19:00', ''], 'new_break_out' => ['19:30', '']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_休憩終了時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => ['17:00', ''], 'new_break_out' => ['19:00', '']])
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        $this->submit(['comment' => ''])
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);

        $this->assertSame('09:00:00', $this->attendance->fresh()->clock_in);
    }

    /** 管理者の修正は申請を介さず、勤怠に直接反映されること */
    public function test_管理者の修正は勤怠に直接反映される(): void
    {
        $this->submit()->assertRedirect('/attendance/'.$this->attendance->id)->assertSessionHasNoErrors();

        $attendance = $this->attendance->fresh();
        $this->assertSame('09:30:00', $attendance->clock_in);
        $this->assertSame('18:30:00', $attendance->clock_out);
        $this->assertSame('管理者による修正', $attendance->note);
        $this->assertSame(0, StampRequest::count());
    }
}
