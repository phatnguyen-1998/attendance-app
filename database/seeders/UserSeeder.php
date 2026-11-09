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
        $admin = User::firstOrNew(['email' => 'admin123@gmail.com']);
        $admin->name = 'admin';
        $admin->password = '12345678'; // cast "hashed" で自動的にハッシュ化される
        $admin->role = User::ROLE_ADMIN;
        $admin->email_verified_at = now();
        $admin->save();

        // 一般ユーザー
        $generalUsers = [
            ['name' => 'user', 'email' => 'user123@gmail.com'],
            ['name' => 'user2', 'email' => 'user2@example.com'],
            ['name' => 'user3', 'email' => 'user3@example.com'],
        ];
        foreach ($generalUsers as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->name = $data['name'];
            $user->password = '12345678';
            $user->role = User::ROLE_USER;
            $user->email_verified_at = now();
            $user->save();
        }

    }
}