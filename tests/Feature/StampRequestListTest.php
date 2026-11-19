<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** テストケース ID 11（申請一覧）：一般ユーザーの申請一覧（承認待ち／承認済み／詳細） */
class StampRequestListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-18 20:00:00'));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // 他のテストに影響しないよう現在日時を元に戻す
        parent::tearDown();
    }

    /** 申請を作る。理由（comment）で一覧上の行を見分ける */
    private function createRequest(string $comment, bool $approved = false, ?User $user = null): StampRequest
    {
        $user ??= $this->user;
        $attendance = Attendance::factory()->for($user)->create(['work_date' => '2026-11-10']);
        $factory = StampRequest::factory()->for($attendance, 'AttendanceRecord');

        return ($approved ? $factory->approved() : $factory)->create(['comment' => $comment]);
    }

    /** 申請一覧を開き、「承認待ち」タブと「承認済み」タブの HTML に分けて返す */
    private function openTabs(): array
    {
        $html = $this->actingAs($this->user)->get('/stamp_correction_request/list')->assertOk()->getContent();

        return explode('id="content2"', $html, 2);
    }

    /** 承認待ちタブに、自分の承認待ちの申請が全て表示され、承認済み・他人の申請は表示されないこと */
    public function test_承認待ちにログインユーザーが行った申請が全て表示されている(): void
    {
        $this->createRequest('理由その１');
        $this->createRequest('理由その２');
        $this->createRequest('承認済みの理由', approved: true);
        $this->createRequest('他人の理由', user: User::factory()->create());

        [$pending] = $this->openTabs();

        $this->assertStringContainsString('理由その１', $pending);
        $this->assertStringContainsString('理由その２', $pending);
        $this->assertStringNotContainsString('承認済みの理由', $pending);
        $this->assertStringNotContainsString('他人の理由', $pending);
    }

    /** 承認済みタブに、承認された申請が全て表示され、承認待ちの申請は表示されないこと */
    public function test_承認済みに管理者が承認した修正申請が全て表示されている(): void
    {
        $this->createRequest('承認済みその１', approved: true);
        $this->createRequest('承認済みその２', approved: true);
        $this->createRequest('承認待ちの理由');

        [, $approved] = $this->openTabs();

        $this->assertStringContainsString('承認済みその１', $approved);
        $this->assertStringContainsString('承認済みその２', $approved);
        $this->assertStringNotContainsString('承認待ちの理由', $approved);
    }

    /** 各申請の「詳細」リンクから、その申請の勤怠詳細画面に遷移すること */
    public function test_各申請の詳細を押下すると勤怠詳細画面に遷移する(): void
    {
        $application = $this->createRequest('理由');

        [$pending] = $this->openTabs();
        $this->assertStringContainsString('href="'.url('/application/'.$application->id).'"', $pending);

        $this->actingAs($this->user)->get('/application/'.$application->id)
            ->assertRedirect('/attendance/'.$application->attendance_id);
    }
}
