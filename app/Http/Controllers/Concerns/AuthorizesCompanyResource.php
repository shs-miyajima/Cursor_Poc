<?php

namespace App\Http\Controllers\Concerns;

trait AuthorizesCompanyResource
{
    /**
     * 他社リソースへのアクセスは 404（NFR-04。存在自体を知らせない）。
     * スーパーユーザーは全企業のリソースにアクセス可（要件 §8）。
     */
    protected function authorizeCompanyResource(?int $companyId): void
    {
        $actor = auth()->user();

        if ($actor->isSuperuser()) {
            return;
        }

        abort_if($companyId !== $actor->company_id, 404);
    }
}
