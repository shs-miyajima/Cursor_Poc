<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * 操作対象の企業を一元解決する（設計 §1 テナント分離）。
 * 管理者・ユーザー → 自分の company、スーパーユーザー → セッション保持の選択企業。
 */
class CompanyContext
{
    private const SESSION_KEY = 'company_context.company_id';

    public function current(): ?Company
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        if ($user->isSuperuser()) {
            $id = Session::get(self::SESSION_KEY);

            return $id !== null ? Company::find($id) : null;
        }

        return $user->company;
    }

    public function switchTo(?Company $company): void
    {
        if ($company === null) {
            Session::forget(self::SESSION_KEY);
        } else {
            Session::put(self::SESSION_KEY, $company->id);
        }
    }

    /**
     * 企業必須画面（S-05 / S-08 / S-11 等）で企業未選択のスーパーユーザーは 403。
     */
    public function requireCompany(): Company
    {
        return $this->current() ?? abort(403);
    }
}
