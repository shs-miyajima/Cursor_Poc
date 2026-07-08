<?php

namespace App\Services;

/**
 * CSV 検証エラー 1 件（VAL-18: 「N 行目: 項目名 — 理由」）。
 */
class CsvImportError
{
    public function __construct(
        public readonly int $line,
        public readonly string $field,
        public readonly string $reason,
    ) {
    }

    public function message(): string
    {
        return "{$this->line} 行目: {$this->field} — {$this->reason}";
    }
}
