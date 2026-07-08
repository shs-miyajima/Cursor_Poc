<?php

namespace App\Models;

use App\Enums\SurveyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'status',
        'deadline_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SurveyStatus::class,
            'deadline_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * 参照時判定の実効状態。公開中でも締切日時を過ぎていれば終了とみなす（NFR-08）。
     * DB の status カラムは書き換えない。
     */
    public function effectiveStatus(): SurveyStatus
    {
        if ($this->status === SurveyStatus::Published
            && $this->deadline_at !== null
            && $this->deadline_at->isPast()) {
            return SurveyStatus::Closed;
        }

        return $this->status;
    }

    public function isAcceptingAnswers(): bool
    {
        return $this->effectiveStatus() === SurveyStatus::Published;
    }
}
