<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperUserSeeder extends Seeder
{
    /**
     * スーパーユーザー初期アカウントを投入する（要件 §6）。
     */
    public function run(): void
    {
        User::withTrashed()->firstOrCreate(
            ['email' => 'su@example.com', 'company_id' => null],
            [
                'name' => 'スーパーユーザー',
                'password' => 'password',
                'role' => UserRole::Superuser,
            ],
        );
    }
}
