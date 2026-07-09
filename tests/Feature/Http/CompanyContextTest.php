<?php

namespace Tests\Feature\Http;

use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CompanyContextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PU-119-auth: CompanyContext::requireCompany(企業未選択) — abort(403)
     */
    public function test_require_company_aborts_when_superuser_has_no_company_selected(): void
    {
        $su = User::factory()->superuser()->create();
        $this->actingAs($su);

        try {
            app(CompanyContext::class)->requireCompany();
            $this->fail('HttpException が発生しませんでした');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
