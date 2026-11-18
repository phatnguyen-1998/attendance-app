<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** テストケース ID 2：ログイン認証機能（一般ユーザー） */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ユーザーを登録する
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('password')]);
    }

    public function test_メールアドレスが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/login')->post('/login', ['email' => '', 'password' => 'password']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->get('/login')->assertSee('メールアドレスを入力してください');
    }

    public function test_パスワードが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => '']);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_登録内容と一致しない場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/login', ['email' => 'wrong@example.com', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_正しい情報でログインすると打刻画面へ遷移する(): void
    {
        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => 'password']);

        $response->assertRedirect('/attendance');
        $this->assertAuthenticated();
    }

    public function test_管理者は一般ユーザーのログイン画面からログインできない(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => bcrypt('password')]);

        $response = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }
}
