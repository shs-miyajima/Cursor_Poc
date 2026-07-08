<?php

namespace Tests\Unit\Enums;

use App\Enums\Gender;
use PHPUnit\Framework\TestCase;

class GenderTest extends TestCase
{
    /**
     * PU-040-other: Gender::fromLabel(正常4値) — male/female/other/no_answer の enum が返る
     */
    public function test_from_label_returns_enum_for_valid_labels(): void
    {
        $this->assertSame(Gender::Male, Gender::fromLabel('男性'));
        $this->assertSame(Gender::Female, Gender::fromLabel('女性'));
        $this->assertSame(Gender::Other, Gender::fromLabel('その他'));
        $this->assertSame(Gender::NoAnswer, Gender::fromLabel('未回答'));
    }

    /**
     * PU-041-other: Gender::fromLabel(不正値) — null が返る（CSV のエラー行判定に使う）
     */
    public function test_from_label_returns_null_for_invalid_label(): void
    {
        $this->assertNull(Gender::fromLabel('男'));
    }
}
