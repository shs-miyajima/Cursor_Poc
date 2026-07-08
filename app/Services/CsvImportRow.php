<?php

namespace App\Services;

/**
 * 検証済み CSV 行 1 件。パスワードは検証時にハッシュ化済み（平文は保持しない。設計 §6）。
 */
class CsvImportRow
{
    public function __construct(
        public readonly int $line,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?int $departmentId,
        public readonly ?string $departmentName,
        public readonly string $gender,
        public readonly ?string $birthDate,
        public readonly ?string $hiredMonth,
        public readonly bool $isUpdate,
        public readonly ?int $userId,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
