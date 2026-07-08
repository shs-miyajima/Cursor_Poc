<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * 企業コード + メールアドレス + パスワードで認証する（VAL-01）。
     * 企業コード空欄はスーパーユーザー（company_id NULL）として認証する（UC-02）。
     * 論理削除済みの企業・ユーザーは SoftDeletes / 検索条件により除外される。
     */
    public function attempt(?string $companyCode, string $email, string $password): ?User
    {
        $email = mb_strtolower($email);

        if ($companyCode === null || $companyCode === '') {
            $user = User::whereNull('company_id')
                ->where('role', UserRole::Superuser)
                ->where('email', $email)
                ->first();
        } else {
            $company = Company::where('code', $companyCode)->first();

            if ($company === null) {
                return null;
            }

            $user = User::where('company_id', $company->id)
                ->where('email', $email)
                ->first();
        }

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * ロール別ホームのルート名を返す（UC-01/02）。
     */
    public function homeRouteFor(User $user): string
    {
        return match ($user->role) {
            UserRole::Superuser => 'admin.home',
            UserRole::Admin => 'dashboard',
            UserRole::User => 'my.surveys.index',
        };
    }
}
