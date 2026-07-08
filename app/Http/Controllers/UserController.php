<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\AuthorizesCompanyResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesCompanyResource;

    public function __construct(private readonly CompanyContext $context)
    {
    }

    public function index(): View
    {
        $company = $this->context->current();

        $users = User::whereNot('role', UserRole::Superuser)
            ->when($company !== null, fn ($q) => $q->where('company_id', $company->id))
            ->with(['company', 'department'])
            ->orderBy('id')
            ->get();

        return view('users.index', [
            'users' => $users,
            'company' => $company,
            // SU 全体ビューのみ登録フォームに企業選択を表示（S-04）
            'companies' => $company === null ? Company::orderBy('id')->get() : null,
            'departments' => $company?->departments()->orderBy('id')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'company_id' => $request->targetCompanyId(),
            'role' => $data['role'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'department_id' => $data['department_id'] ?? null,
            'gender' => $data['gender'] ?? 'no_answer',
            'birth_date' => $data['birth_date'] ?? null,
            'hired_month' => isset($data['hired_month']) ? $data['hired_month'].'-01' : null,
        ]);

        return redirect()->route('users.index')->with('success', 'ユーザーを登録しました');
    }

    public function edit(User $user): View
    {
        $this->authorizeUserResource($user);

        return view('users.edit', [
            'user' => $user,
            'departments' => $user->company?->departments()->orderBy('id')->get() ?? collect(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeUserResource($user);

        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'department_id' => $data['department_id'] ?? null,
            'gender' => $data['gender'] ?? 'no_answer',
            'birth_date' => $data['birth_date'] ?? null,
            'hired_month' => isset($data['hired_month']) ? $data['hired_month'].'-01' : null,
        ]);

        // パスワード空欄は変更なし（S-04a）
        if (($data['password'] ?? null) !== null && $data['password'] !== '') {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'ユーザーを更新しました');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeUserResource($user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'ユーザーを削除しました');
    }

    private function authorizeUserResource(User $user): void
    {
        // スーパーユーザーアカウント自体は本画面の管理対象外
        abort_if($user->isSuperuser(), 404);

        $this->authorizeCompanyResource($user->company_id);
    }
}
