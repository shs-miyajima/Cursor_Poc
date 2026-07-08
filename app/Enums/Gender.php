<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case NoAnswer = 'no_answer';

    public function label(): string
    {
        return match ($this) {
            self::Male => '男性',
            self::Female => '女性',
            self::Other => 'その他',
            self::NoAnswer => '未回答',
        };
    }

    public static function fromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        return null;
    }
}
