<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** テストケース ID 18：公開API 書き込み系 */
class AttendanceRecordWriteTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/attendance-records';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** ログインユーザーの勤怠（09:00〜18:00）を作成する */
    private function createAttendance(array $attributes = []): Attendance
    {
        return Attendance::factory()->for($this->user)->create($attributes);
    }

    public function test_正常なデータで勤怠が登録される(): void
    {
        $response = $this->postJson(self::URL, [
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'テスト',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'work_date' => '2026-11-02',
            'note' => 'テスト',
        ]);
    }

    public function test_必須項目がない場合は422と日本語エラーが返る(): void
    {
        $response = $this->postJson(self::URL, []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'date' => '勤怠日は必須です。',
            'clock_in' => '出勤時刻は必須です。',
        ]);
    }

    public function test_同じ日付の勤怠は登録できない(): void
    {
        $this->createAttendance(['work_date' => '2026-11-02']);

        $response = $this->postJson(self::URL, ['date' => '2026-11-02', 'clock_in' => '09:00:00']);

        $response->assertJsonValidationErrors(['date' => 'この日付の勤怠は既に登録されています。']);
    }

    public function test_形式が不正な場合は422と日本語エラーが返る(): void
    {
        $response = $this->postJson(self::URL, [
            'date' => '2026/11/02',
            'clock_in' => '9:00',
            'clock_out' => '18:00',
            'comment' => str_repeat('あ', 256),
        ]);

        $response->assertJsonValidationErrors([
            'date' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'clock_in' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment' => '備考は 255 文字以内で入力してください。',
        ]);
    }

    public function test_送信した項目のみ更新される(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->putJson(self::URL."/{$attendance->id}", ['comment' => '修正']);

        $response->assertOk();
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'note' => '修正',
        ]);
    }

    public function test_退勤時刻が出勤時刻より前の場合は422になる(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->putJson(self::URL."/{$attendance->id}", ['clock_out' => '08:00:00']);

        $response->assertJsonValidationErrors(['clock_out' => '退勤時刻は出勤時刻より後の時刻を指定してください。']);
    }

    public function test_出勤時刻が登録済みの退勤時刻より後の場合は422になる(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->putJson(self::URL."/{$attendance->id}", ['clock_in' => '23:00:00']);

        $response->assertJsonValidationErrors(['clock_in' => '出勤時刻は退勤時刻より前の時刻を指定してください。']);
    }

    public function test_存在しないidの更新は404になる(): void
    {
        $this->putJson(self::URL.'/99999', ['comment' => '修正'])->assertNotFound();
    }

    public function test_勤怠が削除される(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->deleteJson(self::URL."/{$attendance->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    public function test_存在しないidの削除は404になる(): void
    {
        $this->deleteJson(self::URL.'/99999')->assertNotFound();
    }
}
