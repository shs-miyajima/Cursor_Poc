<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'stock_count',
        'target_department',
    ];

    protected function casts(): array
    {
        return [
            'stock_count' => 'integer',
        ];
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(EquipmentLoanRequest::class);
    }

    /**
     * target_department が NULL の場合は全部署に貸出可能。
     */
    public function isAvailableForDepartment(string $department): bool
    {
        return $this->target_department === null || $this->target_department === $department;
    }
}
