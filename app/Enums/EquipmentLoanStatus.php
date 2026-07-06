<?php

namespace App\Enums;

enum EquipmentLoanStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';

    /**
     * ステータス更新 API で指定を許可する値。
     *
     * @return list<self>
     */
    public static function updatable(): array
    {
        return [self::Approved, self::Rejected, self::Returned];
    }

    /**
     * @return list<string>
     */
    public static function updatableValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::updatable());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => '申請中',
            self::Approved => '貸出中',
            self::Returned => '返却済',
            self::Rejected => '却下',
        };
    }
}
