<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportUserCsvRequest;
use App\Services\CompanyContext;
use App\Services\CsvImportRow;
use App\Services\UserCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserImportController extends Controller
{
    private const SESSION_KEY = 'user_import.rows';

    public function __construct(private readonly CompanyContext $context)
    {
    }

    /**
     * S-05: CSV アップロードフォーム。
     */
    public function showForm(): View
    {
        $this->context->requireCompany();

        return view('users.import');
    }

    /**
     * UC-13: 全行検証。エラーがあれば全件表示して確認画面に進まない（AC-18）。
     */
    public function upload(ImportUserCsvRequest $request, UserCsvImportService $service): RedirectResponse
    {
        $company = $this->context->requireCompany();

        $result = $service->validateCsv($request->file('file'), $company);

        if ($result->hasErrors()) {
            $messages = $result->globalError !== null
                ? [$result->globalError]
                : array_map(fn ($e) => $e->message(), $result->errors);

            return back()->withErrors(['csv' => $messages]);
        }

        session()->put(self::SESSION_KEY, array_map(fn (CsvImportRow $r) => $r->toArray(), $result->rows));

        return redirect()->route('users.import.confirm');
    }

    /**
     * S-05a: 新規 / 更新の確認画面（AC-31）。
     */
    public function confirm(): View|RedirectResponse
    {
        $this->context->requireCompany();

        $rows = session(self::SESSION_KEY);

        if ($rows === null) {
            return redirect()->route('users.import');
        }

        $rows = array_map(fn (array $r) => CsvImportRow::fromArray($r), $rows);

        return view('users.import-confirm', [
            'rows' => $rows,
            'newCount' => count(array_filter($rows, fn ($r) => ! $r->isUpdate)),
            'updateCount' => count(array_filter($rows, fn ($r) => $r->isUpdate)),
        ]);
    }

    /**
     * UC-13: 確定（トランザクション一括反映。AC-32）。
     */
    public function commit(UserCsvImportService $service): RedirectResponse
    {
        $company = $this->context->requireCompany();

        $rows = session(self::SESSION_KEY);

        if ($rows === null) {
            return redirect()->route('users.import');
        }

        $counts = $service->commit(array_map(fn (array $r) => CsvImportRow::fromArray($r), $rows), $company);

        session()->forget(self::SESSION_KEY);

        return redirect()->route('users.index')->with(
            'success',
            "取込が完了しました（新規 {$counts['created']} 件・更新 {$counts['updated']} 件）",
        );
    }
}
