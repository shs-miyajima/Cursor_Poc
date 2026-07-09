<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Http\Concerns\HttpTestHelpers;
use Tests\TestCase;

class UserImportHttpTest extends TestCase
{
    use HttpTestHelpers;
    use RefreshDatabase;

    /**
     * PU-069-inp: CSV(拡張子不正) — 「CSV ファイル(2MB 以内)を選択してください」
     */
    public function test_upload_rejects_non_csv_extension(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $file = UploadedFile::fake()->create('users.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.import.upload'), ['file' => $file]);

        $response->assertSessionHasErrors(['file' => 'CSV ファイル(2MB 以内)を選択してください']);
    }

    /**
     * PU-070-inp: CSV(2MB超) — 「CSV ファイル(2MB 以内)を選択してください」
     */
    public function test_upload_rejects_csv_over_2mb(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $file = UploadedFile::fake()->create('users.csv', 2049, 'text/csv');

        $response = $this->actingAs($admin)
            ->withSession($this->sessionForCompany($company))
            ->post(route('users.import.upload'), ['file' => $file]);

        $response->assertSessionHasErrors(['file' => 'CSV ファイル(2MB 以内)を選択してください']);
    }
}
