<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserCsvImportService
{
    private const MAX_ROWS = 500;

    /**
     * 全行パース・検証（VAL-17/18）。エラーがあっても最後まで検証して全件返す。
     * 行番号はヘッダー行を除いたデータ行の 1 始まり。
     */
    public function validateCsv(UploadedFile $file, Company $company): CsvImportResult
    {
        $dataRows = $this->readDataRows($file);

        if (count($dataRows) > self::MAX_ROWS) {
            return new CsvImportResult(globalError: 'CSV は 500 行以内にしてください');
        }

        // 自社の既存ユーザー（未削除）を email（小文字）で引けるように準備
        $existing = User::where('company_id', $company->id)
            ->get()
            ->keyBy(fn (User $u) => $u->email);

        // 部署マスタ: 英字の大小を区別しない照合（全角半角は区別。マスタ側の表記で紐付け）
        $departments = $company->departments()->get()
            ->keyBy(fn ($d) => mb_strtolower($d->name));

        $errors = [];
        $rows = [];
        $seenEmails = [];

        foreach ($dataRows as $i => $columns) {
            $line = $i + 1;
            $name = trim($columns[0] ?? '');
            $emailRaw = trim($columns[1] ?? '');
            $password = $columns[2] ?? '';
            $departmentName = trim($columns[3] ?? '');
            $genderLabel = trim($columns[4] ?? '');
            $birthDate = trim($columns[5] ?? '');
            $hiredMonth = trim($columns[6] ?? '');

            $rowErrors = [];

            if ($name === '') {
                $rowErrors[] = new CsvImportError($line, '氏名', '必須です');
            } elseif (mb_strlen($name) > 100) {
                $rowErrors[] = new CsvImportError($line, '氏名', '100 文字以内で入力してください');
            }

            $email = mb_strtolower($emailRaw);
            $existingUser = null;

            if ($email === '') {
                $rowErrors[] = new CsvImportError($line, 'メールアドレス', '必須です');
            } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $rowErrors[] = new CsvImportError($line, 'メールアドレス', '形式が正しくありません');
            } elseif (isset($seenEmails[$email])) {
                $rowErrors[] = new CsvImportError($line, 'メールアドレス', 'ファイル内で重複しています');
            } else {
                $seenEmails[$email] = true;
                $existingUser = $existing->get($email);

                // 更新対象はユーザーロールのみ。管理者と同じメールはエラー行（要件 §5.2）
                if ($existingUser !== null && $existingUser->role !== UserRole::User) {
                    $rowErrors[] = new CsvImportError($line, 'メールアドレス', '管理者のメールアドレスは取込できません');
                }
            }

            if ($password === '') {
                $rowErrors[] = new CsvImportError($line, 'パスワード', '必須です');
            } elseif (mb_strlen($password) < 8) {
                $rowErrors[] = new CsvImportError($line, 'パスワード', '8 文字以上で入力してください');
            } elseif (mb_strlen($password) > 255) {
                $rowErrors[] = new CsvImportError($line, 'パスワード', '8〜255 文字で入力してください');
            }

            $departmentId = null;
            if ($departmentName !== '') {
                $department = $departments->get(mb_strtolower($departmentName));
                if ($department === null) {
                    $rowErrors[] = new CsvImportError($line, '部署', '部署マスタに登録されていません');
                } else {
                    $departmentId = $department->id;
                    $departmentName = $department->name;
                }
            } else {
                $departmentName = null;
            }

            $gender = Gender::NoAnswer;
            if ($genderLabel !== '') {
                $gender = Gender::fromLabel($genderLabel);
                if ($gender === null) {
                    $rowErrors[] = new CsvImportError($line, '性別', '男性/女性/その他/未回答 のいずれかで入力してください');
                }
            }

            if ($birthDate !== '' && ! $this->isValidDate($birthDate, 'Y-m-d')) {
                $rowErrors[] = new CsvImportError($line, '生年月日', 'YYYY-MM-DD 形式で入力してください');
            }

            if ($hiredMonth !== '' && ! $this->isValidDate($hiredMonth.'-01', 'Y-m-d')) {
                $rowErrors[] = new CsvImportError($line, '入社年月', 'YYYY-MM 形式で入力してください');
            }

            if ($rowErrors !== []) {
                $errors = [...$errors, ...$rowErrors];

                continue;
            }

            $rows[] = new CsvImportRow(
                line: $line,
                name: $name,
                email: $email,
                passwordHash: Hash::make($password),
                departmentId: $departmentId,
                departmentName: $departmentName,
                gender: $gender->value,
                birthDate: $birthDate !== '' ? $birthDate : null,
                hiredMonth: $hiredMonth !== '' ? $hiredMonth.'-01' : null,
                isUpdate: $existingUser !== null,
                userId: $existingUser?->id,
            );
        }

        return new CsvImportResult(rows: $rows, errors: $errors);
    }

    /**
     * 検証済み行をトランザクションで一括反映する（AC-32）。
     * 更新行は任意列が空欄でも空値で上書きする。ロールは user 固定。
     *
     * @param  CsvImportRow[]  $rows
     * @return array{created: int, updated: int}
     */
    public function commit(array $rows, Company $company): array
    {
        return DB::transaction(function () use ($rows, $company) {
            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $attributes = [
                    'name' => $row->name,
                    'email' => $row->email,
                    'department_id' => $row->departmentId,
                    'gender' => $row->gender,
                    'birth_date' => $row->birthDate,
                    'hired_month' => $row->hiredMonth,
                ];

                if ($row->isUpdate) {
                    $user = User::where('company_id', $company->id)->findOrFail($row->userId);
                    $user->fill($attributes);
                    // 検証時にハッシュ化済みのため casts の hashed を通さず直接セットする
                    $user->forceFill(['password' => $row->passwordHash]);
                    $user->save();
                    $updated++;
                } else {
                    $user = new User([
                        ...$attributes,
                        'company_id' => $company->id,
                        'role' => UserRole::User,
                    ]);
                    $user->forceFill(['password' => $row->passwordHash]);
                    $user->save();
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readDataRows(UploadedFile $file): array
    {
        $handle = $file->openFile();
        $handle->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD);

        $rows = [];
        $isHeader = true;

        foreach ($handle as $columns) {
            if ($columns === [null] || $columns === false) {
                continue;
            }

            if ($isHeader) {
                $isHeader = false;

                continue;
            }

            $rows[] = array_map(fn ($c) => (string) $c, $columns);
        }

        return $rows;
    }

    private function isValidDate(string $value, string $format): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $dt !== false && $dt->format($format) === $value;
    }
}
