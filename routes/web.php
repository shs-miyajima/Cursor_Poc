<?php

use App\Http\Controllers\Api\EquipmentLoanController;
use App\Http\Controllers\Api\EquipmentLoanStatusController;
use App\Http\Controllers\EquipmentLoanPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/equipment-loans', EquipmentLoanPageController::class)
    ->name('equipment-loans.index');

Route::prefix('api')->group(function () {
    Route::get('/equipment-loans', [EquipmentLoanController::class, 'index']);
    Route::post('/equipment-loans', [EquipmentLoanController::class, 'store']);
    Route::patch('/equipment-loans/{equipmentLoan}/status', [EquipmentLoanStatusController::class, 'update']);
});
