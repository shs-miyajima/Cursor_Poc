<?php

use App\Http\Controllers\AdminHomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\MySurveyController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyResultController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // スーパーユーザー専用（全体ビュー・企業管理）
    Route::middleware('role:superuser')->group(function () {
        Route::get('/admin/home', [AdminHomeController::class, 'home'])->name('admin.home');
        Route::post('/admin/switch-company/{company}', [AdminHomeController::class, 'switchCompany'])->name('admin.switch-company');
        Route::post('/admin/reset-company', [AdminHomeController::class, 'resetCompany'])->name('admin.reset-company');

        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
    });

    // スーパーユーザー・管理者
    Route::middleware('role:superuser,admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/api/surveys/{survey}/results', [SurveyResultController::class, 'show'])->name('api.surveys.results');

        // /users/{user} より先に定義して import が ID と解釈されないようにする
        Route::get('/users/import', [UserImportController::class, 'showForm'])->name('users.import');
        Route::post('/users/import', [UserImportController::class, 'upload'])->name('users.import.upload');
        Route::get('/users/import/confirm', [UserImportController::class, 'confirm'])->name('users.import.confirm');
        Route::post('/users/import/commit', [UserImportController::class, 'commit'])->name('users.import.commit');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
        Route::get('/surveys/create', [SurveyController::class, 'create'])->name('surveys.create');
        Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store');
        Route::get('/surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit');
        Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
        Route::post('/surveys/{survey}/publish', [SurveyController::class, 'publish'])->name('surveys.publish');
        Route::post('/surveys/{survey}/close', [SurveyController::class, 'close'])->name('surveys.close');
        Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy');
    });

    // ユーザー（回答者）専用
    Route::middleware('role:user')->group(function () {
        Route::get('/my/surveys', [MySurveyController::class, 'index'])->name('my.surveys.index');
        Route::get('/my/surveys/{survey}', [MySurveyController::class, 'show'])->name('my.surveys.show');
        Route::post('/my/surveys/{survey}/answers', [MySurveyController::class, 'answer'])->name('my.surveys.answer');
    });
});
