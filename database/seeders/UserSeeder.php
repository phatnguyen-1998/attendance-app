<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * 管理者・一般ユーザーのテストアカウントを作成する。
     */
    public function run(): void
    {
        // 管理者
        $admin = User::firstOrNew(['email' => 'user3@example.com']);
        $admin->name = 'ユーザー３（管理者）';
        $admin->password = 'password'; // cast "hashed" で自動的にハッシュ化される
        $admin->role = User::ROLE_ADMIN;
        $admin->email_verified_at = now();
        $admin->save();

        // 一般ユーザー
        $generalUsers = [
            ['name' => 'ユーザー１（一般）', 'email' => 'user1@example.com'],
            ['name' => 'ユーザー２（一般）', 'email' => 'user2@example.com'],
        ];
        foreach ($generalUsers as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->name = $data['name'];
            $user->password = 'password';
            $user->role = User::ROLE_USER;
            $user->email_verified_at = now();
            $user->save();
        }

    }
}
