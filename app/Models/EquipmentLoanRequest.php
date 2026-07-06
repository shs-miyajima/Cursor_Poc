<?php

namespace App\Models;

use App\Enums\EquipmentLoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EquipmentLoanRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'equipment_id',
        'status',
        'requested_from',
        'requested_to',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => EquipmentLoanStatus::class,
            'requested_from' => 'date',
            'requested_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * 返却期限超過: status が approved または return_requested かつ requested_to が本日より前。
     */
    public function isOverdue(): bool
    {
        return in_array($this->status, [EquipmentLoanStatus::Approved, EquipmentLoanStatus::ReturnRequested], true)
            && $this->requested_to->lt(Carbon::today());
    }
}
