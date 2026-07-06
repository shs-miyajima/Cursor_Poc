<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\MockUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class MockUserResolverTest extends TestCase
{
    use RefreshDatabase;

    private MockUserResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MockUserResolver();
    }

    /**
     * PHPUnit-inp-004: mock_user_id 正常解決
     */
    public function test_存在するユーザーIDを指定すると対象ユーザーが返る(): void
    {
        $user = User::factory()->create(['name' => '山田 太郎']);

        $resolved = $this->resolver->resolve($user->id);

        $this->assertTrue($resolved->is($user));
        $this->assertSame('山田 太郎', $resolved->name);
    }

    /**
     * PHPUnit-inp-009: mock_user_id 存在なし
     */
    public function test_存在しないユーザーIDを指定すると404相当の例外になる(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('操作ユーザーが見つかりません');

        $this->resolver->resolve(999999);
    }
}
