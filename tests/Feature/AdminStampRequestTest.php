<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** テストケース ID 15：勤怠情報修正機能（管理者） */
class AdminStampRequestTest extends TestCase
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

    private function createRequest(string $comment, bool $approved = false, ?User $user = null): StampRequest
    {
        $user ??= User::factory()->create();
        $attendance = Attendance::factory()->for($user)->create(['work_date' => '2026-11-10', 'note' => '元の備考']);
        $factory = StampRequest::factory()->for($attendance, 'AttendanceRecord');

        return ($approved ? $factory->approved() : $factory)->create(['comment' => $comment]);
    }

    /** 申請一覧を「承認待ち」「承認済み」のタブごとの HTML に分けて返す */
    private function openTabs(): array
    {
        $html = $this->actingAs($this->admin)->get('/stamp_correction_request/list')->assertOk()->getContent();

        return explode('id="content2"', $html, 2);
    }

    public function test_承認待ちの修正申請が全て表示されている(): void
    {
        $this->createRequest('理由その１');
        $this->createRequest('理由その２');
        $this->createRequest('承認済みの理由', approved: true);

        [$pending] = $this->openTabs();

        $this->assertStringContainsString('理由その１', $pending);
        $this->assertStringContainsString('理由その２', $pending);
        $this->assertStringNotContainsString('承認済みの理由', $pending);
    }

    public function test_承認済みの修正申請が全て表示されている(): void
    {
        $this->createRequest('承認済みその１', approved: true);
        $this->createRequest('承認済みその２', approved: true);
        $this->createRequest('承認待ちの理由');

        [, $approved] = $this->openTabs();

        $this->assertStringContainsString('承認済みその１', $approved);
        $this->assertStringContainsString('承認済みその２', $approved);
        $this->assertStringNotContainsString('承認待ちの理由', $approved);
    }

    public function test_修正申請の詳細内容が正しく表示されている(): void
    {
        $application = $this->createRequest('電車遅延のため', user: User::factory()->create(['name' => 'テスト太郎']));
        $application->proposalBreaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        $this->actingAs($this->admin)->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="テスト太郎"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月10日"', false)
            ->assertSee('value="09:30"', false)
            ->assertSee('value="18:30"', false)
            ->assertSee('value="12:00"', false)
            ->assertSee('value="13:00"', false)
            ->assertSee('電車遅延のため')
            ->assertSee('type="submit">承認', false);
    }

    public function test_修正申請の承認処理が正しく行われる(): void
    {
        $application = $this->createRequest('電車遅延のため');
        $application->proposalBreaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        $this->actingAs($this->admin)->post('/stamp_correction_request/approve/'.$application->id)
            ->assertRedirect('/stamp_correction_request/approve/'.$application->id);

        $application->refresh();
        $this->assertSame(StampRequest::STATUS_APPROVED, $application->status);

        $attendance = $application->AttendanceRecord->fresh();
        $this->assertSame('09:30:00', $attendance->clock_in);
        $this->assertSame('18:30:00', $attendance->clock_out);
        $this->assertSame('電車遅延のため', $attendance->note);
        $this->assertSame('12:00:00', $attendance->breakTimes()->sole()->break_in);

        // 承認後は「承認済み」表示になり、承認ボタンは出ない
        $this->actingAs($this->admin)->get('/stamp_correction_request/approve/'.$application->id)
            ->assertDontSee('type="submit">承認', false)
            ->assertSee('承認済み');
    }
}
