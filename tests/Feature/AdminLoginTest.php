<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** テストケース ID 3：ログイン認証機能（管理者） */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => bcrypt('password')]);
    }

    public function test_メールアドレスが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', ['email' => '', 'password' => 'password']);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->get('/admin/login')->assertSee('メールアドレスを入力してください');
    }

    public function test_パスワードが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => '']);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_登録内容と一致しない場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/admin/login', ['email' => 'wrong@example.com', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_正しい情報でログインすると勤怠一覧画面へ遷移する(): void
    {
        $response = $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password']);

        $response->assertRedirect('/admin/attendance/list');
        $this->assertAuthenticated();
    }

    public function test_一般ユーザーは管理者ログイン画面からログインできない(): void
    {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);

        $response = $this->post('/admin/login', ['email' => 'user@example.com', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }
}
