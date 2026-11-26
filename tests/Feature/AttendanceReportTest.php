<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** テストケース ID 20：マイ勤怠レポート機能 */
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // シーダーと集計は現在日付を基準にするため固定する
        Carbon::setTestNow(Carbon::parse('2026-11-30 20:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** シーディングした user1 でレポート画面を開く */
    private function getSeededReport()
    {
        $this->seed();
        $user = User::where('email', 'user1@example.com')->firstOrFail();

        return $this->actingAs($user)->get('/attendance/report');
    }

    public function test_ゲストはレポート画面にアクセスできない(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    public function test_基本サマリーが予測値どおりに表示される(): void
    {
        $response = $this->getSeededReport();

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total_work_minutes' => 744 * 60,
            'total_overtime_minutes' => 10 * 60,
            'avg_work_minutes' => 8 * 60 + 5,
        ]);
        $response->assertSeeInOrder(['744h 0m', '10h 0m', '8h 5m']);
    }

    public function test_月次推移が過去6ヶ月分正しく集計される(): void
    {
        $response = $this->getSeededReport();

        // 過去5ヶ月は通常勤務15日（120時間）、当月は144時間・残業10時間
        $expected = collect(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10'])
            ->map(fn (string $month) => ['month' => $month, 'work_minutes' => 120 * 60, 'overtime_minutes' => 0])
            ->push(['month' => '2026-11', 'work_minutes' => 144 * 60, 'overtime_minutes' => 10 * 60])
            ->all();

        $response->assertViewHas('monthlyTrend', $expected);
    }

    public function test_当月の異常検知が予測値どおりになる(): void
    {
        $response = $this->getSeededReport();

        $response->assertViewHas('anomalies', [
            'late_count' => 2,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    public function test_勤怠がないユーザーでもレポート画面が表示される(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/attendance/report');

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);
        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
        $response->assertViewHas('monthlyTrend', fn (array $trend) => count($trend) === 6
            && collect($trend)->every(fn (array $row) => $row['work_minutes'] === 0 && $row['overtime_minutes'] === 0));
    }
}
