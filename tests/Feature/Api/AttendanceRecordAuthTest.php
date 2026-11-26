<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** テストケース ID 19：Sanctum 認証・認可 */
class AttendanceRecordAuthTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/attendance-records';

    public function test_未認証の場合は書き込み系が401になる(): void
    {
        $attendance = Attendance::factory()->create();
        $error = ['message' => 'Unauthenticated.'];

        $this->postJson(self::URL, [])->assertUnauthorized()->assertExactJson($error);
        $this->putJson(self::URL."/{$attendance->id}", [])->assertUnauthorized()->assertExactJson($error);
        $this->deleteJson(self::URL."/{$attendance->id}")->assertUnauthorized()->assertExactJson($error);
    }

    public function test_自分の勤怠は更新と削除ができる(): void
    {
        $attendance = Attendance::factory()->create();
        Sanctum::actingAs($attendance->user);

        $this->putJson(self::URL."/{$attendance->id}", ['comment' => '修正'])->assertOk();
        $this->deleteJson(self::URL."/{$attendance->id}")->assertNoContent();
    }

    public function test_他ユーザーの勤怠の更新と削除は403になる(): void
    {
        $attendance = Attendance::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $error = ['error' => 'この操作を実行する権限がありません。'];

        $this->putJson(self::URL."/{$attendance->id}", ['comment' => '修正'])->assertForbidden()->assertExactJson($error);
        $this->deleteJson(self::URL."/{$attendance->id}")->assertForbidden()->assertExactJson($error);
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'note' => null]);
    }

    public function test_管理者は他ユーザーの勤怠を更新と削除ができる(): void
    {
        $attendance = Attendance::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson(self::URL."/{$attendance->id}", ['comment' => '修正'])->assertOk();
        $this->deleteJson(self::URL."/{$attendance->id}")->assertNoContent();
    }
}
