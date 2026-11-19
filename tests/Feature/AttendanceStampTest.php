<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** テストケース ID 4〜8：日時取得・ステータス確認・出勤・休憩・退勤 */
class AttendanceStampTest extends TestCase
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

    /** 打刻画面を開く。ステータスはユーザーにキャッシュされるため毎回取り直す */
    private function openStampPage(): TestResponse
    {
        return $this->actingAs($this->user->fresh())->get('/attendance');
    }

    /** 指定時刻に打刻する */
    private function stamp(string $action, string $time): TestResponse
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 '.$time));

        return $this->actingAs($this->user->fresh())->post('/attendance', ['action' => $action]);
    }

    /** 今日の勤怠を作る（既定は出勤中。clock_out を渡すと退勤済） */
    private function createTodayAttendance(array $attributes = []): Attendance
    {
        return Attendance::factory()->for($this->user)->create([
            'work_date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
            ...$attributes,
        ]);
    }

    // ===== ID 4：日時取得機能 =====

    /** 打刻画面に表示される日付・時刻が、現在日時と画面の表示形式（YYYY年M月D日(曜日) / HH:MM）で一致すること */
    public function test_現在の日時情報が画面と同じ形式で出力されている(): void
    {
        $this->openStampPage()
            ->assertSee('value="2026年11月18日(水)"', false)
            ->assertSee('value="09:00"', false);
    }

    // ===== ID 5：ステータス確認機能 =====

    /** 今日の勤怠が無いユーザーには「勤務外」が表示されること */
    public function test_勤務外の場合_勤怠ステータスが正しく表示される(): void
    {
        $this->openStampPage()->assertSee('勤務外');
    }

    /** 出勤済みで退勤していないユーザーには「出勤中」が表示されること */
    public function test_出勤中の場合_勤怠ステータスが正しく表示される(): void
    {
        $this->createTodayAttendance();

        $this->openStampPage()->assertSee('出勤中');
    }

    /** 休憩入のみで休憩戻が無いユーザーには「休憩中」が表示されること */
    public function test_休憩中の場合_勤怠ステータスが正しく表示される(): void
    {
        $this->createTodayAttendance()->breakTimes()->create(['break_in' => '08:50:00']);

        $this->openStampPage()->assertSee('休憩中');
    }

    /** 退勤時刻が記録済みのユーザーには「退勤済」が表示されること */
    public function test_退勤済の場合_勤怠ステータスが正しく表示される(): void
    {
        $this->createTodayAttendance(['clock_out' => '08:59:00']);

        $this->openStampPage()->assertSee('退勤済');
    }

    // ===== ID 6：出勤機能 =====

    /** 勤務外で「出勤」ボタンが表示され、押すとステータスが「出勤中」になること */
    public function test_出勤ボタンが正しく機能する(): void
    {
        $this->openStampPage()->assertSee('>出勤</button>', false);

        $this->stamp('clock_in', '09:00:00')->assertRedirect('/attendance');

        $this->openStampPage()->assertSee('出勤中');
    }

    /** 退勤済のユーザーには「出勤」ボタンが表示されず、出勤を直接送っても勤怠が増えない（出勤は1日1回）こと */
    public function test_出勤は一日一回のみできる(): void
    {
        $this->createTodayAttendance(['clock_out' => '08:59:00']);

        $this->openStampPage()->assertDontSee('>出勤</button>', false);

        // 退勤済で出勤を送っても勤怠は増えない
        $this->stamp('clock_in', '09:00:00');
        $this->assertSame(1, $this->user->attendances()->count());
    }

    /** 打刻した出勤時刻が、勤怠一覧の該当日の行に表示されること */
    public function test_出勤時刻が勤怠一覧画面で確認できる(): void
    {
        $this->stamp('clock_in', '09:00:00');

        $this->actingAs($this->user->fresh())->get('/attendance/list')
            ->assertSeeInOrder(['11/18(水)', '09:00']);
    }

    // ===== ID 7：休憩機能 =====

    /** 出勤中に「休憩入」ボタンが表示され、押すとステータスが「休憩中」になること */
    public function test_休憩ボタンが正しく機能する(): void
    {
        $this->createTodayAttendance();
        $this->openStampPage()->assertSee('>休憩入</button>', false);

        $this->stamp('break_in', '12:00:00');

        $this->openStampPage()->assertSee('休憩中');
    }

    /** 休憩入→休憩戻の後でも「休憩入」ボタンが再び表示される（休憩は1日に何回でもできる）こと */
    public function test_休憩は一日に何回でもできる(): void
    {
        $this->createTodayAttendance();

        $this->stamp('break_in', '12:00:00');
        $this->stamp('break_out', '12:30:00');

        $this->openStampPage()->assertSee('>休憩入</button>', false);
    }

    /** 休憩中に「休憩戻」ボタンが表示され、押すとステータスが「出勤中」に戻ること */
    public function test_休憩戻ボタンが正しく機能する(): void
    {
        $this->createTodayAttendance();

        $this->stamp('break_in', '12:00:00');
        $this->openStampPage()->assertSee('>休憩戻</button>', false);
        $this->stamp('break_out', '12:30:00');

        $this->openStampPage()->assertSee('出勤中');
    }

    /** 休憩入→休憩戻→再度休憩入の後でも「休憩戻」ボタンが表示される（休憩戻も何回でもできる）こと */
    public function test_休憩戻は一日に何回でもできる(): void
    {
        $this->createTodayAttendance();

        $this->stamp('break_in', '12:00:00');
        $this->stamp('break_out', '12:30:00');
        $this->stamp('break_in', '15:00:00');

        $this->openStampPage()->assertSee('>休憩戻</button>', false);
    }

    /** 休憩した時間が、勤怠一覧に合計（0:30）として表示され、DBにも正確な開始・終了時刻が記録されること */
    public function test_休憩時刻が勤怠一覧画面で確認できる(): void
    {
        $this->createTodayAttendance();

        $this->stamp('break_in', '12:00:00');
        $this->stamp('break_out', '12:30:00');

        // 一覧には休憩の合計時間が表示される
        $this->actingAs($this->user->fresh())->get('/attendance/list')
            ->assertSeeInOrder(['11/18(水)', '09:00', '0:30']);
        $this->assertDatabaseHas('break_times', ['break_in' => '12:00:00', 'break_out' => '12:30:00']);
    }

    // ===== ID 8：退勤機能 =====

    /** 出勤中に「退勤」ボタンが表示され、押すとステータスが「退勤済」になること */
    public function test_退勤ボタンが正しく機能する(): void
    {
        $this->createTodayAttendance();
        $this->openStampPage()->assertSee('>退勤</button>', false);

        $this->stamp('clock_out', '18:00:00');

        $this->openStampPage()->assertSee('退勤済');
    }

    /** 退勤後は操作ボタンが一つも表示されず、休憩入・退勤を直接送っても勤怠が変更されないこと */
    public function test_退勤後は打刻できない(): void
    {
        $attendance = $this->createTodayAttendance(['clock_out' => '18:00:00']);

        // 退勤済のユーザーは操作ボタンが一つも表示されない
        $this->openStampPage()
            ->assertDontSee('>出勤</button>', false)
            ->assertDontSee('>退勤</button>', false)
            ->assertDontSee('>休憩入</button>', false)
            ->assertDontSee('>休憩戻</button>', false);

        // ボタンがなくても直接 POST された場合に、勤怠が変わらないこと
        $this->stamp('break_in', '19:00:00');
        $this->stamp('clock_out', '20:00:00');

        $this->assertSame('18:00:00', $attendance->fresh()->clock_out);
        $this->assertSame(0, $attendance->breakTimes()->count());
    }

    /** 出勤→退勤の打刻後、勤怠一覧の該当日の行に出勤・退勤の両方の時刻が表示されること */
    public function test_退勤時刻が勤怠一覧画面で確認できる(): void
    {
        $this->stamp('clock_in', '09:00:00');
        $this->stamp('clock_out', '18:00:00');

        $this->actingAs($this->user->fresh())->get('/attendance/list')
            ->assertSeeInOrder(['11/18(水)', '09:00', '18:00']);
    }
}
