<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesCompanyResource;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    use AuthorizesCompanyResource;

    public function __construct(private readonly CompanyContext $context)
    {
    }

    public function index(): View
    {
        $company = $this->context->requireCompany();

        return view('departments.index', [
            'departments' => $company->departments()->orderBy('id')->get(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $company = $this->context->requireCompany();

        $company->departments()->create($request->validated());

        return redirect()->route('departments.index')->with('success', '部署を登録しました');
    }

    public function edit(Department $department): View
    {
        $this->authorizeCompanyResource($department->company_id);

        return view('departments.edit', ['department' => $department]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorizeCompanyResource($department->company_id);

        $department->update($request->validated());

        return redirect()->route('departments.index')->with('success', '部署を更新しました');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorizeCompanyResource($department->company_id);

        $department->delete();

        return redirect()->route('departments.index')->with('success', '部署を削除しました');
    }
}
