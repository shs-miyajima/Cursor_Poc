<?php

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MockUserResolver
{
    /**
     * 擬似ログインユーザーを取得する。存在しない場合は 404。
     */
    public function resolve(int $mockUserId): User
    {
        $user = User::find($mockUserId);

        if ($user === null) {
            throw new NotFoundHttpException('操作ユーザーが見つかりません');
        }

        return $user;
    }
}
