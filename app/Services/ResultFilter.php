<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * ダッシュボード絞り込み条件の値オブジェクト（UC-25）。
 */
class ResultFilter
{
    public function __construct(
        public readonly ?int $departmentId = null,
        public readonly ?CarbonImmutable $dateFrom = null,
        public readonly ?CarbonImmutable $dateTo = null,
        public readonly ?string $gender = null,
        public readonly ?string $ageGroup = null,
        public readonly ?CarbonImmutable $hiredFrom = null,
        public readonly ?CarbonImmutable $hiredTo = null,
    ) {
    }
}
