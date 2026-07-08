<?php

namespace App\Enums;

enum QuestionType: string
{
    case Single = 'single';
    case Multiple = 'multiple';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Single => '単一選択',
            self::Multiple => '複数選択',
            self::Text => '自由記述',
        };
    }

    public function hasOptions(): bool
    {
        return $this !== self::Text;
    }
}
