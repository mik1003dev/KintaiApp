<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'name' => '管理者ユーザー',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
            [
                'name' => '一般ユーザー',
                'email' => 'user@example.com',
                'role' => 'user',
            ],
            [
                'name' => '山田 太郎',
                'email' => 'taro@example.com',
                'role' => 'user',
            ],
            [
                'name' => '佐藤 花子',
                'email' => 'hanako@example.com',
                'role' => 'user',
            ],
            [
                'name' => '鈴木 一郎',
                'email' => 'ichiro@example.com',
                'role' => 'user',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                ]
            );
        }
    }
}
