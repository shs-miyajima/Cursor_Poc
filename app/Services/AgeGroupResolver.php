<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * 年代（10 歳刻み）を生年月日の範囲に変換する。基準日は集計実行時の現在日付（設計 §6）。
 */
class AgeGroupResolver
{
    public const GROUPS = [
        'under20' => '〜19 歳',
        '20s' => '20 代',
        '30s' => '30 代',
        '40s' => '40 代',
        '50s' => '50 代',
        '60plus' => '60 代〜',
    ];

    /**
     * @return array{from: ?CarbonImmutable, to: ?CarbonImmutable} birth_date の範囲（両端含む）
     */
    public static function rangeFor(string $ageGroup, CarbonInterface $now): array
    {
        $today = CarbonImmutable::instance($now)->startOfDay();

        // 誕生日当日を含む: N 歳ちょうど = 生年月日が (today - N 年) の人
        return match ($ageGroup) {
            'under20' => ['from' => $today->subYears(20)->addDay(), 'to' => null],
            '20s' => ['from' => $today->subYears(30)->addDay(), 'to' => $today->subYears(20)],
            '30s' => ['from' => $today->subYears(40)->addDay(), 'to' => $today->subYears(30)],
            '40s' => ['from' => $today->subYears(50)->addDay(), 'to' => $today->subYears(40)],
            '50s' => ['from' => $today->subYears(60)->addDay(), 'to' => $today->subYears(50)],
            '60plus' => ['from' => null, 'to' => $today->subYears(60)],
            default => ['from' => null, 'to' => null],
        };
    }
}
