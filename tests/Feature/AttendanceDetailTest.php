<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-18 20:00:00'));

        $this->user = User::factory()->create(['name' => 'テスト太郎']);
        $this->attendance = Attendance::factory()->for($this->user)->create([
            'work_date' => '2026-11-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $this->attendance->breakTimes()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // 他のテストに影響しないよう現在日時を元に戻す
        parent::tearDown();
    }

    /** 正しい修正内容。各テストでは一部だけ上書きして使う */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00', ''],
            'new_break_out' => ['13:00', ''],
            'comment' => '電車遅延のため',
        ], $overrides);
    }

    /** 修正を送信する（エラー時に勤怠詳細へ戻るよう遷移元を指定） */
    private function submit(array $overrides = []): TestResponse
    {
        $url = '/attendance/'.$this->attendance->id;

        return $this->actingAs($this->user)->from($url)->post($url, $this->validData($overrides));
    }

    // ===== ID 10：勤怠詳細情報取得機能 =====

    /** 「名前」がログインユーザーの氏名になっていること */
    public function test_勤怠詳細画面の名前がログインユーザーの氏名になっている(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->attendance->id)
            ->assertSee('value="テスト太郎"', false);
    }

    /** 「日付」が選択した日付（2026年 / 11月10日）になっていること */
    public function test_勤怠詳細画面の日付が選択した日付になっている(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->attendance->id)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月10日"', false);
    }

    /** 「出勤・退勤」の時間が、打刻（09:00 / 18:00）と一致していること */
    public function test_出勤退勤の時間がログインユーザーの打刻と一致している(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->attendance->id)
            ->assertSee('name="new_clock_in" value="09:00"', false)
            ->assertSee('name="new_clock_out" value="18:00"', false);
    }

    /** 「休憩」の時間が、打刻（12:00〜13:00）と一致していること */
    public function test_休憩の時間がログインユーザーの打刻と一致している(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->attendance->id)
            ->assertSee('name="new_break_in[0]" value="12:00"', false)
            ->assertSee('name="new_break_out[0]" value="13:00"', false);
    }

    // ===== ID 11：勤怠詳細情報修正機能 =====

    /** 出勤時間が退勤時間より後の場合、エラーメッセージが表示されること */
    public function test_出勤時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_clock_in' => '19:00', 'new_clock_out' => '18:00'])
            ->assertRedirect('/attendance/'.$this->attendance->id)
            ->assertSessionHasErrors(['new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です']);

        $this->get('/attendance/'.$this->attendance->id)->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    /** 休憩開始時間が退勤時間より後の場合、エラーメッセージが表示されること */
    public function test_休憩開始時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => ['19:00', ''], 'new_break_out' => ['19:30', '']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    /** 休憩終了時間が退勤時間より後の場合、エラーメッセージが表示されること */
    public function test_休憩終了時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => ['17:00', ''], 'new_break_out' => ['19:00', '']])
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    /** 備考欄が未入力の場合、エラーメッセージが表示されること */
    public function test_備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        $this->submit(['comment' => ''])
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    /** エラー時は修正申請が作成されず、勤怠も変更されないこと */
    public function test_エラーの場合は修正申請が作成されない(): void
    {
        $this->submit(['comment' => '']);

        $this->assertSame(0, StampRequest::count());
        $this->assertSame('09:00:00', $this->attendance->fresh()->clock_in);
    }

    /** 修正を保存すると承認待ちの修正申請が作成され、勤怠本体は承認までは変更されないこと */
    public function test_修正申請処理が実行される(): void
    {
        $this->submit()
            ->assertRedirect('/attendance/'.$this->attendance->id)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stamp_requests', [
            'user_id' => $this->user->id,
            'attendance_id' => $this->attendance->id,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '電車遅延のため',
            'status' => StampRequest::STATUS_PENDING,
        ]);
        $this->assertSame('09:00:00', $this->attendance->fresh()->clock_in);
    }

    /** 申請後の勤怠詳細は、承認待ちの内容を閲覧のみで表示し、修正ボタンは出ず、メッセージが表示されること */
    public function test_承認待ちの申請は修正できない(): void
    {
        $this->submit();

        $this->actingAs($this->user)->get('/attendance/'.$this->attendance->id)
            ->assertSee('承認待ちのため修正できません')
            ->assertSee('value="09:30"', false)
            ->assertDontSee('type="submit">修正', false);

        // 承認待ちの間に再送信しても、申請は増えない
        $this->submit(['comment' => '二重申請']);
        $this->assertSame(1, StampRequest::count());
    }

    /** 申請が、管理者の申請一覧と承認画面に表示されること */
    public function test_修正申請が管理者の申請一覧と承認画面に表示される(): void
    {
        $this->submit();
        $application = StampRequest::sole();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/stamp_correction_request/list')
            ->assertSee('テスト太郎')
            ->assertSee('電車遅延のため')
            ->assertSee('/stamp_correction_request/approve/'.$application->id, false);

        $this->actingAs($admin)->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('テスト太郎')
            ->assertSee('value="09:30"', false)
            ->assertSee('value="18:30"', false)
            ->assertSee('電車遅延のため');
    }
}
