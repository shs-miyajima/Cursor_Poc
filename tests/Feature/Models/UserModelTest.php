<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PU-010-other: パスワードのハッシュ保存 — 平文と異なる bcrypt ハッシュが保存される（NFR-01）
     */
    public function test_password_is_stored_as_bcrypt_hash(): void
    {
        $user = User::factory()->create(['password' => 'plain12345']);

        $stored = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame('plain12345', $stored);
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check('plain12345', $stored));
    }

    /**
     * PU-011-other: email 小文字化ミューテータ — taro@x.jp が保存される（AC-33）
     */
    public function test_email_is_normalized_to_lowercase(): void
    {
        $user = User::factory()->create(['email' => 'Taro@X.JP']);

        $stored = DB::table('users')->where('id', $user->id)->value('email');

        $this->assertSame('taro@x.jp', $stored);
    }
}
