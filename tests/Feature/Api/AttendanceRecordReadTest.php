<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\StampRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** テストケース ID 17：公開API 読み取り系 */
class AttendanceRecordReadTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/attendance-records';

    public function test_勤怠一覧がdataとmetaを含むjsonで取得できる(): void
    {
        $this->seed();

        $response = $this->getJson(self::URL);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'user_id', 'user_name', 'date', 'clock_in', 'clock_out', 'total_time', 'total_break_time', 'comment']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.per_page', 20);
        $response->assertJsonPath('meta.total', Attendance::count());
    }

    public function test_ユーザーidと日付と年月で絞り込める(): void
    {
        $user = User::factory()->create();
        $october = Attendance::factory()->for($user)->create(['work_date' => '2026-10-01']);
        $november = Attendance::factory()->for($user)->create(['work_date' => '2026-11-02']);
        Attendance::factory()->create(['work_date' => '2026-11-02']);

        $this->getJson(self::URL."?user_id={$user->id}")->assertJsonPath('meta.total', 2);
        $this->getJson(self::URL.'?date=2026-11-02')->assertJsonPath('meta.total', 2);
        $this->getJson(self::URL."?user_id={$user->id}&month=2026-10")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $october->id);
        $this->getJson(self::URL."?user_id={$user->id}&date=2026-11-02")
            ->assertJsonPath('data.0.id', $november->id);
    }

    public function test_件数とページ番号でページネーションできる(): void
    {
        Attendance::factory()->count(3)->create();

        $response = $this->getJson(self::URL.'?per_page=2&page=2');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_1ページの件数が100を超える場合は422になる(): void
    {
        $response = $this->getJson(self::URL.'?per_page=101');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page' => '1ページあたりの件数は 100 以下で指定してください。']);
    }

    public function test_勤怠詳細がユーザーと休憩と修正申請を含めて取得できる(): void
    {
        $attendance = Attendance::factory()->create();
        $attendance->breakTimes()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        StampRequest::factory()->create(['attendance_id' => $attendance->id]);

        $response = $this->getJson(self::URL."/{$attendance->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $attendance->id);
        $response->assertJsonPath('data.user.name', $attendance->user->name);
        $response->assertJsonPath('data.breaks.0.break_in', '12:00:00');
        $response->assertJsonPath('data.applications.0.status', '承認待ち');
    }

    public function test_存在しないidの場合は404とエラーが返る(): void
    {
        $response = $this->getJson(self::URL.'/99999');

        $response->assertNotFound();
        $response->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
