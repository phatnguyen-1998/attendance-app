<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** テストケース ID 1：認証機能（一般ユーザー） */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** 正しい入力値。各テストでは一部だけ上書きして使う */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_名前が未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/register')->post('/register', $this->validData(['name' => '']));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
        $this->get('/register')->assertSee('お名前を入力してください');
    }

    public function test_メールアドレスが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData(['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_パスワードが8文字未満の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData([
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    public function test_パスワードが一致しない場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData(['password_confirmation' => 'different']));

        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません']);
    }

    public function test_パスワードが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData(['password' => '', 'password_confirmation' => '']));

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_フォームに内容が入力されていた場合_データが正常に保存される(): void
    {
        $response = $this->post('/register', $this->validData());

        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'role' => User::ROLE_USER,
        ]);
        $this->assertAuthenticated();
    }
}
