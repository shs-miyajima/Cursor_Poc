<?php

namespace App\Services;

/**
 * CSV 全行検証の結果（UC-13）。
 */
class CsvImportResult
{
    /**
     * @param  CsvImportRow[]  $rows
     * @param  CsvImportError[]  $errors
     * @param  ?string  $globalError  行数超過等、ファイル単位のエラー（VAL-17）
     */
    public function __construct(
        public readonly array $rows = [],
        public readonly array $errors = [],
        public readonly ?string $globalError = null,
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->globalError !== null || $this->errors !== [];
    }

    public function newCount(): int
    {
        return count(array_filter($this->rows, fn (CsvImportRow $r) => ! $r->isUpdate));
    }

    public function updateCount(): int
    {
        return count(array_filter($this->rows, fn (CsvImportRow $r) => $r->isUpdate));
    }
}
